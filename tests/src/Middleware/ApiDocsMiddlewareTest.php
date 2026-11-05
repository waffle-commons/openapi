<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\OpenApi\Middleware\ApiDocsMiddleware;
use WaffleTests\Commons\OpenApi\AbstractTestCase as TestCase;
use WaffleTests\Commons\OpenApi\Fixture\Controller\PingController;
use WaffleTests\Commons\OpenApi\Fixture\Double\FakeRouter;
use WaffleTests\Commons\OpenApi\Fixture\Routes;

use function base64_encode;
use function hash;
use function preg_match;

#[CoversClass(ApiDocsMiddleware::class)]
final class ApiDocsMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
    }

    public function testThePageLoadsTheSwaggerUiItsPolicyAllows(): void
    {
        $response = $this->middleware()->process($this->request('GET', '/_docs'), $this->next());
        $html = (string) $response->getBody();
        $policy = $response->getHeaderLine('Content-Security-Policy');

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        static::assertStringContainsString(
            '<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.33.3/swagger-ui-bundle.js" integrity="sha384-',
            $html,
        );
        static::assertStringContainsString(
            '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.33.3/swagger-ui.css" integrity="sha384-',
            $html,
        );

        // The one inline script is allowed by its hash, and it reads the document from this middleware.
        $inline = [];
        static::assertSame(1, preg_match('#<script>(.+)</script>#', $html, $inline));
        $script = $inline[1] ?? '';
        static::assertStringContainsString("url: '/_docs/openapi.json'", $script);
        static::assertStringContainsString('validatorUrl: null', $script);
        static::assertStringContainsString("'sha256-" . base64_encode(hash('sha256', $script, true)) . "'", $policy);

        static::assertStringContainsString("default-src 'none'", $policy);
        static::assertStringContainsString('script-src https://cdn.jsdelivr.net ', $policy);
        static::assertStringContainsString("connect-src 'self'", $policy);
        static::assertStringContainsString("frame-ancestors 'none'", $policy);
        static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        static::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        static::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
    }

    public function testThePageEscapesTheTitle(): void
    {
        $middleware = new ApiDocsMiddleware(new FakeRouter([]), $this->factory, $this->factory, '<b>"Shop"</b>');

        $html = (string) $middleware->process($this->request('GET', '/_docs'), $this->next())->getBody();

        static::assertStringContainsString(
            '<title>&lt;b&gt;&quot;Shop&quot;&lt;/b&gt; · API documentation</title>',
            $html,
        );
        static::assertStringNotContainsString('<b>', $html);
    }

    public function testTheDocumentIsGeneratedFromTheRoutes(): void
    {
        $response = $this->middleware()->process($this->request('GET', '/_docs/openapi.json'), $this->next());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        static::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        static::assertSame(
            ['title' => 'Fixture API', 'version' => '2.0.0'],
            self::at(self::decode((string) $response->getBody()), 'info'),
        );
        static::assertArrayHasKey('/ping', self::at(self::decode((string) $response->getBody()), 'paths'));
    }

    public function testHeadIsAnsweredLikeGet(): void
    {
        $page = $this->middleware()->process($this->request('HEAD', '/_docs'), $this->next());
        $document = $this->middleware()->process($this->request('HEAD', '/_docs/openapi.json'), $this->next());

        static::assertSame('text/html; charset=utf-8', $page->getHeaderLine('Content-Type'));
        static::assertSame('application/json', $document->getHeaderLine('Content-Type'));
    }

    public function testEveryOtherRequestGoesDownThePipeline(): void
    {
        foreach ([
            ['POST',   '/_docs'],
            ['DELETE', '/_docs/openapi.json'],
            ['GET',    '/_docs/'],
            ['GET',    '/ping'],
        ] as [$method, $path]) {
            $response = $this->middleware()->process($this->request($method, $path), $this->next());

            static::assertSame(418, $response->getStatusCode(), $method . ' ' . $path);
        }
    }

    private function middleware(): ApiDocsMiddleware
    {
        return new ApiDocsMiddleware(
            new FakeRouter([Routes::route(PingController::class, 'ping', '/ping', ['GET'], 'ping')]),
            $this->factory,
            $this->factory,
            'Fixture API',
            '2.0.0',
        );
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return $this->factory->createServerRequest($method, $path);
    }

    /** The rest of the pipeline: a 418, so a request the middleware let through is easy to tell. */
    private function next(): RequestHandlerInterface
    {
        return new readonly class($this->factory) implements RequestHandlerInterface {
            public function __construct(
                private Psr17Factory $factory,
            ) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse(418);
            }
        };
    }
}
