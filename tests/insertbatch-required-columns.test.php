<?php
/**
 * insertBatch() must not build an INSERT a strict server will reject.
 *
 * GH-1245 removed `SET SESSION sql_mode=''` from PDODB, which had been
 * hiding, since 2016, every write that omitted a NOT NULL column with no
 * DEFAULT. Under a strict sql_mode the server answers 1364 -- "Field 'x'
 * doesn't have a default value" -- and rejects the row; without one it
 * invents a zero value and says nothing. FOGController::save() was taught to
 * write that coercion down. FOGManagerController::insertBatch(), the OTHER
 * write path, was not, so saving FOG settings and tasking a group's snapins
 * both broke on any server with a default sql_mode.
 *
 * What this pins is the MECHANISM, deliberately, not the current call sites.
 * A test that walked today's callers checking their field lists would go
 * green the moment someone added a caller it did not know about -- which is
 * exactly how this arrived, from code written years apart. So: the seam is
 * reachable from both write paths, insertBatch consults it, and the columns
 * it fills stay out of ON DUPLICATE KEY UPDATE.
 *
 * That last one is the load-bearing check. Adding a filled column to the
 * update list would make every settings save blank the description of every
 * setting on the page -- a data-loss bug strictly worse than the error it
 * would be fixing, and one nothing else here would catch.
 *
 * DB-free: this reads the source, like usertracking-permission-split and
 * permission-purge-guard. The behavior itself is proved against a live
 * strict server by background_scripts/prove_insertbatch_backfill.php.
 *
 * Usage: php tests/insertbatch-required-columns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function check($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

$base = (string) file_get_contents(
    "$root/packages/web/src/Base/FOGBase.php"
);
$ctl = (string) file_get_contents(
    "$root/packages/web/src/Base/FOGController.php"
);
$man = (string) file_get_contents(
    "$root/packages/web/src/Base/FOGManagerController.php"
);

/*
 * FOGController and FOGManagerController are SIBLINGS -- both extend FOGBase
 * directly. A seam either of them needs has to live on FOGBase or the other
 * one cannot reach it, which is the structural reason GH-1245's fix reached
 * only one of the two paths.
 */
check(
    'FOGController and FOGManagerController both extend FOGBase',
    1 === preg_match('/abstract class FOGController extends FOGBase\b/', $ctl)
    && 1 === preg_match(
        '/abstract class FOGManagerController extends FOGBase\b/',
        $man
    ),
    $failures,
    $checks
);

foreach (['columnType', 'columnIsNullable', 'emptyValueFor'] as $method) {
    check(
        "$method() lives on FOGBase, where both write paths can reach it",
        false !== strpos($base, "function $method("),
        $failures,
        $checks
    );
    check(
        "$method() is no longer defined on FOGController",
        false === strpos($ctl, "function $method("),
        $failures,
        $checks
    );
}
check(
    'columnsRequiringValue() lives on FOGBase too',
    false !== strpos($base, 'function columnsRequiringValue('),
    $failures,
    $checks
);

/*
 * The catalog is the only source that answers all three parts of "must this
 * column be named": NOT NULL, no DEFAULT, and not AUTO_INCREMENT.
 * schema-expected.php carries the first two but drops AUTO_INCREMENT, so a
 * manifest-driven answer would fill the primary key with 0.
 */
preg_match(
    '/function columnsRequiringValue\(.*?\n    \}/s',
    $base,
    $m
);
$req = $m[0] ?? '';
check(
    'columnsRequiringValue() reads the server catalog',
    false !== strpos($req, 'information_schema'),
    $failures,
    $checks
);
foreach (
    [
        "IS_NULLABLE` = 'NO'" => 'only NOT NULL columns',
        'COLUMN_DEFAULT` IS NULL' => 'only columns with no DEFAULT',
        'auto_increment' => 'and never an AUTO_INCREMENT column'
    ] as $needle => $what
) {
    check(
        "it selects $what",
        false !== strpos($req, $needle),
        $failures,
        $checks
    );
}

$batch = '';
if (preg_match('/public function insertBatch\(.*?\n    \}/s', $man, $m)) {
    $batch = $m[0];
}
check(
    'insertBatch() was found',
    '' !== $batch,
    $failures,
    $checks
);
check(
    'insertBatch() asks which columns the table requires',
    false !== strpos($batch, 'columnsRequiringValue('),
    $failures,
    $checks
);
check(
    'and fills them from the same emptyValueFor() save() uses',
    false !== strpos($batch, 'emptyValueFor('),
    $failures,
    $checks
);
check(
    'the filled columns are added to the column list',
    1 === preg_match('/\$keys\[\]\s*=\s*\$column;/', $batch),
    $failures,
    $checks
);

/*
 * The filled values must reach the statement. Binding them without emitting
 * the placeholders, or emitting placeholders without binding, both produce a
 * broken statement rather than a wrong one -- but neither is visible from the
 * column list alone.
 *
 * And every ROW needs its own placeholder. The fill bind was named once,
 * outside the row loop, and the single `:_fill_0` was then repeated in each
 * VALUES tuple -- which one row survives and two do not: PDODB sets
 * PDO::ATTR_EMULATE_PREPARES => false, so a real server-side prepare answers
 * SQLSTATE[HY093] "Invalid parameter number" to a named parameter used twice.
 * Every batch of two or more rows into a table with an unnamed NOT NULL
 * column failed outright, which is most of group tasking: the snapin and
 * generic branches of Group::createImagePackage() name none of `tasks`' four
 * NFS/image columns. Proved both ways by
 * background_scripts/prove_batch_fill_duplicate_bind.php.
 */
check(
    'their placeholders are emitted into every row tuple',
    1 === preg_match(
        '/foreach \(\$fillCols as \$fillIndex => \$fillVal\)/',
        $batch
    )
    && 1 === preg_match('/\$insertVals\[\$key\]\s*=\s*\$fillVal;/', $batch),
    $failures,
    $checks
);
check(
    'each row binds its own copy, never one shared placeholder',
    1 === preg_match("/'_fill_%d_%d'/", $batch)
    && false === strpos($batch, '$fillKeys'),
    $failures,
    $checks
);

/*
 * The one that protects data rather than the statement.
 */
$dupLines = [];
foreach (explode("\n", $batch) as $line) {
    if (false !== strpos($line, '$dups[]')) {
        $dupLines[] = trim($line);
    }
}
check(
    'ON DUPLICATE KEY UPDATE is still built only from the caller\'s columns',
    1 === count($dupLines),
    $failures,
    $checks
);
check(
    'no filled column is ever added to the update list',
    false === strpos($batch, '$dups[] = sprintf')
    || 0 === preg_match('/\$column.*\n.*\$dups\[\]/', $batch),
    $failures,
    $checks
);

/*
 * The reported call site. Naming the columns is belt and braces now that the
 * backfill exists, but it is what puts a REAL description on a setting row
 * that has to be created rather than a blank one.
 */
$page = (string) file_get_contents(
    "$root/packages/web/src/Pages/FOGConfigurationPage.php"
);
check(
    'both settings savers name description and category',
    2 === preg_match_all(
        "/'id',\s*\n\s*'name',\s*\n\s*'value',\s*\n\s*'description',\s*\n\s*'category'/",
        $page
    ),
    $failures,
    $checks
);
check(
    'and neither still posts the bare three-column list',
    0 === preg_match(
        "/'id',\s*\n\s*'name',\s*\n\s*'value'\s*\n\s*\];/",
        $page
    ),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
