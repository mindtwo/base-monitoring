<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\GitStatusCollector;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;
use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

function gitProjectRoot(): string
{
    $root = TemporaryDirectories::create('m2-git');
    mkdir($root.'/.git');

    return $root;
}

function gitRunner(string $porcelain = ''): FakeProcessRunner
{
    return (new FakeProcessRunner)
        ->onOutput('rev-parse --abbrev-ref HEAD', "main\n")
        ->onOutput('rev-parse --short HEAD', "a1b2c3d\n")
        ->on('status --porcelain', new ProcessResult(true, $porcelain));
}

test('a clean repository reports branch, commit and no changes', function () {
    $root = gitProjectRoot();
    $collector = new GitStatusCollector(gitRunner(), $root, finderWith(['git']));

    expect($collector->supported())->toBeTrue();

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['branch'])->toBe('main')
        ->and($result->data['commit'])->toBe('a1b2c3d')
        ->and($result->data['dirty'])->toBeFalse()
        ->and($result->data['changed_files'])->toBe([])
        ->and($result->data['changed_files_count'])->toBe(0)
        ->and($result->data['changed_files_truncated'])->toBeFalse();
});

test('a dirty repository lists structured changed files', function () {
    $porcelain = " M src/Monitor.php\n?? docs/new-file.md\nR  old.php -> new.php\n";
    $collector = new GitStatusCollector(gitRunner($porcelain), gitProjectRoot(), finderWith(['git']));

    $result = $collector->collect();

    expect($result->data['dirty'])->toBeTrue()
        ->and($result->data['changed_files_count'])->toBe(3)
        ->and($result->data['changed_files'])->toBe([
            ['status' => 'M', 'path' => 'src/Monitor.php'],
            ['status' => '??', 'path' => 'docs/new-file.md'],
            ['status' => 'R', 'path' => 'old.php -> new.php'],
        ]);
});

test('the changed file list is capped with a truncation flag', function () {
    $porcelain = implode("\n", array_map(
        static fn (int $i): string => " M file-$i.php",
        range(1, 30)
    ))."\n";

    $collector = new GitStatusCollector(gitRunner($porcelain), gitProjectRoot(), finderWith(['git']), maxChangedFiles: 10);

    $result = $collector->collect();

    expect($result->data['changed_files_count'])->toBe(30)
        ->and($result->data['changed_files'])->toHaveCount(10)
        ->and($result->data['changed_files_truncated'])->toBeTrue();
});

test('a directory without .git is unsupported', function () {
    $root = TemporaryDirectories::create();
    $collector = new GitStatusCollector(gitRunner(), $root, finderWith(['git']));

    expect($collector->supported())->toBeFalse();
});

test('git failures surface as failed metrics', function () {
    $runner = (new FakeProcessRunner)
        ->on('rev-parse --abbrev-ref HEAD', new ProcessResult(false, '', 'fatal: not a git repository', 128));

    $result = (new GitStatusCollector($runner, gitProjectRoot(), finderWith(['git'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('not a git repository');
});

test('worktree-style .git files are supported too', function () {
    $root = TemporaryDirectories::create();
    file_put_contents($root.'/.git', "gitdir: /somewhere/else\n");

    $collector = new GitStatusCollector(gitRunner(), $root, finderWith(['git']));

    expect($collector->supported())->toBeTrue();
});
