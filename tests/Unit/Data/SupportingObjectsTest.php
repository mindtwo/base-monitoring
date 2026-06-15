<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Data\Source;
use Mindtwo\Monitoring\Data\Technology;
use Mindtwo\Monitoring\Data\TransportResult;
use Mindtwo\Monitoring\Support\InstalledVersion;

test('credentials know whether they are complete', function () {
    expect((new Credentials('key', 'secret'))->isComplete())->toBeTrue()
        ->and((new Credentials('key', ''))->isComplete())->toBeFalse()
        ->and((new Credentials('', 'secret'))->isComplete())->toBeFalse()
        ->and(Credentials::empty()->isComplete())->toBeFalse();
});

test('a source serializes to snake_case keys', function () {
    $source = new Source(Source::TYPE_LARAVEL, 'mindtwo/laravel-monitoring', '1.2.0', '1.0.3');

    expect($source->toArray())->toBe([
        'type' => 'laravel',
        'package' => 'mindtwo/laravel-monitoring',
        'version' => '1.2.0',
        'base_version' => '1.0.3',
    ]);
});

test('the library source resolves its own installed version', function () {
    $source = Source::library();

    expect($source->type)->toBe('library')
        ->and($source->package)->toBe('mindtwo/base-monitoring')
        ->and($source->version)->toBe($source->baseVersion);
});

test('plugin sources resolve plugin and base versions independently', function () {
    $source = Source::plugin(Source::TYPE_WORDPRESS, 'acme/not-installed');

    expect($source->type)->toBe('wordpress')
        ->and($source->version)->toBe(InstalledVersion::UNKNOWN);
});

test('installed version lookup degrades to unknown', function () {
    expect(InstalledVersion::of('acme/definitely-not-installed'))->toBe('unknown');
});

test('transport results expose success and failure factories', function () {
    $delivered = TransportResult::delivered(202);
    $failed = TransportResult::failed('connection refused', 502);

    expect($delivered->success)->toBeTrue()
        ->and($delivered->statusCode)->toBe(202)
        ->and($delivered->error)->toBeNull()
        ->and($failed->success)->toBeFalse()
        ->and($failed->statusCode)->toBe(502)
        ->and($failed->error)->toBe('connection refused');
});

test('technologies know their provenance', function () {
    expect((new Technology('php'))->isKnown())->toBeTrue()
        ->and((new Technology('some-pkg', Technology::SOURCE_PACKAGE))->isKnown())->toBeFalse()
        ->and((new Technology('php'))->toArray())->toBe(['slug' => 'php', 'source' => 'known']);
});

test('process results fall back to stderr output', function () {
    expect((new ProcessResult(true, '', "nginx version: nginx/1.24.0\n"))->anyOutput())
        ->toBe("nginx version: nginx/1.24.0\n")
        ->and((new ProcessResult(true, 'stdout', 'stderr'))->anyOutput())->toBe('stdout');
});
