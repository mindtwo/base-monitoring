<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\Source;
use Mindtwo\Monitoring\SnapshotFactory;
use Mindtwo\Monitoring\Tests\Fakes\LocalHttpServer;
use Mindtwo\Monitoring\Transport\HmacSignatureVerifier;
use Mindtwo\Monitoring\Transport\HttpTransport;

beforeAll(function () {
    $GLOBALS['m2_server'] = new LocalHttpServer;
    $GLOBALS['m2_server']->start();
});

afterAll(function () {
    $GLOBALS['m2_server']->stop();
    unset($GLOBALS['m2_server']);
});

function server(): LocalHttpServer
{
    return $GLOBALS['m2_server'];
}

function transportSnapshot(): Snapshot
{
    return (new SnapshotFactory(
        new Source('laravel', 'mindtwo/laravel-monitoring', '1.0.0', '1.0.0'),
        'production',
        'prj_test'
    ))->make();
}

test('a snapshot is POSTed as signed JSON', function (string $driver) {
    $transport = new HttpTransport(
        server()->url('/api/monitoring'),
        new Credentials('prj_test', 'secret'),
        timeoutSeconds: 5,
        driver: $driver
    );

    $result = $transport->send(transportSnapshot());

    expect($result->success)->toBeTrue()
        ->and($result->statusCode)->toBe(200)
        ->and($result->error)->toBeNull();

    $request = server()->lastRequest();

    expect($request['method'])->toBe('POST')
        ->and($request['uri'])->toBe('/api/monitoring')
        ->and($request['headers']['content-type'])->toBe('application/json')
        ->and($request['headers']['x-monitoring-key'])->toBe('prj_test')
        ->and($request['headers']['user-agent'])->toStartWith('mindtwo-monitoring/');

    // The body is valid snapshot JSON and the signature verifies against it.
    $payload = json_decode($request['body'], true);

    expect($payload['schema_version'])->toBe('1.0')
        ->and($payload['project_key'])->toBe('prj_test');

    $verifier = new HmacSignatureVerifier;

    expect($verifier->verify($request['body'], $request['headers'], new Credentials('prj_test', 'secret')))->toBeTrue()
        ->and($verifier->verify($request['body'], $request['headers'], new Credentials('prj_test', 'wrong')))->toBeFalse();
})->with(['curl', 'stream']);

test('non-2xx responses are reported as failures with the status code', function (string $driver) {
    $transport = new HttpTransport(
        server()->url('/fail-500'),
        new Credentials('prj_test', 'secret'),
        timeoutSeconds: 5,
        driver: $driver
    );

    $result = $transport->send(transportSnapshot());

    expect($result->success)->toBeFalse()
        ->and($result->statusCode)->toBe(500)
        ->and($result->error)->toContain('HTTP 500')
        ->and($result->error)->toContain('server exploded');
})->with(['curl', 'stream']);

test('unreachable endpoints fail without throwing', function (string $driver) {
    $transport = new HttpTransport(
        'http://127.0.0.1:1/api/monitoring',
        new Credentials('prj_test', 'secret'),
        timeoutSeconds: 1,
        driver: $driver
    );

    $result = $transport->send(transportSnapshot());

    expect($result->success)->toBeFalse()
        ->and($result->error)->not->toBeNull();
})->with(['curl', 'stream']);

test('incomplete credentials abort before any request is made', function () {
    $transport = new HttpTransport(server()->url('/api/monitoring'), Credentials::empty());

    $result = $transport->send(transportSnapshot());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('credentials');
});

test('non-http endpoints are rejected', function () {
    $transport = new HttpTransport('ftp://example.com/x', new Credentials('k', 's'));

    $result = $transport->send(transportSnapshot());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('only http(s)');
});
