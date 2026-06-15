<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\OsCollector;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;

test('linux distributions are detected from os-release', function () {
    $collector = new OsCollector(
        new FakeProcessRunner,
        null,
        fixturePath('os-release-ubuntu'),
        'Linux'
    );

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('ubuntu')
        ->and($result->data['version'])->toBe('22.04')
        ->and($result->data['family'])->toBe('Linux')
        ->and($result->data['name'])->toBe('Ubuntu 22.04.4 LTS');
});

test('linux without a readable os-release degrades to uname data', function () {
    $collector = new OsCollector(new FakeProcessRunner, null, '/nonexistent/os-release', 'Linux');

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('linux')
        ->and($result->data['version'])->not->toBeEmpty();
});

test('macos resolves its product version through sw_vers', function () {
    $runner = (new FakeProcessRunner)->onOutput('sw_vers -productVersion', "14.4.1\n");

    $result = (new OsCollector($runner, null, '/etc/os-release', 'Darwin'))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('macos')
        ->and($result->data['version'])->toBe('14.4.1')
        ->and($result->data['name'])->toBe('macOS');
});

test('macos falls back to the kernel release when sw_vers is unavailable', function () {
    $result = (new OsCollector(new FakeProcessRunner(available: false), null, '/etc/os-release', 'Darwin'))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('macos')
        ->and($result->data['version'])->not->toBeEmpty();
});

test('other platforms report family and uname release', function () {
    $result = (new OsCollector(new FakeProcessRunner, null, '/etc/os-release', 'Windows'))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['family'])->toBe('Windows')
        ->and($result->data['version'])->not->toBeEmpty();
});

test('the collector is always supported', function () {
    expect((new OsCollector)->supported())->toBeTrue();
});
