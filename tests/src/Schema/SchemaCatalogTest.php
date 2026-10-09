<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\OpenApi\Enum\Side;
use Waffle\Commons\OpenApi\Schema\ComponentNames;
use Waffle\Commons\OpenApi\Schema\SchemaCatalog;
use Waffle\Commons\Utils\Dto\Shape\DtoShapeReader;
use WaffleTests\Commons\OpenApi\AbstractTestCase as TestCase;
use WaffleTests\Commons\OpenApi\Fixture\Address;
use WaffleTests\Commons\OpenApi\Fixture\Clash\A\Item as ItemA;
use WaffleTests\Commons\OpenApi\Fixture\Clash\B\Item as ItemB;
use WaffleTests\Commons\OpenApi\Fixture\Contact;
use WaffleTests\Commons\OpenApi\Fixture\CreateOrder;
use WaffleTests\Commons\OpenApi\Fixture\Envelope;
use WaffleTests\Commons\OpenApi\Fixture\Folder;
use WaffleTests\Commons\OpenApi\Fixture\OrderView;
use WaffleTests\Commons\OpenApi\Fixture\Ping;
use WaffleTests\Commons\OpenApi\Fixture\TreeNode;

use function json_encode;

#[CoversClass(SchemaCatalog::class)]
#[CoversClass(ComponentNames::class)]
final class SchemaCatalogTest extends TestCase
{
    public function testASymmetricDtoHasOneSchemaForBothSides(): void
    {
        $components = self::components([Address::class], [Address::class]);

        static::assertSame(['Address'], array_keys($components));
        static::assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'street' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                ],
                'required' => ['street', 'city'],
            ],
            self::at($components, 'Address'),
        );
    }

    public function testADtoThatDiffersBetweenSidesHasOneSchemaPerSideWhenBothAreUsed(): void
    {
        $components = self::components([Contact::class], [Contact::class]);

        static::assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string'],
                    'phone' => ['type' => ['string', 'null'], 'default' => null],
                ],
                'required' => ['email'],
            ],
            self::at($components, 'Contact-Input'),
        );
        static::assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string'],
                    'phone' => ['type' => ['string', 'null']],
                ],
                'required' => ['email', 'phone'],
            ],
            self::at($components, 'Contact-Output'),
        );
    }

    public function testADtoThatEmbedsAnAsymmetricDtoIsSplitToo(): void
    {
        $components = self::components([Envelope::class], [Envelope::class]);

        static::assertSame(
            ['Contact-Input', 'Contact-Output', 'Envelope-Input', 'Envelope-Output'],
            array_keys($components),
        );
        static::assertSame(
            ['$ref' => '#/components/schemas/Contact-Input'],
            self::at($components, 'Envelope-Input', 'properties', 'contact'),
        );
        static::assertSame(
            ['$ref' => '#/components/schemas/Contact-Output'],
            self::at($components, 'Envelope-Output', 'properties', 'contact'),
        );
    }

    public function testADtoUsedOnOneSideKeepsItsPlainName(): void
    {
        static::assertSame(['Contact'], array_keys(self::components([Contact::class], [])));
        static::assertSame(['Contact'], array_keys(self::components([], [Contact::class])));
    }

    public function testACycleRefersToItsOwnSide(): void
    {
        $components = self::components([TreeNode::class, Folder::class], [TreeNode::class, Folder::class]);

        static::assertSame(['Folder', 'TreeNode-Input', 'TreeNode-Output'], array_keys($components));
        static::assertSame(
            ['$ref' => '#/components/schemas/TreeNode-Input'],
            self::at($components, 'TreeNode-Input', 'properties', 'children', 'items'),
        );
        static::assertSame([], self::at($components, 'TreeNode-Input', 'properties', 'children', 'default'));
        static::assertSame(
            ['$ref' => '#/components/schemas/TreeNode-Output'],
            self::at($components, 'TreeNode-Output', 'properties', 'children', 'items'),
        );
        static::assertSame(
            ['$ref' => '#/components/schemas/Folder'],
            self::at($components, 'Folder', 'properties', 'children', 'items'),
        );
    }

    public function testRequestFieldsCarryTheirDefaultsAndEnumsTheirBackingValues(): void
    {
        $components = self::components([CreateOrder::class], []);

        static::assertSame(['Address', 'CreateOrder', 'OrderLine', 'Priority'], array_keys($components));
        static::assertSame(['type' => 'integer', 'enum' => [1, 2, 3]], self::at($components, 'Priority'));
        static::assertSame(
            ['$ref' => '#/components/schemas/Priority', 'default' => 2],
            self::at($components, 'CreateOrder', 'properties', 'priority'),
        );
        static::assertSame(['customer', 'lines', 'shipping'], self::at($components, 'CreateOrder', 'required'));
    }

    public function testResponseFieldsAreAllRequiredAndComputedOnesReadOnly(): void
    {
        $view = self::at(self::components([], [OrderView::class]), 'OrderView');
        $properties = self::at($view, 'properties');

        static::assertSame(
            [
                'lineCount',
                'id',
                'customer',
                'lines',
                'status',
                'billing',
                'history',
                'total',
                'paid',
                'priority',
                'tags',
            ],
            self::at($view, 'required'),
        );
        static::assertSame(['type' => 'integer', 'readOnly' => true], self::at($properties, 'lineCount'));
        static::assertSame(['type' => 'number'], self::at($properties, 'total'));
        static::assertSame(['type' => 'boolean'], self::at($properties, 'paid'));
        static::assertSame(
            ['oneOf' => [['$ref' => '#/components/schemas/Address'], ['type' => 'null']]],
            self::at($properties, 'billing'),
        );
        static::assertSame(
            ['oneOf' => [['$ref' => '#/components/schemas/Priority'], ['type' => 'null']]],
            self::at($properties, 'priority'),
        );
        static::assertSame(
            ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Status']],
            self::at($properties, 'history'),
        );
        static::assertSame(
            ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
            self::at($properties, 'tags'),
        );
    }

    public function testClassesSharingAShortNameAreNamedInFull(): void
    {
        static::assertSame(
            ['WaffleTests.Commons.OpenApi.Fixture.Clash.A.Item', 'WaffleTests.Commons.OpenApi.Fixture.Clash.B.Item'],
            array_keys(self::components([], [ItemA::class, ItemB::class])),
        );
    }

    public function testADtoWithoutFieldsIsAnEmptyObject(): void
    {
        $catalog = self::catalog([], [Ping::class]);

        static::assertSame('{"Ping":{"type":"object","properties":{}}}', json_encode($catalog->components()));
    }

    public function testAReferenceToADtoTheCatalogDoesNotHoldFallsBackToTheClassName(): void
    {
        static::assertSame(
            ['$ref' => '#/components/schemas/App\Unknown'],
            self::catalog([], [])->dto('App\Unknown', Side::Output),
        );
    }

    /**
     * @param list<class-string> $requests
     * @param list<class-string> $responses
     */
    private static function catalog(array $requests, array $responses): SchemaCatalog
    {
        return SchemaCatalog::build(
            new DtoShapeReader()->readGraph(...$requests, ...$responses),
            $requests,
            $responses,
        );
    }

    /**
     * The components as JSON reads them back.
     *
     * @param list<class-string> $requests
     * @param list<class-string> $responses
     */
    private static function components(array $requests, array $responses): array
    {
        return self::decode(json_encode(self::catalog($requests, $responses)->components(), JSON_THROW_ON_ERROR));
    }
}
