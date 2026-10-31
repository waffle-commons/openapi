<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi\Middleware;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use Waffle\Commons\Contracts\Routing\RouterInterface;
use Waffle\Commons\OpenApi\Exception\InvalidAttributeException;
use Waffle\Commons\OpenApi\OpenApiGenerator;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;

use function base64_encode;
use function hash;
use function htmlspecialchars;
use function sprintf;

/**
 * The development API documentation ([API-01]): `GET /_docs` answers a Swagger UI
 * page, and `GET /_docs/openapi.json` the document generated from the routes at
 * that moment. Every other request goes on down the pipeline.
 *
 * Development only, and fail-closed: an application mounts it in debug mode,
 * which production refuses, so in production both paths fall through to the
 * router's 404. The document describes every route, the ones a voter guards
 * included; it is meant for the developer's machine.
 *
 * The page loads Swagger UI from jsDelivr, both files pinned by their Subresource
 * Integrity hashes, and answers with its own Content-Security-Policy (decided with
 * the [API-01] design on 2026-10-31): scripts from jsDelivr plus the one inline
 * start-up script, by its hash; styles from jsDelivr plus the inline styles
 * Swagger UI writes; images from the page and `data:`; requests to the page's own
 * origin only; no frame, no base URI, no form sent elsewhere. Swagger UI's online
 * validator is switched off, so the page calls no third party besides jsDelivr.
 *
 * Stateless: nothing is cached, each request reads the router again.
 */
final readonly class ApiDocsMiddleware implements MiddlewareInterface
{
    public const string PATH = '/_docs';

    public const string DOCUMENT_PATH = '/_docs/openapi.json';

    public const string SWAGGER_UI_VERSION = '5.33.3';

    private const string CDN = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@' . self::SWAGGER_UI_VERSION;

    private const string SCRIPT_INTEGRITY = 'sha384-h00llMzD1afyNow1pDmnZKlHsUZgp9KE9HXLFXF2CvbwsBY0+WBO4f75SfGwvM4K';

    private const string STYLE_INTEGRITY = 'sha384-Ov4/wv3j2bmct8cDc5X4ngJZohVPzEmc6uDPH8WeljUxO5vtoykvMEfbu9Vh6RaW';

    private const string START_SCRIPT =
        "window.ui = SwaggerUIBundle({url: '" . self::DOCUMENT_PATH . "', dom_id: '#swagger-ui', validatorUrl: null});";

    public function __construct(
        private RouterInterface $router,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private string $title = 'API',
        private string $version = '1.0.0',
    ) {}

    /**
     * @throws ReflectionException          When a route names a controller method that does not exist.
     * @throws UnsupportedDtoShapeException When a DTO uses a construct the document cannot describe.
     * @throws InvalidAttributeException    When an `#[OA\Response]` names no HTTP status or no `#[Dto]` class.
     * @throws JsonException                When the document cannot be encoded.
     * @throws InvalidArgumentException     From the PSR-7 messages on an invalid header or body.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        if ($method !== 'GET' && $method !== 'HEAD') {
            return $handler->handle($request);
        }

        return match ($request->getUri()->getPath()) {
            self::PATH => $this->page(),
            self::DOCUMENT_PATH => $this->document(),
            default => $handler->handle($request),
        };
    }

    /** @throws InvalidArgumentException From the PSR-7 messages on an invalid header or body. */
    private function page(): ResponseInterface
    {
        $html = sprintf(<<<'HTML'
            <!doctype html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>%1$s · API documentation</title>
            <link rel="stylesheet" href="%2$s/swagger-ui.css" integrity="%3$s" crossorigin="anonymous">
            </head>
            <body>
            <div id="swagger-ui"></div>
            <script src="%2$s/swagger-ui-bundle.js" integrity="%4$s" crossorigin="anonymous"></script>
            <script>%5$s</script>
            </body>
            </html>

            HTML, htmlspecialchars(
            $this->title,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ), self::CDN, self::STYLE_INTEGRITY, self::SCRIPT_INTEGRITY, self::START_SCRIPT);
        $policy = sprintf(
            "default-src 'none'; script-src https://cdn.jsdelivr.net 'sha256-%s'; "
            . "style-src https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; "
            . "base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
            base64_encode(hash('sha256', self::START_SCRIPT, true)),
        );

        return $this
            ->respond($html, 'text/html; charset=utf-8')
            ->withHeader('Content-Security-Policy', $policy)
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    /**
     * @throws ReflectionException
     * @throws UnsupportedDtoShapeException
     * @throws InvalidAttributeException
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    private function document(): ResponseInterface
    {
        $generator = new OpenApiGenerator();
        $document = $generator->generate($this->router->getRoutes(), $this->title, $this->version);

        return $this->respond($generator->encode($document), 'application/json');
    }

    /** @throws InvalidArgumentException From the PSR-7 messages on an invalid header or body. */
    private function respond(string $body, string $contentType): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($this->streamFactory->createStream($body));
    }
}
