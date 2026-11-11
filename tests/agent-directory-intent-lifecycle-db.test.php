<?php
/**
 * The life of one OU placement request against a real server: PREPARED,
 * the committed hand-over to MAY_HAVE_RUN, and the stored outcome. Nothing
 * sends a request twice, a row that may have run protects its owner and its
 * object, and a stale or forged ticket cannot take the right to send.
 *
 * The objects in the payloads are identity tokens. This test never connects
 * to a directory.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-intent-lifecycle-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory intent lifecycle DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-intent-lifecycle-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

use FOG\Agent\DirectoryPlacementIntent as Intent;

/** Runs assertClaim() the way a claim does, inside its own transaction. */
$claim = static function ($hostId, array $state) use ($pdo) {
    return intentFixtureRefusal(static function () use ($pdo, $hostId, $state) {
        $pdo->beginTransaction();
        try {
            Intent::assertClaim($pdo, $hostId, $state);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    });
};

// PREPARED is canceled by a claim for the same object. A ticket alone grants nothing.
$a = intentFixtureCreate($pdo);
$ticket = Intent::prepare($a['payload']);
$row = intentFixtureRow($pdo, $a['target']['guid']);
$t->check(
    'prepare stores PREPARED at generation 1 with the payload hash',
    is_array($row) && 'PREPARED' === $row['dpiStatus'] && 1 === (int)$row['dpiGeneration']
        && hash_equals($row['dpiHash'], hash('sha256', $row['dpiPayload'])) && 1 === $ticket['generation']
);
$t->check('a claim for the same object is allowed', '' === $claim($a['host_id'], $a['state']));
$t->check(
    'the claim canceled the PREPARED row',
    'CANCELED' === intentFixtureStatus($pdo, $a['target']['guid'])
);
$next = Intent::prepare($a['payload']);
$row = intentFixtureRow($pdo, $a['target']['guid']);
$t->check(
    'the next prepare advances the same object to generation 2',
    2 === $next['generation'] && 2 === (int)$row['dpiGeneration'] && 'PREPARED' === $row['dpiStatus']
);
$t->check(
    'a ticket from the canceled generation cannot dispatch',
    'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($ticket) {
        Intent::dispatch($ticket);
    })
);
$t->check(
    'the refused dispatch left generation 2 PREPARED',
    'PREPARED' === intentFixtureStatus($pdo, $a['target']['guid'])
        && 2 === (int)intentFixtureRow($pdo, $a['target']['guid'])['dpiGeneration']
);

// A host fact that changed after prepare cancels that exact row at dispatch.
$b = intentFixtureCreate($pdo);
$bTicket = Intent::prepare($b['payload']);
$pdo->prepare('UPDATE hostDirectory SET hdObservedAt = ? WHERE hdHostID = ?')
    ->execute([gmdate('Y-m-d H:i:s', time() - 86400), $b['host_id']]);
$t->check(
    'dispatch refuses when the host changed since prepare',
    'directory_placement_snapshot_changed' === intentFixtureRefusal(static function () use ($bTicket) {
        Intent::dispatch($bTicket);
    })
);
$t->check(
    'the refusal committed the cancel of that PREPARED row',
    'CANCELED' === intentFixtureStatus($pdo, $b['target']['guid'])
);
$b['payload']['snapshot'] = intentFixtureSnapshot($pdo, $b['host_id']);
$bFresh = Intent::prepare($b['payload']);
$t->check(
    'a fresh snapshot prepares a new generation without reviving the old ticket',
    2 === $bFresh['generation']
        && 'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($bTicket) {
            Intent::dispatch($bTicket);
        })
);

// The name of the target account was given to another host after prepare.
$c = intentFixtureCreate($pdo);
$cTicket = Intent::prepare($c['payload']);
$pdo->prepare('UPDATE hosts SET hostName = ? WHERE hostID = ?')->execute(['RENAMED' . $c['host_id'], $c['host_id']]);
$other = intentFixtureCreate($pdo, ['name' => $c['host_name']]);
$t->check(
    'dispatch refuses when another host now holds the account name',
    in_array(
        intentFixtureRefusal(static function () use ($cTicket) {
            Intent::dispatch($cTicket);
        }),
        ['directory_placement_snapshot_changed', 'directory_placement_account_reassigned'],
        true
    ) && 'CANCELED' === intentFixtureStatus($pdo, $c['target']['guid'])
);

// A forged payload with the original hash cannot dispatch.
$d = intentFixtureCreate($pdo);
$dTicket = Intent::prepare($d['payload']);
$forged = $dTicket;
$forged['payload']['destination']['guid'] = 'dddddddd-dddd-4ddd-addd-dddddddddddd';
$t->check(
    'dispatch refuses a changed payload under the original hash',
    'directory_placement_ticket_changed' === intentFixtureRefusal(static function () use ($forged) {
        Intent::dispatch($forged);
    })
);
$t->check(
    'the forged ticket left the stored row PREPARED with its hash',
    'PREPARED' === intentFixtureStatus($pdo, $d['target']['guid'])
        && hash_equals($dTicket['hash'], intentFixtureRow($pdo, $d['target']['guid'])['dpiHash'])
);

