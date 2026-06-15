<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\ComposerLicensesCollector;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;

test('licenses are summarized and listed per package', function () {
    $payload = json_encode([
        'name' => 'acme/example-app',
        'version' => '1.0.0',
        'license' => ['proprietary'],
        'dependencies' => [
            'laravel/framework' => ['version' => 'v11.9.0', 'license' => ['MIT']],
            'guzzlehttp/guzzle' => ['version' => '7.8.1', 'license' => ['MIT']],
            'acme/dual' => ['version' => '1.0.0', 'license' => ['MIT', 'GPL-2.0-only']],
            'acme/unlicensed' => ['version' => '0.1.0', 'license' => []],
        ],
    ], JSON_THROW_ON_ERROR);

    $runner = (new FakeProcessRunner)->onOutput('composer licenses', $payload);

    $result = (new ComposerLicensesCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['licenses'])->toBe([
            'MIT' => 3,
            'GPL-2.0-only' => 1,
            'unknown' => 1,
        ])
        ->and($result->data['packages']['acme/dual'])->toBe(['MIT', 'GPL-2.0-only'])
        ->and($result->data['packages']['acme/unlicensed'])->toBe([]);
});

test('unparsable output fails', function () {
    $runner = (new FakeProcessRunner)
        ->on('composer licenses', new ProcessResult(false, 'garbage', 'something broke', 1));

    $result = (new ComposerLicensesCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('something broke');
});

test('timeouts are reported explicitly', function () {
    $runner = (new FakeProcessRunner)
        ->on('composer licenses', new ProcessResult(false, '', '', null, true));

    $result = (new ComposerLicensesCollector($runner, composerProject(), finderWith(['composer'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('timed out');
});
