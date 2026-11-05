<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use Waffle\Commons\Contracts\OpenApi\Attribute as OA;
use WaffleTests\Commons\OpenApi\Fixture\Documented\ProblemView;
use WaffleTests\Commons\OpenApi\Fixture\Documented\UserInput;
use WaffleTests\Commons\OpenApi\Fixture\Documented\UserView;

/** Every `#[OA\*]` case: class defaults, method overrides, rewritten and added responses. */
#[OA\Operation(description: 'Users of the fixture API.', tags: ['users'])]
final class DocumentedController
{
    #[OA\Operation(summary: 'Fetch a user')]
    #[OA\Response(200, 'The user.')]
    #[OA\Response(404, 'No user has this id.', ProblemView::class)]
    public function show(int $id): UserView
    {
        return new UserView($id, 'Ada');
    }

    #[OA\Operation(
        summary: 'Create a user',
        description: 'Answers the stored user.',
        tags: ['admin'],
        deprecated: true,
    )]
    #[OA\Response(422, 'The input is invalid.')]
    public function create(UserInput $input): UserView
    {
        return new UserView(1, $input->name);
    }

    public function purge(): void {}
}
