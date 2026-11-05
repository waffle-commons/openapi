<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/** Only ever returned: every scalar kind, a nullable DTO, lists, enums and a computed property. */
#[Dto]
final class OrderView
{
    /** Computed: a virtual property with a `get` hook. */
    public int $lineCount {
        get => count($this->lines);
    }

    /**
     * @param list<OrderLine>   $lines
     * @param list<Status>      $history
     * @param list<string>|null $tags
     */
    public function __construct(
        public private(set) int $id,
        public private(set) string $customer,
        #[ListOf(OrderLine::class)]
        public private(set) array $lines,
        public private(set) Status $status,
        public private(set) ?Address $billing,
        #[ListOf(Status::class)]
        public private(set) array $history,
        public private(set) float $total,
        public private(set) bool $paid,
        public private(set) ?Priority $priority,
        #[ListOf('string')]
        public private(set) ?array $tags,
    ) {}
}
