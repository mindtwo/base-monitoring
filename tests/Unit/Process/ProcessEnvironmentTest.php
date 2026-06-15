<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Process\ProcessEnvironment;

test('extra directories are prepended to the current PATH', function () {
    $path = ProcessEnvironment::augmentedPath(['/opt/fake-interpreter/bin']);
    $directories = explode(PATH_SEPARATOR, $path);

    expect($directories[0])->toBe('/opt/fake-interpreter/bin')
        ->and($directories)->toContain(...explode(PATH_SEPARATOR, (string) getenv('PATH')));
});

test('the running PHP binary directory is always present', function () {
    expect(explode(PATH_SEPARATOR, ProcessEnvironment::augmentedPath()))
        ->toContain(dirname(PHP_BINARY));
});

test('duplicate and empty directories are removed', function () {
    $path = ProcessEnvironment::augmentedPath(['/opt/dup', '/opt/dup', '']);
    $directories = explode(PATH_SEPARATOR, $path);

    expect(array_count_values($directories)['/opt/dup'])->toBe(1)
        ->and($directories)->not->toContain('');
});

test('withAugmentedPath overrides PATH while preserving other variables', function () {
    putenv('M2_ENV_MARKER=preserved');

    try {
        $environment = ProcessEnvironment::withAugmentedPath(['/opt/marker/bin']);

        expect($environment)->toHaveKey('M2_ENV_MARKER')
            ->and($environment['M2_ENV_MARKER'])->toBe('preserved')
            ->and($environment['PATH'])->toBe(ProcessEnvironment::augmentedPath(['/opt/marker/bin']));
    } finally {
        putenv('M2_ENV_MARKER');
    }
});
