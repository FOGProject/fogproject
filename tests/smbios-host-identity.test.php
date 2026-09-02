<?php
/**
 * The firmware identity decision is deterministic and never guesses (#198).
 *
 * A booting machine reports its SMBIOS UUID, system serial, board serial and
 * chassis asset tag. SmbiosIdentity::pick() turns those plus the
 * inventory rows that share any of them into ONE host id or null, and
 * getHostItem() falls back to the MAC on null. The first attempt at this
 * (2018) had no such decision: it took the UUID's first hit, met MSI boards
 * that all report FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF, re-identified every
 * one of them as the same host, and was reverted wholesale.
 *
 * What this pins, each rule with the failure it prevents:
 *
 *   1. A placeholder is not an identity -- empty, a known firmware default,
 *      or one character repeated. That is the MSI case and the '0' board
 *      serial VirtualBox ships.
 *   2. Canonicalization is applied to BOTH sides before comparing, and case
 *      is ignored, so iPXE's rendering and dmidecode's are the same value.
 *   3. Fields score independently and per field: a system serial cannot
 *      satisfy a board-serial match, and a vendor that writes one string
 *      into both serials scores once.
 *   4. The winner must hold the top score alone. A tie is "no opinion".
 *   5. The asset tag breaks a tie but cannot win by itself.
 *
 * DB-free: SmbiosIdentity is pure by design so this file can drive it
 * with arrays. HostManager::resolveHostBySmbios() only adds the query and
 * the pending check around it.
 *
 * Usage: php tests/smbios-host-identity.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('smbios-identity');

use FOG\Base\SmbiosIdentity;

$t = new FogChecks();

// 1. Placeholders.
foreach ([
    '',
    ' ',
    '0',
    '000000000',
    'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF',
    'ffffffff-ffff-ffff-ffff-ffffffffffff',
    '00000000-0000-0000-0000-000000000000',
    '/0000000/',
    'Not Specified',
    'not specified',
    'To be filled by O.E.M.',
    'To Be Set By OEM',
    'Enter Serial',
    'None',
    'N/A',
    'Default string',
    'No Asset Tag',
] as $bad) {
    $t->check(
        "placeholder rejected: '$bad'",
        !SmbiosIdentity::isUsable($bad)
    );
}
foreach ([
    '4c4c4544-0031-4a10-8052-b2c04f4a3733',
    'ABC1234',
    '/ABC1234/CNWS2008B4004F/',
    'VirtualBox-95274a9a-3a76-4573-8db1-255950c35eb0',
    '70638948',
    'f0900000-0000-1000-8000-1a2b3c4d5e6f',
] as $good) {
    $t->check(
        "real value accepted: '$good'",
        SmbiosIdentity::isUsable($good)
    );
}

// 2. Canonicalization and case.
$filter = SmbiosIdentity::usable([
    'sysuuid' => "  4C4C4544-0031-4A10-8052-B2C04F4A3733 ",
    'sysserial' => "ABC  1234",
    'mbserial' => '0',
    'caseasset' => 'Not Specified',
]);
$t->check(
    'usable filter keeps only real values, canonicalized',
    $filter === [
        'sysuuid' => '4C4C4544-0031-4A10-8052-B2C04F4A3733',
        'sysserial' => 'ABC 1234',
    ]
);

$rows = [
    ['hostID' => 1, 'sysuuid' => '4c4c4544-0031-4a10-8052-b2c04f4a3733',
        'sysserial' => 'abc 1234', 'mbserial' => '/ABC1234/X/', 'caseasset' => ''],
    ['hostID' => 2, 'sysuuid' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
        'sysserial' => 'ZZZ9999', 'mbserial' => '', 'caseasset' => ''],
];
$t->check(
    'case and whitespace differences still match (host 1)',
    SmbiosIdentity::pick($filter, $rows) === 1
);

// 3. Per-field scoring.
$want = ['sysserial' => 'SAME', 'mbserial' => 'OTHER'];
$crossed = [
    // The wanted system serial appears in this row's BOARD serial column.
    ['hostID' => 5, 'sysuuid' => '', 'sysserial' => 'nope',
        'mbserial' => 'SAME', 'caseasset' => ''],
];
$t->check(
    'a value in the wrong field does not score',
    SmbiosIdentity::pick($want, $crossed) === null
);
$doubled = [
    ['hostID' => 6, 'sysuuid' => '', 'sysserial' => 'SAME',
        'mbserial' => 'SAME', 'caseasset' => ''],
    ['hostID' => 7, 'sysuuid' => '', 'sysserial' => 'SAME',
        'mbserial' => 'DIFF', 'caseasset' => ''],
];
$t->check(
    'one string in two columns scores once, so two one-field hits tie',
    SmbiosIdentity::pick(['sysserial' => 'SAME'], $doubled) === null
);

// 4. Unique winner.
$tie = [
    ['hostID' => 10, 'sysuuid' => 'aaaa1111-0000-0000-0000-000000000001',
        'sysserial' => '', 'mbserial' => '', 'caseasset' => ''],
    ['hostID' => 11, 'sysuuid' => 'aaaa1111-0000-0000-0000-000000000001',
        'sysserial' => '', 'mbserial' => '', 'caseasset' => ''],
];
$uuidOnly = ['sysuuid' => 'aaaa1111-0000-0000-0000-000000000001'];
$t->check(
    'two hosts sharing the only matching field is no answer',
    SmbiosIdentity::pick($uuidOnly, $tie) === null
);
$broken = $tie;
$broken[1]['sysserial'] = 'SER-11';
$t->check(
    'a second field breaks the tie',
    SmbiosIdentity::pick(
        $uuidOnly + ['sysserial' => 'SER-11'],
        $broken
    ) === 11
);
$t->check(
    'a single matching row wins on one field',
    SmbiosIdentity::pick($uuidOnly, [$tie[0]]) === 10
);
$t->check(
    'no rows is no answer',
    SmbiosIdentity::pick($uuidOnly, []) === null
);

// 5. Asset tag.
$assetOnly = [
    ['hostID' => 20, 'sysuuid' => '', 'sysserial' => '', 'mbserial' => '',
        'caseasset' => 'TAG-1'],
];
$t->check(
    'the asset tag cannot identify a host by itself',
    SmbiosIdentity::pick(['caseasset' => 'TAG-1'], $assetOnly) === null
);
$tagged = $tie;
$tagged[0]['caseasset'] = 'TAG-A';
$tagged[1]['caseasset'] = 'TAG-B';
$t->check(
    'the asset tag breaks a tie between hosts sharing a firmware field',
    SmbiosIdentity::pick(
        $uuidOnly + ['caseasset' => 'TAG-B'],
        $tagged
    ) === 11
);

$t->finish();
