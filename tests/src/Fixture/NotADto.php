<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

/** A plain object without #[Dto]: never a body. */
final readonly class NotADto
{
    public function __construct(
        public string $value = '',
    ) {}
}
