<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\ComposerAuditCollector;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;
use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

function composerProject(): string
{
    $root = TemporaryDirectories::create('m2-composer');
    copy(fixturePath('composer/composer.json'), $root.'/composer.json');
    copy(fixturePath('composer/composer.lock'), $root.'/composer.lock');

    return $root;
}

test('a clean audit reports ok with empty advisory data', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('composer audit', '{"advisories": [], "abandoned": []}');

    $result = (new ComposerAuditCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['advisories_count'])->toBe(0)
        ->and($result->data['abandoned_count'])->toBe(0);
});

test('advisories are flattened and reported as a warning', function () {
    $payload = json_encode([
        'advisories' => [
            'acme/http' => [
                [
                    'advisoryId' => 'PKSA-1',
                    'packageName' => 'acme/http',
                    'affectedVersions' => '<2.0.1',
                    'title' => 'Request smuggling',
                    'cve' => 'CVE-2026-0001',
                    'link' => 'https://example.com/advisory',
                    'severity' => 'high',
                ],
                [
                    'advisoryId' => 'PKSA-2',
                    'packageName' => 'acme/http',
                    'affectedVersions' => '<1.9.0',
                    'title' => 'DoS via header bomb',
                    'cve' => null,
                    'link' => null,
                    'severity' => 'medium',
                ],
            ],
        ],
        'abandoned' => [
            'acme/old' => 'acme/new',
            'acme/dead' => null,
        ],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)
        ->on('composer audit', new ProcessResult(false, $payload, '', 1));

    $result = (new ComposerAuditCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('warning')
        ->and($result->data['advisories_count'])->toBe(2)
        ->and($result->data['advisories'][0])->toBe([
            'package' => 'acme/http',
            'severity' => 'high',
            'cve' => 'CVE-2026-0001',
            'title' => 'Request smuggling',
            'affected_versions' => '<2.0.1',
            'link' => 'https://example.com/advisory',
        ])
        ->and($result->data['abandoned'])->toBe(['acme/old' => 'acme/new', 'acme/dead' => null]);
});

test('the working directory is passed to composer', function () {
    $root = composerProject();
    $runner = (new FakeProcessRunner)->onOutput('composer audit', '{"advisories": []}');

    (new ComposerAuditCollector($runner, $root, finderWith(['composer'])))->collect();

    expect($runner->commands[0])->toContain('--working-dir='.$root)
        ->and($runner->commands[0])->toContain('--format=json');
});

test('php directory is passed so composer can resolve its interpreter under a restricted PATH', function () {
    $bin = TemporaryDirectories::binDir(['php', 'composer']);
    $runner = (new FakeProcessRunner)->onOutput('composer audit', '{"advisories": []}');

    (new ComposerAuditCollector($runner, composerProject(), new ExecutableFinder($bin, [])))->collect();

    expect($runner->extraPaths[0])->toBe([$bin]);
});

test('no extra paths are passed when php cannot be located', function () {
    $runner = (new FakeProcessRunner)->onOutput('composer audit', '{"advisories": []}');

    (new ComposerAuditCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($runner->extraPaths[0])->toBe([]);
});

test('unparsable output fails with the stderr excerpt', function () {
    $runner = (new FakeProcessRunner)
        ->on('composer audit', new ProcessResult(false, '', 'network unreachable', 1));

    $result = (new ComposerAuditCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('network unreachable');
});

test('timeouts are reported explicitly', function () {
    $runner = (new FakeProcessRunner)
        ->on('composer audit', new ProcessResult(false, '', '', null, true));

    $result = (new ComposerAuditCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('timed out');
});

test('missing composer.lock or composer binary means unsupported', function () {
    $noLock = new ComposerAuditCollector(new FakeProcessRunner, TemporaryDirectories::create(), finderWith(['composer']));
    $noBinary = new ComposerAuditCollector(new FakeProcessRunner, composerProject(), new ExecutableFinder('', []));

    expect($noLock->supported())->toBeFalse()
        ->and($noBinary->supported())->toBeFalse();
});
