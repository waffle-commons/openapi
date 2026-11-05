<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/** A symmetric cycle used on both sides: one schema that refers to itself. */
#[Dto]
final readonly class Folder
{
    /**
     * @param list<Folder> $children
     */
    public function __construct(
        public string $name,
        #[ListOf(Folder::class)]
        public array $children,
    ) {}
}
