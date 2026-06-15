<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\ApacheCollector;
use Mindtwo\Monitoring\Collectors\CaddyCollector;
use Mindtwo\Monitoring\Collectors\NginxCollector;
use Mindtwo\Monitoring\Collectors\RedisCollector;
use Mindtwo\Monitoring\Data\ProcessResult;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;
use Mindtwo\Monitoring\Tests\Support\TemporaryDirectories;

function finderWith(array $binaries): ExecutableFinder
{
    return new ExecutableFinder(TemporaryDirectories::binDir($binaries), []);
}

test('nginx version is parsed from stderr', function () {
    $runner = (new FakeProcessRunner)
        ->on('nginx -v', new ProcessResult(true, '', "nginx version: nginx/1.24.0 (Ubuntu)\n"));

    $result = (new NginxCollector($runner, finderWith(['nginx'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe(['technology' => 'nginx', 'version' => '1.24.0']);
});

test('apache version is parsed from any of its binaries', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('httpd -v', "Server version: Apache/2.4.58 (Unix)\nServer built: Oct 2023\n");

    $result = (new ApacheCollector($runner, finderWith(['httpd'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe(['technology' => 'apache-http-server', 'version' => '2.4.58']);
});

test('caddy version is parsed from its version line', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('caddy version', "v2.7.6 h1:w0NymbG2m9PcvKWsrXO6EEkY9Ru4FJK8uQbYcev1p3A=\n");

    $result = (new CaddyCollector($runner, finderWith(['caddy'])))->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe(['technology' => 'caddy', 'version' => '2.7.6']);
});

test('redis version is parsed from redis-server or redis-cli output', function () {
    $serverRunner = (new FakeProcessRunner)
        ->onOutput('redis-server --version', "Redis server v=7.2.4 sha=00000000:0 malloc=libc bits=64 build=fake\n");

    $result = (new RedisCollector($serverRunner, finderWith(['redis-server'])))->collect();

    expect($result->data)->toBe(['technology' => 'redis', 'version' => '7.2.4']);

    $cliRunner = (new FakeProcessRunner)->onOutput('redis-cli --version', "redis-cli 7.2.4\n");

    $result = (new RedisCollector($cliRunner, finderWith(['redis-cli'])))->collect();

    expect($result->data)->toBe(['technology' => 'redis', 'version' => '7.2.4']);
});

test('a missing binary marks the collector unsupported', function () {
    $collector = new NginxCollector(new FakeProcessRunner, new ExecutableFinder('', []));

    expect($collector->supported())->toBeFalse()
        ->and($collector->collect()->status)->toBe('unsupported');
});

test('a disabled process runner marks the collector unsupported', function () {
    $collector = new NginxCollector(new FakeProcessRunner(available: false), finderWith(['nginx']));

    expect($collector->supported())->toBeFalse();
});

test('unparsable output fails with an explanatory excerpt', function () {
    $runner = (new FakeProcessRunner)->onOutput('nginx -v', 'something unexpected');

    $result = (new NginxCollector($runner, finderWith(['nginx'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('something unexpected');
});

test('empty output fails with a clear message', function () {
    $runner = (new FakeProcessRunner)->on('nginx -v', new ProcessResult(true, '', ''));

    $result = (new NginxCollector($runner, finderWith(['nginx'])))->collect();

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('produced no output');
});
