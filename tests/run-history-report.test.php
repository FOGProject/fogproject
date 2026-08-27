<?php
/**
 * The Run History report is wired to ActivityWindow, and to the right gate.
 *
 * ADR 0022 decision 4 shipped ActivityWindow with no caller, which the ADR
 * itself flagged as the thing most likely to let it rot. This is the caller,
 * and the parts of the wiring that fail SILENTLY are what this pins:
 *
 *   - the report is discovered by FILENAME. lib/reports/*.report.php becomes
 *     a menu entry with underscores turned into spaces, so the file name,
 *     the class name, the `f` parameter the JS switches on and the
 *     REPORT_NODES key all have to agree. Any one of them out of step gives
 *     a menu entry that opens an empty page, with no error anywhere.
 *   - the column keys getList() emits must match the ones the DataTables
 *     definition asks for. A mismatch renders blank cells, not an error.
 *   - the permission. Reports share the `report` node by default, which is
 *     the defect ADR 0023 opens with; this one is task activity and must
 *     resolve to `task`. Getting that wrong widens access silently.
 *   - the menu label must be registered for xgettext, because the labels are
 *     built from filenames at runtime and a runtime-built msgid never
 *     reaches the catalogue.
 *
 * What this canNOT check is that getList() RUNS. It did not, the first time:
 * `TaskStateManager->find()` does not exist in 1.6 and a call to it is a
 * fatal that looks entirely plausible in a diff. That, and the timezone bug
 * underneath it, are covered by
 * /home/telliott/labs/adr0020/prove_runhistory.php against a lab database.
 *
 * Usage: php tests/run-history-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('run-history');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';
$report = $web . '/lib/reports/run_history.report.php';

$t->check('the report file exists', is_readable($report));
$src = is_readable($report) ? file_get_contents($report) : '';

/*
 * 1. The four names that have to agree.
 *
 * basename minus '.report.php', underscores to spaces, is the menu label
 * and the `f` parameter; the same string underscored is the REPORT_NODES
 * key; the class name matches the file for the autoloader.
 */
$slug = 'run_history';
$label = str_replace('_', ' ', $slug);
$t->check(
    'the class name matches the file name, so the autoloader finds it',
    class_exists('FOG\Run_History')
);
$t->check(
    'it extends ReportManagement, so it appears in the report menu at all',
    class_exists('FOG\Run_History')
    && is_subclass_of('FOG\Run_History', 'FOG\ReportManagement')
);
$js = file_get_contents($web . '/management/js/fog/report/fog.report.file.js');
$t->check(
    "the JS switches on '$label', which is what the filename decodes to",
    false !== strpos($js, "case '" . $label . "':")
);

/*
 * 2. The columns. getList() emits an associative row; the JS names each key
 *    it wants. Both lists are read out of the source rather than assumed,
 *    so adding a column to one and not the other fails here.
 */
$wanted = [];
$at = strpos($js, "case '" . $label . "':");
if (false !== $at) {
    $block = substr($js, $at, strpos($js, 'break;', $at) - $at);
    if (preg_match_all("/\{data: '([a-zA-Z]+)'\}/", $block, $m)) {
        $wanted = $m[1];
    }
}
$t->check('the JS names its columns', count($wanted) > 0);
foreach ($wanted as $col) {
    $t->check(
        "getList() emits the '$col' key the grid asks for",
        false !== strpos($src, "'" . $col . "' =>")
    );
}
$t->check(
    'the table id in the JS is the one the page renders',
    false !== strpos($js, "#runhistory-table")
    && false !== strpos($src, "'runhistory-table'")
);
$t->check(
    'the header row has one cell per column',
    count($wanted) === substr_count(
        substr($src, strpos($src, '$this->headerData'), 400),
        '_('
    )
);

/*
 * 3. The gate. This is the one that fails dangerously rather than visibly.
 */
$nodes = constant('FOG\Auth\Authorization::REPORT_NODES');
$t->check(
    'the report is listed in REPORT_NODES rather than inheriting `report`',
    array_key_exists($slug, (array)$nodes)
);
$t->check(
    'and it resolves to `task`, where Task Management gates the same rows',
    'task' === ($nodes[$slug] ?? null)
);

/*
 * 4. The menu label reaches the catalogue. The labels are built from
 *    filenames at runtime, so xgettext sees nothing unless the literal is
 *    written out in _reportNamesForTranslation().
 */
$page = file_get_contents($web . '/lib/pages/reportmanagement.page.php');
$t->check(
    "the menu label is registered for xgettext",
    false !== strpos($page, "_('Run History');")
);

/*
 * 5. It really does read ActivityWindow, and reads it the bounded way.
 */
$t->check(
    'getList() goes through ActivityWindow::between()',
    false !== strpos($src, 'ActivityWindow::between(')
);
$t->check(
    'the source list is whitelisted against ActivityWindow::sources()',
    false !== strpos($src, 'ActivityWindow::sources()')
);
$t->check(
    'no per-row object lookup: hosts are resolved in one query',
    false !== strpos($src, 'WHERE `hostID` IN (')
    && false === strpos($src, "getClass('Host', ")
);
$t->check(
    'the host id list is cast to int before it is interpolated',
    false !== strpos($src, "array_map('intval', \$hostIDs)")
);

/*
 * 6. The window is built on FOG's clock.
 *
 * The columns compared are stamped by save() through niceDate(), which uses
 * the configured timezone. A bound built with PHP's date() is silently
 * offset by however far apart the two are -- BETWEEN matches a shifted
 * window and answers a question nobody asked. Found in the lab, where the
 * two clocks were five hours apart and a task created seconds earlier did
 * not appear in a window ending "now".
 */
$t->check(
    'the window uses niceDate(), FOG\'s clock',
    false !== strpos($src, 'self::niceDate()')
);
$t->check(
    'and does NOT fall back to PHP\'s date()/time() for the bounds',
    false === strpos($src, 'return [date($fmt')
    && false === strpos($src, '$e = time();')
);
$t->check(
    'a malformed bound is dropped rather than passed to BETWEEN',
    false !== strpos($src, 'false === strtotime($v)')
);

$t->finish();
