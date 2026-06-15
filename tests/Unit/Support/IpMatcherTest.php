<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Support\IpMatcher;

test('an empty allow-list allows everything', function () {
    expect(IpMatcher::allows('203.0.113.10', []))->toBeTrue();
});

test('exact IPv4 addresses match', function () {
    expect(IpMatcher::allows('203.0.113.10', ['203.0.113.10']))->toBeTrue()
        ->and(IpMatcher::allows('203.0.113.11', ['203.0.113.10']))->toBeFalse();
});

test('IPv4 CIDR ranges match', function () {
    $list = ['10.1.0.0/16'];

    expect(IpMatcher::allows('10.1.255.255', $list))->toBeTrue()
        ->and(IpMatcher::allows('10.2.0.1', $list))->toBeFalse()
        ->and(IpMatcher::allows('10.1.7.13', ['10.1.7.0/24']))->toBeTrue()
        ->and(IpMatcher::allows('10.1.8.13', ['10.1.7.0/24']))->toBeFalse();
});

test('non-octet-aligned prefixes match correctly', function () {
    // /22 spans 10.1.4.0 – 10.1.7.255
    $list = ['10.1.4.0/22'];

    expect(IpMatcher::allows('10.1.7.255', $list))->toBeTrue()
        ->and(IpMatcher::allows('10.1.8.0', $list))->toBeFalse();
});

test('IPv6 addresses and ranges match', function () {
    expect(IpMatcher::allows('::1', ['::1']))->toBeTrue()
        ->and(IpMatcher::allows('2001:db8::1', ['2001:db8::/32']))->toBeTrue()
        ->and(IpMatcher::allows('2001:db9::1', ['2001:db8::/32']))->toBeFalse();
});

test('mixed lists match any entry', function () {
    $list = ['127.0.0.1', '10.0.0.0/8', '::1'];

    expect(IpMatcher::allows('10.20.30.40', $list))->toBeTrue()
        ->and(IpMatcher::allows('127.0.0.1', $list))->toBeTrue()
        ->and(IpMatcher::allows('::1', $list))->toBeTrue()
        ->and(IpMatcher::allows('192.168.0.1', $list))->toBeFalse();
});

test('a /0 prefix matches every address of the same family', function () {
    expect(IpMatcher::allows('8.8.8.8', ['0.0.0.0/0']))->toBeTrue()
        ->and(IpMatcher::allows('2001:db8::1', ['::/0']))->toBeTrue();
});

test('IPv4 never matches IPv6 entries and vice versa', function () {
    expect(IpMatcher::allows('127.0.0.1', ['::/0']))->toBeFalse()
        ->and(IpMatcher::allows('::1', ['0.0.0.0/0']))->toBeFalse();
});

test('invalid input never matches', function () {
    expect(IpMatcher::allows('not-an-ip', ['10.0.0.0/8']))->toBeFalse()
        ->and(IpMatcher::allows('10.0.0.1', ['not-an-entry']))->toBeFalse()
        ->and(IpMatcher::allows('10.0.0.1', ['10.0.0.0/99']))->toBeFalse()
        ->and(IpMatcher::allows('10.0.0.1', ['10.0.0.0/abc']))->toBeFalse()
        ->and(IpMatcher::allows('10.0.0.1', ['']))->toBeFalse();
});

test('matches() requires an explicit entry (no empty-list pass)', function () {
    expect(IpMatcher::matches('10.0.0.1', []))->toBeFalse();
});
