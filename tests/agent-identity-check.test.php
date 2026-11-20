<?php
/**
 * The agent identity check and the enrollment feature negotiation.
 *
 * Covers FOG\Agent\MachineIdentity (the resolver rules, the enrollment gate,
 * the identity/check answer) and the way FOG\Agent\Enrollment::handle uses it.
 * Two behaviors here are security properties, and each was made to fail
 * before it was kept:
 *
 *   - POST /agent/v1/enroll is public. A refused request must NOT write the
 *     stored row, or anyone who knows a key's fingerprint could block that
 *     key's agent for good.
 *   - identity/check must not name a host unless the identity matched the
 *     caller's own host. On a mismatch the resolved id would tell an
 *     enrolled agent which host owns the values it sent.
 *
 * No database: the fake PDODB answers from a responder and records every
 * statement, so "the row was not written" is read off the statement log.
 *
 * Usage: php tests/agent-identity-check.test.php
 * Exit status 0 = pass, 1 = fail.
 */
require __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('agent-identity-check');
$fake = FogTestHarness::fakeDb();
$t = new FogChecks();

use FOG\Agent\Enrollment;
use FOG\Agent\MachineIdentity as I;
use FOG\Items\AgentEnrollment;
use FOG\Items\Host;
use FOG\Items\Inventory;

/**
 * A full stored row for a model: every column present, so loading it is
 * quiet, with the given columns set over the blanks.
 */
function identityRow($model, array $set)
{
    $fields = new \ReflectionProperty($model, 'databaseFields');
    $fields->setAccessible(true);
    $row = [];
    foreach ($fields->getValue(new $model()) as $column) {
        $row[$column] = '';
    }
    return $set + $row;
}

/** How many statements in the log write to the enrollment table. */
function identityEnrollmentWrites(array $log)
{
    $count = 0;
    foreach ($log as $sql) {
        if (preg_match('/^\s*(UPDATE|INSERT|REPLACE|DELETE)\b.*`agentEnrollment`/is', (string)$sql)) {
            $count++;
        }
    }
    return $count;
}

function identityRefuses(callable $fn)
{
    try {
        $fn();
        return null;
    } catch (\RuntimeException $e) {
        return [$e->getMessage(), $e->getCode()];
    }
}

// 1. The resolver never votes between hosts.
$t->check('unique MAC resolves a host', ['status' => 'resolved', 'host_id' => 2] === I::choose(null, false, [2]));
$t->check('same host across NICs resolves', ['status' => 'resolved', 'host_id' => 2] === I::choose(2, false, [2, 2]));
$t->check('SMBIOS never overrides another MAC host', 'conflict' === I::choose(1, false, [2])['status']);
$t->check('no majority vote', 'conflict' === I::choose(1, false, [1, 2])['status']);
$t->check('ambiguous firmware is not no firmware', 'conflict' === I::choose(null, true, [2])['status']);
$t->check('NIC replacement with unique firmware', 2 === I::choose(2, false, [])['host_id']);
$t->check('empty identifiers unresolved', 'unresolved' === I::choose(null, false, [])['status']);
$t->check('Route getList stdClass candidate', 7 === I::candidateHostID((object)['hostID' => 7]));
// A MAC alone cannot vouch for a machine whose serial contradicts the
// MAC host's (a shared USB adapter on another laptop, forum 18319).
$t->check('a MAC whose host the serial contradicts is a conflict', 'conflict' === I::choose(null, false, [2], true)['status']);
$t->check('a MAC with no contradiction still resolves', 2 === I::choose(null, false, [2], false)['host_id']);
$t->check('firmware that names the host outranks the clash flag', 2 === I::choose(2, false, [2], true)['host_id']);
// Forum 18319, agent enrollment: firmware names host 1, and a shared
// adapter's MAC names host 2, whose stored serial proves it is a different
// machine. The firmware's host wins, as at boot in enforce and register.
$t->check('firmware wins over a MAC host proven to be another machine',
    ['status' => 'resolved', 'host_id' => 1] === I::choose(1, false, [2], false, [2]));
