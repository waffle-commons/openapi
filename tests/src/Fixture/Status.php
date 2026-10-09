<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

enum Status: string
{
    case Draft = 'draft';
    case Sent = 'sent';
}
