<?php
/**
 * How an OU placement request and a native directory operation share one
 * computer object, against a real server and with the real claim and prepare
 * code. A claim cancels a PREPARED request and is refused by one that may
 * have run. Another host's native operation in a protected phase keeps a
 * Move from being prepared. A name taken by another host beats a stale
 * request. Different objects never block each other.
 *
 * No directory is contacted.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-placement-native-order-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory placement native order DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-placement-native-order-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

use FOG\Agent\DirectoryPlacementIntent as Intent;

$claim = static function (array $plan) {
    $saved = FOG\Agent\DirectoryRejoin::settings($plan['host']);
    return intentFixtureRefusal(static function () use ($plan, $saved) {
        FOG\Agent\DirectoryOperation::claim($plan['host'], $saved, $plan['body']);
    });
};
$phase = static function (array $plan) {
    return FOG\Agent\DirectoryRejoin::settings($plan['host'])['state']['phase'] ?? null;
};

// A real claim cancels a request that was never sent.
$a = intentFixtureReadyPlan($pdo);
$aTicket = Intent::prepare(intentFixturePayloadFor($pdo, $a));
$t->check('a claim for the same object starts while only a PREPARED request exists', '' === $claim($a) && 'started' === $phase($a));
$t->check(
    'the claim canceled that request and its ticket cannot dispatch',
    'CANCELED' === intentFixtureStatus($pdo, $a['target']['guid'])
        && 'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($aTicket) {
            Intent::dispatch($aTicket);
        })
);

// A request that may have run refuses the claim and changes nothing.
$b = intentFixtureReadyPlan($pdo);
Intent::dispatch(Intent::prepare(intentFixturePayloadFor($pdo, $b)));
$savedBefore = FOG\Agent\DirectoryRejoin::settings($b['host']);
$rowBefore = intentFixtureRow($pdo, $b['target']['guid']);
$t->check(
    'a claim for an object with a MAY_HAVE_RUN request is refused',
    'directory_placement_target_protected' === $claim($b)
);
$t->check(
    'the refused claim left the operation state and the request unchanged',
    $savedBefore === FOG\Agent\DirectoryRejoin::settings($b['host']) && $rowBefore === intentFixtureRow($pdo, $b['target']['guid'])
);

// A different object is not blocked, in either direction.
$c = intentFixtureReadyPlan($pdo);
$d = intentFixtureReadyPlan($pdo);
$t->check(
    'a claim for another object starts while the first object is MAY_HAVE_RUN',
    '' === $claim($c) && 'started' === $phase($c)
);
Intent::dispatch(Intent::prepare(intentFixturePayloadFor($pdo, $d)));
$t->check('a request for another object dispatches beside a started claim', 'MAY_HAVE_RUN' === intentFixtureStatus($pdo, $d['target']['guid']));

// Another host's native operation on the same object keeps a Move from being prepared.
$placement = intentFixtureCreate($pdo);
$other = intentFixtureCreate($pdo);
$otherHost = (new FOG\Items\Host())->set('id', $other['host_id'])->set('name', $other['host_name']);
foreach (['started', 'awaiting_reboot', 'unknown', 'failed'] as $p) {
    FOG\Agent\DirectoryRejoin::change($otherHost, static function (&$config, &$state) use ($other, $placement, $p) {
        $state = ['version' => 1, 'protocol' => 2, 'host_id' => $other['host_id'], 'attempt_id' => bin2hex(random_bytes(16)),
            'phase' => $p, 'operation' => 'join', 'name' => 'OTHER' . strtoupper(bin2hex(random_bytes(3))),
            'fingerprint' => hash('sha256', 'o'), 'target' => ['guid' => $placement['target']['guid'], 'sid' => $placement['target']['sid']]];
    });
    $authority = intentFixtureAll($pdo, 'SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID = ?', [$other['host_id']]);
    $t->check(
        'a Move for an object in the ' . $p . ' operation of another host is refused',
        'directory_target_operation_in_progress' === intentFixtureRefusal(static function () use ($placement) {
            Intent::prepare($placement['payload']);
        }) && null === intentFixtureRow($pdo, $placement['target']['guid'])
            && $authority === intentFixtureAll($pdo, 'SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID = ?', [$other['host_id']])
    );
}
FOG\Agent\DirectoryRejoin::change($otherHost, static function (&$config, &$state) use ($other) {
    $state = ['version' => 1, 'protocol' => 2, 'host_id' => $other['host_id'], 'attempt_id' => bin2hex(random_bytes(16)),
        'phase' => 'verified', 'operation' => 'join', 'name' => $other['host_name'],
        'target' => ['guid' => intentFixtureGuid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999)]];
});
$ticket = Intent::prepare($placement['payload']);
$t->check(
    'a finished operation of another host on a different object does not block a Move',
    1 === $ticket['generation'] && 'PREPARED' === intentFixtureStatus($pdo, $placement['target']['guid'])
);

// A protected operation of the same host keeps its own Move from being prepared.
$own = intentFixtureCreate($pdo);
$ownHost = (new FOG\Items\Host())->set('id', $own['host_id'])->set('name', $own['host_name']);
FOG\Agent\DirectoryRejoin::change($ownHost, static function (&$config, &$state) {
    $state = ['version' => 1, 'phase' => 'started', 'attempt_id' => bin2hex(random_bytes(16))];
});
$own['payload']['snapshot'] = intentFixtureSnapshot($pdo, $own['host_id']);
$t->check(
    'a Move is refused while the same host has an operation that may have changed its account',
    'directory_placement_native_in_progress' === intentFixtureRefusal(static function () use ($own) {
        Intent::prepare($own['payload']);
    }) && null === intentFixtureRow($pdo, $own['target']['guid'])
);

// Another host takes the target account's name before the Move is prepared.
$stale = intentFixtureReadyPlan($pdo, 'LOOK' . random_int(1000, 9999), 'HOLD' . random_int(1000, 9999));
$stalePayload = intentFixturePayloadFor($pdo, $stale);
$targetName = rtrim($stale['target']['account'], '$');
$taker = intentFixtureReadyPlan($pdo, null, 'TAKER' . random_int(1000, 9999));
$pdo->prepare('UPDATE hosts SET hostName = ? WHERE hostID = ?')->execute([$targetName, $taker['host_id']]);
$t->check(
    'a request built from an earlier lookup is refused once another host holds the account name',
    'directory_placement_account_reassigned' === intentFixtureRefusal(static function () use ($stalePayload) {
        Intent::prepare($stalePayload);
    }) && null === intentFixtureRow($pdo, $stale['target']['guid'])
);

$t->finish();
