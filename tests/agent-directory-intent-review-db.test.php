<?php
/**
 * What an operator sees and does about OU placement requests, against a real
 * server. A request that may have run, or whose result is unknown, protects
 * its host from deletion. An operator clears an UNKNOWN request: it becomes
 * CANCELED and the change is audited. A config change cancels a request that
 * was never sent and is refused by one that may have run.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-intent-review-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory intent review DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-intent-review-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

use FOG\Agent\DirectoryPlacementIntent as Intent;
use FOG\Agent\DirectoryRejoin;

// The audit row needs its table: build what is missing.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table) {
    $pdo->exec($table['create']);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$hostOf = static function (array $fixture) {
    return (new FOG\Items\Host())->set('id', $fixture['host_id'])->set('name', $fixture['host_name'])
        ->set('ADDomain', 'corp.example');
};
/** A request in the given status. UNKNOWN comes from a failed Move result. */
$inStatus = static function ($status) use ($pdo) {
    $fixture = intentFixtureCreate($pdo);
    $ticket = Intent::prepare($fixture['payload']);
    if ('PREPARED' !== $status) {
        Intent::dispatch($ticket);
    }
    if ('SUCCEEDED' === $status) {
        Intent::finish($ticket, true, 'ldap_success');
    } elseif ('UNKNOWN' === $status) {
        Intent::finish($ticket, false, 'refused');
    } elseif ('CANCELED' === $status) {
        throw new \LogicException('use abandonUndispatched for CANCELED');
    }
    return [$fixture, $ticket];
};

// protectedHosts() lists the owners of a request that may have run or is UNKNOWN.
$ids = [];
foreach (['PREPARED', 'MAY_HAVE_RUN', 'UNKNOWN', 'SUCCEEDED'] as $status) {
    list($fixture) = $inStatus($status);
    $ids[$status] = $fixture['host_id'];
}
$canceled = intentFixtureCreate($pdo);
Intent::abandonUndispatched(Intent::prepare($canceled['payload']));
$ids['CANCELED'] = $canceled['host_id'];
$protected = DirectoryRejoin::protectedHosts(array_values($ids));
sort($protected);
$expected = [$ids['MAY_HAVE_RUN'], $ids['UNKNOWN']];
sort($expected);
$t->check(
    'host deletion is protected for MAY_HAVE_RUN and UNKNOWN requests only',
    $expected === $protected
);

// An operator clears an UNKNOWN request.
list($unknown, $unknownTicket) = $inStatus('UNKNOWN');
$host = $hostOf($unknown);
$pending = Intent::pendingReview($host);
$t->check(
    'the review list shows the UNKNOWN request with its destination',
    1 === count($pending) && 'UNKNOWN' === $pending[0]['status']
        && strtolower($unknown['target']['guid']) === strtolower($pending[0]['guid'])
        && $unknown['destination']['dn'] === $pending[0]['destination']
);
$t->check(
    'a clear with another GUID is refused and changes nothing',
    'directory_intent_review_changed' === intentFixtureRefusal(static function () use ($host) {
        Intent::clearIntentReview($host, intentFixtureGuid(), 'operator');
    }) && 'UNKNOWN' === intentFixtureStatus($pdo, $unknown['target']['guid'])
);
Intent::clearIntentReview($host, strtoupper($unknown['target']['guid']), 'operator');
// auditLog is not reset between runs, so find this run's row by the object's GUID.
$audit = intentFixtureAll(
    $pdo,
    'SELECT alType,alSubjectID,alText FROM auditLog WHERE alType = ? AND alSubjectID = ? AND alText LIKE ?',
    ['host.directory.intent_review_cleared', $unknown['host_id'], '%' . strtolower($unknown['target']['guid']) . '%']
);
$t->check(
    'a clear turns UNKNOWN into CANCELED, removes the protection, and is audited with the operator',
    'CANCELED' === intentFixtureStatus($pdo, $unknown['target']['guid']) && [] === Intent::pendingReview($host)
        && [] === DirectoryRejoin::protectedHosts([$unknown['host_id']])
        && 1 === count($audit) && false !== strpos($audit[0]['alText'], 'operator')
        && false !== strpos($audit[0]['alText'], strtolower($unknown['target']['guid']))
);
$t->check(
    'the old ticket cannot dispatch after the clear',
    'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($unknownTicket) {
        Intent::dispatch($unknownTicket);
    })
);
$t->check(
    'a second clear finds nothing to review',
    'directory_intent_review_not_required' === intentFixtureRefusal(static function () use ($host, $unknown) {
        Intent::clearIntentReview($host, $unknown['target']['guid'], 'operator');
    })
);

