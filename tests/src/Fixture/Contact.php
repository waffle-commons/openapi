<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/** Asymmetric and used on both sides: Contact-Input and Contact-Output. */
#[Dto]
final readonly class Contact
{
    public function __construct(
        public string $email,
        public ?string $phone = null,
    ) {}
}
