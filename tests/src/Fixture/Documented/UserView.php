<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Documented;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\OpenApi\Attribute as OA;

/**
 * A response body: `id` is described on its property (the response side reads the property), `name` on a
 * promoted parameter, which carries the attribute on both.
 */
#[Dto]
final class UserView
{
    #[OA\Property(description: 'Public identifier.', example: 42)]
    public private(set) int $id;

    public function __construct(
        int $id,
        #[OA\Property(description: 'Display name.')]
        public private(set) string $name,
    ) {
        $this->id = $id;
    }
}
