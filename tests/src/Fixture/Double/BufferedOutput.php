<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Double;

use Waffle\Commons\Contracts\Console\Enum\Verbosity;
use Waffle\Commons\Contracts\Console\OutputInterface;

/**
 * Console output kept in memory, standard output and errors apart.
 */
final class BufferedOutput implements OutputInterface
{
    public string $out = '';

    public string $err = '';

    private Verbosity $verbosity = Verbosity::NORMAL;

    #[\Override]
    public function write(string $message, Verbosity $threshold = Verbosity::NORMAL): void
    {
        $this->out .= $message;
    }

    #[\Override]
    public function writeLine(string $message, Verbosity $threshold = Verbosity::NORMAL): void
    {
        $this->out .= $message . "\n";
    }

    #[\Override]
    public function writeError(string $message): void
    {
        $this->err .= $message . "\n";
    }

    #[\Override]
    public function getVerbosity(): Verbosity
    {
        return $this->verbosity;
    }

    #[\Override]
    public function setVerbosity(Verbosity $level): void
    {
        $this->verbosity = $level;
    }
}
