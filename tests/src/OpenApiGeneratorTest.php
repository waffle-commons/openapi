<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi;

use JsonException;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionException;
use Waffle\Commons\OpenApi\OpenApiGenerator;
use Waffle\Commons\OpenApi\Schema\ComponentNames;
use Waffle\Commons\OpenApi\Schema\SchemaCatalog;
use WaffleTests\Commons\OpenApi\AbstractTestCase as TestCase;
use WaffleTests\Commons\OpenApi\Fixture\Controller\OrderController;
use WaffleTests\Commons\OpenApi\Fixture\Routes;

use function array_keys;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function hash;
use function json_decode;
use function json_encode;
use function str_replace;
use function substr_count;

#[CoversClass(OpenApiGenerator::class)]
#[CoversClass(SchemaCatalog::class)]
#[CoversClass(ComponentNames::class)]
final class OpenApiGeneratorTest extends TestCase
{
    private const string SCHEMA_FILE = __DIR__ . '/../fixtures/oas-3.1-schema-2026-08-03.json';

    private const string SCHEMA_ID = 'https://spec.openapis.org/oas/3.1/schema/2026-08-03';

    private const string SCHEMA_SHA256 = '59f106413cb48c31299f96f024c938d3628aed6cd02cd14bcfb2fcaae7a130b6';

    /**
     * The [API-01] acceptance criterion: the generated document validates against
     * the official OpenAPI 3.1 schema.
     */
    public function testTheDocumentValidatesAgainstTheOfficialOpenApi31Schema(): void
    {
        $result = self::validate($this->json());

        static::assertTrue($result->isValid(), self::errors($result));
    }

    /**
     * The oracle itself: the same validator rejects what the schema forbids.
     */
    public function testTheOfficialSchemaRejectsInvalidDocuments(): void
    {
        $pathParameterNotRequired = <<<'JSON'
            {"openapi": "3.1.1", "info": {"title": "t", "version": "1"}, "paths": {"/a/{id}": {"get": {
                "parameters": [{"name": "id", "in": "path", "schema": {"type": "integer"}}],
                "responses": {"200": {"description": "OK"}}
            }}}}
            JSON;
        $schemaIsANumber = <<<'JSON'
            {"openapi": "3.1.1", "info": {"title": "t", "version": "1"}, "paths": {},
             "components": {"schemas": {"Address": 42}}}
            JSON;
        $unknownOperationKey = <<<'JSON'
            {"openapi": "3.1.1", "info": {"title": "t", "version": "1"}, "paths": {"/a": {"get": {
                "responses": {"200": {"description": "OK"}}, "bogus": 1
            }}}}
            JSON;

        static::assertFalse(self::validate($pathParameterNotRequired)->isValid());
        static::assertFalse(self::validate($schemaIsANumber)->isValid());
        static::assertFalse(self::validate($unknownOperationKey)->isValid());
    }

    /**
     * The full document for the fixture application. After a deliberate change
     * to the generator, regenerate it with `WAFFLE_UPDATE_GOLDEN=1 composer tests`.
     */
    public function testTheDocumentMatchesTheGoldenFile(): void
    {
        $json = $this->json();
        if (getenv('WAFFLE_UPDATE_GOLDEN') === '1') {
            file_put_contents(Routes::GOLDEN_FILE, $json);
        }

        static::assertStringEqualsFile(Routes::GOLDEN_FILE, $json);
    }

    public function testDescribesTheApiAndTheOpenApiVersion(): void
    {
        $document = $this->document();

        static::assertSame('3.1.1', $document['openapi']);
        static::assertSame(['title' => 'Fixture API', 'version' => '2.0.0'], self::at($document, 'info'));
    }

