<?php
/**
 * A storage node row's IPv6 address matches this machine however it is typed.
 *
 * The machine's own list (getIPAddress) holds addresses as `ip` prints them:
 * fd00::1. A node row can hold FD00::1, fd00:0:0::1 or [fd00::1] for the
 * same address. checkIfNodeMaster() compares with in_array() and
 * waitInterfaceReady() with array_intersect(), both exact string matches,
 * so a differently typed row never matched and the daemon waited forever.
 * resolveHostname() and loadKnownIps() now return the canonical form. IPv4
 * addresses and names are unchanged.
 *
 * Usage: php tests/node-ip-canonical-ipv6.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGBase;
use FOG\Service\FOGService;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('node-ip-canonical-ipv6');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

foreach (['FD00::1', 'fd00:0:0::1', '[fd00::1]', ' fd00::1 ', 'fd00::0001'] as $typed) {
    $t->check(
        "resolveHostname('$typed') is fd00::1",
        'fd00::1' === FOGBase::resolveHostname($typed)
    );
}
$t->check(
    'an IPv4 address is untouched',
    '192.0.2.5' === FOGBase::resolveHostname('192.0.2.5')
);
$t->check(
    'a name is untouched by canonicalIP()',
    'node-1.example.test' === FOGBase::canonicalIP('node-1.example.test')
);
$t->check(
    'a bracketed non-address is untouched by canonicalIP()',
    '[node]' === FOGBase::canonicalIP('[node]')
);

// loadKnownIps(): the node-address side of the waitInterfaceReady() compare.
$stored = ['FD00::1', '[fd00:0:0::2]', '192.0.2.5'];
$db->responder = function ($sql) use ($stored) {
    if (!preg_match('/^\s*SELECT\b.*?`nfsGroupMembers`/s', $sql)) {
        return null;
    }
    $cols = FogFakePdo::selectedColumns($sql);
    $rows = [];
    foreach ($stored as $ip) {
        $row = [];
        foreach ($cols as $c) {
            $row[$c] = $ip;
        }
        $rows[] = $row;
    }
    return $rows;
};
$known = FOGService::loadKnownIps();
$db->responder = null;
$t->check(
    'loadKnownIps() returns canonical addresses: ' . json_encode($known),
    ['fd00::1', 'fd00::2', '192.0.2.5'] === array_values($known)
);
$t->check(
    'a differently typed node row now intersects the machine address list',
    ['fd00::1'] === array_values(array_intersect($known, ['fd00::1']))
);

$t->finish();
