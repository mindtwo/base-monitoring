<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Support\ServerIp;

test('detect returns null or a usable, non-loopback ip address', function () {
    $ip = ServerIp::detect();

    expect($ip === null || (
        filter_var($ip, FILTER_VALIDATE_IP) !== false
        && $ip !== '0.0.0.0'
        && $ip !== '::'
        && $ip !== '::1'
        && ! str_starts_with($ip, '127.')
    ))->toBeTrue();
});

test('detection is stable within a process', function () {
    expect(ServerIp::detect())->toBe(ServerIp::detect());
});
