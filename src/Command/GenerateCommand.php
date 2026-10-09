<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi\Command;

use JsonException;
use ReflectionException;
use Waffle\Commons\Contracts\Console\CommandInterface;
use Waffle\Commons\Contracts\Console\Enum\ExitCode;
use Waffle\Commons\Contracts\Console\InputInterface;
use Waffle\Commons\Contracts\Console\OutputInterface;
use Waffle\Commons\Contracts\Routing\RouterInterface;
use Waffle\Commons\OpenApi\OpenApiGenerator;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;

use function dirname;
use function file_exists;
use function file_put_contents;
use function getmypid;
use function hrtime;
use function is_dir;
use function mkdir;
use function rename;
use function sprintf;
use function unlink;

/**
 * `waffle openapi:generate` — writes the application's OpenAPI 3.1 document at
 * build time ([API-01]).
 *
 * The command reads the routes from the booted router (the same metadata
 * `route:list` and `route:compile` read) and hands them to the
 * {@see OpenApiGenerator}. It lives in this component and is registered in
 * `bin/waffle` next to the console commands ([CARRY-04]); it never runs on a
 * request.
 *
 * The document is written atomically: to a temporary file beside the target,
 * then renamed over it, so a reader never sees half a document. `--output=-`
 * writes it to the standard output instead.
 */
final readonly class GenerateCommand implements CommandInterface
{
    public const string DEFAULT_OUTPUT = 'openapi.json';

    public const string DEFAULT_TITLE = 'API';

    public const string DEFAULT_VERSION = '1.0.0';

    public function __construct(
        private RouterInterface $router,
        private OpenApiGenerator $generator = new OpenApiGenerator(),
    ) {}

    #[\Override]
    public function getName(): string
    {
        return 'openapi:generate';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Generates the OpenAPI 3.1 document of the application from its routes and DTOs.';
    }

    #[\Override]
    public function getHelp(): string
    {
        return (
            "Reads every #[Route] from the router and the typed signatures of their\n"
            . "controllers, and writes an OpenAPI 3.1 document: paths, path parameters,\n"
            . "#[Dto] request and response bodies, and their schemas.\n\n"
            . "  --output=<path>        Where to write it (default: openapi.json); '-' for the standard output.\n"
            . "  --title=<title>        The API title (default: API).\n"
            . '  --api-version=<ver>    The API version (default: 1.0.0).'
        );
    }

    #[\Override]
    public function getSynopsis(): string
    {
        return 'openapi:generate [--output=<path>|-] [--title=<title>] [--api-version=<version>]';
    }

    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getOption('output') ?? self::DEFAULT_OUTPUT;
        $title = $input->getOption('title') ?? self::DEFAULT_TITLE;
        $version = $input->getOption('api-version') ?? self::DEFAULT_VERSION;

        try {
            $document = $this->generator->generate($this->router->getRoutes(), $title, $version);
            $json = $this->generator->encode($document);
        } catch (ReflectionException|UnsupportedDtoShapeException|JsonException $exception) {
            $output->writeError(sprintf('OpenAPI generation failed: %s', $exception->getMessage()));

            return ExitCode::FAILURE->value;
        }

        if ($target === '-') {
            $output->write($json);

            return ExitCode::SUCCESS->value;
        }

        if (!self::write($target, $json)) {
            $output->writeError(sprintf('Cannot write the OpenAPI document to "%s".', $target));

            return ExitCode::CONFIG->value;
        }

        $output->writeLine(sprintf('OpenAPI document written to "%s".', $target));

        return ExitCode::SUCCESS->value;
    }

    /**
     * Writes `$contents` to a temporary file beside `$path`, then renames it over
     * `$path`.
     */
    private static function write(string $path, string $contents): bool
    {
        $directory = dirname($path);
        if (file_exists($directory) && !is_dir($directory)) {
            return false;
        }

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            return false;
        }

        $temporary = sprintf('%s.%d.%d.tmp', $path, (int) getmypid(), hrtime(true));
        if (file_put_contents($temporary, $contents) === false) {
            return false;
        }

        if (rename($temporary, $path)) {
            return true;
        }

        unlink($temporary);

        return false;
    }
}
