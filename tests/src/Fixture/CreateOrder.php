<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/** Asymmetric (optional fields), and only ever sent: it keeps its plain name. */
#[Dto]
final readonly class CreateOrder
{
    /**
     * @param list<OrderLine> $lines
     */
    public function __construct(
        public string $customer,
        #[ListOf(OrderLine::class)]
        public array $lines,
        public Address $shipping,
        public ?string $note = null,
        public Priority $priority = Priority::Normal,
    ) {}
}
