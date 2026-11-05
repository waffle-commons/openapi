<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * Its own fields match on both sides, but it embeds the asymmetric Contact: used
 * on both sides, it is split too, each side referring to the matching Contact.
 */
#[Dto]
final readonly class Envelope
{
    public function __construct(
        public string $id,
        public Contact $contact,
    ) {}
}
