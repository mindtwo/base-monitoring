# Changelog

All notable changes to `mindtwo/base-monitoring` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- `npm_audit` now reports the individual advisories (`advisories` / `advisories_count`)
  alongside the existing severity counts, so the dashboard can show *which* packages are
  affected. Each advisory carries `package`, `severity`, `cve`, `title`, `affected_versions`,
  `link` and `fix_available` — the same shape as `composer_audit`. Both the npm v7+ report
  (`vulnerabilities` map with `via`) and the legacy v6 `advisories` map are supported.
- The snapshot `source` now reports the producing host's IP as `server_ip` (detected
  identically for push and pull, `null` when it cannot be determined), so the central
  dashboard can identify which server a snapshot came from.

### Fixed

- `composer_audit`, `composer_licenses` and `npm_audit` no longer fail with
  `env: php: No such file or directory` / `env: node: No such file or directory`
  when run under a restricted PATH (php-fpm, cron, launchd). The `ExecutableFinder`
  could locate the `composer`/`npm` wrappers via its fallback directories, but the
  spawned process did not inherit a PATH wide enough for the interpreter those
  wrappers re-exec through `#!/usr/bin/env php|node`. Process runs now accept extra
  PATH directories, and the affected collectors pass the directory of their
  interpreter (composer → php, npm → node) so the shebang resolves.

## 1.0.0 - 2026-06-13

Initial release.

### Added

- `Http\PullRequestHandler`: the framework-agnostic pull-endpoint core (rate limit → IP
  allow-list → configuration guard → signature verification → fault-isolated snapshot),
  shared by the WordPress, Craft and server plugins.
- `Support\FixedWindowRateLimiter` over injectable key/value storage (WP transients, Craft
  cache, arrays in tests).
- `Support\DatabaseVersion`: driver + raw server version → technology identifier and clean
  version, MariaDB-behind-mysql aware — shared by all live-connection collectors.
- Per-metric `duration_ms` annotated by the SnapshotBuilder, so slow hosts and collectors are
  visible on the dashboard.
- pnpm-lock.yaml support (v6 and v9 formats) in the npm packages collector.

- Collector contract plus a 15-collector default catalog: `os`, `php`, `database`, `nginx`,
  `apache`, `caddy`, `redis`, `node`, `system`, `composer_packages`, `npm_packages`,
  `composer_audit`, `composer_licenses`, `npm_audit`, `git`.
- `Monitor` registry with `register()` (duplicate-key guarded), `replace()`, `forget()` and
  lazy, fault-isolated `addCustomData()` providers.
- `SnapshotBuilder` with per-collector fault isolation — a failing collector can never abort
  a snapshot.
- Versioned `Snapshot` wire format (`schema_version` 1.0) with a flattened technology list.
- HMAC-SHA256 request signing/verification with replay protection (timestamped signatures,
  configurable tolerance window, constant-time comparison).
- Dependency-free `HttpTransport` (curl with stream fallback, TLS verification, http(s)-only,
  no redirects).
- `ProcessRunner` contract with a native `proc_open` runner (argv-only, timeout-bounded) and
  an optional symfony/process runner.
- Pure-PHP `ExecutableFinder` scanning PATH plus common sbin directories.
- Technology slug resolution against a pinned endoflife.date registry (381 slugs) with alias
  map and package/repository-derived fallbacks; `composer refresh-slugs` regenerator.
- `IpMatcher` supporting plain IPs and CIDR ranges (IPv4/IPv6) for plugin allow-lists.
