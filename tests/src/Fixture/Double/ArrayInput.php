<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Double;

use Waffle\Commons\Contracts\Console\InputInterface;

/**
 * Console input whose options are given up front.
 */
final readonly class ArrayInput implements InputInterface
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(
        private array $options = [],
    ) {}

    #[\Override]
    public function getArgument(string $name, ?string $default = null): ?string
    {
        return $default;
    }

    #[\Override]
    public function getOption(string $name, ?string $default = null): ?string
    {
        return $this->options[$name] ?? $default;
    }

    #[\Override]
    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    #[\Override]
    public function getArguments(): array
    {
        return [];
    }

    #[\Override]
    public function getOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    public function getRawArguments(): array
    {
        return [];
    }
}
