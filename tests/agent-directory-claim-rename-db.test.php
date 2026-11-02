<?php
/**
 * The claim of a rename re-checks, inside its own transaction, that the name
 * the host is renaming away from still belongs to no other host. Host A is
 * mid-rename from OLDA to NEWA, then another host is renamed to OLDA: the
 * claim must answer 409 rename_name_reassigned and change nothing. With that
 * host gone the same claim succeeds.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-claim-rename-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory claim rename DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-claim-rename-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
$t = new FogChecks();

$fingerprint = str_repeat('f', 64);
$domain = 'corp.example';
$pdo->exec('DELETE FROM hostDirectory');
$pdo->exec('DELETE FROM hosts');
$pdo->exec('DELETE FROM tasks');
$insertHost = $pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostAgentFingerprint) VALUES (?,?,1,?,?)');
$insertHost->execute([81, 'NEWA', $domain, $fingerprint]);
// The second host takes the name host A is renaming away from.
$insertHost->execute([82, 'OLDA', $domain, str_repeat('e', 64)]);

$host = (new FOG\Items\Host())->set('id', 81)->set('name', 'NEWA')->set('useAD', 1)
    ->set('ADDomain', $domain)->set('agentFingerprint', $fingerprint);
$config = ['version' => 1, 'protocol' => 2, 'allow_create' => true, 'name' => 'NEWA', 'domain' => $domain,
    'config_revision' => str_repeat('c', 32)];
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, '');
$target = ['guid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'sid' => 'S-1-5-21-1-81',
    'account' => 'OLDA$', 'dn' => 'CN=OLDA,OU=Computers,DC=corp,DC=example'];
$attempt = str_repeat('7', 32);
$state = [
    'version' => 1, 'protocol' => 2, 'operation' => 'rename', 'mode' => 'rename', 'target' => $target,
    'host_id' => 81, 'name' => 'NEWA', 'domain' => $domain, 'dc' => 'dc1.corp.example', 'check_dc' => '',
    'phase' => 'ready', 'attempt_id' => $attempt, 'config_revision' => $revision, 'fingerprint' => $fingerprint,
    'expected_current' => 'OLDA', 'expected_pending' => 'NEWA', 'observed_at' => time(),
    'last_deploy_task_id' => 0, 'trigger_task_id' => 0, 'placement_hold' => true,
    'observation' => [
        'revision' => $revision, 'current_name' => 'OLDA', 'pending_name' => 'NEWA', 'domain' => $domain,
        'netbios' => 'CORP', 'role' => 'workstation', 'secure_channel' => 'true', 'dc' => 'dc1.corp.example',
        'dc_reachable' => true, 'boot' => ['marker' => 'claim-rename', 'started' => gmdate(DATE_ATOM)]
    ]
];
$pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (81,?,?)')
    ->execute([json_encode($config), json_encode($state)]);
$body = ['attempt_id' => $attempt, 'config_revision' => $revision, 'operation' => 'rename',
    'task_id' => 0, 'target_guid' => $target['guid']];

$row = static function () use ($pdo) {
    return $pdo->query('SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=81')->fetch(\PDO::FETCH_ASSOC);
};
$before = $row();
$reason = '';
$code = 0;
try {
    FOG\Agent\DirectoryOperation::claim($host, FOG\Agent\DirectoryRejoin::settings($host), $body);
} catch (\RuntimeException $e) {
    $reason = $e->getMessage();
    $code = $e->getCode();
}
$t->check('a claim whose rename source name now belongs to another host answers 409 rename_name_reassigned',
    'rename_name_reassigned' === $reason && 409 === $code);
$t->check('the refused claim changed nothing and left no transaction open', $before === $row() && !$pdo->inTransaction());

$pdo->exec('DELETE FROM hosts WHERE hostID=82');
$reason = '';
try {
    FOG\Agent\DirectoryOperation::claim($host, FOG\Agent\DirectoryRejoin::settings($host), $body);
} catch (\RuntimeException $e) {
    $reason = $e->getMessage();
}
$t->check('with the name free again the same claim succeeds',
    '' === $reason && 'started' === (FOG\Agent\DirectoryRejoin::settings($host)['state']['phase'] ?? null));

$t->finish();
