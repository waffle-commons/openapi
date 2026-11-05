<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Double;

use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Container\ContainerInterface;
use Waffle\Commons\Contracts\Routing\MatchedRoute;
use Waffle\Commons\Contracts\Routing\RouterInterface;

/**
 * A router that already knows its routes.
 */
final readonly class FakeRouter implements RouterInterface
{
    /**
     * @param list<MatchedRoute> $routes
     */
    public function __construct(
        private array $routes,
    ) {}

    #[\Override]
    public function boot(ContainerInterface $container): static
    {
        return $this;
    }

    #[\Override]
    public function matchRequest(ServerRequestInterface $request): ?MatchedRoute
    {
        return null;
    }

    #[\Override]
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
