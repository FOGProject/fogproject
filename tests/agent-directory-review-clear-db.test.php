<?php
/**
 * A host whose directory operation may have changed the computer or its
 * account cannot be deleted until an operator clears the review. This runs the
 * delete guard that Route::deletemass() calls, and DirectoryRejoin::clearReview(),
 * against a real server.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-review-clear-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory review clear DB: FOG_TEST_DSN not set\n";
    exit(0);
}

/** A directory with no computer account, so a planned rebind would create one. */
class ReviewClearFakeLdap
{
    public function connect($uri, $bind, $password, $ca = '') { return true; }
    public function domainContext($domain)
    {
        return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example'];
    }
    public function computerRecord($base, $account) { return ['status' => 'absent']; }
    public function close() { }
}
class_alias(ReviewClearFakeLdap::class, 'FOG\\Net\\FOGLdap', false);

$directoryDbName = 'agent-directory-review-clear-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
$t = new FogChecks();

$guard = new \ReflectionMethod(FOG\Router\Route::class, '_assertNoDirectoryReview');
$guard->setAccessible(true);
/** The code the delete guard refuses with, or 0 when it allows the delete. */
$refusal = static function ($class, array $ids) use ($guard) {
    try {
        $guard->invoke(null, $class, $ids);
    } catch (\Exception $e) {
        return $e->getCode();
    }
    return 0;
};

$attempt = str_repeat('d', 32);
$put = static function ($hostID, $state) use ($pdo) {
    $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?')->execute([$hostID]);
    $pdo->prepare('DELETE FROM hosts WHERE hostID=?')->execute([$hostID]);
    $pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain) VALUES (?,?,1,?)')
        ->execute([$hostID, 'RV' . $hostID, 'corp.example']);
    $pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (?,?,?)')
        ->execute([$hostID, json_encode(['version' => 1, 'protocol' => 2, 'retry_authorized' => true]), $state]);
};
$state = static function ($phase, $extra = []) use ($attempt) {
    return json_encode($extra + [
        'version' => 1, 'protocol' => 2, 'phase' => $phase, 'attempt_id' => $attempt,
        'last_deploy_task_id' => 5, 'trigger_task_id' => 5
    ]);
};

$cases = [
    'failed' => $state('failed'),
    'unknown' => $state('unknown'),
    'started' => $state('started'),
    'awaiting_reboot' => $state('awaiting_reboot'),
    'undecodable' => '{bad'
];
$hostID = 40;
foreach ($cases as $label => $raw) {
    $put(++$hostID, $raw);
    $t->check(
        'B2 ' . $label . ' state refuses the host delete with 409',
        409 === $refusal('host', [$hostID])
    );
    $t->check(
        'B2 ' . $label . ' state refuses the hostdirectory row delete with 409',
        409 === $refusal(
            'hostdirectory',
            [(int)$pdo->query('SELECT hdID FROM hostDirectory WHERE hdHostID=' . $hostID)->fetchColumn()]
        )
    );
}
$put(60, $state('verified'));
$t->check('a verified state does not block the delete', 0 === $refusal('host', [60]));
$t->check('a class other than host does not run the guard', 0 === $refusal('image', [41]));

// Clearing a review: the wrong attempt is refused, the right one unblocks the
// delete and moves the deploy counters forward, never back.
$put(70, $state('failed'));
// A deploy task that COMPLETED ten minutes ago: the state DirectoryTask treats
// as eligible when it is newer than the host's deploy high-water mark.
$taskEnd = (new \DateTimeImmutable('now', FOG\Base\FOGBase::storageTimeZone()))->sub(new \DateInterval('PT10M'));
$pdo->prepare('INSERT INTO tasks (taskID,taskHostID,taskTypeID,taskStateID,taskStateChangedTime) VALUES (900,70,?,4,?)')
    ->execute([FOG\Items\TaskType::DEPLOY, $taskEnd->format('Y-m-d H:i:s')]);
