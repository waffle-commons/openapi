<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Documented;

use Waffle\Commons\Contracts\Attribute\Dto;

/** An error body that only an `#[OA\Response]` names. */
#[Dto]
final readonly class ProblemView
{
    public function __construct(
        public string $title,
        public int $status,
    ) {}
}
