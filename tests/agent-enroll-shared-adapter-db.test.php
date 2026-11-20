<?php
/**
 * Agent enrollment through a shared USB adapter, on a real server (forum
 * 18319).
 *
 * The reporter's laptop L has a full inventory. Its adapter's MAC is stored
 * on another laptop's host Y. Before the fix, the directory identity path
 * called that a conflict with host 0, the foreign key refused the row
 * (error 1452), and the agent got 409 on every poll. This test drives
 * Enrollment::handle() against real tables with the real foreign key:
 *
 *   - the laptop binds to L on both enrollment paths (the shared adapter);
 *   - a copied UUID (L's UUID, a serial that contradicts L) stays a
 *     conflict on both paths;
 *   - a conflict the serials cannot settle is stored with no host and
 *     answers 202, and a later poll binds it once the admin removes the
 *     stray MAC. It stays an identity conflict, so a token never approves
 *     it: only an admin's click does;
 *   - an unregistered machine's conflict becomes a pending host once the
 *     stray MAC is removed, also still a conflict;
 *   - a request with no MAC is stored with no host, and a token is not
 *     spent on it.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-enroll-shared-adapter-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP agent enroll shared adapter DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-enroll-shared-adapter-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];
directoryTestBindPdodb($pdo);
// Creating a pending host reaches MACAddress, which logs through FOGCore.
FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());

use FOG\Agent\Enrollment;

$t = new FogChecks();

// Every other core table, empty: Host::load() joins several, and a missing
// one makes every host load as invalid. Then the real foreign key that
// refused host 0.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$kept = ['hosts', 'hostDirectory', 'directoryPlacementIntent', 'multicastSessions', 'tasks', 'agentEnrollment', 'globalSettings'];
foreach ($manifest['tables'] as $table => $spec) {
    if (in_array($table, $kept, true) || empty($spec['create'])) {
        continue;
    }
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    $pdo->exec($spec['create']);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec(
    'ALTER TABLE `agentEnrollment` ADD CONSTRAINT `fk_agentEnrollment_aeHostID` '
    . 'FOREIGN KEY (`aeHostID`) REFERENCES `hosts` (`hostID`) ON DELETE CASCADE'
);
$pdo->exec(
    "INSERT INTO globalSettings (settingKey, settingValue) "
    . "VALUES ('FOG_HOST_IDENTIFY_SMBIOS', 'register')"
);

// Fixture. Y owns the adapter MAC; L is the laptop; U has no inventory.
$host = function ($id, $name, $mac, $serial, $uuid) use ($pdo) {
    $pdo->prepare('INSERT INTO hosts (hostID, hostName) VALUES (?, ?)')->execute([$id, $name]);
    $pdo->prepare('INSERT INTO hostMAC (hmHostID, hmMAC, hmPrimary, hmPending) VALUES (?, ?, 1, 0)')
        ->execute([$id, $mac]);
    if ('' !== $serial) {
        $pdo->prepare('INSERT INTO inventory (iHostID, iSysserial, iSystemUUID) VALUES (?, ?, ?)')
            ->execute([$id, $serial, $uuid]);
    }
};
$uuidL = '18319000-0000-4000-8000-000000000002';
$host(21, 'zz-y', '02:18:31:9a:00:01', 'Y-SERIAL-1', '18319000-0000-4000-8000-000000000001');
$host(22, 'zz-l', '02:18:31:9a:00:02', 'L-SERIAL-1', $uuidL);
$host(23, 'zz-u', '02:18:31:9a:00:03', '', '');

/**
 * One enrollment client: a fresh key, and a poll that answers
 * "<code> <status> <reason>" or "<code> <exception>".
 */
