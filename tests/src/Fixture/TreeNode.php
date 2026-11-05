<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/** An asymmetric cycle used on both sides: each side refers to itself. */
#[Dto]
final readonly class TreeNode
{
    /**
     * @param list<TreeNode> $children
     */
    public function __construct(
        public string $label,
        #[ListOf(TreeNode::class)]
        public array $children = [],
    ) {}
}
