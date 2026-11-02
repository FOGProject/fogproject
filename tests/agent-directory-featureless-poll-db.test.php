<?php
/**
 * A poll that declares no directory features is never refused. The agent sends
 * one whenever its feature probe or identity check fails, and a 409 would stop
 * the host's whole poll. DirectoryRejoin::beforePoll() must return without
 * throwing and without writing, for a host with an operation in flight and
 * config enabled. Route::agentPoll() adds directory_rejoin, directory_check and
 * directory_hold only inside the MachineIdentity::requested() branch, which the
 * source check below pins. A poll that declares the task feature without the
 * identity feature is self-contradictory and stays a 409.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-featureless-poll-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory featureless poll DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-featureless-poll-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
$t = new FogChecks();

$fingerprint = str_repeat('f', 64);
$config = ['version' => 1, 'protocol' => 2, 'enabled' => true, 'allow_create' => false, 'name' => 'B', 'domain' => 'corp.example'];
$state = [
    'version' => 1, 'protocol' => 2, 'phase' => 'started', 'attempt_id' => str_repeat('c', 32),
    'host_id' => 7, 'fingerprint' => $fingerprint, 'last_deploy_task_id' => 3
];
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?, hdRejoinState=? WHERE hdHostID=7')
    ->execute([json_encode($config), json_encode($state)]);
$host = (new FOG\Items\Host())->set('id', 7)->set('name', 'B')->set('useAD', 1)
    ->set('ADDomain', 'corp.example')->set('agentFingerprint', $fingerprint);

$before = $pdo->query('SELECT * FROM hostDirectory WHERE hdHostID=7')->fetch(\PDO::FETCH_ASSOC);
$error = null;
try {
    FOG\Agent\DirectoryRejoin::beforePoll($host, ['agent_version' => 'v0.1.18']);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
$after = $pdo->query('SELECT * FROM hostDirectory WHERE hdHostID=7')->fetch(\PDO::FETCH_ASSOC);
$t->check('a featureless poll is not refused with an operation in flight', null === $error);
$t->check('a featureless poll leaves the hostDirectory row byte for byte unchanged', $before === $after);

$error = null;
try {
    FOG\Agent\DirectoryRejoin::beforePoll($host, ['features' => [FOG\Agent\DirectoryOperation::FEATURE]]);
} catch (\RuntimeException $e) {
    $error = $e->getMessage() . '/' . $e->getCode();
}
$t->check(
    'the task feature without the identity feature stays a 409',
    'directory_target_feature_required/409' === $error
);

$source = file_get_contents(dirname(__DIR__) . '/packages/web/src/Router/Route.php');
$gate = strpos($source, 'if (\\FOG\\Agent\\MachineIdentity::requested($body)) {');
$t->check(
    'the poll answer adds directory_rejoin only inside the identity-feature branch',
    false !== $gate
    && false !== ($set = strpos($source, "\$answer['directory_rejoin'] = \\FOG\\Agent\\DirectoryRejoin::plan", $gate))
    && $set - $gate < 400
    && 1 === substr_count($source, "\$answer['directory_rejoin'] = \\FOG\\Agent\\DirectoryRejoin::plan")
);

$t->finish();
