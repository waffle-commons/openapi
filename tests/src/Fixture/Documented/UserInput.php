<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Documented;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\OpenApi\Attribute as OA;

/** A request body whose first field is described: the request side reads the constructor parameter. */
#[Dto]
final readonly class UserInput
{
    public function __construct(
        #[OA\Property(description: 'Display name.', example: 'Ada')]
        public string $name,
        public ?string $email = null,
    ) {}
}
