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
        ->and($result->data['vulnerabilities']['total'])->toBe(0)
        ->and($result->data['advisories_count'])->toBe(0)
        ->and($result->data['advisories'])->toBe([]);
});

test('npm v7 advisories are extracted with package detail', function () {
    $payload = json_encode([
        'auditReportVersion' => 2,
        'vulnerabilities' => [
            'axios' => [
                'name' => 'axios',
                'severity' => 'critical',
                'isDirect' => true,
                'via' => [
                    [
                        'source' => 1098583,
                        'name' => 'axios',
                        'dependency' => 'axios',
                        'title' => 'Server-Side Request Forgery in axios',
                        'url' => 'https://github.com/advisories/GHSA-8hc4-vh64-cxmj',
                        'severity' => 'critical',
                        'range' => '>=1.3.2 <1.7.4',
                    ],
                    'follow-redirects',
                ],
                'range' => '>=1.3.2 <1.7.4',
                'fixAvailable' => ['name' => 'axios', 'version' => '1.7.4', 'isSemVerMajor' => false],
            ],
        ],
        'metadata' => ['vulnerabilities' => ['info' => 0, 'low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 1, 'total' => 1]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->on('npm', new ProcessResult(false, $payload, '', 1));

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->status)->toBe('warning')
        ->and($result->data['advisories_count'])->toBe(1)
        ->and($result->data['advisories'][0])->toBe([
            'package' => 'axios',
            'severity' => 'critical',
            'cve' => null,
            'title' => 'Server-Side Request Forgery in axios',
            'affected_versions' => '>=1.3.2 <1.7.4',
            'link' => 'https://github.com/advisories/GHSA-8hc4-vh64-cxmj',
            'fix_available' => '1.7.4',
        ]);
});

test('the same advisory surfacing under several packages is reported once', function () {
    $advisory = [
        'source' => 1098583,
        'name' => 'axios',
        'title' => 'Server-Side Request Forgery in axios',
        'url' => 'https://github.com/advisories/GHSA-8hc4-vh64-cxmj',
        'severity' => 'high',
        'range' => '<1.7.4',
    ];

    $payload = json_encode([
        'vulnerabilities' => [
            'axios' => ['name' => 'axios', 'severity' => 'high', 'via' => [$advisory], 'fixAvailable' => true],
            '@scope/sdk' => ['name' => '@scope/sdk', 'severity' => 'high', 'via' => [$advisory], 'fixAvailable' => false],
        ],
        'metadata' => ['vulnerabilities' => ['high' => 2, 'total' => 2]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->on('npm', new ProcessResult(false, $payload, '', 1));

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->data['advisories_count'])->toBe(1);
});

test('npm v6 legacy advisories are extracted', function () {
    $payload = json_encode([
        'advisories' => [
            '1065' => [
                'id' => 1065,
                'module_name' => 'lodash',
                'severity' => 'high',
                'title' => 'Prototype Pollution',
                'url' => 'https://npmjs.com/advisories/1065',
                'vulnerable_versions' => '<4.17.12',
                'patched_versions' => '>=4.17.12',
                'cves' => ['CVE-2019-10744'],
            ],
        ],
        'metadata' => ['vulnerabilities' => ['info' => 0, 'low' => 0, 'moderate' => 0, 'high' => 1, 'critical' => 0]],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->on('npm', new ProcessResult(false, $payload, '', 1));

    $result = (new NpmAuditCollector($runner, npmAuditProject(), finderWith(['npm'])))->collect();

    expect($result->status)->toBe('warning')
        ->and($result->data['advisories'][0])->toBe([
            'package' => 'lodash',
            'severity' => 'high',
            'cve' => 'CVE-2019-10744',
            'title' => 'Prototype Pollution',
            'affected_versions' => '<4.17.12',
            'link' => 'https://npmjs.com/advisories/1065',
            'fix_available' => true,
        ]);
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

test('node directory is passed so npm can resolve its interpreter under a restricted PATH', function () {
    $bin = TemporaryDirectories::binDir(['node', 'npm']);
    $runner = (new FakeProcessRunner)->onOutput('npm', '{"metadata":{"vulnerabilities":{"total":0}}}');

    (new NpmAuditCollector($runner, npmAuditProject(), new ExecutableFinder($bin, [])))->collect();

    expect($runner->extraPaths[0])->toBe([$bin]);
});

test('the node directory is preferred when node lives apart from npm', function () {
    $npmDir = TemporaryDirectories::binDir(['npm']);
    $nodeDir = TemporaryDirectories::binDir(['node']);
    $runner = (new FakeProcessRunner)->onOutput('npm', '{"metadata":{"vulnerabilities":{"total":0}}}');

    $finder = new ExecutableFinder($npmDir.PATH_SEPARATOR.$nodeDir, []);
    (new NpmAuditCollector($runner, npmAuditProject(), $finder))->collect();

    expect($runner->extraPaths[0])->toBe([$nodeDir, $npmDir]);
});
