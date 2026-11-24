<?php
/**
 * Removing one agent seat of a host, on a real server.
 *
 * Seat::remove() is the revocation path: the agent authenticates by its
 * fingerprint's seat, so deleting the seat row stops it. This drives the real
 * code against real tables and checks:
 *
 *   - only the removed OS's seat row goes; the other seat still resolves;
 *   - the host mirror moves to the remaining seat when it held the removed
 *     key, and clears when the last seat goes;
 *   - the removed OS's open software rows close, and its fact hash goes;
 *   - the key's enrollment row is pending with the reason seat-removed;
 *   - a repeat enroll of that key with a VALID token stays pending: only an
 *     admin's click binds it again;
 *   - removing a seat that does not exist is a 404;
 *   - one agent.seat.remove audit row per removal.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 * The database is always fog_seatremove_test.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-seat-remove-db.test.php
 * Exit status 0 = pass or skip, 1 = fail.
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP agent seat remove DB: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/directory-db-connect.php';

FogTestHarness::boot('agent-seat-remove-db');
$fake = FogTestHarness::fakeDb();

$db = 'fog_seatremove_test';
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$admin = new \PDO(
    preg_replace('/;?dbname=[^;]*/i', '', (string)getenv('FOG_TEST_DSN')),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$admin->exec("DROP DATABASE IF EXISTS `$db`");
$admin->exec("CREATE DATABASE `$db`");
$pdo = new \PDO(
    directoryTestDsn($db),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
);
$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';

use FOG\Agent\Enrollment;
use FOG\Agent\Principal;
use FOG\Agent\Seat;

$t = new FogChecks();

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table => $spec) {
    if (!empty($spec['create'])) {
        $pdo->exec($spec['create']);
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

directoryTestBindPdodb($pdo);
FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());
$pdo->exec("INSERT INTO globalSettings (settingKey, settingValue) VALUES ('FOG_HOST_IDENTIFY_SMBIOS', 'off')");

$fpWin = str_repeat('a', 64);
$fpLin = str_repeat('b', 64);
$mirror = function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hostAgentFingerprint, hostAgentVersion, hostAgentCheckin FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
    return array_map('strval', $q->fetch(\PDO::FETCH_NUM));
};
$seatRows = function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT haOS, haFingerprint FROM hostAgents WHERE haHostID=? ORDER BY haOS');
    $q->execute([$hostID]);
    return $q->fetchAll(\PDO::FETCH_KEY_PAIR);
};
$enrollment = function ($fp) use ($pdo) {
    $q = $pdo->prepare('SELECT aeState, aeReason FROM agentEnrollment WHERE aeFingerprint=?');
    $q->execute([$fp]);
    return $q->fetch(\PDO::FETCH_NUM);
};
$removeCode = function ($hostID, $os) {
    try {
        Seat::remove($hostID, $os, 'tester');
    } catch (\RuntimeException $e) {
        return $e->getCode();
    }
    return 0;
};

// ---------------------------------------------------------------------------
// A dual-boot host. The Linux seat wrote last, so the mirror holds its key.
// The Linux enrollment row is issued, its certificate already collected.
// ---------------------------------------------------------------------------
$pdo->exec("INSERT INTO hosts (hostID, hostName) VALUES (10, 'dual-boot')");
$pdo->prepare('INSERT INTO hostMAC (hmHostID, hmMAC, hmPrimary, hmPending) VALUES (10, ?, 1, 0)')
    ->execute(['02:46:00:00:00:10']);