function enrollClient($label, array $identity, $feature, $token = '')
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_csr_export(openssl_csr_new(['commonName' => $label], $key, ['digest_alg' => 'sha256']), $csr);
    $body = ['protocol' => 1, 'csr_pem' => $csr, 'identity' => $identity, 'hostname' => $label, 'token' => $token];
    if ($feature) {
        $body['features'] = ['directory-join-target-v1'];
    }
    return [
        function () use ($body) {
            try {
                list($code, $payload) = Enrollment::handle($body, '203.0.113.9');
                return $code . ' ' . ($payload['status'] ?? '') . ' ' . ($payload['reason'] ?? '');
            } catch (\RuntimeException $e) {
                return $e->getCode() . ' ' . $e->getMessage();
            }
        },
        Enrollment::fingerprint($csr),
    ];
}
/** The stored row for a key: [hostID or null, reason], or null. */
function enrollRow(\PDO $pdo, $fingerprint)
{
    $q = $pdo->prepare('SELECT aeHostID, aeReason FROM agentEnrollment WHERE aeFingerprint = ?');
    $q->execute([$fingerprint]);
    $row = $q->fetch(\PDO::FETCH_NUM);
    return $row ? [null === $row[0] ? null : (int)$row[0], $row[1]] : null;
}
$identity = function ($serial, array $macs, $uuid = null) use ($uuidL) {
    return ['system_uuid' => $uuid ?: $uuidL, 'system_serial' => $serial, 'macs' => $macs];
};
$adapter = '02:18:31:9a:00:01';

// 1. The laptop through the shared adapter, on both paths.
foreach (['directory identity' => true, 'legacy' => false] as $path => $feature) {
    list($poll, $fp) = enrollClient("laptop-$path", $identity('L-SERIAL-1', [$adapter]), $feature);
    $t->check("$path: the laptop is pending, not refused", '202 pending known-host-no-agent' === $poll());
    $t->check("$path: the laptop binds to its own host", [22, 'known-host-no-agent'] === enrollRow($pdo, $fp));
}
list($poll, $fp) = enrollClient('laptop-own-nic', $identity('L-SERIAL-1', [$adapter, '02:18:31:9a:00:02']), true);
$poll();
$t->check('the laptop with its own NIC MAC too binds to its own host', [22, 'known-host-no-agent'] === enrollRow($pdo, $fp));

// 2. A copied UUID: L's UUID with a serial that contradicts L's.
list($poll, $fp) = enrollClient('copied-feature', $identity('Z-SERIAL-9', [$adapter]), true);
$t->check('directory identity: a copied UUID is a pending conflict', '202 pending identity-conflict' === $poll());
$t->check('directory identity: the copied UUID row has no host', [null, 'identity-conflict'] === enrollRow($pdo, $fp));
list($poll, $fp) = enrollClient('copied-legacy', $identity('Z-SERIAL-9', [$adapter]), false);
$poll();
$t->check('legacy: a copied UUID stays a conflict', 'identity-conflict' === (enrollRow($pdo, $fp)[1] ?? ''));

// 3. A conflict the serials cannot settle: U has no inventory. The agent
//    holds a valid enrollment token, which must never approve a conflict.
$token = 'tok-18319-' . bin2hex(random_bytes(8));
$pdo->prepare('INSERT INTO agentEnrollToken (atName, atHash, atUses) VALUES (?, ?, 5)')
    ->execute(['zz-18319', hash('sha256', $token)]);
$tokenUses = function () use ($pdo) {
    return (int)$pdo->query("SELECT atUses FROM agentEnrollToken WHERE atName = 'zz-18319'")->fetchColumn();
};
list($poll, $fp) = enrollClient('unproven', $identity('L-SERIAL-1', ['02:18:31:9a:00:03']), true, $token);
$t->check('an unproven conflict answers 202, not 409', '202 pending identity-conflict' === $poll());
$t->check('an unproven conflict is stored with no host', [null, 'identity-conflict'] === enrollRow($pdo, $fp));
$t->check('a repeat poll still answers 202', '202 pending identity-conflict' === $poll());
$pdo->exec("DELETE FROM hostMAC WHERE hmMAC = '02:18:31:9a:00:03'");
$t->check(
    'after the admin removes the stray MAC, the next poll binds but stays a conflict',
    '202 pending identity-conflict' === $poll()
);
$t->check('the row is bound to the firmware host, reason unchanged', [22, 'identity-conflict'] === enrollRow($pdo, $fp));
$t->check('the token did not approve the bound conflict', '202 pending identity-conflict' === $poll());
$t->check(
    'the token was never spent on it',
    5 === $tokenUses()
);