$t->check('firmware wins when its own MAC is also reported',
    ['status' => 'resolved', 'host_id' => 1] === I::choose(1, false, [1, 2], false, [2]));
$t->check('a MAC host that is not proven different still conflicts',
    'conflict' === I::choose(1, false, [2, 3], false, [2])['status']);
$t->check('a proven-different MAC host without firmware is still a conflict',
    'conflict' === I::choose(null, false, [2], true, [2])['status']);
$t->check('ambiguous firmware ignores the shared list',
    'conflict' === I::choose(null, true, [2], false, [2])['status']);
$t->check('array candidate', 7 === I::candidateHostID(['hostID' => 7]));
$t->check('unknown candidate is not a host', 0 === I::candidateHostID((object)[]));

// 2. Feature negotiation and the enrollment gate.
$t->check('directory identity feature is recognized', I::requested(['features' => ['directory-join-target-v1']]));
$t->check('another feature name is not recognized', !I::requested(['features' => ['directory-join-mj1']]));
$t->check('a missing feature list is not a request', !I::requested([]));
$hardware = ['macs' => ['00:11:22:33:44:55']];
$saved = $hardware + ['_directoryIdentity' => ['required' => true]];
$t->check('legacy unmanaged enrollment unchanged', 'allowed' === I::enrollmentGate($hardware, $hardware, false));
$t->check('legacy pending cannot silently acquire feature', 'upgrade_blocked' === I::enrollmentGate($hardware, $hardware, true));
$t->check('required enrollment cannot downgrade', 'feature_required' === I::enrollmentGate($saved, $hardware, false));
$t->check('same required request', 'allowed' === I::enrollmentGate($saved, $hardware, true));
// The SMBIOS tuple binds a pending key to its machine; the MAC list does
// not. A dock, or an agent that stops sending a VPN TAP's MAC, changes it.
$smbios = ['system_uuid' => 'uuid-1', 'system_serial' => 'ser-1', 'board_serial' => 'mb-1', 'chassis_asset' => 'asset-1'];
$savedFull = $smbios + $hardware + ['_directoryIdentity' => ['required' => true]];
$t->check('a pending key may change its MAC list',
    'allowed' === I::enrollmentGate($savedFull, $smbios + ['macs' => ['00:11:22:33:44:66']], true));
$t->check('a pending key may drop a MAC',
    'allowed' === I::enrollmentGate($savedFull, $smbios + ['macs' => []], true));
foreach (array_keys($smbios) as $field) {
    $changed = $smbios;
    $changed[$field] = 'other';
    $t->check("changed pending $field cannot be signed",
        'upgrade_blocked' === I::enrollmentGate($savedFull, $changed + $hardware, true));
    $missing = $smbios;
    unset($missing[$field]);
    $t->check("missing pending $field cannot be signed",
        'upgrade_blocked' === I::enrollmentGate($savedFull, $missing + $hardware, true));
}
$unmanaged = (new AgentEnrollment())->set('identity', json_encode($hardware));
$t->check('a request that never asked for the feature has nothing to guard',
    null === identityRefuses(function () use ($unmanaged) { Enrollment::guardDirectoryIdentity($unmanaged); }));

// 3. F1: a refused enroll request leaves the stored row alone.
$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_csr_export(openssl_csr_new(['commonName' => 'agent-test'], $key, ['digest_alg' => 'sha256']), $csr);
$storedIdentity = json_encode($hardware);
$fake->responder = function ($sql, $params) use ($storedIdentity) {
    if (false === strpos($sql, '`agentEnrollment`') || 0 !== stripos(ltrim($sql), 'SELECT')) {
        return null;
    }
    // getIds() reads a list of ids; load() reads one flat row.
    if (false !== strpos($sql, 'SELECT `aeID`')) {
        return [['aeID' => 5]];
    }
    return identityRow(AgentEnrollment::class, [
        'aeID' => 5, 'aeHostID' => 7, 'aeFingerprint' => 'x', 'aeCSR' => 'csr', 'aeIdentity' => $storedIdentity,
        'aeState' => 'pending', 'aeReason' => 'known-host'
    ]);
};
$fake->log = [];
$refusal = identityRefuses(function () use ($csr, $hardware) {
    Enrollment::handle([
        'protocol' => 1, 'csr_pem' => $csr, 'identity' => $hardware,
        'features' => ['directory-join-target-v1']
    ], '203.0.113.9');
});
$t->check('feature request against a row without the marker answers 409',
    ['enrollment_upgrade_requires_new_key', 409] === $refusal);
