<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\PhpCollector;

test('the php collector reports the runtime version', function () {
    $collector = new PhpCollector;

    expect($collector->supported())->toBeTrue();

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('php')
        ->and($result->data['version'])->toBe(PHP_VERSION)
        ->and($result->data['sapi'])->toBe(PHP_SAPI)
        ->and($result->data)->toHaveKey('memory_limit');
});
