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
 *   6. Registration acts on the firmware only in enforce mode and only when
 *      the MAC found nothing; a known MAC is never overruled, and log mode
 *      never attaches.
 *   7. A usable serial that differs on both sides means another machine
 *      booted with this host's MAC (a shared USB adapter, forum 18319), so
 *      boot records nothing. The system serial outranks the board serial,
 *      so a board swap that keeps the service tag still updates the UUID.
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

// 6. Registration gating.
foreach ([
    ['enforce', 0, 7, 'attach', 'enforce, MAC unknown, firmware unique: attach'],
    ['log', 0, 7, 'log', 'log mode never attaches'],
    ['off', 0, 7, 'none', 'off says nothing'],
    ['', 0, 7, 'none', 'missing setting is off'],
    ['enforce', 3, 7, 'log', 'a known MAC is never overruled, even in enforce'],
    ['enforce', 7, 7, 'none', 'agreement is silence'],
    ['enforce', 0, 0, 'none', 'no firmware match is silence'],
    ['log', 3, 7, 'log', 'log mode logs a disagreement'],
] as [$mode, $macID, $smbiosID, $want, $label]) {
    $t->check(
        "registration: $label",
        SmbiosIdentity::registrationAction($mode, $macID, $smbiosID) === $want
    );
}

// 7. A differing serial both sides know means another machine booted with
//    this host's MAC (a shared USB adapter, forum 18319). Boot must not
//    record that machine's UUID onto the host.
foreach ([
    [['sysserial' => '7XPOC01'], ['sysserial' => '9ABCD02'], 'sysserial', 'differing system serial'],
    [['mbserial' => '/7XPOC01/CN12/'], ['mbserial' => '/9ABCD02/CN34/'], 'mbserial', 'differing board serial'],
    [['sysserial' => '7XPOC01'], ['sysserial' => ' 7xpoc01 '], null, 'same serial, other case and spacing'],
    [['sysserial' => ''], ['sysserial' => '9ABCD02'], null, 'nothing stored: no contradiction'],
    [['sysserial' => '7XPOC01'], ['sysserial' => ''], null, 'nothing reported: no contradiction'],
    [['sysserial' => 'To Be Set By OEM'], ['sysserial' => '9ABCD02'], null, 'stored placeholder is not a serial'],
    [['sysserial' => '7XPOC01'], ['sysserial' => '000000000'], null, 'reported placeholder is not a serial'],
    [['sysserial' => '7XPOC01', 'mbserial' => '/7XPOC01/CN12/'], ['sysserial' => '7XPOC01', 'mbserial' => '/7XPOC01/CN99/'], null, 'board swap with a kept service tag still updates'],
    [['sysserial' => '7XPOC01', 'mbserial' => '/7XPOC01/CN12/'], ['sysserial' => '9ABCD02', 'mbserial' => '/7XPOC01/CN12/'], 'sysserial', 'system serial outranks a matching board serial'],
    [['sysserial' => 'To Be Set By OEM', 'mbserial' => '/7XPOC01/CN12/'], ['sysserial' => 'To Be Set By OEM', 'mbserial' => '/9ABCD02/CN34/'], 'mbserial', 'board serial decides when no system serial'],
    [['sysuuid' => '01234567-89ab-cdef-0123-456789abcdef'], ['sysuuid' => '11234567-89ab-cdef-0123-456789abcdef'], null, 'a UUID alone never contradicts'],
    [['caseasset' => 'A1'], ['caseasset' => 'B2'], null, 'an asset tag never contradicts'],
] as [$stored, $reported, $want, $label]) {
    $t->check(
        "contradicts: $label",
        SmbiosIdentity::contradicts($stored, $reported) === $want
    );
}

$t->finish();
