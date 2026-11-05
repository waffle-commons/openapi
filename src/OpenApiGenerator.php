<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi;

use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use stdClass;
use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Routing\MatchedRoute;
use Waffle\Commons\OpenApi\Enum\Side;
use Waffle\Commons\OpenApi\Exception\InvalidAttributeException;
use Waffle\Commons\OpenApi\Schema\SchemaCatalog;
use Waffle\Commons\Utils\Dto\Shape\DtoShapeReader;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;

use function array_key_exists;
use function class_exists;
use function count;
use function in_array;
use function json_encode;
use function ksort;
use function preg_match_all;
use function preg_replace;
use function strtolower;

/**
 * Builds an OpenAPI 3.1 document from the application's routes and the typed
 * signatures of their controllers, with no hand-written YAML ([API-01]).
 *
 * The routes come from `RouterInterface::getRoutes()` ([CARRY-04]): each
 * `MatchedRoute` names its path, HTTP methods, route name, controller class and
 * method. The generator reflects on that method at build time, never on a
 * request:
 *
 * - **Path parameters** come from the `{name}` and `{name:constraint}`
 *   placeholders; their type from the controller parameter of the same name
 *   (`int`, `float`, `bool`, else `string`); a string parameter's constraint
 *   becomes its pattern.
 * - **Request body:** the first parameter typed with a `#[Dto]` class, described
 *   by its constructor parameters, as the serializer hydrates it.
 * - **Responses**, as the kernel converts what the controller returns: a
 *   `#[Dto]` is a `200` JSON body described by its readable properties; an
 *   `array` a `200` JSON body; a `string` a `200` HTML page; `void` a `204`; any
 *   other type a bare `200`. A nullable return adds the `204`.
 *
 * - **What a signature cannot say** comes from the contracts' `#[OA\*]`
 *   attributes: `Operation` (summary, description, tags, deprecated) on the
 *   method or, as defaults, on the controller class; `Response` rewrites a status
 *   documented above or adds another; `Property` describes a DTO field (see
 *   {@see SchemaCatalog}).
 *
 * DTO and enum schemas are shared through `components/schemas` (see
 * {@see SchemaCatalog}). Without any attribute the document is valid, if terse:
 * it carries no descriptions beyond the status texts.
 *
 * Stateless: every call reflects again and keeps nothing.
 */
