<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Clash\B;

use Waffle\Commons\Contracts\Attribute\Dto;

/** Shares its short name with Clash\A\Item. */
#[Dto]
final readonly class Item
{
    public function __construct(
        public int $count,
    ) {}
}
