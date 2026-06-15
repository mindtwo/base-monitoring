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
 * Best-effort database detection via installed client binaries. Note that a
 * client's version can differ from the server's — framework plugins override
 * this metric with the live connection's server version where possible.
 */
final class DatabaseCollector extends AbstractCollector
{
    private ProcessRunner $processRunner;

    private ExecutableFinder $executables;

    private TechnologyResolver $technologies;

    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?ExecutableFinder $executables = null,
        ?TechnologyResolver $technologies = null
    ) {
        $this->processRunner = $processRunner ?? ProcessRunnerFactory::make();
        $this->executables = $executables ?? new ExecutableFinder;
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'database';
    }

    public function supported(): bool
    {
        return $this->processRunner->available() && $this->firstBinary() !== null;
    }

    public function collect(): CollectionResult
    {
        $found = $this->firstBinary();

        if ($found === null) {
            return CollectionResult::unsupported($this->key());
        }

        [$name, $path] = $found;

        $result = $this->processRunner->run([$path, '--version'], 10);
        $output = trim($result->anyOutput());

        if ($output === '') {
            return CollectionResult::failed($this->key(), sprintf('"%s --version" produced no output.', $name));
        }

        $detected = $this->parse($name, $output);

        if ($detected === null) {
            return CollectionResult::failed($this->key(), sprintf('Unable to parse a version from "%s".', $this->excerpt($output)));
        }

        [$identifier, $version] = $detected;

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve($identifier),
            $version,
            ['detected_via' => 'cli', 'client' => $name]
        ));
    }

    /**
     * @return array{0: string, 1: string}|null [binary name, absolute path]
     */
    private function firstBinary(): ?array
    {
        foreach (['mysql', 'mariadb', 'psql', 'sqlite3'] as $name) {
            $path = $this->executables->find($name);

            if ($path !== null) {
                return [$name, $path];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null [technology identifier, version]
     */
    private function parse(string $binary, string $output): ?array
    {
        if ($binary === 'mysql' || $binary === 'mariadb') {
            // MariaDB ships a "mysql" binary: "mysql  Ver 15.1 Distrib 10.11.6-MariaDB, for debian-linux-gnu"
            if (stripos($output, 'mariadb') !== false) {
                if (preg_match('/Distrib (\d+(?:\.\d+)+)/i', $output, $matches) === 1
                    || preg_match('/Ver (\d+(?:\.\d+)+)-MariaDB/i', $output, $matches) === 1
                    || preg_match('/(\d+\.\d+\.\d+)-MariaDB/i', $output, $matches) === 1
                    || preg_match('/Ver (\d+(?:\.\d+)+)/i', $output, $matches) === 1) {
                    return ['mariadb', $matches[1]];
                }

                return null;
            }

            // "mysql  Ver 8.0.36 for macos14.2 on arm64 (Homebrew)"
            return preg_match('/Ver (\d+(?:\.\d+)+)/i', $output, $matches) === 1
                ? ['mysql', $matches[1]]
                : null;
        }

        if ($binary === 'psql') {
            // "psql (PostgreSQL) 16.2 (Ubuntu 16.2-1.pgdg22.04+1)"
            return preg_match('/\(PostgreSQL\) (\d+(?:\.\d+)*)/i', $output, $matches) === 1
                ? ['postgresql', $matches[1]]
                : null;
        }

        // sqlite3: "3.45.1 2024-01-30 16:01:20 …"
        return preg_match('/^(\d+\.\d+(?:\.\d+)*)/', $output, $matches) === 1
            ? ['sqlite', $matches[1]]
            : null;
    }
}
