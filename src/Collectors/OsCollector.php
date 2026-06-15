<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

/**
 * Operating system detection: /etc/os-release on Linux, sw_vers on macOS,
 * php_uname() everywhere else. Always supported — degrades to uname data.
 */
final class OsCollector extends AbstractCollector
{
    private ProcessRunner $processRunner;

    private TechnologyResolver $technologies;

    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?TechnologyResolver $technologies = null,
        private string $osReleasePath = '/etc/os-release',
        private ?string $osFamily = null
    ) {
        $this->processRunner = $processRunner ?? ProcessRunnerFactory::make();
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'os';
    }

    public function collect(): CollectionResult
    {
        $family = $this->osFamily ?? PHP_OS_FAMILY;

        if ($family === 'Linux') {
            return $this->collectLinux($family);
        }

        if ($family === 'Darwin') {
            return $this->collectMacos($family);
        }

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve($family),
            php_uname('r'),
            ['family' => $family, 'name' => php_uname('s')]
        ));
    }

    private function collectLinux(string $family): CollectionResult
    {
        $release = $this->parseOsRelease();
        $id = $release['ID'] ?? 'linux';

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve($id),
            $release['VERSION_ID'] ?? php_uname('r'),
            [
                'family' => $family,
                'name' => $release['PRETTY_NAME'] ?? $release['NAME'] ?? php_uname('s'),
                'kernel' => php_uname('r'),
            ]
        ));
    }

    private function collectMacos(string $family): CollectionResult
    {
        $version = null;

        if ($this->processRunner->available()) {
            $result = $this->processRunner->run(['sw_vers', '-productVersion'], 5);

            if ($result->successful && trim($result->output) !== '') {
                $version = trim($result->output);
            }
        }

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve('macos'),
            $version ?? php_uname('r'),
            [
                'family' => $family,
                'name' => 'macOS',
                'kernel' => php_uname('r'),
            ]
        ));
    }

    /**
     * @return array<string, string>
     */
    private function parseOsRelease(): array
    {
        if (! is_readable($this->osReleasePath)) {
            return [];
        }

        $contents = (string) file_get_contents($this->osReleasePath);
        $values = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $values[trim($name)] = trim(trim($value), '"\'');
        }

        return $values;
    }
}
