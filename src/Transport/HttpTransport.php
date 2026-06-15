<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Transport;

use Mindtwo\Monitoring\Contracts\RequestSigner;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\TransportResult;
use Mindtwo\Monitoring\Support\InstalledVersion;
use Throwable;

/**
 * Dependency-free HTTP POST transport: curl when the extension is loaded, PHP
 * streams otherwise. The payload is signed by the configured RequestSigner;
 * TLS verification is on by default and redirects are never followed.
 */
final class HttpTransport implements Transport
{
    public const DEFAULT_ENDPOINT = 'https://monitoring.mindtwo.com/api/monitoring';

    public const DRIVER_CURL = 'curl';

    public const DRIVER_STREAM = 'stream';

    private RequestSigner $signer;

    private string $driver;

    /**
     * @param  string|null  $driver  "curl", "stream" or null to pick automatically
     */
    public function __construct(
        private string $endpoint,
        private Credentials $credentials,
        ?RequestSigner $signer = null,
        private int $timeoutSeconds = 10,
        private ?string $userAgent = null,
        private bool $verifyTls = true,
        ?string $driver = null
    ) {
        $this->signer = $signer ?? new HmacRequestSigner;
        $this->driver = $driver ?? (extension_loaded('curl') ? self::DRIVER_CURL : self::DRIVER_STREAM);
    }

    public function send(Snapshot $snapshot): TransportResult
    {
        $scheme = strtolower((string) parse_url($this->endpoint, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return TransportResult::failed(sprintf('Invalid monitoring endpoint "%s" — only http(s) URLs are supported.', $this->endpoint));
        }

        if (! $this->credentials->isComplete()) {
            return TransportResult::failed('Monitoring credentials are not configured.');
        }

        try {
            $payload = $snapshot->toJson();
        } catch (Throwable $exception) {
            return TransportResult::failed('Unable to encode the snapshot: '.$exception->getMessage());
        }

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->userAgent ?? 'mindtwo-monitoring/'.InstalledVersion::of('mindtwo/base-monitoring'),
            ],
            $this->signer->headers($payload, $this->credentials)
        );

        try {
            return $this->driver === self::DRIVER_CURL && extension_loaded('curl')
                ? $this->sendWithCurl($payload, $headers)
                : $this->sendWithStreams($payload, $headers);
        } catch (Throwable $exception) {
            return TransportResult::failed($exception->getMessage());
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function sendWithCurl(string $payload, array $headers): TransportResult
    {
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $handle = curl_init($this->endpoint);

        if ($handle === false) {
            return TransportResult::failed('Unable to initialize curl.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSeconds),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        $body = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        curl_close($handle);

        if ($body === false) {
            return TransportResult::failed($error !== '' ? $error : 'The monitoring request failed.');
        }

        return $this->toResult($statusCode, is_string($body) ? $body : '');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function sendWithStreams(string $payload, array $headers): TransportResult
    {
        $headerLines = '';

        foreach ($headers as $name => $value) {
            $headerLines .= $name.': '.$value."\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $payload,
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => [
                'verify_peer' => $this->verifyTls,
                'verify_peer_name' => $this->verifyTls,
            ],
        ]);

        $errorMessage = null;

        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;

            return true;
        });

        try {
            $body = file_get_contents($this->endpoint, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($body === false) {
            return TransportResult::failed($errorMessage ?? 'The monitoring request failed.');
        }

        $statusCode = 0;

        // $http_response_header is populated by the stream wrapper for the last request.
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }

        return $this->toResult($statusCode, $body);
    }

    private function toResult(int $statusCode, string $body): TransportResult
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return TransportResult::delivered($statusCode);
        }

        $summary = trim(mb_substr(strip_tags($body), 0, 300));

        return TransportResult::failed(
            sprintf('The monitoring endpoint responded with HTTP %d%s.', $statusCode, $summary !== '' ? ': '.$summary : ''),
            $statusCode
        );
    }
}