    public function testPathPlaceholdersLoseTheirConstraintWhichBecomesAStringPattern(): void
    {
        $document = $this->document();

        static::assertSame(
            [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
            self::at($document, 'paths', '/orders/{id}', 'get', 'parameters'),
            'JSON Schema applies a pattern to strings only.',
        );
        static::assertSame(
            [[
                'name' => 'path',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'pattern' => '^(?:.*)$',
                ],
            ]],
            self::at($document, 'paths', '/proxy/{path}', 'get', 'parameters'),
        );
    }

    public function testAPathParameterTakesTheTypeOfTheControllerParameter(): void
    {
        $parameters = self::at(
            $this->document(),
            'paths',
            '/lookup/{code}/{ratio}/{flag}/{other}',
            'get',
            'parameters',
        );

        $names = [];
        $schemas = [];
        foreach (array_keys($parameters) as $index) {
            $parameter = self::at($parameters, $index);
            $names[] = $parameter['name'];
            $schemas[] = self::at($parameter, 'schema');
        }

        static::assertSame(['code', 'ratio', 'flag', 'other'], $names);
        static::assertSame(
            [['type' => 'string'], ['type' => 'number'], ['type' => 'boolean'], ['type' => 'string']],
            $schemas,
        );
    }

    public function testEachHttpMethodIsAnOperationInDocumentOrder(): void
    {
        $document = $this->document();
        $order = self::at($document, 'paths', '/orders/{id}');

        static::assertSame(['get', 'put', 'delete', 'patch'], array_keys($order));
        static::assertSame('orders_show', self::at($order, 'get')['operationId']);
        static::assertSame('orders_update_put', self::at($order, 'put')['operationId']);
        static::assertSame('orders_update_patch', self::at($order, 'patch')['operationId']);
        static::assertSame(['get', 'head'], array_keys(self::at($document, 'paths', '/ping')));
    }

    public function testAHeadResponseHasNoBody(): void
    {
        $ping = self::at($this->document(), 'paths', '/ping');

        static::assertArrayHasKey('content', self::at($ping, 'get', 'responses', 200));
        static::assertSame([200 => ['description' => 'OK']], self::at($ping, 'head', 'responses'));
    }

    public function testARouteThatAcceptsAnyMethodIsDocumentedForTheCommonOnes(): void
    {
        $proxy = self::at($this->document(), 'paths', '/proxy/{path}');

        static::assertSame(['get', 'put', 'post', 'delete', 'patch'], array_keys($proxy));
        static::assertSame('proxy_post', self::at($proxy, 'post')['operationId']);
    }

    public function testAMethodOpenApiCannotDescribeIsLeftOut(): void
    {
        $notes = self::at($this->document(), 'paths', '/orders/{id}/notes');

        static::assertSame(['post'], array_keys($notes));
        static::assertArrayNotHasKey(
            'requestBody',
            self::at($notes, 'post'),
            'A parameter without #[Dto] is not a body.',
        );
    }

    public function testOperationIdsAreUniqueInTheDocument(): void
    {
        $document = $this->document();

        static::assertSame('clash', self::at($document, 'paths', '/clash/a', 'get')['operationId']);
        static::assertSame('clash_2', self::at($document, 'paths', '/clash/b', 'get')['operationId']);
    }

    public function testADtoParameterIsTheRequestBody(): void
    {
        $document = $this->document();

        static::assertSame(
            [
                'required' => true,
                'content' => [
                    'application/json' => ['schema' => [
                        '$ref' => '#/components/schemas/CreateOrder',
                    ]],
                ],
            ],
            self::at($document, 'paths', '/orders', 'post', 'requestBody'),
        );
        static::assertFalse(self::at($document, 'paths', '/orders/search', 'post', 'requestBody')['required']);
    }

