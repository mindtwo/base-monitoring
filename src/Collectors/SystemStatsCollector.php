<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Collectors;

use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;

/**
 * Host statistics: CPU count, memory and disk usage. Pure-PHP/procfs based on
 * Linux, sysctl/vm_stat on macOS. Values that cannot be determined on the
 * current platform are reported as null instead of failing.
 */
final class SystemStatsCollector extends AbstractCollector
{
    private ProcessRunner $processRunner;

    private string $projectRoot;

    public function __construct(
        ?ProcessRunner $processRunner = null,
        ?string $projectRoot = null,
        private ?string $osFamily = null,
        private string $memInfoPath = '/proc/meminfo',
        private string $cpuInfoPath = '/proc/cpuinfo'
    ) {
        $this->processRunner = $processRunner ?? ProcessRunnerFactory::make();
        $this->projectRoot = $projectRoot ?? (string) getcwd();
    }

    public function key(): string
    {
        return 'system';
    }

    public function collect(): CollectionResult
    {
        $family = $this->osFamily ?? PHP_OS_FAMILY;

        $diskPath = $this->projectRoot !== '' && is_dir($this->projectRoot) ? $this->projectRoot : '/';
        $diskTotal = @disk_total_space($diskPath);
        $diskFree = @disk_free_space($diskPath);

        return CollectionResult::ok($this->key(), [
            'cpu_count' => $this->cpuCount($family),
            'memory_total' => $this->memoryTotal($family),
            'memory_available' => $this->memoryAvailable($family),
            'disk_total' => $diskTotal === false ? null : (int) $diskTotal,
            'disk_free' => $diskFree === false ? null : (int) $diskFree,
            'disk_path' => $diskPath,
        ]);
    }

    private function cpuCount(string $family): ?int
    {
        if ($family === 'Linux' && is_readable($this->cpuInfoPath)) {
            $count = preg_match_all('/^processor\s*:/m', (string) file_get_contents($this->cpuInfoPath));

            if (is_int($count) && $count > 0) {
                return $count;
            }
        }

        if ($family === 'Darwin') {
            $count = $this->intFromCommand(['sysctl', '-n', 'hw.logicalcpu']);

            if ($count !== null) {
                return $count;
            }
        }

        if ($family === 'Windows') {
            $count = (int) getenv('NUMBER_OF_PROCESSORS');

            if ($count > 0) {
                return $count;
            }
        }

        $count = $this->intFromCommand(['nproc']);

        return $count !== null && $count > 0 ? $count : null;
    }

    private function memoryTotal(string $family): ?int
    {
        if ($family === 'Linux') {
            return $this->memInfoBytes('MemTotal');
        }

        if ($family === 'Darwin') {
            return $this->intFromCommand(['sysctl', '-n', 'hw.memsize']);
        }

        return null;
    }

    private function memoryAvailable(string $family): ?int
    {
        if ($family === 'Linux') {
            return $this->memInfoBytes('MemAvailable') ?? $this->memInfoBytes('MemFree');
        }

        if ($family === 'Darwin') {
            return $this->darwinAvailableMemory();
        }

        return null;
    }

    /**
     * Reads a kB value such as "MemTotal: 16314840 kB" and returns bytes.
     */
    private function memInfoBytes(string $field): ?int
    {
        if (! is_readable($this->memInfoPath)) {
            return null;
        }

        $contents = (string) file_get_contents($this->memInfoPath);

        if (preg_match('/^'.preg_quote($field, '/').':\s+(\d+)\s*kB/mi', $contents, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * 1024;
    }

    /**
     * Approximates available memory on macOS from vm_stat (free + inactive pages).
     */
    private function darwinAvailableMemory(): ?int
    {
        if (! $this->processRunner->available()) {
            return null;
        }

        $result = $this->processRunner->run(['vm_stat'], 5);

        if (! $result->successful) {
            return null;
        }

        $output = $result->output;

        if (preg_match('/page size of (\d+) bytes/', $output, $pageSize) !== 1) {
            return null;
        }

        $pages = 0;

        foreach (['Pages free', 'Pages inactive'] as $field) {
            if (preg_match('/^'.preg_quote($field, '/').':\s+(\d+)\./m', $output, $matches) === 1) {
                $pages += (int) $matches[1];
            }
        }

        return $pages > 0 ? $pages * (int) $pageSize[1] : null;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function intFromCommand(array $command): ?int
    {
        if (! $this->processRunner->available()) {
            return null;
        }

        $result = $this->processRunner->run($command, 5);
        $output = trim($result->output);

        if (! $result->successful || preg_match('/^\d+$/', $output) !== 1) {
            return null;
        }

        return (int) $output;
    }
}
