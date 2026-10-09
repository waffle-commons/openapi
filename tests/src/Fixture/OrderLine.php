<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/** Symmetric. */
#[Dto]
final readonly class OrderLine
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {}
}
