# mindtwo/base-monitoring

[![Tests](https://github.com/mindtwo/base-monitoring/actions/workflows/tests.yml/badge.svg)](https://github.com/mindtwo/base-monitoring/actions/workflows/tests.yml)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](phpstan.neon.dist)
[![PHP 8.0+](https://img.shields.io/badge/php-%5E8.0-blue)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-lightgrey)](LICENSE.md)

The framework-agnostic **collecting core** of the mindtwo monitoring suite. It safely gathers
infrastructure, package and security-audit data from any PHP environment and ships it as a
signed, versioned JSON snapshot — either pushed to the central monitoring endpoint or served
through a plugin's pull endpoint.

Framework plugins build on this core:

| Package | Adds |
| --- | --- |
| [`mindtwo/laravel-monitoring`](https://github.com/mindtwo/laravel-monitoring) | Laravel collectors, scheduler push, signed pull endpoint |
| [`mindtwo/wordpress-monitoring`](https://github.com/mindtwo/wordpress-monitoring) | WordPress core/plugin/theme collectors, WP-Cron push |
| [`mindtwo/craftcms-monitoring`](https://github.com/mindtwo/craftcms-monitoring) | Craft CMS collectors, console/queue push |
| [`mindtwo/server-monitoring`](https://github.com/mindtwo/server-monitoring) | Standalone server-level monitoring (Docker, load) |

## What it collects

| Metric key | Source |
| --- | --- |
| `os` | `/etc/os-release` (Linux), `sw_vers` (macOS), `php_uname()` fallback |
| `php` | `PHP_VERSION`, SAPI, memory limit — always available |
| `database` | best-effort CLI client detection (`mysql`/`mariadb`/`psql`/`sqlite3`) — plugins override with the live connection |
| `nginx`, `apache`, `caddy`, `redis` | version output of the respective binary, when installed |
| `node` | `node --version` plus the npm version |
| `system` | CPU count, total/available memory, total/free disk |
| `composer_packages` | parsed offline from `composer.lock`, with dev/direct flags |
| `npm_packages` | parsed offline from `package-lock.json` (v1–v3), `npm-shrinkwrap.json`, `yarn.lock` (classic & berry) or `pnpm-lock.yaml` (v6 & v9) |
| `composer_audit` | `composer audit --format=json` — advisories and abandoned packages |
| `composer_licenses` | `composer licenses --format=json` — license summary and per-package list |
| `npm_audit` | `npm audit --json` — severity counts plus per-advisory detail |
| `git` | branch, commit, dirty state, changed files (capped) |

Detected software is normalized to a **technology slug** pinned from
[endoflife.date](https://endoflife.date), so the central dashboard can match versions against
end-of-life data. Unknown software degrades to a slug derived from the package or `org/repo`
name — resolution is always offline.

## Safety guarantees

The core is built to run on *any* environment without ever breaking the host application:

- **Per-collector fault isolation** — every collector runs inside its own guard. A throwing
  collector becomes a `failed` metric, a missing binary an `unsupported` metric. One bad
  collector can never abort the snapshot.
- **No shell interpolation** — commands run as argv arrays through a `ProcessRunner`
  (symfony/process when installed, a dependency-free `proc_open` runner otherwise). Every run
  is timeout-bounded and degrades gracefully where process functions are disabled.
- **Binary discovery without shelling out** — the `ExecutableFinder` scans `PATH` plus common
  sbin directories in pure PHP.
- **Zero runtime dependencies** — PHP >= 8.0 and ext-json are all it needs.

## Installation

```bash
composer require mindtwo/base-monitoring
```

## Quick start

```php
use Mindtwo\Monitoring\Monitor;

// A monitor with the full default collector catalog:
$monitor = Monitor::make(projectRoot: '/var/www/my-project');

$snapshot = $monitor->snapshot();

$snapshot->toArray(); // structured payload
$snapshot->toJson();  // wire format
```

### Pushing to the central endpoint

```php
use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Monitor;
use Mindtwo\Monitoring\Transport\HttpTransport;

$transport = new HttpTransport(
    endpoint: 'https://monitoring.mindtwo.com/api/monitoring',
    credentials: new Credentials('prj_live_8f3a…', $secret),
);

$result = Monitor::make()->push($transport);

$result->success;    // bool — transports never throw
$result->statusCode; // ?int
$result->error;      // ?string
```

### Custom collectors

A collector is one unit of data collection. Implement the contract (or extend
`AbstractCollector`), return a `CollectionResult`, register it:

```php
use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Data\CollectionResult;

final class DockerCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'docker';
    }

    public function collect(): CollectionResult
    {
        return CollectionResult::ok($this->key(), ['version' => '26.1.0']);
    }
}

$monitor->register(new DockerCollector());          // throws on duplicate keys
$monitor->replace(new MyBetterDatabaseCollector()); // intentional override
$monitor->addCustomData('deployment', fn () => ['region' => 'eu-central-1']);
```

## The snapshot payload

`metrics` is an open map — each collector owns the shape under its key, so adding a collector
adds a key and never requires a schema migration. Empty `custom_data` serializes as `{}`.

```json
{
  "schema_version": "1.0",
  "collected_at": "2026-06-09T12:00:00+00:00",
  "environment": "production",
  "project_key": "prj_live_8f3a…",
  "source": { "type": "laravel", "package": "mindtwo/laravel-monitoring", "version": "1.2.0", "base_version": "1.0.3" },
  "metrics": {
    "os":  { "status": "ok", "technology": "ubuntu", "version": "22.04", "family": "Linux", "name": "Ubuntu 22.04.4 LTS", "kernel": "5.15.0-101-generic" },
    "php": { "status": "ok", "technology": "php", "version": "8.3.2", "sapi": "fpm-fcgi", "memory_limit": "256M" },
    "composer_audit": { "status": "warning", "advisories_count": 1, "advisories": [ { "package": "acme/http", "severity": "high", "cve": "CVE-2026-0001", "title": "…", "affected_versions": "<2.0.1", "link": "…" } ], "abandoned_count": 0, "abandoned": {} },
    "npm_audit": { "status": "warning", "vulnerabilities": { "info": 0, "low": 0, "moderate": 0, "high": 1, "critical": 0, "total": 1 }, "advisories_count": 1, "advisories": [ { "package": "axios", "severity": "high", "cve": null, "title": "Server-Side Request Forgery in axios", "affected_versions": "<1.7.4", "link": "https://github.com/advisories/GHSA-8hc4-vh64-cxmj", "fix_available": "1.7.4" } ] },
    "nginx": { "status": "unsupported" }
  },
  "technologies": [
    { "technology": "ubuntu", "version": "22.04", "source": "known" },
    { "technology": "php", "version": "8.3.2", "source": "known" }
  ],
  "custom_data": {}
}
```

Every metric carries a `status`: `ok`, `warning`, `failed`, `skipped` or `unsupported`.

## Authentication: signed requests (v1)

Authentication uses a **project key + secret** pair. The secret never travels on the wire —
requests carry an HMAC signature instead, with a timestamp baked into the signed string for
replay protection:

```text
signature = hex( hmac_sha256( "{timestamp}.{payload}", secret ) )
```

| Header | Value |
| --- | --- |
| `X-Monitoring-Key` | the project key |
| `X-Monitoring-Timestamp` | current Unix timestamp |
| `X-Monitoring-Signature` | the HMAC signature above |

- `payload` is the **raw request body** (the exact JSON bytes for a push, the empty string for
  a body-less pull request).
- Verifiers reject timestamps outside a tolerance window (default **300 seconds**) and compare
  in constant time (`hash_equals`).

```php
use Mindtwo\Monitoring\Transport\HmacRequestSigner;
use Mindtwo\Monitoring\Transport\HmacSignatureVerifier;

$headers = (new HmacRequestSigner)->headers($payload, $credentials);   // outbound
$valid = (new HmacSignatureVerifier)->verify($payload, $headers, $credentials); // inbound
```

## Architecture

```text
Collectors (base + plugin) ─▶ Monitor (registry) ─▶ SnapshotBuilder ─▶ Snapshot ─▶ Transport ─▶ POST endpoint
                                                     (fault isolation)    (DTO)      (HMAC signed)
```

Every variable behavior is a contract in `Mindtwo\Monitoring\Contracts` with a default
implementation — plugins extend, never modify:

| Contract | Responsibility | Default |
| --- | --- | --- |
| `Collector` | one unit of collection | 15-collector catalog |
| `Transport` | deliver a snapshot | `HttpTransport` (curl/stream) |
| `ConfigurationRepository` | per-framework config chain | provided by plugins |
| `RequestSigner` / `SignatureVerifier` | request authentication | HMAC-SHA256 v1 |
| `TechnologyResolver` | slug normalization | pinned endoflife.date registry |
| `ProcessRunner` | guarded shell access | symfony/process or native |

Plugins additionally reuse `Http\PullRequestHandler` (the complete pull-endpoint guard
chain), `Support\FixedWindowRateLimiter`, `Support\IpMatcher` and
`Support\DatabaseVersion`, so security-relevant behavior is implemented and tested exactly
once. Every metric carries a `duration_ms` so slow collectors are visible centrally.

## Technology slug registry

The slug list is pinned in code (`Technology\Slugs`) for offline, deterministic resolution.
Refresh it from the endoflife.date release data:

```bash
composer refresh-slugs
```

Review the diff, commit, release. Aliases for differing detector output live in
`Technology\Aliases` and are extensible via the resolver constructor.

## Development

```bash
composer install
composer check    # pint --test + phpstan (level 8) + pest
```

PHP 8.0 compatibility is enforced statically (`phpVersion: 80000` in phpstan.neon.dist).

## Security

If you discover a security issue, please email [info@mindtwo.de](mailto:info@mindtwo.de)
instead of opening a public issue.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