// 4. An unregistered machine on the adapter: no firmware host, and Y's
//    serial proves the MAC is on another machine.
list($poll, $fp) = enrollClient('newlaptop', $identity('N-SERIAL-1', [$adapter], '18319000-0000-4000-8000-000000000009'), true);
$t->check('an unregistered machine on the adapter is a pending conflict', '202 pending identity-conflict' === $poll());
$pdo->exec("DELETE FROM hostMAC WHERE hmMAC = '$adapter'");
$poll();
$row = enrollRow($pdo, $fp);
$t->check(
    'after the MAC is removed, it becomes a new pending host, still a conflict',
    is_array($row) && $row[0] > 23 && 'identity-conflict' === $row[1]
);

// 5. No MAC at all, with a valid token: nothing to bind, so the token is
//    not spent and the agent waits.
list($poll, $fp) = enrollClient('nomac', ['system_uuid' => '18319000-0000-4000-8000-0000000000ff', 'system_serial' => 'X', 'macs' => []], false, $token);
$t->check('a request with no MAC waits as pending', '202 pending no-mac' === $poll());
// A repeat poll is the one that reaches the automatic approvals.
$t->check('a repeat poll with no MAC still waits as pending', '202 pending no-mac' === $poll());
$t->check('a request with no MAC is stored with no host', [null, 'no-mac'] === enrollRow($pdo, $fp));
$t->check(
    'the token is not spent on a request with no host',
    5 === $tokenUses()
);

// 6. The re-check is for identity conflicts only. A directory request with
//    no MAC stays unbound even when its firmware later names a host.
$uuidQ = '18319000-0000-4000-8000-0000000000ee';
list($poll, $fp) = enrollClient('nomac-dir', ['system_uuid' => $uuidQ, 'system_serial' => 'Q-SERIAL', 'macs' => []], true);
$poll();
$t->check('a directory request with no MAC is stored with no host', [null, 'no-mac'] === enrollRow($pdo, $fp));
$pdo->exec("INSERT INTO hosts (hostID, hostName) VALUES (31, 'zz-q')");
$pdo->exec("INSERT INTO inventory (iHostID, iSysserial, iSystemUUID) VALUES (31, 'Q-SERIAL', '$uuidQ')");
$poll();
$t->check('a later poll does not bind a request that is not a conflict', [null, 'no-mac'] === enrollRow($pdo, $fp));

// 7. An agent without the directory feature, with a valid token. The
//    legacy resolver binds the conflict to the firmware's host at first
//    contact. A token still never approves it: only an admin's click does.
//    The adapter MAC now belongs to case 4's pending host, so Y gets a
//    second MAC for this case.
$pdo->exec("INSERT INTO hostMAC (hmHostID, hmMAC, hmPrimary, hmPending) VALUES (21, '02:18:31:9a:00:04', 0, 0)");
list($poll, $fp) = enrollClient('copied-legacy-token', $identity('Z-SERIAL-9', ['02:18:31:9a:00:04']), false, $token);
$t->check('legacy: a conflict with a token is pending on first contact', '202 pending identity-conflict' === $poll());
$t->check('legacy: a conflict with a token is pending on a repeat poll', '202 pending identity-conflict' === $poll());
// The row has a host, so the check reaches the conflict guard, not the
// early return for a request with no host.
$t->check('legacy: the conflict is bound to the firmware host', [22, 'identity-conflict'] === enrollRow($pdo, $fp));
$t->check(
    'legacy: the token is not spent on a conflict',
    5 === $tokenUses()
);

$t->finish();