Seat::bind(10, 'windows', $fpWin, '2027-02-01 00:00:00', '0.2.0', '2026-11-20 11:00:00');
Seat::bind(10, 'linux', $fpLin, '2027-03-01 00:00:00', '0.2.1', '2026-11-20 12:00:00');
$pdo->prepare(
    'INSERT INTO agentEnrollment (aeHostID, aeFingerprint, aeCSR, aeOS, aeState, aeReason) VALUES (10, ?, ?, ?, ?, ?)'
)->execute([$fpLin, 'x', 'linux', 'issued', 'new-seat']);
$pdo->exec(
    "INSERT INTO hostSoftware (hsHostID, hsOS, hsName, hsSource, hsVersion) VALUES "
    . "(10, 'linux', 'lin-prog', 'dpkg', '1'), (10, 'windows', 'win-prog', 'msi', '1')"
);
$pdo->exec(
    "INSERT INTO hostFactState (hfsHostID, hfsKind, hfsHash) VALUES "
    . "(10, 'software:linux', 'h1'), (10, 'software:windows', 'h2'), (10, 'inventory', 'h3')"
);
$t->check('premise: the mirror holds the Linux key', $fpLin === $mirror(10)[0]);

// A token that is valid for the repeat enroll below.
$token = 'tok-seat-remove-' . bin2hex(random_bytes(8));
$pdo->prepare('INSERT INTO agentEnrollToken (atName, atHash, atUses) VALUES (?, ?, 5)')
    ->execute(['seat-remove', hash('sha256', $token)]);
$tokenUses = function () use ($pdo) {
    return (int)$pdo->query("SELECT atUses FROM agentEnrollToken WHERE atName = 'seat-remove'")->fetchColumn();
};

// ---------------------------------------------------------------------------
// 1. Remove the Linux seat.
// ---------------------------------------------------------------------------
Seat::remove(10, 'linux', 'tester');
$t->check('remove: only the Linux seat row goes', ['windows' => $fpWin] === $seatRows(10));
$t->check('remove: the Linux key no longer resolves', [] === Seat::principal($fpLin));
$principal = Seat::principal($fpWin);
$t->check('remove: the Windows key still resolves', 1 === count($principal) && 'windows' === $principal[0]['os']);
$t->check(
    'remove: the mirror moves to the remaining seat',
    [$fpWin, '0.2.0', '2026-11-20 11:00:00'] === $mirror(10)
);
$t->check(
    'remove: the Linux software rows close, the Windows rows stay open',
    ['lin-prog' => true, 'win-prog' => false] === array_map(
        'boolval',
        $pdo->query('SELECT hsName, hsRemovedAt IS NOT NULL FROM hostSoftware')->fetchAll(\PDO::FETCH_KEY_PAIR)
    )
);
$t->check(
    'remove: the software:linux fact is gone and the others stay',
    ['inventory', 'software:windows'] === $pdo->query('SELECT hfsKind FROM hostFactState ORDER BY hfsKind')->fetchAll(\PDO::FETCH_COLUMN)
);
$t->check('remove: the enrollment row is pending seat-removed', ['pending', 'seat-removed'] === $enrollment($fpLin));
$audit = $pdo->query("SELECT alSubjectType, alSubjectID, alSubjectLabel, alRenderable, alText FROM auditLog WHERE alType='agent.seat.remove'")
    ->fetchAll(\PDO::FETCH_NUM);
$t->check('remove: one audit row', 1 === count($audit));
$t->check(
    'remove: the audit row names the host, the OS, the key and the admin',
    1 === count($audit)
        && ['host', '10', 'dual-boot', '1'] === array_slice(array_map('strval', $audit[0]), 0, 4)
        && 'removed the linux seat, key ' . substr($fpLin, 0, 16) . ', by tester' === $audit[0][4]
);