$t->check('the refused request wrote nothing to the enrollment table', 0 === identityEnrollmentWrites($fake->log));

// A repeat for a feature row whose MAC list changed passes the gate, and the
// stored identity keeps what the first request sent.
$storedFull = json_encode($savedFull);
$newMac = '00:11:22:33:44:66';
$writes = [];
$fake->responder = function ($sql, $params) use ($storedFull, &$writes) {
    if (false === strpos($sql, '`agentEnrollment`')) {
        return null;
    }
    if (0 !== stripos(ltrim($sql), 'SELECT')) {
        $writes[] = json_encode($params);
        return null;
    }
    if (false !== strpos($sql, 'SELECT `aeID`')) {
        return [['aeID' => 5]];
    }
    return identityRow(AgentEnrollment::class, [
        'aeID' => 5, 'aeHostID' => 7, 'aeFingerprint' => 'x', 'aeCSR' => 'csr', 'aeIdentity' => $storedFull,
        'aeState' => 'pending', 'aeReason' => 'known-host'
    ]);
};
// No inventory or MAC rows: the issuance guard then finds the stored
// identity unresolved, which is after the gate and not what is tested here.
$rowCount = $fake->pdo->rowCount;
$fake->pdo->rowCount = 0;
$refusal = identityRefuses(function () use ($csr, $smbios, $newMac) {
    Enrollment::handle([
        'protocol' => 1, 'csr_pem' => $csr, 'identity' => $smbios + ['macs' => [$newMac]],
        'features' => ['directory-join-target-v1']
    ], '203.0.113.9');
});
$fake->pdo->rowCount = $rowCount;
$t->check('a changed MAC list is not refused as a new key',
    ['enrollment_upgrade_requires_new_key', 409] !== $refusal);
// The repeat saves the row (updated, remoteIP). This check proves $writes
// captures writes, so the next check cannot pass on an empty list.
$t->check('the repeat wrote the row', [] !== $writes);
$t->check('the repeat never stores the request MAC list',
    [] === array_filter($writes, function ($w) use ($newMac) { return false !== strpos($w, $newMac); }));

// 4. F2: identity/check names a host only on a match.
// Route::getList() reads through the fake PDO handle: one MAC row owned by
// host 9. new Host(9) reads through the fake PDODB: a valid, non-pending row.
$fake->pdo->rowCount = 1;
$fake->pdo->rowFactory = function ($columns, $n) {
    $row = FogFakePdo::defaultRow($columns, $n);
    $row['hmHostID'] = 9;
    $row['hmPending'] = 0;
    return $row;
};
$fake->responder = function ($sql, $params) {
    if (false !== strpos($sql, 'FROM `hosts`') && 0 === stripos(ltrim($sql), 'SELECT')) {
        return identityRow(Host::class, ['hostID' => 9, 'hostName' => 'other', 'hostPending' => 0]);
    }
    return null;
};
$fake->log = [];
$body = ['features' => ['directory-join-target-v1'], 'identity' => ['macs' => ['00:11:22:33:44:55']]];
$principal = (new Host())->set('id', 3);
$answer = I::check($principal, $body);
$t->check('another host\'s identity is a mismatch', 'mismatch' === ($answer['identity']['status'] ?? null));
$t->check('a mismatch is blocked', 'identity_blocked' === $answer['status']);
$t->check('a mismatch does not name the other host', 0 === ($answer['identity']['host_id'] ?? null));
$t->check('the caller still sees its own host id', 3 === ($answer['identity']['principal_host_id'] ?? null));
$principal = (new Host())->set('id', 9);
$answer = I::check($principal, $body);
$t->check('the caller\'s own identity matches', 'matched' === ($answer['identity']['status'] ?? null));
$t->check('a match names the host', 9 === ($answer['identity']['host_id'] ?? null));
$t->check('a request without the feature is refused with 400',
    ['identity_request_invalid', 400] === identityRefuses(function () use ($principal) {
        I::check($principal, ['identity' => []]);
    }));

