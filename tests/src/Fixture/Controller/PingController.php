<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use WaffleTests\Commons\OpenApi\Fixture\Ping;

final class PingController
{
    public function ping(): Ping
    {
        return new Ping();
    }
}