    public function testResponsesFollowWhatTheKernelMakesOfTheReturnedValue(): void
    {
        $paths = self::at($this->document(), 'paths');

        static::assertSame(
            [
                200 => [
                    'description' => 'OK',
                    'content' => [
                        'application/json' => ['schema' => [
                            '$ref' => '#/components/schemas/OrderView',
                        ]],
                    ],
                ],
            ],
            self::at($paths, '/orders/{id}', 'get', 'responses'),
            'A #[Dto] is a JSON body.',
        );
        static::assertSame(
            [200, 204],
            array_keys(self::at($paths, '/orders/{id}', 'put', 'responses')),
            'A nullable return adds the 204.',
        );
        static::assertSame(
            [204 => ['description' => 'No Content']],
            self::at($paths, '/orders/{id}', 'delete', 'responses'),
        );
        static::assertSame(
            [200 => ['description' => 'OK', 'content' => ['application/json' => []]]],
            self::at($paths, '/orders', 'get', 'responses'),
            'An array is a JSON body.',
        );
        static::assertSame(
            [200 => ['description' => 'OK', 'content' => ['text/html' => []]]],
            self::at($paths, '/orders/page', 'get', 'responses'),
            'A string is an HTML page.',
        );
        static::assertSame([200 => ['description' => 'OK']], self::at($paths, '/orders/raw', 'get', 'responses'));
        static::assertSame([200 => ['description' => 'OK']], self::at($paths, '/orders/search', 'post', 'responses'));
        static::assertSame([200 => ['description' => 'OK']], self::at($paths, '/proxy/{path}', 'get', 'responses'));
    }

    public function testWithoutRoutesTheDocumentIsEmptyButValid(): void
    {
        $generator = new OpenApiGenerator();
        $json = $generator->encode($generator->generate([]));

        static::assertSame(
            "{\n    \"openapi\": \"3.1.1\",\n    \"info\": {\n        \"title\": \"API\",\n        \"version\": \"1.0.0\"\n    },\n    \"paths\": {}\n}\n",
            $json,
        );
        static::assertTrue(self::validate($json)->isValid());
    }

    public function testARouteToAMissingMethodFailsTheGeneration(): void
    {
        $this->expectException(ReflectionException::class);

        new OpenApiGenerator()->generate([Routes::route(
            OrderController::class,
            'missing',
            '/missing',
            ['GET'],
            'missing',
        )]);
    }

    public function testATitleJsonCannotEncodeFailsTheEncoding(): void
    {
        $generator = new OpenApiGenerator();

        $this->expectException(JsonException::class);

        $generator->encode($generator->generate([], "\xB1\x31"));
    }

    private function json(): string
    {
        $generator = new OpenApiGenerator();

        return $generator->encode($generator->generate(Routes::all(), 'Fixture API', '2.0.0'));
    }

    private function document(): array
    {
        return self::decode($this->json());
    }

    private static function validate(string $json): ValidationResult
    {
        $schema = (string) file_get_contents(self::SCHEMA_FILE);
        static::assertSame(self::SCHEMA_SHA256, hash('sha256', $schema), 'The official schema must stay verbatim.');

        // opis/json-schema 2.6 does not resolve $dynamicRef. With this schema as
        // the entry point, its only "meta" anchor is $defs/schema, so each
        // `$dynamicRef: #meta` means `$ref: #/$defs/schema` (see fixtures/NOTICE.md).
        static::assertSame(1, substr_count($schema, '"$dynamicAnchor"'));
        static::assertSame('meta', self::at(self::decode($schema), '$defs', 'schema')['$dynamicAnchor']);
        $count = 0;
        $compatible = str_replace('"$dynamicRef": "#meta"', '"$ref": "#/$defs/schema"', $schema, $count);
        static::assertSame(4, $count);

        $validator = new CompliantValidator();
        $validator->resolver()?->registerRaw($compatible, self::SCHEMA_ID);

        return $validator->validate(json_decode($json, false, 512, JSON_THROW_ON_ERROR), self::SCHEMA_ID);
    }

    private static function errors(ValidationResult $result): string
    {
        $error = $result->error();

        return $error === null
            ? ''
            : json_encode(new ErrorFormatter()->format($error, false), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
