<?php
/**
 * FOGBase::resolveHostname() -- the negative cache PingHosts runs on.
 *
 * A PingHosts cycle on a fleet that is not in DNS took 5m26s on an 88-host
 * database, of which the ping batch was 2s and the rest was resolution.
 * Measured on the reference server, the cost is wildly asymmetric and nothing
 * in the stack caches it:
 *
 *   a name that resolves  :    0.2 ms local, 23.8 ms remote
 *   a name that does not  : 3776.3 ms, every single time
 *
 * So failures are cached and successes are not. This pins that split, which
 * is the part with the correctness argument in it -- a stale failure is the
 * same answer the caller would have reached anyway, a stale success is a host
 * pinged at an address it has moved off.
 *
 * Asserted on the resolver CALL COUNT rather than on elapsed time. A timing
 * assertion here would be measuring the local resolver's mood and would go
 * flaky and then get deleted; the count is exactly what the cache is for.
 *
 * Names come from RFC 2606's reserved .invalid TLD, which is guaranteed never
 * to resolve, so the miss path is real without depending on this machine's
 * DNS being in any particular state.
 *
 * Usage: php tests/resolve-hostname-negative-cache.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
require_once $repo . '/packages/web/vendor/autoload.php';

use FOG\Base\FOGBase;

// [passed, what it means], classified in one pass at the end. Collected as
// plain data rather than through a helper that writes to globals: a static
// analyzer cannot see a `global` write from inside a function, and then calls
// the reporting below dead code.
$results = [];

/**
 * Resolve, and report how many times the system resolver was actually hit.
 *
 * @param string $host the name to resolve
 * @param int    $ttl  seconds to cache a failure for, 0 to not
 *
 * @return array [returned value, resolver calls spent]
 */
function resolveCounting($host, $ttl)
{
    $before = FOGBase::resolverCalls();
    $got = FOGBase::resolveHostname($host, $ttl);
    return [$got, FOGBase::resolverCalls() - $before];
}

// A literal IP never reaches the resolver at all, cache or no cache. This is
// the reason checkIfNodeMaster() costs 0.003 ms a pass on a server whose
// storage nodes are configured by address, and so needs none of this.
[$got, $calls] = resolveCounting('10.255.20.1', 3600);
$results[] = ['10.255.20.1' === $got, 'a literal IP is returned unchanged'];
$results[] = [0 === $calls, 'a literal IP costs no resolver call'];

// A failure, cached. gethostbyname() signals failure by handing the name
// back, so that IS the expected return value -- both times.
$dead = 'fog-negative-cache-' . getmypid() . '.invalid';
[$got, $calls] = resolveCounting($dead, 3600);
$results[] = [$dead === $got, 'an unresolvable name comes back unchanged'];
$results[] = [1 === $calls, 'the first lookup of a dead name reaches the resolver'];

[$got, $calls] = resolveCounting($dead, 3600);
$results[] = [$dead === $got, 'a cached failure returns the same value as a fresh one'];
$results[] = [0 === $calls, 'the second lookup of a dead name is served from cache'];

// ... but only for callers that asked for it. The default is 0, which is what
// keeps every pre-existing call site behaving exactly as it did.
[, $calls] = resolveCounting($dead, 0);
$results[] = [1 === $calls, 'ttl 0 ignores the cache and resolves anyway'];

// The DEFAULT, called the way every pre-existing site calls it -- one
// argument. Passing 0 explicitly above tests the path; it does not test that
// the path is what an unchanged caller gets, and those are two different
// claims. checkIfNodeMaster(), MulticastTask and the web all call it this way.
$before = FOGBase::resolverCalls();
FOGBase::resolveHostname($dead);
FOGBase::resolveHostname($dead);
$results[] = [
    2 === FOGBase::resolverCalls() - $before,
    'the default ttl is 0, so an unchanged caller never caches'
];

// A success is NOT cached: it must be re-read every time, because it is the
// answer that goes stale in a way that matters.
[$got, $calls] = resolveCounting('localhost', 3600);
$results[] = [1 === $calls, 'the first lookup of a live name reaches the resolver'];
$firstGot = $got;
[$got, $calls] = resolveCounting('localhost', 3600);
$results[] = [1 === $calls, 'a success is re-resolved rather than cached'];
$results[] = [$firstGot === $got, 'and gives the same answer'];

// An expired entry resolves again. ttl 1 with a second's wait is the one
// place a real clock is unavoidable, and one second is not a flaky wait.
$dead2 = 'fog-negative-expiry-' . getmypid() . '.invalid';
resolveCounting($dead2, 1);
sleep(2);
[, $calls] = resolveCounting($dead2, 1);
$results[] = [1 === $calls, 'an expired entry is looked up again'];

// The jitter keeps every cached failure inside the documented ceiling while
// spreading the re-checks, so no single cycle inherits all of them at once.
$seen = [];
for ($i = 0; $i < 200; $i++) {
    $seen[] = FOGBase::negativeTtlJitter(3600);
}
$results[] = [min($seen) >= 1800, 'jitter never drops below half the ttl'];
$results[] = [max($seen) <= 3600, 'jitter never exceeds the ttl'];
$results[] = [count(array_unique($seen)) > 1, 'jitter actually spreads'];
$results[] = [0 === FOGBase::negativeTtlJitter(0), 'a ttl of 0 jitters to 0'];
$results[] = [1 === FOGBase::negativeTtlJitter(1), 'a ttl of 1 stays 1'];

$failed = 0;
foreach ($results as [$passed, $why]) {
    echo $passed ? "  ok    $why\n" : "  FAIL  $why\n";
    $failed += $passed ? 0 : 1;
}
echo "\n";
if ($failed > 0) {
    echo 'FAIL (' . $failed . ' of ' . count($results) . " assertions)\n";
    exit(1);
}
echo 'PASS (' . count($results) . " assertions)\n";