$host = (new FOG\Items\Host())->set('id', 70)->set('name', 'RV70')->set('useAD', 1)
    ->set('ADDomain', 'corp.example')->set('agentFingerprint', '');
$settingsCache = [FOG\Agent\State::FACTS_SETTING => ['value' => '1']];
foreach ([
    'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN' => 'CN=fixture',
    'FOG_DIRECTORY_BIND_PASSWORD' => 'fixture-only',
    'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example',
    'FOG_DIRECTORY_CA_CERT' => ''
] as $key => $value) {
    $settingsCache[$key] = ['value' => $value];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
$reason = '';
try {
    FOG\Agent\DirectoryRejoin::clearReview($host, str_repeat('e', 32), 'operator');
} catch (\RuntimeException $e) {
    $reason = $e->getMessage();
}
$t->check('B2 a wrong expected attempt is refused as rejoin_review_changed', 'rejoin_review_changed' === $reason);
$t->check('the refused clear left the review in place', 409 === $refusal('host', [70]));

FOG\Agent\DirectoryRejoin::clearReview($host, $attempt, 'operator');
$t->check('B2 after clearReview the delete is allowed', 0 === $refusal('host', [70]));
$cleared = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'the cleared state keeps the evidence and the actor',
    'operator_cleared' === ($cleared['state']['reason'] ?? null)
    && $attempt === ($cleared['state']['cleared_attempt'] ?? null)
    && 'failed' === ($cleared['state']['cleared_phase'] ?? null)
    && 'operator' === ($cleared['state']['cleared_by'] ?? null)
);
$t->check(
    'the deploy highwater covers the latest deploy task on the host',
    (int)($cleared['state']['last_deploy_task_id'] ?? 0) >= 900
);
// A fresh, healthy observation taken after the task ended, so the only thing
// that can keep plan() from binding a new attempt to task 900 is the cleared
// high-water mark.
$dc = 'dc1.corp.example';
$bootStarted = $taskEnd->add(new \DateInterval('PT5M'))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
// The first poll with the feature initializes the config (a new revision); the
// agent then observes against that revision, as directory_check tells it to.
FOG\Agent\DirectoryOperation::observe($host, []);
$observation = [
    'revision' => FOG\Agent\DirectoryOperation::revision($host, FOG\Agent\DirectoryRejoin::settings($host)['config'], ''),
    'current_name' => 'RV70', 'pending_name' => 'RV70', 'domain' => 'corp.example', 'netbios' => 'CORP',
    'role' => 'workstation', 'secure_channel' => 'true', 'dc' => $dc, 'dc_reachable' => true,
    'boot' => ['marker' => 'review-clear-boot', 'started' => $bootStarted]
];
FOG\Agent\DirectoryOperation::observe($host, ['directory_observation' => $observation]);
$beforePlan = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$planned = FOG\Agent\DirectoryRejoin::plan($host);
$afterPlan = FOG\Agent\DirectoryRejoin::settings($host)['state'];$t->check(
    'plan() on the cleared state does not bind a new attempt to the cleared deploy task',
    null === $planned && empty($afterPlan['attempt_id']) && $beforePlan === $afterPlan
);
$again = '';
try {
    FOG\Agent\DirectoryRejoin::clearReview($host, $attempt, 'operator');
} catch (\RuntimeException $e) {
    $again = $e->getMessage();
}
$t->check('clearing a cleared state is refused', 'rejoin_review_not_required' === $again);

// An unreadable row can be cleared too, without an attempt to compare.
$put(80, '{bad');
$bad = (new FOG\Items\Host())->set('id', 80)->set('name', 'RV80');
FOG\Agent\DirectoryRejoin::clearReview($bad, '', 'operator');
$after = FOG\Agent\DirectoryRejoin::settings($bad);
$t->check(
    'an unreadable state is cleared and then reads as cleared',
    0 === $refusal('host', [80]) && 'unreadable' === ($after['state']['cleared_phase'] ?? null)
);

$t->finish();
