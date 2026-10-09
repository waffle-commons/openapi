<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/** Symmetric: the same two required fields in and out. */
#[Dto]
final readonly class Address
{
    public function __construct(
        public string $street,
        public string $city,
    ) {}
}