// A request stuck as MAY_HAVE_RUN (a crashed worker) blocks deletion until an operator clears it.
list($running, $runningTicket) = $inStatus('MAY_HAVE_RUN');
$runningHost = $hostOf($running);
$t->check(
    'a MAY_HAVE_RUN request blocks the host\'s deletion and is listed for review',
    [$running['host_id']] === DirectoryRejoin::protectedHosts([$running['host_id']])
        && 1 === count(Intent::pendingReview($runningHost)) && 'MAY_HAVE_RUN' === Intent::pendingReview($runningHost)[0]['status']
);
$t->check(
    'a clear with another GUID is refused for a MAY_HAVE_RUN request and changes nothing',
    'directory_intent_review_changed' === intentFixtureRefusal(static function () use ($runningHost) {
        Intent::clearIntentReview($runningHost, intentFixtureGuid(), 'operator');
    }) && 'MAY_HAVE_RUN' === intentFixtureStatus($pdo, $running['target']['guid'])
        && [$running['host_id']] === DirectoryRejoin::protectedHosts([$running['host_id']])
);
// A fresh MAY_HAVE_RUN row may belong to a Move that is still waiting on the
// directory, so clearing it would allow a second Move. It is refused.
$t->check(
    'a fresh MAY_HAVE_RUN request cannot be cleared and nothing changes',
    'directory_placement_move_may_be_running' === intentFixtureRefusal(static function () use ($runningHost, $running) {
        Intent::clearIntentReview($runningHost, $running['target']['guid'], 'operator');
    }) && 'MAY_HAVE_RUN' === intentFixtureStatus($pdo, $running['target']['guid'])
);
$pdo->prepare('UPDATE directoryPlacementIntent SET dpiUpdated=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL ? SECOND) WHERE dpiGUID=?')
    ->execute([Intent::CLEAR_MAY_HAVE_RUN_AFTER + 60, hex2bin(str_replace('-', '', $running['target']['guid']))]);
Intent::clearIntentReview($runningHost, $running['target']['guid'], 'operator');
$audit = intentFixtureAll(
    $pdo,
    'SELECT alText FROM auditLog WHERE alType = ? AND alSubjectID = ? AND alText LIKE ?',
    ['host.directory.intent_review_cleared', $running['host_id'], '%' . strtolower($running['target']['guid']) . '%']
);
$t->check(
    'a clear turns MAY_HAVE_RUN into CANCELED, lets the host be deleted, and the audit text names the status',
    'CANCELED' === intentFixtureStatus($pdo, $running['target']['guid'])
        && [] === DirectoryRejoin::protectedHosts([$running['host_id']])
        && 1 === count($audit) && false !== strpos($audit[0]['alText'], 'MAY_HAVE_RUN')
);
$rowAfterClear = intentFixtureRow($pdo, $running['target']['guid']);
$t->check(
    'a worker that finishes after the clear loses its CAS and cannot overwrite CANCELED',
    'directory_placement_result_generation_changed' === intentFixtureRefusal(static function () use ($runningTicket) {
        Intent::finish($runningTicket, true, 'ldap_success');
    }) && $rowAfterClear === intentFixtureRow($pdo, $running['target']['guid'])
);

// A config change cancels a request that was never sent.
$prepared = intentFixtureCreate($pdo);
$preparedTicket = Intent::prepare($prepared['payload']);
DirectoryRejoin::configure($hostOf($prepared), ['rejoin_allow_create' => 1], 'operator');
$t->check(
    'a config change cancels the host\'s PREPARED request and the ticket cannot dispatch',
    'CANCELED' === intentFixtureStatus($pdo, $prepared['target']['guid'])
        && 'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($preparedTicket) {
            Intent::dispatch($preparedTicket);
        })
);

// A config change is refused while a request may have run, and writes nothing.
list($busy) = $inStatus('MAY_HAVE_RUN');
$configBefore = intentFixtureAll($pdo, 'SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID = ?', [$busy['host_id']]);
$rowBefore = intentFixtureRow($pdo, $busy['target']['guid']);
$t->check(
    'a config change is refused while the host\'s request may have run',
    'directory_placement_owner_protected' === intentFixtureRefusal(static function () use ($hostOf, $busy) {
        DirectoryRejoin::configure($hostOf($busy), ['rejoin_allow_create' => 1], 'operator');
    }) && $configBefore === intentFixtureAll($pdo, 'SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID = ?', [$busy['host_id']])
        && $rowBefore === intentFixtureRow($pdo, $busy['target']['guid'])
);

// The next prepare for an owner whose host facts changed cancels the stale PREPARED request.
$stale = intentFixtureCreate($pdo);
$staleTicket = Intent::prepare($stale['payload']);
$pdo->prepare('UPDATE hostDirectory SET hdComputerDN = ? WHERE hdHostID = ?')
    ->execute(['CN=' . $stale['host_name'] . ',OU=Elsewhere,DC=corp,DC=example', $stale['host_id']]);
$fresh = $stale['payload'];
$fresh['snapshot'] = intentFixtureSnapshot($pdo, $stale['host_id']);
$freshTicket = Intent::prepare($fresh);
$t->check(
    'a later prepare cancels the stale PREPARED request of the same object and advances its generation',
    2 === $freshTicket['generation'] && 'PREPARED' === intentFixtureStatus($pdo, $stale['target']['guid'])
        && 'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($staleTicket) {
            Intent::dispatch($staleTicket);
        })
);

$t->finish();
