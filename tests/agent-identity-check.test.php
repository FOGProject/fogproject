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
$t->check('changed pending identity cannot be signed', 'upgrade_blocked' === I::enrollmentGate($saved, ['macs' => ['00:11:22:33:44:66']], true));
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

$t->finish();
