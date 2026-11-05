<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use Waffle\Commons\Contracts\OpenApi\Attribute as OA;
use WaffleTests\Commons\OpenApi\Fixture\NotADto;

/** `#[OA\Response]` attributes the document cannot carry. */
final class MisdocumentedController
{
    #[OA\Response(600, 'Not an HTTP status.')]
    public function badStatus(): void {}

    #[OA\Response(400, 'Not a #[Dto] body.', NotADto::class)]
    public function badType(): void {}
}
