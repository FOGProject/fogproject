<?php
/**
 * NodeFailure loads by id (GH-1951).
 *
 * NodeFailure overrode $loadQueryTemplate with a three-placeholder query.
 * FOGController::load() hands the template six arguments, so the table
 * name landed in the wrong slot ("Incorrect table name ''"), the SELECT
 * was rejected and new NodeFailure(3) never loaded. GET, PUT and DELETE on
 * /nodefailure/{id} all answered 404 while /nodefailure/list showed the
 * row. The override also limited a load to the last five minutes of
 * nfDateTime; nothing relies on that, because "is this node still failed"
 * is decided in PHP from failureTime, which is stored five minutes ahead.
 *
 * This test pins that load() issues a SELECT naming the nfsFailures table
 * keyed on nfID, with no time window, and that the row it returns loads.
 *
 * DB-free: the harness's fake connection answers the SELECT.
 *
 * Usage: php tests/nodefailure-load.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('nodefailure-load');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

$db->responder = function ($sql, $params) {
    if (0 === strpos(ltrim($sql), 'SELECT')
        && false !== strpos($sql, 'nfsFailures')
    ) {
        // One row, as fetch()->get() hands load() a single record.
        return [
            'nfID' => 3,
            'nfNodeID' => 7,
            'nfTaskID' => 11,
            'nfHostID' => 13,
            'nfGroupID' => 2,
            'nfDateTime' => '2026-01-01 00:00:00',
        ];
    }
    return null;
};

$nf = new \FOG\Items\NodeFailure(3);

$select = '';
foreach ($db->log as $sql) {
    if (0 === strpos(ltrim($sql), 'SELECT')
        && false !== strpos($sql, 'FROM `nfsFailures`')
    ) {
        $select = $sql;
        break;
    }
}

$t->check('load issues a SELECT', '' !== $select);
$t->check(
    'SELECT names the nfsFailures table',
    1 === preg_match('/FROM\s+`nfsFailures`/', $select)
);
$t->check(
    'SELECT is keyed on nfID',
    1 === preg_match('/WHERE\s+`nfID`\s*=\s*:id/', $select)
);
$t->check('SELECT has no time window', false === stripos($select, 'INTERVAL'));
$t->check('row loads (isValid)', true === $nf->isValid());
$t->check('id is set', 3 === (int) $nf->get('id'));
$t->check('storagenodeID is set', 7 === (int) $nf->get('storagenodeID'));

$t->finish();
