<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;

/**
 * Repository state of the deployed project: branch, commit and uncommitted
 * changes — a dirty production checkout is a deployment smell worth surfacing.
 */
final class GitStatusCollector extends AbstractCollector
{
    public const DEFAULT_MAX_CHANGED_FILES = 200;

    private ProcessRunner $processRunner;

    private ExecutableFinder $executables;

    private string $projectRoot;

    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?string $projectRoot = null,
        ?ExecutableFinder $executables = null,
        private int $maxChangedFiles = self::DEFAULT_MAX_CHANGED_FILES
    ) {
        $this->processRunner = $processRunner ?? ProcessRunnerFactory::make();
        $this->executables = $executables ?? new ExecutableFinder;
        $this->projectRoot = $projectRoot ?? (string) getcwd();
    }

    public function key(): string
    {
        return 'git';
    }

    public function supported(): bool
    {
        return $this->processRunner->available()
            && file_exists($this->projectRoot.'/.git')
            && $this->executables->exists('git');
    }

    public function collect(): CollectionResult
    {
        $branch = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);
        $commit = $this->git(['rev-parse', '--short', 'HEAD']);
        $status = $this->git(['status', '--porcelain']);

        if (! $branch->successful || ! $commit->successful || ! $status->successful) {
            $failed = ! $branch->successful ? $branch : (! $commit->successful ? $commit : $status);

            return CollectionResult::failed($this->key(), sprintf(
                'git failed: %s',
                $this->excerpt($failed->errorOutput !== '' ? $failed->errorOutput : 'unknown error')
            ));
        }

        $changedFiles = $this->parsePorcelain($status->output);
        $count = count($changedFiles);

        return CollectionResult::ok($this->key(), [
            'branch' => trim($branch->output),
            'commit' => trim($commit->output),
            'dirty' => $count > 0,
            'changed_files_count' => $count,
            'changed_files' => array_slice($changedFiles, 0, $this->maxChangedFiles),
            'changed_files_truncated' => $count > $this->maxChangedFiles,
        ]);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function git(array $arguments): ProcessResult
    {
        $git = $this->executables->find('git') ?? 'git';

        return $this->processRunner->run(
            array_merge([$git, '-C', $this->projectRoot], $arguments),
            10
        );
    }

    /**
     * @return array<int, array{status: string, path: string}>
     */
    private function parsePorcelain(string $output): array
    {
        $files = [];

        foreach (explode("\n", $output) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $files[] = [
                'status' => trim(substr($line, 0, 2)),
                'path' => trim(substr($line, 3)),
            ];
        }

        return $files;
    }
}
