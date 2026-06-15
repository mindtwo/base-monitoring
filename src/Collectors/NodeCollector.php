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
 * Node.js runtime plus the npm version when npm is installed.
 */
final class NodeCollector extends AbstractCollector
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
        return 'node';
    }

    public function supported(): bool
    {
        return $this->processRunner->available() && $this->executables->exists('node');
    }

    public function collect(): CollectionResult
    {
        $node = $this->executables->find('node');

        if ($node === null) {
            return CollectionResult::unsupported($this->key());
        }

        $version = $this->version([$node, '--version']);

        if ($version === null) {
            return CollectionResult::failed($this->key(), 'Unable to determine the Node.js version.');
        }

        $extra = [];
        $npm = $this->executables->find('npm');

        if ($npm !== null) {
            $extra['npm_version'] = $this->version([$npm, '--version']);
        }

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve('node'),
            $version,
            $extra
        ));
    }

    /**
     * @param  array<int, string>  $command
     */
    private function version(array $command): ?string
    {
        $result = $this->processRunner->run($command, 10);
        $output = trim($result->anyOutput());

        if ($output === '') {
            return null;
        }

        return preg_match('/v?(\d+\.\d+(?:\.\d+)*)/', $output, $matches) === 1 ? $matches[1] : null;
    }
}
