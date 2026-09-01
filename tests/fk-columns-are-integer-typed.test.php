<?php
/**
 * Every enabled foreign key is integer on both sides, so no declared
 * constraint can carry a collation.
 *
 * WHY THIS EXISTS, and it is not really about types.
 *
 * bin/upgrade-rehearsal.php's seed used to plant a collation mismatch --
 * `ALTER TABLE groupMembers CONVERT TO CHARACTER SET utf8mb4 ...` -- described
 * as producing the errno 3780 InnoDB raises when the two sides of a foreign key
 * disagree on collation. It produced nothing. groupMembers has three columns
 * and all three are int(11), so CONVERT TO CHARACTER SET changed the table
 * default and left every key column's collation NULL: collation does not apply
 * to an integer. Both of that table's constraints landed on every run, and the
 * rehearsal reported a clean pass for a case it had never once exercised.
 *
 * Repointing the seed was not available. Of the enabled relationships in
 * commons/schema-constraints.php, every column this file can resolve is int or
 * mediumint. The map's last string-typed entry, virus.vHostMAC ->
 * hostMAC.hmMAC, went with the ClamAV scan in GH-328; it was deliberately
 * 'class' => 'poly', 'action' => 'none' with no 'enabled' key, and its sides
 * were varchar(50) and varchar(59), so it could not have been a foreign key
 * even if someone had enabled it.
 *
 * So the seed was removed rather than fixed, and THIS is what stands in its
 * place. "No enabled constraint can carry a collation" is a census of today's
 * map, not a property of it, and a census left in a comment is a census that
 * goes stale silently. Enable a string-typed relationship and this test goes
 * red and says the rehearsal now needs the collation seed back.
 *
 * WHAT IT DOES NOT DO. It does not require every relationship to resolve. The
 * manifest is core-only by design, so plugin tables (location, ou, windowsKeys,
 * LDAPGroups and the rest) are legitimately absent and are skipped -- but the
 * number that resolve is asserted to be non-trivial, because a lookup that
 * silently resolved nothing would pass this file vacuously, which is the exact
 * failure it was written in response to.
 *
 * DB-free: reads commons/schema-constraints.php and commons/schema-expected.php.
 *
 * Usage: php tests/fk-columns-are-integer-typed.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $what     what is being asserted
 * @param bool   $ok       whether it holds
 * @param array  $failures collected failures
 * @param int    $checks   running count
 *
 * @return void
 */
function fkCheck($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$rels = include $web . '/commons/schema-constraints.php';
$manifest = include $web . '/commons/schema-expected.php';
$tables = isset($manifest['tables']) && is_array($manifest['tables'])
    ? $manifest['tables']
    : [];

fkCheck('the constraint map loads', is_array($rels) && count($rels) > 0, $failures, $checks);
fkCheck('the manifest has tables', count($tables) > 0, $failures, $checks);

$enabled = 0;
$resolved = 0;

foreach ((array) $rels as $rel) {
    if (!is_array($rel) || empty($rel['enabled'])) {
        continue;
    }
    $enabled++;
    $sides = [
        ['child', $rel['child'] ?? '', $rel['column'] ?? ''],
        ['parent', $rel['parent'] ?? '', $rel['pcolumn'] ?? ''],
    ];
    foreach ($sides as $side) {
        list($which, $table, $column) = $side;
        if (!isset($tables[$table]['columns'][$column])) {
            // Plugin tables are not in the core manifest. Not a failure.
            continue;
        }
        $resolved++;
        $type = trim((string) $tables[$table]['columns'][$column]);
        $base = strtolower(preg_replace('/[( ].*/', '', $type));
        fkCheck(
            sprintf(
                '%s side of an enabled constraint, %s.%s, is `%s`. A '
                . 'string-typed foreign key column CAN carry a collation, and '
                . 'InnoDB refuses a constraint whose two sides disagree on one '
                . '(errno 3780). bin/upgrade-rehearsal.php has no seed for that '
                . 'case -- it was removed as unreachable. Add one, and update '
                . 'the comment in its section 5 that says so.',
                $which,
                $table,
                $column,
                $type
            ),
            !preg_match('/char|text|blob|enum|set/', $base),
            $failures,
            $checks
        );
    }
}

fkCheck(
    sprintf('the map has enabled relationships (found %d)', $enabled),
    $enabled > 50,
    $failures,
    $checks
);
// The guard against this file passing for the reason the seed did. An earlier
// cut of this census read the manifest at the wrong nesting depth, resolved
// zero columns, and reported "0 string-typed" -- which is indistinguishable
// from a clean pass and is exactly how the collation seed went unnoticed.
fkCheck(
    sprintf(
        'the manifest lookup actually resolves columns (resolved %d of %d '
        . 'sides). Zero would make every check above vacuous.',
        $resolved,
        $enabled * 2
    ),
    $resolved > 100,
    $failures,
    $checks
);

printf("fk column types: %d check(s)\n", $checks);
if (count($failures)) {
    foreach ($failures as $f) {
        printf("  FAIL  %s\n", $f);
    }
    printf("  %d failed\n", count($failures));
    exit(1);
}
printf("  all passed (%d enabled relationships, %d column sides resolved)\n", $enabled, $resolved);
exit(0);
