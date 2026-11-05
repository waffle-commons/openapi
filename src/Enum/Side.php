<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi\Enum;

/**
 * The side of the HTTP boundary a DTO schema describes: what a request may send
 * (its constructor parameters) or what a response carries (its readable
 * properties).
 */
enum Side: string
{
    case Input = 'Input';
    case Output = 'Output';
}
