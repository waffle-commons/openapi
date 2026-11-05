<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\OpenApi\Attribute\Operation;
use Waffle\Commons\Contracts\OpenApi\Attribute\Property;
use Waffle\Commons\Contracts\OpenApi\Attribute\Response;
use Waffle\Commons\OpenApi\Enum\Side;
use Waffle\Commons\OpenApi\Exception\InvalidAttributeException;
use Waffle\Commons\OpenApi\Schema\SchemaCatalog;

use function class_exists;
use function ksort;
use function sprintf;

/**
 * Reads the contracts' `#[OA\*]` attributes at build time ([API-01]): what a
 * signature cannot say about an operation, its responses and its DTO fields.
 *
 * Stateless: every call reflects again and keeps nothing.
 */
final class AttributeReader
{
    /**
     * What `#[OA\Operation]` says about an operation: the method's summary and
     * description, else the class's; the method's tags when it has any, else the
     * class's; deprecated when either level says so.
     *
     * @param ReflectionClass<object> $controller The route's controller class, which may extend the one declaring the method.
     *
     * @return array{tags?: list<string>, summary?: string, description?: string, deprecated?: true}
     */
    public static function operation(ReflectionMethod $method, ReflectionClass $controller): array
    {
        $onMethod = ($method->getAttributes(Operation::class)[0] ?? null)?->newInstance();
        $onClass = ($controller->getAttributes(Operation::class)[0] ?? null)?->newInstance();

        $described = [];
        $tags = $onMethod?->tags ?? [];
        $tags = $tags === [] ? $onClass?->tags ?? [] : $tags;
        if ($tags !== []) {
            $described['tags'] = $tags;
        }

        $summary = $onMethod?->summary ?? $onClass?->summary;
        if ($summary !== null) {
            $described['summary'] = $summary;
        }

        $description = $onMethod?->description ?? $onClass?->description;
        if ($description !== null) {
            $described['description'] = $description;
        }

        if (($onMethod?->deprecated ?? false) || ($onClass?->deprecated ?? false)) {
            $described['deprecated'] = true;
        }

        return $described;
    }

    /**
     * The `#[Dto]` classes the method's `#[OA\Response]` attributes name as bodies.
     *
     * @return list<class-string>
     *
     * @throws ReflectionException
     * @throws InvalidAttributeException When a status is not an HTTP status or a type is not a `#[Dto]` class.
     */
    public static function bodyTypes(ReflectionMethod $method): array
    {
        $types = [];
        foreach (self::responses($method) as $response) {
            if ($response->type === null) {
                continue;
            }

            $types[] = $response->type;
        }

        return $types;
    }

    /**
     * The responses the signature implies, rewritten and completed by the method's
     * `#[OA\Response]` attributes: a documented status takes the new description
     * and, given a type, the new body; another status is added. Sorted by status.
     *
     * @param array<int, array> $responses
     *
     * @return array<int, array>
     *
     * @throws ReflectionException
     * @throws InvalidAttributeException When a status is not an HTTP status or a type is not a `#[Dto]` class.
     */
    public static function documentResponses(array $responses, ReflectionMethod $method, SchemaCatalog $catalog): array
    {
        foreach (self::responses($method) as $documented) {
            $response = $responses[$documented->status] ?? [];
            $response['description'] = $documented->description;
            if ($documented->type !== null) {
                $response['content'] = [
                    'application/json' => ['schema' => $catalog->dto($documented->type, Side::Output)],
                ];
            }

            $responses[$documented->status] = $response;
        }

        ksort($responses);

        return $responses;
    }

    /**
     * The `#[OA\Property]` on the constructor parameter a request field comes from.
     *
     * @param class-string $class
     *
     * @throws ReflectionException
     */
    public static function inputProperty(string $class, string $name): ?Property
    {
        foreach (new ReflectionClass($class)->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->name === $name) {
                return ($parameter->getAttributes(Property::class)[0] ?? null)?->newInstance();
            }
        }

        return null;
    }

    /**
     * The `#[OA\Property]` on the property a response field comes from.
     *
     * @param class-string $class
     *
     * @throws ReflectionException
     */
    public static function outputProperty(string $class, string $name): ?Property
    {
        $reflection = new ReflectionClass($class);
        if (!$reflection->hasProperty($name)) {
            return null;
        }

        return ($reflection->getProperty($name)->getAttributes(Property::class)[0] ?? null)?->newInstance();
    }

    /**
     * The method's `#[OA\Response]` attributes, checked.
     *
     * @return list<Response>
     *
     * @throws ReflectionException
     * @throws InvalidAttributeException When a status is not an HTTP status or a type is not a `#[Dto]` class.
     */
    private static function responses(ReflectionMethod $method): array
    {
        $responses = [];
        foreach ($method->getAttributes(Response::class) as $attribute) {
            $response = $attribute->newInstance();
            if ($response->status < 100 || $response->status > 599) {
                throw new InvalidAttributeException(sprintf(
                    '#[OA\Response] on %s::%s(): %d is not an HTTP status.',
                    $method->class,
                    $method->name,
                    $response->status,
                ));
            }

            $type = (string) $response->type;
            if ($response->type !== null && (!class_exists($type) || !self::isDto(new ReflectionClass($type)))) {
                throw new InvalidAttributeException(sprintf(
                    '#[OA\Response] on %s::%s(): %s is not a #[Dto] class.',
                    $method->class,
                    $method->name,
                    $type,
                ));
            }

            $responses[] = $response;
        }

        return $responses;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function isDto(ReflectionClass $class): bool
    {
        return $class->getAttributes(Dto::class) !== [];
    }
}
