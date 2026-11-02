<?php
/**
 * One host with an unreadable directory state must not stop any other host.
 * Host 2 holds a state that is not JSON but still contains "attempt_id", so
 * the overlap scan reads it. Host 1 plans, claims and polls as usual, host 2's
 * Active Directory tab still renders, and host 2 cannot be deleted until an
 * operator clears the review.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-bad-row-isolation-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory bad row isolation DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-bad-row-isolation-db';
require __DIR__ . '/lib/directory-db.php';
directoryTestBindPdodb($pdo);
$t = new FogChecks();

$fingerprint = str_repeat('f', 64);
$bad = '{"attempt_id":"' . str_repeat('9', 32) . '", broken';
$pdo->exec('DELETE FROM hostDirectory');
$pdo->exec('DELETE FROM hosts');
$pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostAgentFingerprint) VALUES (1,?,1,?,?)')
    ->execute(['GOOD1', 'corp.example', $fingerprint]);
$pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostAgentFingerprint) VALUES (2,?,1,?,?)')
    ->execute(['BAD2', 'corp.example', str_repeat('e', 64)]);

$attempt = str_repeat('a', 32);
$config = ['version' => 1, 'protocol' => 2, 'allow_create' => false, 'name' => 'GOOD1', 'domain' => 'corp.example'];
$state = [
    'version' => 1, 'protocol' => 2, 'operation' => 'join', 'mode' => 'existing',
    'host_id' => 1, 'name' => 'GOOD1', 'domain' => 'corp.example', 'phase' => 'ready',
    'attempt_id' => $attempt, 'fingerprint' => $fingerprint, 'last_deploy_task_id' => 0
];
$pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (1,?,?)')
    ->execute([json_encode($config), json_encode($state)]);
$pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (2,?,?)')
    ->execute([json_encode(['version' => 1]), $bad]);

$host1 = (new FOG\Items\Host())->set('id', 1)->set('name', 'GOOD1')->set('useAD', 1)
    ->set('ADDomain', 'corp.example')->set('agentFingerprint', $fingerprint);
$host2 = (new FOG\Items\Host())->set('id', 2)->set('name', 'BAD2')->set('useAD', 1)
    ->set('ADDomain', 'corp.example')->set('agentFingerprint', str_repeat('e', 64));

$t->check(
    'B3 host 1 reads its own state while host 2 is unreadable',
    $attempt === (FOG\Agent\DirectoryRejoin::settings($host1)['state']['attempt_id'] ?? null)
);
$error = '';
try {
    FOG\Agent\DirectoryOperation::assertNoOverlap(1, $state);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
$t->check('B3 the overlap scan skips the unreadable row instead of blocking host 1', '' === $error);

// A claim runs the same scan inside its transaction; it may stop later for
// reasons of its own (no task in this fixture) but never because of host 2.
$error = '';
try {
    FOG\Agent\DirectoryOperation::claim($host1, FOG\Agent\DirectoryRejoin::settings($host1), ['id' => 1, 'attempt_id' => $attempt]);
} catch (\Throwable $e) {
    $error = $e->getMessage();
}
$t->check(
    'B3 a claim for host 1 is not refused because of host 2',
    !in_array($error, ['directory_target_operation_in_progress', 'rejoin_plan_changed'], true)
        && false === strpos($error, 'unreadable')
);
$t->check('the claim left no transaction open', !$pdo->inTransaction());

$t->check(
    'B3 host 2 is the only protected host of the two',
    [2] === FOG\Agent\DirectoryRejoin::protectedHosts([1, 2])
);

// Host 2's tab: it renders, says the state is unreadable and offers the clear.
$page = (new \ReflectionClass(FOG\Pages\HostManagement::class))->newInstanceWithoutConstructor();
foreach (['obj' => $host2, 'childClass' => 'Host'] as $property => $value) {
    $reflection = new \ReflectionProperty(FOG\Base\FOGPage::class, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($page, $value);
}
$knownEvents = new \ReflectionProperty(FOG\Base\HookManager::class, 'knownEvents');
$knownEvents->setAccessible(true);
$knownEvents->setValue(null, ['HOST_EDIT_AD_FIELDS' => true]);
$GLOBALS['node'] = 'host';
$render = new \ReflectionMethod(FOG\Pages\HostManagement::class, 'hostAD');
$render->setAccessible(true);
$html = '';
$thrown = '';
ob_start();
try {
    $render->invoke($page);
} catch (\Throwable $e) {
    $thrown = get_class($e) . ': ' . $e->getMessage();
}
$html = ob_get_clean();
$t->check('B3 host 2 AD tab renders with an unreadable state', '' === $thrown && false !== strpos($html, 'Domain Join Status'));
$t->check(
    'B3 host 2 AD tab offers the review clear',
    false !== strpos($html, 'name="rejoin_clear_review"')
);

$guard = new \ReflectionMethod(FOG\Router\Route::class, '_assertNoDirectoryReview');
$guard->setAccessible(true);
$refusal = static function ($ids) use ($guard) {
    try {
        $guard->invoke(null, 'host', $ids);
    } catch (\Exception $e) {
        return $e->getCode();
    }
    return 0;
};
$t->check('B3 host 2 delete is refused', 409 === $refusal([2]));
$t->check('B3 host 1 delete is not refused', 0 === $refusal([1]));
FOG\Agent\DirectoryRejoin::clearReview($host2, '', 'operator');
$t->check('B3 host 2 delete is allowed once cleared', 0 === $refusal([2]));

$t->finish();
