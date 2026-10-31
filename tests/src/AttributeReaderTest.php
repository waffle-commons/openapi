<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\OpenApi\AttributeReader;
use WaffleTests\Commons\OpenApi\AbstractTestCase as TestCase;
use WaffleTests\Commons\OpenApi\Fixture\Documented\UserInput;
use WaffleTests\Commons\OpenApi\Fixture\Documented\UserView;

#[CoversClass(AttributeReader::class)]
final class AttributeReaderTest extends TestCase
{
    public function testReadsAFieldsPropertyAttributeOnTheSideItDescribes(): void
    {
        static::assertSame('Display name.', AttributeReader::inputProperty(UserInput::class, 'name')?->description);
        static::assertSame('Ada', AttributeReader::inputProperty(UserInput::class, 'name')?->example);
        static::assertNull(AttributeReader::inputProperty(UserInput::class, 'email'));
        static::assertSame('Public identifier.', AttributeReader::outputProperty(UserView::class, 'id')?->description);
    }

    public function testANameTheClassDoesNotDeclareHasNoAttribute(): void
    {
        static::assertNull(AttributeReader::inputProperty(UserInput::class, 'missing'));
        static::assertNull(AttributeReader::outputProperty(UserView::class, 'missing'));
    }
}
