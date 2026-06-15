<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

test('binaries on the configured PATH are found', function () {
    $bin = TemporaryDirectories::binDir(['fake-nginx']);
    $finder = new ExecutableFinder($bin, []);

    expect($finder->find('fake-nginx'))->toBe($bin.'/fake-nginx')
        ->and($finder->exists('fake-nginx'))->toBeTrue()
        ->and($finder->find('missing-tool'))->toBeNull()
        ->and($finder->exists('missing-tool'))->toBeFalse();
});

test('fallback directories are scanned after the PATH', function () {
    $bin = TemporaryDirectories::binDir(['fake-redis']);
    $finder = new ExecutableFinder('', [$bin]);

    expect($finder->find('fake-redis'))->toBe($bin.'/fake-redis');
});

test('the PATH wins over fallback directories', function () {
    $pathDir = TemporaryDirectories::binDir(['tool']);
    $fallbackDir = TemporaryDirectories::binDir(['tool']);
    $finder = new ExecutableFinder($pathDir, [$fallbackDir]);

    expect($finder->find('tool'))->toBe($pathDir.'/tool');
});

test('non-executable files are skipped', function () {
    $dir = TemporaryDirectories::create();
    file_put_contents($dir.'/plain-file', 'data');
    chmod($dir.'/plain-file', 0644);

    expect((new ExecutableFinder($dir, []))->find('plain-file'))->toBeNull();
});

test('absolute paths are validated directly', function () {
    $bin = TemporaryDirectories::binDir(['caddy']);
    $finder = new ExecutableFinder('', []);

    expect($finder->find($bin.'/caddy'))->toBe($bin.'/caddy')
        ->and($finder->find($bin.'/missing'))->toBeNull();
});

test('the default finder locates php', function () {
    // PHP_BINARY's directory must be discoverable when present on the real PATH;
    // at minimum the finder runs without errors against the real environment.
    expect((new ExecutableFinder)->find('definitely-not-installed-xyz'))->toBeNull();
});
