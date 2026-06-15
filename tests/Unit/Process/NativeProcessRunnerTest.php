<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Process\NativeProcessRunner;

test('the native runner is available in the test environment', function () {
    expect((new NativeProcessRunner)->available())->toBeTrue();
});

test('successful commands capture stdout and the exit code', function () {
    $result = (new NativeProcessRunner)->run([PHP_BINARY, '-r', 'echo "hello";']);

    expect($result->successful)->toBeTrue()
        ->and($result->output)->toBe('hello')
        ->and($result->errorOutput)->toBe('')
        ->and($result->exitCode)->toBe(0)
        ->and($result->timedOut)->toBeFalse();
});

test('failing commands capture stderr and the non-zero exit code', function () {
    $result = (new NativeProcessRunner)->run([PHP_BINARY, '-r', 'fwrite(STDERR, "broken"); exit(3);']);

    expect($result->successful)->toBeFalse()
        ->and($result->errorOutput)->toBe('broken')
        ->and($result->exitCode)->toBe(3);
});

test('large output is captured completely without deadlocking', function () {
    $result = (new NativeProcessRunner)->run([PHP_BINARY, '-r', 'echo str_repeat("x", 200000);']);

    expect($result->successful)->toBeTrue()
        ->and(strlen($result->output))->toBe(200000);
});

test('processes exceeding the timeout are terminated', function () {
    $start = microtime(true);
    $result = (new NativeProcessRunner)->run([PHP_BINARY, '-r', 'sleep(10);'], 1);
    $duration = microtime(true) - $start;

    expect($result->successful)->toBeFalse()
        ->and($result->timedOut)->toBeTrue()
        ->and($result->errorOutput)->toContain('timeout')
        ->and($duration)->toBeLessThan(5.0);
});

test('an empty command fails gracefully', function () {
    $result = (new NativeProcessRunner)->run([]);

    expect($result->successful)->toBeFalse()
        ->and($result->errorOutput)->toBe('No command given.');
});

test('a nonexistent binary fails gracefully', function () {
    $result = (new NativeProcessRunner)->run(['/definitely/not/a/binary-xyz']);

    expect($result->successful)->toBeFalse();
});
