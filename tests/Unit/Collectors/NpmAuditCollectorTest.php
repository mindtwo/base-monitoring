<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\NpmAuditCollector;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;
use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

function npmAuditProject(): string
{
    return npmProject('package-lock-v3.json', 'package-lock.json');
}

test('a clean audit reports ok with zeroed counts', function () {
    $payload = json_encode([
        'auditReportVersion' => 2,
        'vulnerabilities' => [],
        'metadata' => ['vulnerabilities' => ['info' => 0, 'low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0, 'total' => 0]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->onOutput('npm', $payload);

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['vulnerabilities']['total'])->toBe(0);
});

test('vulnerabilities are reported as a warning with severity counts', function () {
    $payload = json_encode([
        'auditReportVersion' => 2,
        'metadata' => ['vulnerabilities' => ['info' => 0, 'low' => 3, 'moderate' => 1, 'high' => 2, 'critical' => 1, 'total' => 7]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)
        ->on('npm', new ProcessResult(false, $payload, '', 1));

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->status)->toBe('warning')
        ->and($result->data['vulnerabilities'])->toBe([
            'info' => 0, 'low' => 3, 'moderate' => 1, 'high' => 2, 'critical' => 1, 'total' => 7,
        ]);
});

test('info-only findings still count as ok', function () {
    $payload = json_encode([
        'metadata' => ['vulnerabilities' => ['info' => 2, 'low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0, 'total' => 2]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->onOutput('npm', $payload);

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->status)->toBe('ok');
});

test('npm error envelopes fail with their summary', function () {
    $payload = json_encode([
        'error' => ['code' => 'ENOAUDIT', 'summary' => 'Registry unavailable', 'detail' => ''],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->on('npm', new ProcessResult(false, $payload, '', 1));

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toBe('Registry unavailable');
});

test('missing totals are computed from the severity counts', function () {
    $payload = json_encode([
        'metadata' => ['vulnerabilities' => ['info' => 1, 'low' => 2, 'moderate' => 0, 'high' => 0, 'critical' => 0]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->onOutput('npm', $payload);

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->data['vulnerabilities']['total'])->toBe(3);
});

test('a project without a package-lock is unsupported', function () {
    $rootWithYarnOnly = TemporaryDirectories::create();
    copy(fixturePath('npm/yarn-classic.lock'), $rootWithYarnOnly.'/yarn.lock');

    $collector = new NpmAuditCollector(new FakeProcessRunner, $rootWithYarnOnly, finderWith(['npm']));

    expect($collector->supported())->toBeFalse();
});

test('a missing npm binary is unsupported', function () {
    $collector = new NpmAuditCollector(new FakeProcessRunner, npmAuditProject(), new ExecutableFinder('', []));

    expect($collector->supported())->toBeFalse();
});
