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
 * DB-free: this reads the source, like its sibling
 * empty-values-and-strict-sql-mode.test.php.
 *
 * Usage: php tests/insertbatch-required-columns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';
$failures = array();
$checks = 0;

/**
 * Source with comments removed, so a commented-out line can neither satisfy
 * a check nor fail one.
 *
 * @param string $file the file to read
 *
 * @return string
 */
function ibStrip($file)
{
    $clean = '';
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

/**
 * Records a check.
 *
 * @param bool   $ok      whether it passed
 * @param string $message what failed, stated as the defect
 *
 * @return void
 */
function ibCheck($ok, $message)
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
    }
}

$base = ibStrip($web . '/lib/fog/fogbase.class.php');
$ctl = ibStrip($web . '/lib/fog/fogcontroller.class.php');
$man = ibStrip($web . '/lib/fog/fogmanagercontroller.class.php');
$page = ibStrip($web . '/lib/pages/fogconfigurationpage.class.php');

// ---------------------------------------------------------------
// 1. The seam is somewhere BOTH write paths can reach.
// ---------------------------------------------------------------
ibCheck(
    preg_match('#abstract class FOGController extends FOGBase\b#', $ctl)
    && preg_match(
        '#abstract class FOGManagerController extends FOGBase\b#',
        $man
    ),
    'FOGController and FOGManagerController are no longer both direct '
    . 'children of FOGBase, so what follows may be checking the wrong thing'
);

foreach (array('columnType', 'columnIsNullable', 'emptyValueFor') as $method) {
    ibCheck(
        strpos($base, "function $method(") !== false,
        "$method() is not on FOGBase, so insertBatch() cannot reach it -- "
        . 'which is the whole reason GH-1245 fixed only one of the two write '
        . 'paths'
    );
    ibCheck(
        strpos($ctl, "function $method(") === false,
        "$method() is defined on FOGController as well as FOGBase; two "
        . 'copies of a coercion rule will drift'
    );
}
ibCheck(
    strpos($base, 'function columnsRequiringValue(') !== false,
    'columnsRequiringValue() is not on FOGBase'
);

// ---------------------------------------------------------------
// 2. What counts as "required" -- all three parts, or it is wrong.
// ---------------------------------------------------------------
// Squashed rather than isolated by regex: columnsRequiringValue() has two
// `return $out;` statements, so a non-greedy match on the method stops at the
// early one and reads none of the body that matters.
$loader = preg_replace('#\s+#', '', $base);
ibCheck(
    strpos($loader, "!empty(\$meta['required'])") !== false,
    'columnsRequiringValue() no longer filters on the required flag'
);
ibCheck(
    strpos($loader, "'required'=>!\$nullable&&!\$auto") !== false,
    'the required flag no longer excludes nullable and auto_increment '
    . 'columns. A nullable column needs no value, and filling an '
    . 'AUTO_INCREMENT key with 0 writes over the value the server was about '
    . 'to generate'
);
ibCheck(
    strpos($loader, "isset(\$row['d'])||null===\$row['d']") !== false,
    'the required flag no longer excludes columns that carry a DEFAULT. '
    . 'Filling one overrides the default the schema chose'
);
ibCheck(
    strpos($loader, '`COLUMN_DEFAULT`AS`d`') !== false
    && strpos($loader, '`EXTRA`AS`e`') !== false,
    'the catalog query no longer reads COLUMN_DEFAULT and EXTRA, so the '
    . 'required flag is computed from data it does not have'
);

// ---------------------------------------------------------------
// 3. insertBatch() consults it, and the fill reaches the statement.
// ---------------------------------------------------------------
preg_match('#public function insertBatch\(.*?\n    \}#s', $man, $m);
$batch = isset($m[0]) ? $m[0] : '';
ibCheck($batch !== '', 'insertBatch() could not be isolated');
ibCheck(
    strpos($batch, 'columnsRequiringValue(') !== false,
    'insertBatch() no longer asks which columns the table requires'
);
ibCheck(
    strpos($batch, 'emptyValueFor(') !== false,
    'insertBatch() no longer fills them from the same emptyValueFor() '
    . 'save() uses, so the two write paths can coerce differently'
);
ibCheck(
    preg_match('#\$keys\[\]\s*=\s*\$column;#', $batch) === 1,
    'the filled columns are no longer added to the column list'
);
ibCheck(
    strpos($batch, '$fillKeys') !== false
    && preg_match(
        '#array_merge\(\s*\(array\)\s*\$insertKeys,\s*\$fillKeys\s*\)#',
        $batch
    ) === 1,
    'the fill placeholders are no longer emitted into every row tuple, so '
    . 'the column list and the value list would disagree'
);
ibCheck(
    preg_match_all('#\$insertVals\s*=\s*\$fillVals;#', $batch) === 2,
    'the fill values are not re-bound for every chunk. $insertVals is unset '
    . 'at the end of each chunk, so binding them once covers only the first '
    . '500 rows and a bigger batch fails on the second'
);

// ---------------------------------------------------------------
// 4. The one that protects data rather than the statement.
// ---------------------------------------------------------------
$dupLines = 0;
foreach (explode("\n", $batch) as $line) {
    if (strpos($line, '$dups[]') !== false) {
        $dupLines++;
    }
}
ibCheck(
    $dupLines === 1,
    'ON DUPLICATE KEY UPDATE is built from more than the caller\'s own '
    . 'columns. A filled column in the update list blanks the stored value '
    . 'on every existing row -- for globalSettings that is the description '
    . 'of every setting, wiped the moment anyone presses save'
);

// ---------------------------------------------------------------
// 5. The reported call site names what it knows.
// ---------------------------------------------------------------
ibCheck(
    preg_match(
        "#'id',\s*'name',\s*'value',\s*'description',\s*'category'#",
        preg_replace('#\s+#', ' ', $page)
    ) === 1,
    'the FOG Settings saver no longer names description and category. The '
    . 'backfill can only supply \'\'; this is what puts a real description '
    . 'on a setting row that has to be created'
);

ibCheck(
    strlen($base) > 50000 && strlen($man) > 10000 && strlen($page) > 50000,
    'the scan did not reach the sources and would pass vacuously'
);

if (count($failures) > 0) {
    echo 'FAIL insertbatch-required-columns (' . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS insertbatch-required-columns ($checks checks)\n";
exit(0);
