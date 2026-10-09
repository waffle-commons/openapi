<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use LogicException;
use Psr\Http\Message\ResponseInterface;

final class ProxyController
{
    public function forward(string $path): ResponseInterface
    {
        throw new LogicException('Reflected only: ' . $path);
    }
}