final readonly class OpenApiGenerator
{
    public const string OPENAPI_VERSION = '3.1.1';

    /** The methods documented for a route that accepts any method. */
    public const array ANY_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** The operations an OpenAPI 3.1 path item holds, in their document order. */
    private const array OPERATIONS = ['GET', 'PUT', 'POST', 'DELETE', 'OPTIONS', 'HEAD', 'PATCH', 'TRACE'];

    /** A route placeholder, as the router parses it: `{name}` or `{name:constraint}`. */
    private const string PLACEHOLDER = '/\{([a-zA-Z_]\w*)(?::([^}]+))?\}/';

    public function __construct(
        private DtoShapeReader $reader = new DtoShapeReader(),
    ) {}

    /**
     * @param list<MatchedRoute> $routes
     *
     * @return array The document, ready for {@see self::encode()}.
     *
     * @throws ReflectionException          When a route names a controller method that does not exist.
     * @throws UnsupportedDtoShapeException When a DTO uses a construct the document cannot describe.
     * @throws InvalidAttributeException    When an `#[OA\Response]` names no HTTP status or no `#[Dto]` class.
     */
    public function generate(array $routes, string $title = 'API', string $version = '1.0.0'): array
    {
        $requestRoots = [];
        $responseRoots = [];
        foreach ($routes as $route) {
            $method = new ReflectionMethod($route->className, $route->method);
            $body = self::body($method);
            if ($body !== null) {
                $requestRoots[] = $body[0];
            }

            $returned = self::dtoClass($method->getReturnType());
            if ($returned !== null) {
                $responseRoots[] = $returned;
            }

            $responseRoots = [...$responseRoots, ...AttributeReader::bodyTypes($method)];
        }

        $catalog = SchemaCatalog::build(
            $this->reader->readGraph(...$requestRoots, ...$responseRoots),
            $requestRoots,
            $responseRoots,
        );

        $paths = [];
        $operationIds = [];
        foreach ($routes as $route) {
            $template = preg_replace(self::PLACEHOLDER, '{$1}', $route->path) ?? $route->path;
            $methods = $route->methods === [] ? self::ANY_METHODS : $route->methods;
            foreach ($methods as $httpMethod) {
                if (!in_array($httpMethod, self::OPERATIONS, true)) {
                    continue;
                }

                $operationId = self::operationId($route, $httpMethod, count($methods) > 1, $operationIds);
                $operationIds[$operationId] = true;
                $paths[$template][$httpMethod] = $this->operation($route, $httpMethod, $operationId, $catalog);
            }
        }

        ksort($paths);
        $document = [
            'openapi' => self::OPENAPI_VERSION,
            'info' => ['title' => $title, 'version' => $version],
            'paths' => $paths === [] ? new stdClass() : self::lowercaseMethods($paths),
        ];

        $components = $catalog->components();
        if ($components !== []) {
            $document['components'] = ['schemas' => $components];
        }

        return $document;
    }

    /**
     * The document as pretty-printed JSON, ending with a newline.
     *
     * @throws JsonException When a value cannot be encoded (a string that is not UTF-8).
     */
    public function encode(array $document): string
    {
        return (
            json_encode(
                $document,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION,
            ) . "\n"
        );
    }

    /**
     * @throws ReflectionException
     * @throws InvalidAttributeException
     */
    private function operation(
        MatchedRoute $route,
        string $httpMethod,
        string $operationId,
        SchemaCatalog $catalog,
    ): array {
        $method = new ReflectionMethod($route->className, $route->method);
        $operation = AttributeReader::operation($method, new ReflectionClass($route->className))
        + ['operationId' => $operationId];

        $parameters = self::pathParameters($route->path, $method);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $body = self::body($method);
        if ($body !== null) {
            $operation['requestBody'] = [
                'required' => $body[1],
                'content' => ['application/json' => ['schema' => $catalog->dto($body[0], Side::Input)]],
            ];
        }

        $responses = AttributeReader::documentResponses(
            self::responses($method->getReturnType(), $catalog),
            $method,
            $catalog,
        );
        if ($httpMethod === 'HEAD') {
            // A response to HEAD carries the headers of a GET, never its body.
            foreach ($responses as $status => $response) {
                unset($response['content']);
                $responses[$status] = $response;
            }
        }

        $operation['responses'] = $responses;

        return $operation;
    }

    /**
     * One path parameter per placeholder, in path order.
     *
     * @return list<array>
     */
    private static function pathParameters(string $path, ReflectionMethod $method): array
    {
        $types = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $types[$parameter->getName()] = $type instanceof ReflectionNamedType ? $type->getName() : 'string';
        }

        $placeholders = [];
        preg_match_all(self::PLACEHOLDER, $path, $placeholders, PREG_SET_ORDER);

        $parameters = [];
        foreach ($placeholders as $placeholder) {
            $name = $placeholder[1] ?? '';
            $schema = ['type' => match ($types[$name] ?? 'string') {
                'int' => 'integer',
                'float' => 'number',
                'bool' => 'boolean',
                default => 'string',
            }];
            // JSON Schema applies `pattern` to strings only.
            $constraint = $placeholder[2] ?? '';
            if ($constraint !== '' && $schema['type'] === 'string') {
                $schema['pattern'] = '^(?:' . $constraint . ')$';
            }

            $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => $schema];
        }

        return $parameters;
    }

    /**
     * The responses the kernel produces from what the controller returns, keyed
     * by status code.
     *
     * @return array<int, array>
     *
     * @throws ReflectionException
     */
    private static function responses(?ReflectionType $returned, SchemaCatalog $catalog): array
    {
        $name = $returned instanceof ReflectionNamedType ? $returned->getName() : null;
        if ($name === 'void' || $name === 'null') {
            return ['204' => ['description' => 'No Content']];
        }

        $dto = self::dtoClass($returned);
        $ok = match (true) {
            $dto !== null => [
                'description' => 'OK',
                'content' => ['application/json' => ['schema' => $catalog->dto($dto, Side::Output)]],
            ],
            $name === 'array' => ['description' => 'OK', 'content' => ['application/json' => new stdClass()]],
            $name === 'string' => ['description' => 'OK', 'content' => ['text/html' => new stdClass()]],
            default => ['description' => 'OK'],
        };

        $responses = ['200' => $ok];
        if ($returned !== null && $returned->allowsNull() && $name !== 'mixed') {
            $responses['204'] = ['description' => 'No Content'];
        }

        return $responses;
    }

    /**
     * The first parameter typed with a `#[Dto]` class, and whether a request must
     * send it (it is neither nullable nor defaulted).
     *
     * @return array{class-string, bool}|null
     *
     * @throws ReflectionException
     */
    private static function body(ReflectionMethod $method): ?array
    {
        foreach ($method->getParameters() as $parameter) {
            $class = self::dtoClass($parameter->getType());
            if ($class !== null) {
                return [$class, !$parameter->allowsNull() && !$parameter->isDefaultValueAvailable()];
            }
        }

        return null;
    }

    /**
     * The `#[Dto]` class a type names, if it names one.
     *
     * @return class-string|null
     *
     * @throws ReflectionException
     */
    private static function dtoClass(?ReflectionType $type): ?string
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();
        if (!class_exists($class) || new ReflectionClass($class)->getAttributes(Dto::class) === []) {
            return null;
        }

        return $class;
    }

    /**
     * The route's name, suffixed with the method when the route serves several,
     * then with a number until it is unique in the document.
     *
     * @param array<string, true> $taken
     */
    private static function operationId(MatchedRoute $route, string $method, bool $several, array $taken): string
    {
        $base = $several ? $route->name . '_' . strtolower($method) : $route->name;
        $candidate = $base;
        for ($suffix = 2; array_key_exists($candidate, $taken); $suffix++) {
            $candidate = $base . '_' . $suffix;
        }

        return $candidate;
    }

    /**
     * Orders each path's operations as OpenAPI lists them and names them in
     * lowercase.
     *
     * @param array<string, array<string, array>> $paths
     *
     * @return array<string, array<string, array>>
     */
    private static function lowercaseMethods(array $paths): array
    {
        $ordered = [];
        foreach ($paths as $template => $operations) {
            foreach (self::OPERATIONS as $method) {
                if (!array_key_exists($method, $operations)) {
                    continue;
                }

                $ordered[$template][strtolower($method)] = $operations[$method];
            }
        }

        return $ordered;
    }
}
