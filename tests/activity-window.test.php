<?php
/**
 * ADR 0022 decision 4: ActivityWindow's projection and its indexes.
 *
 * The class unions five work-item tables behind one column set. Almost
 * everything that can be wrong with it is in the SQL text, and SQL text is
 * exactly what a unit test can read without a server -- which matters here,
 * because the alternative is finding out on an install.
 *
 * What this pins, and why each one is a real failure rather than a style
 * preference:
 *
 * 1. **Every arm names columns that exist on the table it selects from.**
 *    Checked against commons/schema-expected.php, which is the manifest the
 *    installer builds from, so a column renamed by a future schema step
 *    fails here rather than at the first window query. A wrong column is a
 *    SQL error at runtime and nothing before it.
 *
 * 2. **Every arm produces the same six output columns in the same order.**
 *    A UNION takes its column names from the FIRST arm, so an arm that
 *    disagrees does not error -- it silently supplies its second column as
 *    somebody else's `subjectID`. That is the worst shape of failure this
 *    class can have: a plausible number in the wrong field.
 *
 * 3. **The five tables are the five ADR 0022 named as work items** -- and
 *    `imagingLog` is not among them, because decision 3 retired it. The
 *    list is spelled out so losing one fails rather than shortening the
 *    union silently.
 *
 * 4. **Schema step 354 indexes exactly the start column each arm bounds
 *    on.** These two are written in different files and are useless apart:
 *    an index on a column nothing filters is write cost for nothing, and a
 *    filter on an unindexed column is a full scan per table. Drifting is
 *    silent in both directions.
 *
 * 5. **`task` and `snapinjob` report a NULL end.** ADR 0022 decision 2
 *    rules out substituting `taskStateChangedTime`, because every
 *    transition overwrites it. Someone "fixing" the NULL would produce a
 *    duration that is quietly wrong rather than absent.
 *
 * DB-free: the map is a private static array of literals, and the manifest
 * is a PHP file.
 *
 * Usage: php tests/activity-window.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('activity-window');
FogTestHarness::fakeDb();

$t = new FogChecks();

$root = dirname(__DIR__);

$mapMethod = new \ReflectionMethod('FOG\ActivityWindow', '_map');
$mapMethod->setAccessible(true);
$map = (array)$mapMethod->invoke(null);

// 3. The sources, by name.
$expected = [
    'task' => 'tasks',
    'snapinjob' => 'snapinJobs',
    'snapintask' => 'snapinTasks',
    'multicastsession' => 'multicastSessions',
    'filedeletequeue' => 'fileDeleteQueue'
];
$t->check(
    'the window covers exactly the five work-item tables ('
    . implode(', ', array_keys($map)) . ')',
    array_keys($map) === array_keys($expected)
);
$t->check(
    'and imagingLog is not among them, having been retired',
    false === strpos(
        (string)file_get_contents(
            $root . '/packages/web/src/Audit/ActivityWindow.php'
        ),
        'imagingLog'
    )
);
$t->check(
    'sources() reports the same set',
    \FOG\ActivityWindow::sources() === array_keys($expected)
);

// 2. One column set, one order, every arm.
$frame = ['subjectID', 'startedAt', 'endedAt', 'state', 'label'];
foreach ($map as $source => $m) {
    $t->check(
        "$source selects from `{$expected[$source]}`",
        isset($m['table']) && $m['table'] === $expected[$source]
    );
    $keys = array_values(
        array_diff(array_keys($m), ['table', 'join'])
    );
    $t->check(
        "$source maps the frame in the union's column order",
        $keys === $frame
    );
}

// 1. Every column named exists on the table it is qualified with. Read from
//    the manifest rather than from a live server, so this runs in CI.
$expectedFile = require $root . '/packages/web/commons/schema-expected.php';
$manifest = isset($expectedFile['tables'])
    ? (array)$expectedFile['tables']
    : [];
$t->check('the schema manifest loaded', count($manifest) > 0);

if (count($manifest) > 0) {
    // Case-insensitively, because the manifest keys tables as the server
    // reports them and the SQL writes them as the models do.
    $columns = [];
    foreach ($manifest as $table => $meta) {
        if (!isset($meta['columns'])) {
            continue;
        }
        $columns[strtolower($table)] = array_map(
            'strtolower',
            array_keys((array)$meta['columns'])
        );
    }
    foreach ($map as $source => $m) {
        $sql = implode(' ', array_values($m));
        // `table`.`column` pairs only. A bare literal ('' , 0, NULL) has no
        // table to check it against and is not a column reference.
        preg_match_all('/`([A-Za-z0-9_]+)`\.`([A-Za-z0-9_]+)`/', $sql, $hits, PREG_SET_ORDER);
        $t->check("$source references at least one real column", count($hits) > 0);
        foreach ($hits as $hit) {
            $table = strtolower($hit[1]);
            $column = strtolower($hit[2]);
            $t->check(
                "$source: `{$hit[1]}`.`{$hit[2]}` exists",
                isset($columns[$table])
                && in_array($column, $columns[$table], true)
            );
        }
    }
}

// 5. The two tables with no end column report NULL, deliberately.
foreach (['task', 'snapinjob'] as $source) {
    $t->check(
        "$source reports a NULL end, because its table has none",
        isset($map[$source]) && 'NULL' === $map[$source]['endedAt']
    );
}
// Read from the map rather than from the file text: the class docblock
// names the column in order to rule it out, so a text search finds the
// explanation and calls it the defect.
$substituted = [];
foreach ($map as $source => $m) {
    if (false !== strpos(implode(' ', array_values($m)), 'taskStateChangedTime')) {
        $substituted[] = $source;
    }
}
$t->check(
    'and no arm substitutes taskStateChangedTime for an end ('
    . (count($substituted) ? implode(', ', $substituted) : 'none') . ')',
    [] === $substituted
);

// 4. Schema 354 indexes exactly what the arms bound on.
$schema = (string)file_get_contents($root . '/packages/web/commons/schema.php');
$pos = strpos($schema, '// 354');
$t->check('schema step 354 exists', false !== $pos);
if (false !== $pos) {
    $step = substr($schema, $pos);
    foreach ($map as $source => $m) {
        // The column the arm filters and orders on.
        preg_match(
            '/`' . preg_quote($m['table'], '/') . '`\.`([A-Za-z0-9_]+)`/',
            $m['startedAt'],
            $hit
        );
        $t->check(
            "$source bounds on a column of its own table",
            isset($hit[1])
        );
        if (!isset($hit[1])) {
            continue;
        }
        $t->check(
            "step 354 indexes `{$m['table']}`.`{$hit[1]}`",
            false !== strpos($step, "'" . $m['table'] . "', '" . $hit[1] . "'")
        );
    }
}

$t->finish();
