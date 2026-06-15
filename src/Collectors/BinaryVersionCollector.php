<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

/**
 * Base class for collectors that detect a technology by locating a binary and
 * parsing its --version style output. Unavailable binaries or disabled process
 * functions mark the metric as unsupported instead of failing.
 */
abstract class BinaryVersionCollector extends AbstractCollector
{
    protected const PROCESS_TIMEOUT_SECONDS = 10;

    protected ProcessRunner $processRunner;

    protected ExecutableFinder $executables;

    protected TechnologyResolver $technologies;

    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?ExecutableFinder $executables = null,
        ?TechnologyResolver $technologies = null
    ) {
        $this->processRunner = $processRunner ?? ProcessRunnerFactory::make();
        $this->executables = $executables ?? new ExecutableFinder;
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    /**
     * Candidate binary names — the first one found on this system wins.
     *
     * @return array<int, string>
     */
    abstract protected function binaries(): array;

    /**
     * The identifier handed to the TechnologyResolver (e.g. "nginx").
     */
    abstract protected function technologyIdentifier(): string;

    abstract protected function parseVersion(string $output): ?string;

    /**
     * @return array<int, string>
     */
    protected function arguments(): array
    {
        return ['--version'];
    }

    public function supported(): bool
    {
        return $this->processRunner->available() && $this->executable() !== null;
    }

    public function collect(): CollectionResult
    {
        $binary = $this->executable();

        if ($binary === null) {
            return CollectionResult::unsupported($this->key());
        }

        $result = $this->processRunner->run(
            array_merge([$binary], $this->arguments()),
            static::PROCESS_TIMEOUT_SECONDS
        );

        $output = trim($result->anyOutput());

        if ($output === '') {
            return CollectionResult::failed($this->key(), sprintf(
                '"%s" produced no output%s.',
                basename($binary),
                $result->timedOut ? ' (timed out)' : ''
            ));
        }

        $version = $this->parseVersion($output);

        if ($version === null) {
            return CollectionResult::failed($this->key(), sprintf(
                'Unable to parse a version from "%s".',
                $this->excerpt($output)
            ));
        }

        $technology = $this->technologies->resolve($this->technologyIdentifier());

        return CollectionResult::ok($this->key(), $this->technologyData($technology, $version));
    }

    protected function executable(): ?string
    {
        foreach ($this->binaries() as $name) {
            $path = $this->executables->find($name);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }
}
