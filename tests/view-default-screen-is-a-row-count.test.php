<?php
/**
 * FOG_VIEW_DEFAULT_SCREEN must be seeded as a ROW COUNT, and a bad value must
 * not be able to reach a grid.
 *
 * The setting used to name a screen -- LIST or SEARCH -- and that is still what
 * it means on 1.5. In 1.6 it was repurposed into the grids' rows-per-page
 * default, and the Configuration page renders it as a 10/25/50/100/All picker,
 * but commons/schema.php step 17 was never corrected. An upgraded install is
 * fine because it carries its 1.5 numeric row forward; a FRESH 1.6 install
 * seeded the literal string SEARCH into what is now a row count.
 *
 * That value reaches the browser through the hidden #pageLength input and goes
 * through parseInt(), so a fresh install handed every grid `pageLength: NaN`.
 * Nothing errors. Infinite scroll survived by accident -- Scroller needs a
 * finite chunk and already had a fallback for "All" -- and classic paging,
 * which had no guard, took the NaN.
 *
 * Both halves are pinned, because either alone leaves the bug reachable: fix
 * only the seed and an existing install stays broken; fix only the browser and
 * the Configuration page still shows a row-count picker with SEARCH in it.
 *
 * The database half runs only with FOG_TEST_DSN set and models the three real
 * paths -- fresh install, an install still holding SEARCH, and an admin who has
 * chosen a number the picker does not offer, whose choice must survive.
 *
 * Usage: php tests/view-default-screen-is-a-row-count.test.php
 *   FOG_TEST_DSN=mysql:host=127.0.0.1;port=3306 FOG_TEST_USER=root \
 *   FOG_TEST_PASS=secret php tests/view-default-screen-is-a-row-count.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$schemaFile = $web . '/commons/schema.php';
$fails = [];

// ---------------------------------------------------------------- textual --

$schema = file_get_contents($schemaFile);

// The seed itself. Anchored on the value and category together, so a partial
// edit that fixes the prose and leaves 'SEARCH' behind is still a failure.
if (!preg_match(
    "#'FOG_VIEW_DEFAULT_SCREEN'.{0,600}?','25','FOG View Settings'#s",
    $schema
)) {
    $fails[] = 'step 17 does not seed FOG_VIEW_DEFAULT_SCREEN with a numeric '
        . 'row count, so a fresh install starts with a value the grids '
        . 'parseInt() into NaN';
}
if (strpos($schema, "and <b>SEARCH</b>.','SEARCH','FOG View Settings'") !== false) {
    $fails[] = 'step 17 still seeds the 1.5 screen-name value and description '
        . 'for FOG_VIEW_DEFAULT_SCREEN';
}

// The repair for installs that already took the bad seed, and its precision.
if (!preg_match(
    "#SET `settingValue` = '25' \"\s*\n\s*\. \"WHERE `settingKey` = "
    . "'FOG_VIEW_DEFAULT_SCREEN' \"\s*\n\s*\. \"AND `settingValue` IN "
    . "\('SEARCH','LIST'\)#",
    $schema
)) {
    $fails[] = 'no repair step that rewrites only the two screen-name values: '
        . 'without the IN guard the repair also resets a number an admin '
        . 'chose, and without the step an existing install keeps SEARCH';
}

$js = file_get_contents($web . '/management/js/fog/fog.common.js');

// The browser-side normalization, and crucially WHERE it is. The whole defect
// was that the only guard lived inside the infinite-scroll branch, so classic
// paging had none -- pin the guard to the parseInt that feeds it, not to the
// file, or a guard that drifts back inside that branch still matches.
if (!preg_match(
    "~var pageLength = parseInt\(\\\$\('\#pageLength'\)\.val\(\)\);"
    . '.{0,900}?'
    . '\n  if \(isNaN\(pageLength\) \|\| pageLength === 0 \|\| pageLength < -1\) \{'
    . '\n    pageLength = 25;'
    . '\n  \}~s',
    $js
)) {
    $fails[] = 'registerTable() does not normalize #pageLength at the top of '
        . 'the function: a non-numeric setting reaches classic paging as '
        . 'pageLength: NaN, which DataTables does not catch';
}

// -1 is the length menu's "All" and must survive the guard, or choosing All
// silently becomes 25 on every paged grid.
if (preg_match('#if \(isNaN\(pageLength\)[^\n]*pageLength < 1\) \{#', $js)) {
    $fails[] = 'the normalization rejects -1, which is the legitimate "All" '
        . 'from the length menu';
}

// -------------------------------------------------------------- behavioral --

$dsn = getenv('FOG_TEST_DSN');
if ($dsn === false || $dsn === '') {
    if ($fails) {
        foreach ($fails as $f) {
            echo "FAIL: $f\n";
        }
        exit(1);
    }
    echo "PASS: FOG_VIEW_DEFAULT_SCREEN is seeded as a row count "
        . "(SKIP: no FOG_TEST_DSN, repair not executed)\n";
    exit(0);
}

$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$user = ($user === false) ? 'root' : $user;
$pass = ($pass === false) ? '' : $pass;
$db = 'fog_view_default_screen_test';

require __DIR__ . '/lib/fog-schema-collector.php';
$steps = fogCollectSchemaSteps($schemaFile, $db, PHP_INT_MAX);

// Every statement any step aims at this one setting, in order. Taken by
// content rather than by step number so that renumbering cannot silently
// empty this list -- an empty list is checked for below.
$sql = [];
foreach ($steps as $updates) {
    foreach ((array)$updates as $update) {
        if (is_string($update)
            && strpos($update, 'FOG_VIEW_DEFAULT_SCREEN') !== false
        ) {
            $sql[] = $update;
        }
    }
}
if (count($sql) < 2) {
    fwrite(
        STDERR,
        "FAIL: expected a seed and at least one repair naming "
        . "FOG_VIEW_DEFAULT_SCREEN; found " . count($sql) . "\n"
    );
    exit(1);
}

$manifest = include $web . '/commons/schema-expected.php';
$create = $manifest['tables']['globalSettings']['create'] ?? '';
if (!$create) {
    fwrite(STDERR, "FAIL: no globalSettings create in the manifest\n");
    exit(1);
}

try {
    $pdo = new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    echo "SKIP  cannot connect with FOG_TEST_DSN; repair not executed\n";
    exit($fails ? 1 : 0);
}

// Each case is the state the row is in BEFORE the steps run, and what it must
// be after. null means the row does not exist yet -- the fresh-install path,
// where the seed itself is what creates it.
$cases = [
    'fresh install' => [null, '25'],
    'took the bad seed' => ['SEARCH', '25'],
    'the other screen name' => ['LIST', '25'],
    'admin chose a number off the picker' => ['250', '250'],
    'admin chose All' => ['-1', '-1'],
];

foreach ($cases as $label => $case) {
    list($before, $expected) = $case;
    $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $db));
    $pdo->exec(sprintf('CREATE DATABASE `%s`', $db));
    $pdo->exec(sprintf('USE `%s`', $db));
    $pdo->exec($create);
    if ($before !== null) {
        $ins = $pdo->prepare(
            'INSERT INTO `globalSettings` '
            . '(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) '
            . "VALUES ('FOG_VIEW_DEFAULT_SCREEN','old text',?,'FOG View Settings')"
        );
        $ins->execute([$before]);
    }
    foreach ($sql as $one) {
        $pdo->exec($one);
    }
    $got = $pdo
        ->query(
            'SELECT `settingValue` FROM `globalSettings` '
            . "WHERE `settingKey` = 'FOG_VIEW_DEFAULT_SCREEN'"
        )
        ->fetchColumn();
    if ((string)$got !== $expected) {
        $fails[] = sprintf(
            '%s: value was %s and should be %s after the schema runs, but is %s',
            $label,
            $before === null ? '(absent)' : $before,
            $expected,
            var_export($got, true)
        );
    }
    // The description is ours and must be brought up to date wherever the row
    // exists at all -- the old wording documents behavior that is gone.
    $desc = (string)$pdo
        ->query(
            'SELECT `settingDesc` FROM `globalSettings` '
            . "WHERE `settingKey` = 'FOG_VIEW_DEFAULT_SCREEN'"
        )
        ->fetchColumn();
    if (strpos($desc, 'how many rows') === false) {
        $fails[] = $label . ': the description was not brought up to date ('
            . substr($desc, 0, 60) . ')';
    }
}
$pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $db));

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
printf(
    "PASS: FOG_VIEW_DEFAULT_SCREEN is a row count, and the repair fixed %d "
    . "cases without touching an admin's own choice\n",
    count($cases)
);
exit(0);
