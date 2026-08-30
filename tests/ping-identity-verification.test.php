<?php
/**
 * Ping::identityMatches() -- does the machine at this address belong to us?
 *
 * PingHosts now prefers hosts.hostIP, written on every client check-in, over
 * a DNS lookup. That is only safe because of this function.
 *
 * An echo reply says an ADDRESS answered. On a DHCP network that is not the
 * same claim as "this host is up": a lease handed to MachineA on Monday can
 * belong to PrinterX by Friday, so pinging a host's last known address and
 * calling it up reports a machine that has been off all week as online. That
 * is worse than the "not reachable" it replaces, because it is confidently
 * wrong rather than merely unhelpful -- and it is the failure the maintainer
 * raised before this was built.
 *
 * FOG's identity for a host is its MAC, so the MAC at that address is what
 * turns "an address answered" into "this host answered".
 *
 * THE THIRD VALUE IS THE POINT. ARP is link-local, so an off-segment host has
 * no entry and its identity is not knowable from this server at all. That
 * must read as null -- "cannot tell" -- and never as either answer: folded
 * into true it invents the false-up this exists to prevent, folded into false
 * it reports every routed host down. Both foldings are pinned below.
 *
 * Pure and instant: no network, no database, no daemon. The ARP table is
 * passed in, so this reads a fixture rather than whatever this machine's
 * kernel happens to hold.
 *
 * Usage: php tests/ping-identity-verification.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
require_once $repo . '/packages/web/vendor/autoload.php';

use FOG\Net\Ping;

// [passed, what it means], classified at the end. Plain data rather than a
// helper writing to globals: a static analyzer cannot see a `global` write
// from inside a function and calls the reporting below dead code.
$results = [];

$arp = [
    '10.255.20.50' => '08:00:27:e5:df:5e',
    '10.255.20.51' => 'AA-BB-CC-DD-EE-FF',
    '10.255.20.52' => '',
];

$mine = ['08:00:27:e5:df:5e'];

// The address answered and the MAC is ours. The only case that may be up.
$results[] = [
    true === Ping::identityMatches('10.255.20.50', $mine, $arp),
    'a matching MAC verifies the host',
];

// The address answered and something else holds it -- the PrinterX case.
$results[] = [
    false === Ping::identityMatches('10.255.20.51', $mine, $arp),
    'a different MAC at the address is a mismatch, not a match',
];

// Off-segment: no entry at all. Must be "cannot tell", not either answer.
$results[] = [
    null === Ping::identityMatches('10.255.20.99', $mine, $arp),
    'an address with no ARP entry is unverifiable, not down',
];
$results[] = [
    false !== Ping::identityMatches('10.255.20.99', $mine, $arp),
    'and unverifiable is NOT reported as a mismatch',
];
$results[] = [
    true !== Ping::identityMatches('10.255.20.99', $mine, $arp),
    'and unverifiable is NOT reported as verified',
];

// An entry with no usable lladdr is the same "cannot tell" -- the kernel
// asked and got nothing back, which is not evidence about identity.
$results[] = [
    null === Ping::identityMatches('10.255.20.52', $mine, $arp),
    'an empty lladdr is unverifiable rather than a mismatch',
];

// A host with no MACs registered cannot be verified against anything. It must
// not pass: "we know nothing about this host" is not "this is our host".
$results[] = [
    false === Ping::identityMatches('10.255.20.50', [], $arp),
    'a host with no registered MACs never verifies',
];

// Separator and case must not decide identity. hostMAC rows, `ip neigh` and
// anything an admin typed disagree on both, and a false mismatch here CLEARS
// a perfectly good stored address.
foreach (['08-00-27-E5-DF-5E', '08:00:27:E5:DF:5E', '080027e5df5e'] as $form) {
    $results[] = [
        true === Ping::identityMatches('10.255.20.50', [$form], $arp),
        "MAC form '$form' compares equal",
    ];
}

// Any one of a host's MACs is enough -- a machine with wired and wireless
// answers on whichever it is using.
$results[] = [
    true === Ping::identityMatches(
        '10.255.20.50',
        ['de:ad:be:ef:00:01', '08:00:27:e5:df:5e'],
        $arp
    ),
    'any one of the host MACs is enough to verify',
];

// Junk must not verify by accident -- normalization strips separators, so a
// short or malformed value must be rejected rather than compared loosely.
foreach (['', 'not-a-mac', '08:00:27'] as $junk) {
    $results[] = [
        true !== Ping::identityMatches('10.255.20.50', [$junk], $arp),
        "malformed MAC '$junk' does not verify",
    ];
}

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