// 5. serialClash() reads the MAC host's stored serials (forum 18319).
// Host 9 is loaded with its inventory joined in, system serial STORED-9.
$fake->responder = function ($sql, $params) {
    if (false !== strpos($sql, 'FROM `hosts`') && 0 === stripos(ltrim($sql), 'SELECT')) {
        return identityRow(Host::class, ['hostID' => 9, 'hostName' => 'other', 'hostPending' => 0])
            + identityRow(Inventory::class, ['iID' => 4, 'iHostID' => 9, 'iSysserial' => 'STORED-9']);
    }
    return null;
};
$t->check('a different system serial clashes with the MAC host',
    'sysserial' === I::serialClash(9, ['sysserial' => 'OTHER-LAPTOP']));
$t->check('the host\'s own serial does not clash',
    null === I::serialClash(9, ['sysserial' => 'stored-9']));
$t->check('a placeholder serial does not clash',
    null === I::serialClash(9, ['sysserial' => 'To Be Set By OEM']));
$t->check('no reported identity does not clash', null === I::serialClash(9, []));
// The full resolver: host 9's MAC, another machine's serial, no firmware
// match. The inventory OR-lookup answers with NO row: a returned row that
// scored nothing would read as "ambiguous" and refuse for that reason
// instead, so this check could pass without the serial clash.
$fake->pdo->rowFactory = function ($columns, $n) {
    if (in_array('iHostID', $columns, true)) {
        return null;
    }
    $row = FogFakePdo::defaultRow($columns, $n);
    $row['hmHostID'] = 9;
    $row['hmPending'] = 0;
    return $row;
};
$fake->log = [];
$t->check('resolve() refuses a MAC the serial contradicts',
    'conflict' === I::resolve(['macs' => ['00:11:22:33:44:55'], 'system_serial' => 'OTHER-LAPTOP'])['status']);
$t->check('resolve() still resolves the host\'s own machine by MAC',
    ['status' => 'resolved', 'host_id' => 9] === I::resolve(['macs' => ['00:11:22:33:44:55'], 'system_serial' => 'STORED-9']));

// 6. The legacy enrollment resolver, the path forum 18319 hit: a MAC-only
// match the serial contradicts is a conflict, which the deploy window never
// approves. The host's own machine is still a first binding.
// getHostByMacAddresses() reads the MAC owner through getIds(), which asks
// the fake PDODB for a list of id rows; the host load is section 5's.
$hostResponder = $fake->responder;
$fake->responder = function ($sql, $params) use ($hostResponder) {
    if (false !== strpos($sql, 'SELECT `hmHostID` FROM `hostMAC`')) {
        return [['hmHostID' => 9]];
    }
    return $hostResponder($sql, $params);
};
$legacy = new \ReflectionMethod(Enrollment::class, '_resolve');
$legacy->setAccessible(true);
$t->check('enrollment: a contradicted MAC match is a conflict',
    [9, Enrollment::REASON_CONFLICT] === $legacy->invoke(null, ['system_serial' => 'OTHER-LAPTOP'], ['00:11:22:33:44:55'], 'windows'));
$t->check('enrollment: the host\'s own machine is a first binding',
    [9, Enrollment::REASON_KNOWN] === $legacy->invoke(null, ['system_serial' => 'STORED-9'], ['00:11:22:33:44:55'], 'windows'));

$t->finish();
