<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/** No field at all: an empty object. */
#[Dto]
final readonly class Ping {}
