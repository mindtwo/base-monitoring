<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\SystemStatsCollector;
use Mindtwo\Monitoring\Tests\Fakes\FakeProcessRunner;

test('linux stats come from procfs', function () {
    $collector = new SystemStatsCollector(
        new FakeProcessRunner,
        sys_get_temp_dir(),
        'Linux',
        fixturePath('meminfo'),
        fixturePath('cpuinfo')
    );

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['cpu_count'])->toBe(4)
        ->and($result->data['memory_total'])->toBe(16314840 * 1024)
        ->and($result->data['memory_available'])->toBe(8910848 * 1024)
        ->and($result->data['disk_total'])->toBeInt()
        ->and($result->data['disk_free'])->toBeInt();
});

test('macos stats come from sysctl and vm_stat', function () {
    $runner = (new FakeProcessRunner)
        ->onOutput('sysctl -n hw.logicalcpu', "8\n")
        ->onOutput('sysctl -n hw.memsize', "17179869184\n")
        ->onOutput('vm_stat', implode("\n", [
            'Mach Virtual Memory Statistics: (page size of 16384 bytes)',
            'Pages free:                              100000.',
            'Pages active:                            300000.',
            'Pages inactive:                          200000.',
            'Pages speculative:                        50000.',
        ])."\n");

    $result = (new SystemStatsCollector($runner, sys_get_temp_dir(), 'Darwin'))->collect();

    expect($result->data['cpu_count'])->toBe(8)
        ->and($result->data['memory_total'])->toBe(17179869184)
        ->and($result->data['memory_available'])->toBe((100000 + 200000) * 16384);
});

test('unknown values degrade to null instead of failing', function () {
    $collector = new SystemStatsCollector(
        new FakeProcessRunner(available: false),
        sys_get_temp_dir(),
        'Linux',
        '/nonexistent/meminfo',
        '/nonexistent/cpuinfo'
    );

    $result = $collector->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['cpu_count'])->toBeNull()
        ->and($result->data['memory_total'])->toBeNull()
        ->and($result->data['memory_available'])->toBeNull()
        ->and($result->data['disk_total'])->toBeInt();
});

test('a missing project root falls back to the filesystem root for disk stats', function () {
    $result = (new SystemStatsCollector(new FakeProcessRunner(available: false), '/nonexistent/path', 'Linux'))->collect();

    expect($result->data['disk_path'])->toBe('/');
});

test('the collector runs against the real host without errors', function () {
    $result = (new SystemStatsCollector)->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toHaveKeys([
            'cpu_count', 'memory_total', 'memory_available', 'disk_total', 'disk_free',
        ]);
});
