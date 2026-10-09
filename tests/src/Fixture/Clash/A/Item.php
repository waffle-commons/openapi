<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Clash\A;

use Waffle\Commons\Contracts\Attribute\Dto;

/** Shares its short name with Clash\B\Item: both get their full name. */
#[Dto]
final readonly class Item
{
    public function __construct(
        public string $code,
    ) {}
}