// ---------------------------------------------------------------------------
// 2. The agent still holds its key and a valid token. It enrolls again.
//    Only an admin's click binds it: the token must not.
// ---------------------------------------------------------------------------
$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_csr_export(openssl_csr_new(['commonName' => 'removed-rig'], $key, ['digest_alg' => 'sha256']), $csr);
$fpRig = Enrollment::fingerprint($csr);
// The row of the removed key is the one this CSR's key made. Replace the
// issued row with the stub CSR by it, so the repeat enroll sends a real CSR.
$pdo->prepare('DELETE FROM agentEnrollment WHERE aeFingerprint=?')->execute([$fpLin]);
$pdo->prepare('DELETE FROM hostAgents WHERE haFingerprint=?')->execute([$fpRig]);
Seat::bind(10, 'linux', $fpRig, '2027-03-01 00:00:00', '0.2.1', '2026-11-21 12:00:00');
$pdo->prepare(
    'INSERT INTO agentEnrollment (aeHostID, aeFingerprint, aeCSR, aeOS, aeState, aeReason) VALUES (10, ?, ?, ?, ?, ?)'
)->execute([$fpRig, $csr, 'linux', 'issued', 'new-seat']);
Seat::remove(10, 'linux', 'tester');
$t->check('rig: the removed key waits as seat-removed', ['pending', 'seat-removed'] === $enrollment($fpRig));

$enroll = function () use ($csr, $token) {
    $body = [
        'protocol' => 1, 'csr_pem' => $csr, 'os' => 'linux', 'hostname' => 'removed-rig',
        'identity' => ['macs' => ['02:46:00:00:00:10']], 'token' => $token
    ];
    list($code, $payload) = Enrollment::handle($body, '203.0.113.9');
    return $code . ' ' . ($payload['status'] ?? '') . ' ' . ($payload['reason'] ?? '');
};
$t->check('rig: a repeat enroll with a valid token stays pending seat-removed', '202 pending seat-removed' === $enroll());
$t->check('rig: and again', '202 pending seat-removed' === $enroll());
$t->check('rig: the row is still pending seat-removed', ['pending', 'seat-removed'] === $enrollment($fpRig));
$t->check('rig: no seat was bound', ['windows' => $fpWin] === $seatRows(10));
$t->check('rig: the token was not spent', 5 === $tokenUses());

// ---------------------------------------------------------------------------
// 2b. A seat with NO enrollment row (a schema 466 migration, or a cascaded
//     delete). Removal makes the row, so the agent's next enroll is not a
//     first contact that a token can approve.
// ---------------------------------------------------------------------------
$key2 = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_csr_export(openssl_csr_new(['commonName' => 'migrated-rig'], $key2, ['digest_alg' => 'sha256']), $csr2);
$fpMig = Enrollment::fingerprint($csr2);
Seat::bind(10, 'darwin', $fpMig, '2027-03-01 00:00:00', '0.2.1', '2026-11-22 12:00:00');
$t->check('no row: premise, the seat has no enrollment row', false === $enrollment($fpMig));
Seat::remove(10, 'darwin', 'tester');
$t->check('no row: removal made a pending seat-removed row', ['pending', 'seat-removed'] === $enrollment($fpMig));
$made = $pdo->prepare('SELECT aeHostID, aeOS FROM agentEnrollment WHERE aeFingerprint=?');
$made->execute([$fpMig]);
$t->check('no row: the row carries the host and the OS', ['10', 'darwin'] === array_map('strval', $made->fetch(\PDO::FETCH_NUM)));
// An attacker who knows only the public key (an old client certificate
// carries it) races the real agent: same key, forged self-signature. It must
// be refused, and must not spend the one-time adopt of the marker row.
$der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $csr2));
$der[strlen($der) - 6] = chr(ord($der[strlen($der) - 6]) ^ 0xff);
$forged = "-----BEGIN CERTIFICATE REQUEST-----\n" . chunk_split(base64_encode($der), 64, "\n")
    . "-----END CERTIFICATE REQUEST-----\n";
$t->check('forged: premise, the forged CSR still carries the real public key', false !== @openssl_csr_get_public_key($forged)
    && Principal::spkiFingerprint(@openssl_csr_get_public_key($forged)) === $fpMig);
