<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi\Schema;

use BackedEnum;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use Waffle\Commons\OpenApi\Enum\Side;
use Waffle\Commons\Utils\Dto\Shape\DtoShape;
use Waffle\Commons\Utils\Dto\Shape\Enum\TypeKind;

use function array_count_values;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function assert;
use function count;
use function enum_exists;
use function is_a;
use function preg_replace;
use function str_replace;
use function strrchr;
use function substr;

/**
 * Which component schemas a document needs and what they are called.
 *
 * A DTO is used on the input side when a request body reaches it through
 * constructor parameters, and on the output side when a response body reaches it
 * through readable properties. It is *symmetric* when both sides describe the
 * same shape: same fields, same types, every field required, nothing computed,
 * and every DTO it refers to symmetric too. A DTO that is used on both sides but
 * is not symmetric gets two components, `Name-Input` and `Name-Output`; any
 * other gets one, `Name`.
 *
 * `Name` is the short class name, or the full name with dots for backslashes
 * when two classes share the short one.
 */
final readonly class ComponentNames
{
    /**
     * @param array<class-string, string> $inputs      The component of each DTO used in a request.
     * @param array<class-string, string> $outputs     The component of each DTO used in a response.
     * @param array<class-string, string> $enums       The component of each backed enum used.
     * @param array<string, array>        $enumSchemas The schema of each enum component, by component name.
     */
    private function __construct(
        public array $inputs,
        public array $outputs,
        public array $enums,
        public array $enumSchemas,
    ) {}

    /**
     * @param array<class-string, DtoShape> $shapes        Every DTO the request and response roots reach.
     * @param list<class-string>            $requestRoots  The DTOs sent as request bodies.
     * @param list<class-string>            $responseRoots The DTOs returned as response bodies.
     *
     * @throws ReflectionException When an enum cannot be reflected.
     */
    public static function plan(array $shapes, array $requestRoots, array $responseRoots): self
    {
        $inputs = self::reach($shapes, $requestRoots, Side::Input);
        $outputs = self::reach($shapes, $responseRoots, Side::Output);
        $symmetric = self::symmetric($shapes);
        $enums = [...self::enums($shapes, $inputs, Side::Input), ...self::enums($shapes, $outputs, Side::Output)];
        $base = self::baseNames([...array_keys([...$inputs, ...$outputs]), ...array_keys($enums)]);

        $enumNames = [];
        $enumSchemas = [];
        foreach ($enums as $enum => $schema) {
            $enumNames[$enum] = $base[$enum] ?? $enum;
            $enumSchemas[$base[$enum] ?? $enum] = $schema;
        }

        return new self(
            self::sideNames($inputs, $outputs, $symmetric, $base, Side::Input),
            self::sideNames($outputs, $inputs, $symmetric, $base, Side::Output),
            $enumNames,
            $enumSchemas,
        );
    }

    /**
     * @param array<class-string, true>   $used      The DTOs used on `$side`.
     * @param array<class-string, true>   $other     The DTOs used on the other side.
     * @param array<class-string, bool>   $symmetric
     * @param array<class-string, string> $base
     *
     * @return array<class-string, string>
     */
    private static function sideNames(array $used, array $other, array $symmetric, array $base, Side $side): array
    {
        $names = [];
        foreach (array_keys($used) as $class) {
            $split = !($symmetric[$class] ?? false) && array_key_exists($class, $other);
            $names[$class] = ($base[$class] ?? $class) . ($split ? '-' . $side->value : '');
        }

        return $names;
    }

    /**
     * The DTOs a side reaches from its roots through that side's fields.
     *
     * @param array<class-string, DtoShape> $shapes
     * @param list<class-string>            $roots
     *
     * @return array<class-string, true>
     */
    private static function reach(array $shapes, array $roots, Side $side): array
    {
        $reached = [];
        $pending = $roots;
        while ($pending !== []) {
            $class = array_pop($pending);
            $shape = $shapes[$class] ?? null;
            if ($shape === null || array_key_exists($class, $reached)) {
                continue;
            }

            $reached[$class] = true;
            foreach ($side === Side::Input ? $shape->inputs : $shape->outputs as $field) {
                $nested = $field->type->referencedDto();
                if ($nested !== null) {
                    $pending[] = $nested;
                }
            }
        }

        return $reached;
    }

    /**
     * Whether each DTO is symmetric. A DTO starts symmetric when its own fields
     * match, then loses it when it refers to an asymmetric DTO, until nothing
     * changes: a cycle of symmetric DTOs stays symmetric.
     *
     * @param array<class-string, DtoShape> $shapes
     *
     * @return array<class-string, bool>
     */
    private static function symmetric(array $shapes): array
    {
        $symmetric = [];
        foreach ($shapes as $class => $shape) {
            $symmetric[$class] = self::matchesItself($shape);
        }

        do {
            $changed = false;
            foreach ($shapes as $class => $shape) {
                if (!($symmetric[$class] ?? false) || !self::dependsOnAsymmetric($shape, $symmetric)) {
                    continue;
                }

                $symmetric[$class] = false;
                $changed = true;
            }
        } while ($changed);

        return $symmetric;
    }

    /**
     * @param array<class-string, bool> $symmetric
     */
    private static function dependsOnAsymmetric(DtoShape $shape, array $symmetric): bool
    {
        foreach ($shape->dependencies() as $dependency) {
            if (!($symmetric[$dependency] ?? true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a DTO's own inputs and outputs describe one shape: the same
     * names with the same types, every input required, no output computed.
     */
    private static function matchesItself(DtoShape $shape): bool
    {
        if (count($shape->inputs) !== count($shape->outputs)) {
            return false;
        }

        foreach ($shape->inputs as $input) {
            $output = $shape->output($input->name);
            if (
                $output === null
                || $output->computed
                || !$input->required
                || $output->type->describe() !== $input->type->describe()
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * The schemas of the backed enums a side's DTOs refer to, directly or as
     * list elements, keyed by enum class.
     *
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, true>     $classes The DTOs used on `$side`.
     *
     * @return array<class-string, array{type: string, enum: list<int|string>}>
     *
     * @throws ReflectionException
     */
    private static function enums(array $shapes, array $classes, Side $side): array
    {
        $enums = [];
        foreach ($shapes as $class => $shape) {
            if (!array_key_exists($class, $classes)) {
                continue;
            }

            foreach ($side === Side::Input ? $shape->inputs : $shape->outputs as $field) {
                $type = $field->type->item ?? $field->type;
                $enum = $type->class;
                if (
                    $type->kind !== TypeKind::Enum
                    || $enum === null
                    || !enum_exists($enum)
                    || !is_a($enum, BackedEnum::class, true)
                ) {
                    continue;
                }

                $enums[$enum] = [
                    'type' => $type->backing === TypeKind::Int ? 'integer' : 'string',
                    'enum' => self::backingValues($enum),
                ];
            }
        }

        return $enums;
    }

    /**
     * The backing value of each case of a backed enum, in declaration order.
     *
     * @param class-string<BackedEnum> $enum
     *
     * @return list<int|string>
     *
     * @throws ReflectionException
     */
    private static function backingValues(string $enum): array
    {
        $values = [];
        foreach (new ReflectionEnum($enum)->getCases() as $case) {
            assert($case instanceof ReflectionEnumBackedCase, 'A backed enum has backed cases.');
            $values[] = $case->getBackingValue();
        }

        return $values;
    }

    /**
     * Each class's base name: its short name, or its full name with dots when
     * another class shares the short one. Characters a component name cannot
     * hold become underscores.
     *
     * @param list<class-string> $classes
     *
     * @return array<class-string, string>
     */
    private static function baseNames(array $classes): array
    {
        $short = [];
        foreach ($classes as $class) {
            $last = strrchr($class, '\\');
            $short[$class] = $last === false ? $class : substr($last, 1);
        }

        $counts = array_count_values($short);
        $names = [];
        foreach ($short as $class => $name) {
            $chosen = ($counts[$name] ?? 0) > 1 ? str_replace('\\', '.', $class) : $name;
            $names[$class] = preg_replace('/[^A-Za-z0-9._-]/', '_', $chosen) ?? $chosen;
        }

        return $names;
    }
}
