<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use WaffleTests\Commons\OpenApi\Fixture\Clash\A\Item as ItemA;
use WaffleTests\Commons\OpenApi\Fixture\Clash\B\Item as ItemB;

final class ClashController
{
    public function a(): ItemA
    {
        return new ItemA('a');
    }

    public function b(): ItemB
    {
        return new ItemB(1);
    }
}
