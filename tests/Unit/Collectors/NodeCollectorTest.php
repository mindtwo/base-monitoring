<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\NodeCollector;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;

test('node and npm versions are collected together', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('node --version', "v20.11.1\n")
        ->onOutput('npm --version', "10.2.4\n");

    $result = (new NodeCollector($runner, finderWith(['node', 'npm'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe([
            'technology' => 'nodejs',
            'version' => '20.11.1',
            'npm_version' => '10.2.4',
        ]);
});

test('node without npm still reports its version', function () {
    $runner = (new FakeProcessRunner)->onOutput('node --version', "v20.11.1\n");

    $result = (new NodeCollector($runner, finderWith(['node'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe(['technology' => 'nodejs', 'version' => '20.11.1']);
});

test('missing node means unsupported even when npm exists', function () {
    $collector = new NodeCollector(new FakeProcessRunner, finderWith(['npm']));

    expect($collector->supported())->toBeFalse()
        ->and($collector->collect()->status)->toBe('unsupported');
});

test('unparsable node output fails', function () {
    $runner = (new FakeProcessRunner)->onOutput('node --version', 'flubber');

    $result = (new NodeCollector($runner, finderWith(['node'])))->collect();

    expect($result->status)->toBe('failed');
});

test('no binaries at all means unsupported', function () {
    expect((new NodeCollector(new FakeProcessRunner, new ExecutableFinder('', [])))->supported())->toBeFalse();
});
