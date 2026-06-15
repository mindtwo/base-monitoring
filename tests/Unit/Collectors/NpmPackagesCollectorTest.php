<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\NpmPackagesCollector;
use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

function npmProject(string $fixtureName, string $lockName): string
{
    $root = TemporaryDirectories::create('m2-npm');
    copy(fixturePath('npm/'.$fixtureName), $root.'/'.$lockName);

    return $root;
}

test('package-lock v3 entries are flattened and deduplicated', function () {
    $collector = new NpmPackagesCollector(npmProject('package-lock-v3.json', 'package-lock.json'));

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['lockfile'])->toBe('package-lock.json')
        ->and($result->data['count'])->toBe(4);

    $packages = collect_packages($result->data['packages']);

    expect($packages['vue'])->toBe(['name' => 'vue', 'version' => '3.4.21', 'dev' => false])
        ->and($packages['vite']['dev'])->toBeTrue()
        ->and($packages['@vitejs/plugin-vue']['version'])->toBe('5.0.4')
        // The hoisted copy wins over the nested duplicate.
        ->and($packages['nested-dep']['version'])->toBe('2.0.0');
});

test('package-lock v1 trees are walked recursively', function () {
    $collector = new NpmPackagesCollector(npmProject('package-lock-v1.json', 'package-lock.json'));

    $result = $collector->collect();

    $packages = collect_packages($result->data['packages']);

    expect($result->data['count'])->toBe(3)
        ->and($packages['jquery']['version'])->toBe('3.7.1')
        ->and($packages['webpack']['dev'])->toBeTrue()
        ->and($packages['acorn']['version'])->toBe('6.4.2');
});

test('yarn classic lockfiles are parsed including scoped packages', function () {
    $collector = new NpmPackagesCollector(npmProject('yarn-classic.lock', 'yarn.lock'));

    $result = $collector->collect();

    $packages = collect_packages($result->data['packages']);

    expect($result->data['lockfile'])->toBe('yarn.lock')
        ->and($result->data['count'])->toBe(3)
        ->and($packages['@babel/core']['version'])->toBe('7.24.0')
        ->and($packages['axios']['version'])->toBe('1.6.8')
        ->and($packages['lodash']['version'])->toBe('4.17.21');
});

test('yarn berry lockfiles are parsed and __metadata is skipped', function () {
    $collector = new NpmPackagesCollector(npmProject('yarn-berry.lock', 'yarn.lock'));

    $result = $collector->collect();

    $packages = collect_packages($result->data['packages']);

    expect($result->data['count'])->toBe(2)
        ->and($packages['@scope/tool']['version'])->toBe('2.1.4')
        ->and($packages['react']['version'])->toBe('18.2.0');
});

test('pnpm v6 lockfiles are parsed including scoped and peer-suffixed keys', function () {
    $collector = new NpmPackagesCollector(npmProject('pnpm-lock-v6.yaml', 'pnpm-lock.yaml'));

    $result = $collector->collect();

    $packages = collect_packages($result->data['packages']);

    expect($result->data['lockfile'])->toBe('pnpm-lock.yaml')
        ->and($result->data['count'])->toBe(3)
        ->and($packages['@vitejs/plugin-vue']['version'])->toBe('5.0.4')
        ->and($packages['vite']['version'])->toBe('5.2.0')
        ->and($packages['vue']['version'])->toBe('3.4.21');
});

test('pnpm v9 lockfiles are parsed from the packages section', function () {
    $collector = new NpmPackagesCollector(npmProject('pnpm-lock-v9.yaml', 'pnpm-lock.yaml'));

    $result = $collector->collect();

    $packages = collect_packages($result->data['packages']);

    expect($result->data['count'])->toBe(3)
        ->and($packages['@scope/tool']['version'])->toBe('2.1.4')
        ->and($packages['react']['version'])->toBe('18.2.0')
        ->and($packages['loose-envify']['version'])->toBe('1.4.0');
});

test('projects without a lockfile are unsupported', function () {
    $collector = new NpmPackagesCollector(TemporaryDirectories::create());

    expect($collector->supported())->toBeFalse()
        ->and($collector->collect()->status)->toBe('unsupported');
});

test('package-lock takes precedence over yarn.lock', function () {
    $root = npmProject('package-lock-v3.json', 'package-lock.json');
    copy(fixturePath('npm/yarn-classic.lock'), $root.'/yarn.lock');

    $result = (new NpmPackagesCollector($root))->collect();

    expect($result->data['lockfile'])->toBe('package-lock.json');
});
