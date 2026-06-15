<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Process\ProcessRunnerFactory;
use Mindtwo\Monitoring\Process\SymfonyProcessRunner;

test('the symfony runner is picked by the factory when symfony/process is installed', function () {
    // symfony/process is a dev dependency of this package, so it must be picked here.
    expect(SymfonyProcessRunner::supported())->toBeTrue()
        ->and(ProcessRunnerFactory::make())->toBeInstanceOf(SymfonyProcessRunner::class);
});

test('successful commands capture stdout and the exit code', function () {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'echo "hello";']);

    expect($result->successful)->toBeTrue()
        ->and($result->output)->toBe('hello')
        ->and($result->exitCode)->toBe(0);
});

test('failing commands capture stderr and the exit code', function () {
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'fwrite(STDERR, "broken"); exit(3);']);

    expect($result->successful)->toBeFalse()
        ->and($result->errorOutput)->toBe('broken')
        ->and($result->exitCode)->toBe(3);
});

test('processes exceeding the timeout are terminated', function () {
    $start = microtime(true);
    $result = (new SymfonyProcessRunner)->run([PHP_BINARY, '-r', 'sleep(10);'], 1);
    $duration = microtime(true) - $start;

    expect($result->successful)->toBeFalse()
        ->and($result->timedOut)->toBeTrue()
        ->and($duration)->toBeLessThan(5.0);
});

test('an empty command fails gracefully', function () {
    expect((new SymfonyProcessRunner)->run([])->successful)->toBeFalse();
});

test('extra paths are prepended to the child process PATH', function () {
    $result = (new SymfonyProcessRunner)->run(
        [PHP_BINARY, '-r', 'echo getenv("PATH");'],
        15,
        ['/opt/m2-interpreter/bin']
    );

    expect($result->successful)->toBeTrue()
        ->and(explode(PATH_SEPARATOR, $result->output)[0])->toBe('/opt/m2-interpreter/bin');
});