// The hand-over commits MAY_HAVE_RUN before anything is sent.
Intent::dispatch($dTicket);
$t->check('dispatch stores MAY_HAVE_RUN', 'MAY_HAVE_RUN' === intentFixtureStatus($pdo, $d['target']['guid']));
$before = intentFixtureRow($pdo, $d['target']['guid']);
$t->check(
    'a row that may have run cannot be abandoned as unsent',
    'directory_placement_cancel_changed' === intentFixtureRefusal(static function () use ($dTicket) {
        Intent::abandonUndispatched($dTicket);
    }) && $before === intentFixtureRow($pdo, $d['target']['guid'])
);
$t->check(
    'a second dispatch of the same ticket is refused',
    'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($dTicket) {
        Intent::dispatch($dTicket);
    })
);
$t->check('it protects its owner from deletion', [$d['host_id']] === Intent::protectedOwners([$d['host_id'], $a['host_id']]));
$t->check(
    'it refuses a config change of its owner',
    'directory_placement_owner_protected' === intentFixtureRefusal(static function () use ($pdo, $d) {
        $pdo->beginTransaction();
        try {
            Intent::cancelOwnerPrepared($pdo, $d['host_id']);
        } finally {
            $pdo->rollBack();
        }
    })
);
$t->check(
    'it refuses a claim for the same object by another host',
    'directory_placement_target_protected' === $claim($d['host_id'] + 1, $d['state'])
);
$t->check(
    'finish refuses a non-boolean success and changes nothing',
    'directory_placement_result_invalid' === intentFixtureRefusal(static function () use ($dTicket) {
        Intent::finish($dTicket, 'false', 'invalid-result-type');
    }) && 'MAY_HAVE_RUN' === intentFixtureStatus($pdo, $d['target']['guid'])
);

// A false result is UNKNOWN. It stays protected and is never sent again.
Intent::finish($dTicket, false, 'supplied-false-result');
$unknown = intentFixtureRow($pdo, $d['target']['guid']);
$result = json_decode((string)$unknown['dpiResult'], true);
$t->check(
    'a false result stores UNKNOWN with a bounded diagnostic',
    'UNKNOWN' === $unknown['dpiStatus'] && 1 === ($result['version'] ?? null)
        && false === ($result['ldap_success'] ?? null)
        && 'supplied-false-result' === ($result['ldap_result'] ?? null)
        && hash('sha256', 'supplied-false-result') === ($result['ldap_result_sha256'] ?? null)
        && 'not_attempted' === ($result['facts_projection'] ?? null)
);
$t->check(
    'finish cannot write a second time',
    'directory_placement_result_generation_changed' === intentFixtureRefusal(static function () use ($dTicket) {
        Intent::finish($dTicket, true, 'must-not-overwrite');
    }) && $unknown === intentFixtureRow($pdo, $d['target']['guid'])
);
$t->check(
    'an UNKNOWN row cannot be dispatched or abandoned',
    'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($dTicket) {
        Intent::dispatch($dTicket);
    }) && 'directory_placement_cancel_changed' === intentFixtureRefusal(static function () use ($dTicket) {
        Intent::abandonUndispatched($dTicket);
    }) && $unknown === intentFixtureRow($pdo, $d['target']['guid'])
);
$t->check('an UNKNOWN row still protects its owner', [$d['host_id']] === Intent::protectedOwners([$d['host_id']]));

// A newer observation between dispatch and finish is kept.
$e = intentFixtureCreate($pdo);
$eTicket = Intent::prepare($e['payload']);
Intent::dispatch($eTicket);
$newerDn = 'CN=' . $e['host_name'] . ',OU=NewerFacts,DC=corp,DC=example';
$newerAt = gmdate('Y-m-d H:i:s');
$pdo->prepare('UPDATE hostDirectory SET hdComputerDN = ?, hdObservedAt = ? WHERE hdHostID = ?')->execute([$newerDn, $newerAt, $e['host_id']]);
Intent::finish($eTicket, true, 'ldap_success');
$facts = intentFixtureAll($pdo, 'SELECT hdComputerDN,hdObservedAt FROM hostDirectory WHERE hdHostID = ?', [$e['host_id']])[0];
$t->check(
    'a success is recorded and a newer observation is not overwritten',
    'SUCCEEDED' === intentFixtureStatus($pdo, $e['target']['guid'])
        && $newerDn === $facts['hdComputerDN'] && $newerAt === $facts['hdObservedAt']
        && 'cas_miss' === (json_decode((string)intentFixtureRow($pdo, $e['target']['guid'])['dpiResult'], true)['facts_projection'] ?? null)
);
$t->check('a SUCCEEDED row no longer protects its owner', [] === Intent::protectedOwners([$e['host_id']]));

$t->finish();
