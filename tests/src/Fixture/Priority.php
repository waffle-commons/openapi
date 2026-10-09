<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

enum Priority: int
{
    case Low = 1;
    case Normal = 2;
    case High = 3;
}
