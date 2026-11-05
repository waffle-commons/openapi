<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Console\CommandInterface;
use Waffle\Commons\Contracts\Console\Enum\ExitCode;
use Waffle\Commons\OpenApi\Command\GenerateCommand;
use Waffle\Commons\OpenApi\OpenApiGenerator;
use WaffleTests\Commons\OpenApi\AbstractTestCase as TestCase;
use WaffleTests\Commons\OpenApi\Fixture\Controller\MisdocumentedController;
use WaffleTests\Commons\OpenApi\Fixture\Controller\OrderController;
use WaffleTests\Commons\OpenApi\Fixture\Double\ArrayInput;
use WaffleTests\Commons\OpenApi\Fixture\Double\BufferedOutput;
use WaffleTests\Commons\OpenApi\Fixture\Double\FakeRouter;
use WaffleTests\Commons\OpenApi\Fixture\Routes;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function scandir;
use function unlink;

#[CoversClass(GenerateCommand::class)]
final class GenerateCommandTest extends TestCase
{
    private const string WORK_DIRECTORY = __DIR__ . '/../../../var/test-output';

    #[\Override]
    protected function tearDown(): void
    {
        self::remove(self::WORK_DIRECTORY);
        parent::tearDown();
    }

    public function testIsAConsoleCommandNamedOpenapiGenerate(): void
    {
        $command = new GenerateCommand(new FakeRouter([]));

        static::assertInstanceOf(CommandInterface::class, $command);
        static::assertSame('openapi:generate', $command->getName());
        static::assertSame(
            'Generates the OpenAPI 3.1 document of the application from its routes and DTOs.',
            $command->getDescription(),
        );
        static::assertSame(
            'openapi:generate [--output=<path>|-] [--title=<title>] [--api-version=<version>]',
            $command->getSynopsis(),
        );
        static::assertStringContainsString('--output=<path>', $command->getHelp());
        static::assertSame('openapi.json', GenerateCommand::DEFAULT_OUTPUT);
    }

    public function testWritesTheDocumentToTheGivenPathCreatingItsDirectory(): void
    {
        $path = self::WORK_DIRECTORY . '/nested/docs/openapi.json';
        $output = new BufferedOutput();

        $code = new GenerateCommand(new FakeRouter(Routes::all()))->execute(new ArrayInput([
            'output' => $path,
            'title' => 'Fixture API',
            'api-version' => '2.0.0',
        ]), $output);

        static::assertSame(ExitCode::SUCCESS->value, $code);
        static::assertStringEqualsFile(Routes::GOLDEN_FILE, (string) file_get_contents($path));
        static::assertSame('OpenAPI document written to "' . $path . '".' . "\n", $output->out);
        static::assertSame('', $output->err);
        static::assertSame([$path], glob(self::WORK_DIRECTORY . '/nested/docs/*'), 'No temporary file is left.');
    }

    public function testReplacesAnExistingDocument(): void
    {
        $path = self::WORK_DIRECTORY . '/openapi.json';
        mkdir(self::WORK_DIRECTORY, 0o775, true);
        file_put_contents($path, 'stale');

        $code = new GenerateCommand(new FakeRouter([]))->execute(new ArrayInput([
            'output' => $path,
        ]), new BufferedOutput());

        static::assertSame(ExitCode::SUCCESS->value, $code);
        static::assertSame(
            ['title' => 'API', 'version' => '1.0.0'],
            self::at(self::decode((string) file_get_contents($path)), 'info'),
        );
    }

    public function testWritesToTheStandardOutputWithTheDefaultTitleAndVersion(): void
    {
        $output = new BufferedOutput();

        $code = new GenerateCommand(new FakeRouter([]))->execute(new ArrayInput(['output' => '-']), $output);

        $generator = new OpenApiGenerator();
        static::assertSame(ExitCode::SUCCESS->value, $code);
        static::assertSame($generator->encode($generator->generate([], 'API', '1.0.0')), $output->out);
    }

    public function testRefusesAnOutputWhoseDirectoryIsAFile(): void
    {
        mkdir(self::WORK_DIRECTORY, 0o775, true);
        $file = self::WORK_DIRECTORY . '/not-a-directory';
        file_put_contents($file, '');
        $output = new BufferedOutput();

        $code = new GenerateCommand(new FakeRouter([]))->execute(new ArrayInput([
            'output' => $file . '/openapi.json',
        ]), $output);

        static::assertSame(ExitCode::CONFIG->value, $code);
        static::assertSame('Cannot write the OpenAPI document to "' . $file . '/openapi.json".' . "\n", $output->err);
    }

    public function testReportsAGenerationThatFails(): void
    {
        $output = new BufferedOutput();

        $code = new GenerateCommand(new FakeRouter([
            Routes::route(OrderController::class, 'missing', '/missing', ['GET'], 'missing'),
        ]))->execute(new ArrayInput(['output' => '-']), $output);

        static::assertSame(ExitCode::FAILURE->value, $code);
        static::assertStringStartsWith('OpenAPI generation failed: ', $output->err);
        static::assertSame('', $output->out);
    }

    public function testReportsAResponseAttributeTheDocumentCannotCarry(): void
    {
        $output = new BufferedOutput();

        $code = new GenerateCommand(new FakeRouter([
            Routes::route(MisdocumentedController::class, 'badStatus', '/bad', ['GET'], 'bad'),
        ]))->execute(new ArrayInput(['output' => '-']), $output);

        static::assertSame(ExitCode::FAILURE->value, $code);
        static::assertStringContainsString('600 is not an HTTP status', $output->err);
        static::assertSame('', $output->out);
    }

    public function testReportsADocumentThatCannotBeEncoded(): void
    {
        $output = new BufferedOutput();

        $code = new GenerateCommand(new FakeRouter([]))->execute(new ArrayInput([
            'output' => '-',
            'title' => "\xB1\x31",
        ]), $output);

        static::assertSame(ExitCode::FAILURE->value, $code);
        static::assertStringStartsWith('OpenAPI generation failed: ', $output->err);
    }

    private static function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::remove($path . '/' . $entry);
        }

        rmdir($path);
    }
}
