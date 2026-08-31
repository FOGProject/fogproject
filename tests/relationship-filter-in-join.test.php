<?php
/**
 * A relationship's filter belongs in the JOIN ON clause, never in WHERE.
 *
 * `$databaseFieldClassRelationships` entries may carry an optional 4th
 * element -- a filter on the joined table. Exactly one exists in the whole
 * codebase, and it is load-bearing:
 *
 *     'MACAddressAssociation' => ['hostID', 'id', 'primac', ['primary' => 1]]
 *
 * so that `$host->get('primac')` is the host's PRIMARY MAC rather than
 * whichever row came back first. `buildQuery()` used to emit that filter into
 * `$whereArrayAnd`, and a WHERE predicate on the right-hand table of a LEFT
 * OUTER JOIN is not a filter -- it is an INNER JOIN written the long way.
 * Rows with nothing to join to are dropped by the WHERE, so a host with no
 * `hmPrimary='1'` row stopped existing:
 *
 *     new Host($id)->isValid()   -> false
 *     HostManager->find(...)     -> 0 objects
 *     GET /fog/host/$id          -> 404
 *
 * on a row sitting in the table. Un-loadable, un-editable, un-deletable.
 * And `buildQuery()` recurses, so the same predicate reached every class
 * whose relationship chain passes through Host -- a task belonging to such a
 * host was invisible to TaskManager, which is the half that turns a display
 * bug into an operational one.
 *
 * This branch fixed it in 1cd7446f6 and shipped no test with the fix, which
 * is what this file is. It is STRUCTURAL and needs no database: the defect is
 * in the SQL text, so the SQL text is what it reads -- and so it runs in CI,
 * where there is no server to drive rows through. dev-branch, which got the
 * same fix later, carries this file plus a behavioral counterpart that does
 * drive real rows.
 *
 * Usage: php tests/relationship-filter-in-join.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('relfilter');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Builds one class's join text and its WHERE additions.
 *
 * @param string $class the class to build for
 *
 * @return array [joins, whereArrayAnd]
 */
function relFilterBuild($class)
{
    $obj = FOGCore::getClass($class);
    $join = [];
    $where = [];
    $c = null;
    return $obj->buildQuery($join, $where, $c);
}

/*
 * 1. The filter is really there. Without this every assertion below would
 *    pass just as well against a relationship map that had quietly lost it,
 *    and the test would be measuring nothing.
 */
$relProp = new \ReflectionProperty(
    get_class(FOGCore::getClass('Host')),
    'databaseFieldClassRelationships'
);
$relProp->setAccessible(true);
$rels = $relProp->getValue(FOGCore::getClass('Host'));
$macRel = $rels['MACAddressAssociation'] ?? null;
$t->check(
    'Host still declares a filtered relationship to MACAddressAssociation',
    is_array($macRel) && isset($macRel[3]) && is_array($macRel[3])
    && array_key_exists('primary', $macRel[3])
);

/*
 * 2. It is emitted inside the ON clause of the hostMAC join, and the join is
 *    still an outer one. Both halves matter: moving the predicate to ON while
 *    turning the join inner would drop exactly the same rows.
 */
[$joins, $where] = relFilterBuild('Host');
$t->check(
    'the hostMAC join is still a LEFT OUTER JOIN',
    false !== strpos($joins, 'LEFT OUTER JOIN `hostMAC` ON ')
);
$t->check(
    "hmPrimary is part of the hostMAC ON clause",
    false !== strpos(
        $joins,
        "ON `hostMAC`.`hmHostID`=`hosts`.`hostID` AND `hostMAC`.`hmPrimary` = '1'"
    )
);

/*
 * 3. And nothing about the optional table reached WHERE -- for Host, and for
 *    every class that inherits the join transitively. The list is spelled out
 *    rather than derived so that a class LOSING its path to Host shows up as
 *    a skipped name here, not as silence.
 */
$classes = [
    'Host',
    'Task',
    'SnapinJob',
    'SnapinTask',
    // ImagingLog was here until ADR 0022 decision 3 retired the table, and
    // nothing replaces it. TaskLog is the class that took over recording an
    // imaging run, but it declares NO relationship to Host at all -- it
    // reaches a host through the id it stores, not through a join -- so it
    // does not belong on this list. That is deliberate rather than an
    // oversight: giving TaskLog a Host relationship would pull Host's
    // filtered hostMAC join, and the `primary => 1` predicate with it, into
    // every taskLog query. That is the exact defect this file exists to
    // catch, so the fix for it must not create a new instance of it.
    'NodeFailure',
    'UserTracking',
    'OUAssociation',
];

/*
 * OUAssociation is the one name here that a plugin owns
 * (lib/plugins/ou/src/Items/OUAssociation.php), and plugins are FETCHED by
 * bin/fetch-plugins.sh rather than tracked -- so lib/plugins does not exist
 * at all in a fresh clone, a fresh worktree, or on a CI runner. Failing on it
 * there says "a class lost its path to Host" when the truth is "nobody ran
 * fetch-plugins", which is not a regression and not actionable.
 *
 * A missing CORE class still fails, which is the assertion this list was
 * written for. Only the plugin-provided ones are excused, and only when the
 * plugin tree is genuinely absent -- if the tree IS there and the class is
 * not, that is a real regression and still fails.
 *
 * This is why every fogproject pull request reported 79/2 rather than 80/1
 * once fog-workflows GH-27 turned pipefail on: the suite had been failing in
 * CI from the day it was added, and `run-all.sh | tee` was discarding the
 * status. See GH-1241 for the same swallow in the schema job.
 */
$pluginOwned = ['OUAssociation'];
$pluginsFetched = is_dir(dirname(__DIR__) . '/packages/web/lib/plugins');
foreach ($classes as $class) {
    // Resolved through qualify(), which is exactly what relFilterBuild()'s
    // getClass() call does below -- so the existence check and the thing it
    // guards cannot disagree about which class a short name means. Core is
    // namespaced under src/ and no longer aliased into the global namespace
    // (ADR 0013 §2); the short names are kept here because they are also the
    // labels in every message this loop writes.
    if (!class_exists(\FOG\Base\FOGBase::qualify($class))) {
        if (in_array($class, $pluginOwned, true) && !$pluginsFetched) {
            echo "  SKIP  $class is plugin-provided and lib/plugins is not"
                . " fetched here\n";
            continue;
        }
        $t->check("$class exists, so its join is actually being checked", false);
        continue;
    }
    [$j, $w] = relFilterBuild($class);
    $t->check(
        "$class puts no hostMAC predicate in WHERE",
        !preg_grep('/hostMAC|hmPrimary/', (array)$w)
    );
    // A class that reaches Host must actually carry the join, or "no
    // predicate in WHERE" is true for the boring reason.
    if ('Host' !== $class) {
        $t->check(
            "$class reaches the hostMAC join at all",
            false !== strpos($j, 'JOIN `hostMAC` ON ')
        );
    }
}

$t->finish();
