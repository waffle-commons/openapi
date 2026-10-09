<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use LogicException;
use WaffleTests\Commons\OpenApi\Fixture\CreateOrder;
use WaffleTests\Commons\OpenApi\Fixture\NotADto;
use WaffleTests\Commons\OpenApi\Fixture\OrderView;

/**
 * Never called: the generator only reflects on the signatures.
 */
final class OrderController
{
    public function list(): array
    {
        throw new LogicException('Reflected only.');
    }

    public function show(int $id): OrderView
    {
        throw new LogicException('Reflected only: ' . $id);
    }

    public function create(CreateOrder $order): OrderView
    {
        throw new LogicException('Reflected only: ' . $order->customer);
    }

    public function update(int $id, CreateOrder $order): ?OrderView
    {
        throw new LogicException('Reflected only: ' . $id . $order->customer);
    }

    public function delete(int $id): void
    {
        throw new LogicException('Reflected only: ' . $id);
    }

    public function page(): string
    {
        throw new LogicException('Reflected only.');
    }

    /**
     * No return type: a bare 200.
     */
    public function raw()
    {
        throw new LogicException('Reflected only.');
    }

    public function search(?CreateOrder $filter = null): array|string
    {
        throw new LogicException('Reflected only: ' . ($filter?->customer ?? ''));
    }

    public function lookup(string $code, float $ratio, bool $flag): array
    {
        throw new LogicException('Reflected only: ' . $code . $ratio . ($flag ? '1' : '0'));
    }

    public function annotate(NotADto $note, int $id): void
    {
        throw new LogicException('Reflected only: ' . $note->value . $id);
    }
}
