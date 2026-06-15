<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\ComposerPackagesCollector;

test('composer.lock packages are listed with dev, direct and technology flags', function () {
    $collector = new ComposerPackagesCollector(fixturePath('composer'));

    expect($collector->supported())->toBeTrue();

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['count'])->toBe(4);

    $packages = collect_packages($result->data['packages']);

    expect($packages['laravel/framework'])->toBe([
        'name' => 'laravel/framework',
        'version' => '11.9.0',
        'dev' => false,
        'direct' => true,
        'technology' => 'laravel',
    ])
        ->and($packages['guzzlehttp/guzzle']['direct'])->toBeFalse()
        // "guzzle" is itself a pinned endoflife.date slug, matched via the repo segment.
        ->and($packages['guzzlehttp/guzzle']['technology'])->toBe('guzzle')
        ->and($packages['craftcms/cms']['technology'])->toBe('craft-cms')
        ->and($packages['pestphp/pest']['dev'])->toBeTrue()
        ->and($packages['pestphp/pest']['direct'])->toBeTrue();
});

test('a project without composer.lock is unsupported', function () {
    expect((new ComposerPackagesCollector(sys_get_temp_dir()))->supported())->toBeFalse();
});

test('malformed lock files fail with a clear message', function () {
    $root = Mindtwo\Monitoring\Tests\Support\TemporaryDirectories::create();
    file_put_contents($root.'/composer.lock', '{not json');

    $result = (new ComposerPackagesCollector($root))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('composer.lock');
});

/**
 * @param  array<int, array<string, mixed>>  $packages
 * @return array<string, array<string, mixed>>
 */
function collect_packages(array $packages): array
{
    $keyed = [];

    foreach ($packages as $package) {
        $keyed[$package['name']] = $package;
    }

    return $keyed;
}
