<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi\Schema;

use BackedEnum;
use ReflectionException;
use stdClass;
use Waffle\Commons\OpenApi\Enum\Side;
use Waffle\Commons\Utils\Dto\Shape\DtoShape;
use Waffle\Commons\Utils\Dto\Shape\Enum\TypeKind;
use Waffle\Commons\Utils\Dto\Shape\FieldType;

use function ksort;

/**
 * The component schemas of one OpenAPI document: one per backed enum, and one
 * per `#[Dto]` side the operations use ([API-01]).
 *
 * A request body is described by the DTO's constructor parameters, as the
 * serializer hydrates it; a response body by its readable properties, as the
 * serializer writes it. Both come from the `utils` DtoShapeReader, so the
 * document and the serializer agree. {@see ComponentNames} decides which
 * components exist and what they are called.
 *
 * Built once per document and immutable: no state survives a generation.
 */
final readonly class SchemaCatalog
{
    private const string REF_PREFIX = '#/components/schemas/';

    /**
     * @param array<class-string, DtoShape> $shapes
     */
    private function __construct(
        private array $shapes,
        private ComponentNames $names,
    ) {}

    /**
     * @param array<class-string, DtoShape> $shapes        Every DTO the request and response roots reach.
     * @param list<class-string>            $requestRoots  The DTOs sent as request bodies.
     * @param list<class-string>            $responseRoots The DTOs returned as response bodies.
     *
     * @throws ReflectionException When an enum cannot be reflected.
     */
    public static function build(array $shapes, array $requestRoots, array $responseRoots): self
    {
        return new self($shapes, ComponentNames::plan($shapes, $requestRoots, $responseRoots));
    }

    /**
     * A reference to the schema of `$class` on `$side`.
     *
     * @return array{'$ref': string}
     */
    public function dto(string $class, Side $side): array
    {
        $names = $side === Side::Input ? $this->names->inputs : $this->names->outputs;

        return self::reference($names[$class] ?? $class);
    }

    /**
     * The schema of a field's type on `$side`: a JSON type, or a reference to a
     * DTO or enum component, `null` added when the field is nullable.
     */
    public function type(FieldType $type, Side $side): array
    {
        $class = $type->class ?? '';

        return match ($type->kind) {
            TypeKind::Int => self::scalar('integer', $type->nullable),
            TypeKind::Float => self::scalar('number', $type->nullable),
            TypeKind::String => self::scalar('string', $type->nullable),
            TypeKind::Bool => self::scalar('boolean', $type->nullable),
            TypeKind::Enum => self::nullable(self::reference($this->names->enums[$class] ?? $class), $type->nullable),
            TypeKind::Dto => self::nullable($this->dto($class, $side), $type->nullable),
            TypeKind::List => [
                'type' => $type->nullable ? ['array', 'null'] : 'array',
                'items' => $type->item === null ? new stdClass() : $this->type($type->item, $side),
            ],
        };
    }

    /**
     * The component schemas, sorted by name.
     *
     * @return array<string, array>
     */
    public function components(): array
    {
        $components = $this->names->enumSchemas;
        foreach ($this->shapes as $class => $shape) {
            $input = $this->names->inputs[$class] ?? null;
            if ($input !== null) {
                $components[$input] = $this->inputSchema($shape);
            }

            // A symmetric DTO used on both sides has one component, already built.
            $output = $this->names->outputs[$class] ?? null;
            if ($output !== null) {
                $components[$output] ??= $this->outputSchema($shape);
            }
        }

        ksort($components);

        return $components;
    }

    /**
     * What a request may send: the constructor parameters, required when they
     * have no default and are not nullable.
     */
    private function inputSchema(DtoShape $shape): array
    {
        $properties = [];
        $required = [];
        foreach ($shape->inputs as $field) {
            $property = $this->type($field->type, Side::Input);
            if ($field->hasDefault) {
                $property['default'] = $field->default instanceof BackedEnum ? $field->default->value : $field->default;
            }

            $properties[$field->name] = $property;
            if ($field->required) {
                $required[] = $field->name;
            }
        }

        return self::object($properties, $required);
    }

    /**
     * What a response carries: every readable property, always present
     * (`null` included); a computed one is read-only.
     */
    private function outputSchema(DtoShape $shape): array
    {
        $properties = [];
        $required = [];
        foreach ($shape->outputs as $field) {
            $property = $this->type($field->type, Side::Output);
            if ($field->computed) {
                $property['readOnly'] = true;
            }

            $properties[$field->name] = $property;
            $required[] = $field->name;
        }

        return self::object($properties, $required);
    }

    /**
     * @return array{'$ref': string}
     */
    private static function reference(string $name): array
    {
        return ['$ref' => self::REF_PREFIX . $name];
    }

    /**
     * @param array<string, array> $properties
     * @param list<string>         $required
     */
    private static function object(array $properties, array $required): array
    {
        $schema = ['type' => 'object', 'properties' => $properties === [] ? new stdClass() : $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @return array{type: string|list<string>}
     */
    private static function scalar(string $type, bool $nullable): array
    {
        return ['type' => $nullable ? [$type, 'null'] : $type];
    }

    /**
     * @param array{'$ref': string} $reference
     */
    private static function nullable(array $reference, bool $nullable): array
    {
        return $nullable ? ['oneOf' => [$reference, ['type' => 'null']]] : $reference;
    }
}
