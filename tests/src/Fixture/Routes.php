<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture;

use Waffle\Commons\Contracts\Routing\MatchedRoute;
use WaffleTests\Commons\OpenApi\Fixture\Controller\ClashController;
use WaffleTests\Commons\OpenApi\Fixture\Controller\ContactController;
use WaffleTests\Commons\OpenApi\Fixture\Controller\OrderController;
use WaffleTests\Commons\OpenApi\Fixture\Controller\PingController;
use WaffleTests\Commons\OpenApi\Fixture\Controller\ProxyController;
use WaffleTests\Commons\OpenApi\Fixture\Controller\TreeController;

/**
 * The routes of the fixture application, as the router returns them.
 */
final class Routes
{
    public const string GOLDEN_FILE = __DIR__ . '/../../fixtures/openapi.golden.json';

    /**
     * @return list<MatchedRoute>
     */
    public static function all(): array
    {
        return [
            self::route(OrderController::class, 'list', '/orders', ['GET'], 'orders_list'),
            self::route(OrderController::class, 'show', '/orders/{id:\\d+}', ['GET'], 'orders_show'),
            self::route(OrderController::class, 'create', '/orders', ['POST'], 'orders_create'),
            self::route(OrderController::class, 'update', '/orders/{id:\\d+}', ['PUT', 'PATCH'], 'orders_update'),
            self::route(OrderController::class, 'delete', '/orders/{id:\\d+}', ['DELETE'], 'orders_delete'),
            self::route(OrderController::class, 'page', '/orders/page', ['GET'], 'orders_page'),
            self::route(OrderController::class, 'raw', '/orders/raw', ['GET'], 'orders_raw'),
            self::route(OrderController::class, 'search', '/orders/search', ['POST'], 'orders_search'),
            self::route(OrderController::class, 'lookup', '/lookup/{code}/{ratio}/{flag}/{other}', ['GET'], 'lookup'),
            self::route(OrderController::class, 'annotate', '/orders/{id}/notes', ['POST', 'PURGE'], 'orders_annotate'),
            self::route(ContactController::class, 'save', '/contacts', ['PUT'], 'contacts_save'),
            self::route(ContactController::class, 'wrap', '/envelopes', ['PUT'], 'envelopes_wrap'),
            self::route(TreeController::class, 'echo', '/trees', ['POST'], 'trees_echo'),
            self::route(TreeController::class, 'folder', '/folders', ['POST'], 'folders_create'),
            self::route(ClashController::class, 'a', '/clash/a', ['GET'], 'clash'),
            self::route(ClashController::class, 'b', '/clash/b', ['GET'], 'clash'),
            self::route(PingController::class, 'ping', '/ping', ['GET', 'HEAD'], 'ping'),
            self::route(ProxyController::class, 'forward', '/proxy/{path:.*}', [], 'proxy'),
        ];
    }

    /**
     * @param class-string     $class
     * @param non-empty-string $method
     * @param non-falsy-string $path
     * @param list<string>     $methods
     * @param non-falsy-string $name
     */
    public static function route(
        string $class,
        string $method,
        string $path,
        array $methods,
        string $name,
    ): MatchedRoute {
        return new MatchedRoute(
            className: $class,
            method: $method,
            arguments: [],
            path: $path,
            name: $name,
            methods: $methods,
        );
    }
}