$t->check('forged: a forged self-signature has no fingerprint', null === Enrollment::fingerprint($forged));
list($forgedCode) = Enrollment::handle([
    'protocol' => 1, 'csr_pem' => $forged, 'os' => 'darwin', 'hostname' => 'attacker',
    'identity' => ['macs' => ['02:46:00:00:00:99']], 'token' => $token
], '198.51.100.7');
$t->check('forged: the enroll is refused', 202 !== $forgedCode && 200 !== $forgedCode);
$marker = $pdo->prepare('SELECT aeCSR FROM agentEnrollment WHERE aeFingerprint=?');
$marker->execute([$fpMig]);
$t->check('forged: the row still holds the marker', Seat::MARKER_CSR === (string)$marker->fetchColumn());
$enrollMig = function () use ($csr2, $token) {
    $body = [
        'protocol' => 1, 'csr_pem' => $csr2, 'os' => 'darwin', 'hostname' => 'migrated-rig',
        'identity' => ['macs' => ['02:46:00:00:00:10']], 'token' => $token
    ];
    list($code, $payload) = Enrollment::handle($body, '203.0.113.9');
    return $code . ' ' . ($payload['status'] ?? '') . ' ' . ($payload['reason'] ?? '');
};
$t->check('no row: a repeat enroll with a valid token stays pending seat-removed', '202 pending seat-removed' === $enrollMig());
$t->check('no row: no seat was bound and the token was not spent', ['windows' => $fpWin] === $seatRows(10) && 5 === $tokenUses());
// The repeat adopted the agent's own CSR in place of the marker, and kept the
// row pending seat-removed on the same host and OS.
$csrOf = function ($fp) use ($pdo) {
    $q = $pdo->prepare('SELECT aeCSR FROM agentEnrollment WHERE aeFingerprint=?');
    $q->execute([$fp]);
    return (string)$q->fetchColumn();
};
// The model trims the stored text, so compare trimmed.
$t->check('adopt: the row now holds the agent\'s own CSR, not the marker', trim($csr2) === $csrOf($fpMig));
$made->execute([$fpMig]);
$t->check('adopt: host and OS are unchanged', ['10', 'darwin'] === array_map('strval', $made->fetch(\PDO::FETCH_NUM)));
// Admin Approve is the way back in. Signing is not configured in the test
// DB, so this asserts approve got past the guards to the signing step (the
// 'not configured' 503), not that a certificate was issued.
$migID = (int)$pdo->query("SELECT aeID FROM agentEnrollment WHERE aeFingerprint='$fpMig'")->fetchColumn();
$approveErr = null;
try {
    Enrollment::approve($migID, 'tester');
} catch (\RuntimeException $e) {
    $approveErr = $e->getCode() . ' ' . $e->getMessage();
}
$t->check('adopt: admin approve reaches the signing step', '503 agent certificate issuance is not configured' === $approveErr);
// A different key's CSR never touches that row.
$key3 = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_csr_export(openssl_csr_new(['commonName' => 'other-rig'], $key3, ['digest_alg' => 'sha256']), $csr3);
Enrollment::handle(
    ['protocol' => 1, 'csr_pem' => $csr3, 'os' => 'darwin', 'hostname' => 'other-rig',
        'identity' => ['macs' => ['02:46:00:00:00:10']], 'token' => $token],
    '203.0.113.9'
);
$t->check('adopt: a different key\'s CSR leaves that row alone', trim($csr2) === $csrOf($fpMig));
// A row with a real CSR is not overwritten by a repeat enroll of the same key.
openssl_csr_export(openssl_csr_new(['commonName' => 'rig-again'], $key, ['digest_alg' => 'sha256']), $csrRig2);
$t->check('premise: same key, different CSR text', $csrRig2 !== $csr && Enrollment::fingerprint($csrRig2) === $fpRig);
Enrollment::handle(
    ['protocol' => 1, 'csr_pem' => $csrRig2, 'os' => 'linux', 'hostname' => 'removed-rig',
        'identity' => ['macs' => ['02:46:00:00:00:10']], 'token' => $token],
    '203.0.113.9'
);
$t->check('adopt: a row with a real CSR is not overwritten', trim($csr) === $csrOf($fpRig));
// A directory-identity agent on a no-row seat. The first repeat adopts and
// stores its identity as first contact does, so the SECOND repeat matches the
// gate: 202, not 409 enrollment_upgrade_requires_new_key.
$key4 = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_csr_export(openssl_csr_new(['commonName' => 'dir-rig'], $key4, ['digest_alg' => 'sha256']), $csr4);
$fpDir = Enrollment::fingerprint($csr4);
Seat::bind(10, 'plan9', $fpDir, null, '0.2.1', '2026-11-22 14:00:00');
Seat::remove(10, 'plan9', 'tester');
$enrollDir = function () use ($csr4, $token) {
    $body = [
        'protocol' => 1, 'csr_pem' => $csr4, 'os' => 'plan9', 'hostname' => 'dir-rig', 'token' => $token,
        'features' => ['directory-join-target-v1'],
        'identity' => [
            'system_uuid' => '18319000-0000-4000-8000-0000000000aa', 'system_serial' => 'DIR-SERIAL-1',
            'macs' => ['02:46:00:00:00:10']
        ]
    ];
    try {
        list($code, $payload) = Enrollment::handle($body, '203.0.113.9');
        return $code . ' ' . ($payload['status'] ?? '') . ' ' . ($payload['reason'] ?? '');
    } catch (\RuntimeException $e) {
        return $e->getCode() . ' ' . $e->getMessage();
    }
};
// The other-rig enroll above is a first contact that a token may approve, so
// the baseline is read here.
$usesBefore = $tokenUses();
$t->check('directory: the first repeat adopts and waits', '202 pending seat-removed' === $enrollDir());
$t->check('directory: the second repeat is 202, not 409', '202 pending seat-removed' === $enrollDir());
$t->check('directory: the token is not spent', $usesBefore === $tokenUses());
$t->check('directory: the row is still pending seat-removed', ['pending', 'seat-removed'] === $enrollment($fpDir));
// A denied key stays denied.
$pdo->prepare("UPDATE agentEnrollment SET aeState='denied', aeReason='rebind' WHERE aeFingerprint=?")->execute([$fpMig]);
Seat::bind(10, 'darwin', $fpMig, null, '0.2.1', '2026-11-22 13:00:00');
Seat::remove(10, 'darwin', 'tester');
$t->check('denied: a denied key stays denied', ['denied', 'rebind'] === $enrollment($fpMig));
$noOS = null;
try {
    Seat::remove(10, 'darwin', 'tester');
} catch (\RuntimeException $e) {
    $noOS = $e->getCode();
}
$t->check('denied: removing it again is a 404', 404 === $noOS);

// ---------------------------------------------------------------------------
// 3. The last seat: the mirror clears. A missing seat is a 404.
// ---------------------------------------------------------------------------
Seat::remove(10, 'windows', 'tester');
$t->check('last seat: no seat rows remain', [] === $seatRows(10));
$cleared = $pdo->query(
    'SELECT hostAgentFingerprint, hostAgentNotAfter, hostAgentVersion, hostAgentUpdateState, hostAgentCheckin FROM hosts WHERE hostID=10'
)->fetch(\PDO::FETCH_NUM);
$t->check('last seat: the mirror is cleared', ['', null, '', '', null] === $cleared);
$t->check('missing: a seat that does not exist is a 404', 404 === $removeCode(10, 'windows'));
$t->check('missing: a host that does not exist is a 404', 404 === $removeCode(99, 'linux'));
$t->check(
    'audit: one row per removal, none for the refused ones',
    6 === (int)$pdo->query("SELECT COUNT(*) FROM auditLog WHERE alType='agent.seat.remove'")->fetchColumn()
);

$t->finish();
