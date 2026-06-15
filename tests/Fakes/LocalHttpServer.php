<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Tests\Fakes;

use RuntimeException;

/**
 * Boots PHP's built-in web server with the capture router for transport
 * integration tests. Received requests (headers + raw body) are written to a
 * capture file the test can inspect.
 */
final class LocalHttpServer
{
    /** @var resource|null */
    private $process;

    private int $port = 0;

    private string $captureFile = '';

    public function start(): void
    {
        $this->captureFile = tempnam(sys_get_temp_dir(), 'm2-capture-') ?: sys_get_temp_dir().'/m2-capture';

        $attempts = 0;

        do {
            $this->port = random_int(20000, 29999);
            $started = $this->boot();
            $attempts++;
        } while (! $started && $attempts < 5);

        if (! $started) {
            throw new RuntimeException('Unable to start the local test HTTP server.');
        }
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->captureFile !== '' && file_exists($this->captureFile)) {
            unlink($this->captureFile);
        }
    }

    public function url(string $path = '/'): string
    {
        return sprintf('http://127.0.0.1:%d%s', $this->port, $path);
    }

    /**
     * @return array{headers: array<string, string>, body: string, method: string, uri: string}
     */
    public function lastRequest(): array
    {
        $contents = (string) file_get_contents($this->captureFile);

        if ($contents === '') {
            throw new RuntimeException('The test server captured no request.');
        }

        /** @var array{headers: array<string, string>, body: string, method: string, uri: string} */
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function boot(): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$this->port, __DIR__.'/capture-router.php'],
            $descriptors,
            $pipes,
            null,
            ['M2_CAPTURE_FILE' => $this->captureFile]
        );

        if (! is_resource($process)) {
            return false;
        }

        $this->process = $process;

        // Wait until the server accepts connections (max ~2.5s).
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.1);

            if (is_resource($socket)) {
                fclose($socket);

                return true;
            }

            $status = proc_get_status($process);

            if (! $status['running']) {
                proc_close($process);
                $this->process = null;

                return false;
            }

            usleep(50_000);
        }

        $this->stop();

        return false;
    }
}
