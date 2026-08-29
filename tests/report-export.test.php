<?php
/**
 * A report's CSV export is the whole report, and says so when it is not.
 *
 * THE BUG THIS GUARDS. The report toolbar's Copy/CSV/Excel/Print buttons are
 * DataTables' own and can only see rows the browser is holding. On the eight
 * reports that page server side that is ONE PAGE: click CSV on User Logins
 * with fifty thousand rows behind it and you get twenty-five, in a file that
 * looks exactly like a complete one. FOG already solved this once -- the
 * management export screen carries "CSV (All)" and explains the difference in
 * prose -- and reports had the explanation without the button.
 *
 * The failure mode is silence in both directions, which is why each half is
 * pinned separately:
 *
 *   - reportRows() is the seam. A report that keeps its own getList() never
 *     reaches exportAll(), so the button downloads an empty file rather than
 *     erroring. Nothing in the UI would say so.
 *   - the export must POST. Route::listem() reads its DataTables request from
 *     php://input and from nothing else, so a GET export silently drops the
 *     search box and hands back the unfiltered table -- which still looks
 *     like a successful export.
 *   - length=-1 is bounded server side (LIMIT 0, MAX_ROWS). A capped CSV is
 *     indistinguishable from a complete one once it is on disk, so the cap
 *     goes in the file NAME, the only channel a download has left.
 *
 * Usage: php tests/report-export.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('report-export');
// A connection, because reportTitles() now fires REPORT_TITLE_DATA and
// HookManager::processEvent() reads (and records) the event name.
FogTestHarness::fakeDb();

use FOG\ReportManagement;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

/*
 * 1. The seam. Every shipped report fills reportRows() and none of them
 *    keeps a getList() of its own -- the two together are what make the
 *    grid and the export the same query.
 */
$reports = [];
foreach ((array) glob($web . '/lib/reports/*.report.php') as $file) {
    $class = 'FOG\\' . implode(
        '_',
        array_map('ucfirst', explode('_', basename($file, '.report.php')))
    );
    $t->check(
        basename($file) . ': its class loads',
        class_exists($class)
    );
    if (!class_exists($class)) {
        continue;
    }
    $reports[] = $class;
    // Through the reflection's own name, not the one built above: PHP
    // resolves class names case insensitively, so Pending_MAC_List answers
    // to Pending_Mac_List and a string comparison against the derived name
    // fails on a class that is perfectly present.
    $rc = new \ReflectionClass($class);
    $real = $rc->getName();
    $t->check(
        "$real: implements reportRows()",
        $rc->hasMethod('reportRows')
        && $rc->getMethod('reportRows')->getDeclaringClass()->getName() === $real
    );
    $t->check(
        "$real: does NOT override getList(), so exportAll() sees the rows",
        !$rc->hasMethod('getList')
        || $rc->getMethod('getList')->getDeclaringClass()->getName() !== $real
    );
}
$t->check('there were reports to check', count($reports) > 5);

/*
 * 2. exportAll() is a READ. Reports resolve a permission per report through
 *    REPORT_NODES, and an export that resolved to `edit` would be denied to
 *    exactly the people the report is for.
 */
$m = new \ReflectionMethod('FOG\Auth\Authorization', '_subToAction');
$m->setAccessible(true);
$t->check(
    'exportall resolves to a view action, on POST as well as GET',
    'view' === $m->invoke(null, 'exportall', true)
    && 'view' === $m->invoke(null, 'exportall', false)
);
// The per-report narrowing itself is pinned by tests/lib/report-wiring.php,
// which reads REPORT_NODES through a variable; repeating it here with a
// literal key only teaches PHPStan that the answer is a constant.

/*
 * 3. The cell writer. A grid column can carry markup -- the host name is a
 *    link, the MAC carries a vendor icon -- because the browser renders it.
 *    A spreadsheet does not, so the raw value would put `<a href=...>` in
 *    the cell. Driven, not grepped.
 */
$cell = new \ReflectionMethod('FOG\ReportManagement', '_exportCell');
$cell->setAccessible(true);
$t->check(
    'a link column exports as the text a person was reading',
    '(1) - lab-07' === $cell->invoke(
        null,
        '<a href="../management/index.php?node=host&sub=edit&id=1">'
        . '(1) - lab-07</a>'
    )
);
$t->check(
    'entities inside markup come back as characters',
    'R&D box' === $cell->invoke(null, '<span>R&amp;D box</span>')
);
$t->check(
    'a value with no markup is passed through verbatim, entities and all',
    'R&amp;D box' === $cell->invoke(null, 'R&amp;D box')
);
$t->check(
    'a plain value is untouched',
    '2026-08-29 10:00:00' === $cell->invoke(null, '2026-08-29 10:00:00')
);
$t->check(
    'a value that merely CONTAINS a bracket is not mangled',
    '5 < 10' === $cell->invoke(null, '5 < 10')
);
$t->check(
    'a null cell is an empty string, not the word null',
    '' === $cell->invoke(null, null)
);

/*
 * 3b. The column choice. This is the rule about what LEAVES the server, so
 *     it is driven rather than read: the names come from a request, and a
 *     request can name a column Route::listem() deliberately stripped.
 */
$pick = function (array $present, array $wanted, array $labels) {
    return ReportManagement::pickExportColumns($present, $wanted, $labels);
};
$t->check(
    'the requested columns come back in the requested order, with headings',
    [['b', 'a'], ['Bee', 'Ay']]
        === $pick(['a', 'b', 'c'], ['b', 'a'], ['Bee', 'Ay'])
);
$t->check(
    'a column the rows do not carry is DROPPED, not emitted blank',
    [['username'], ['User']]
        === $pick(
            ['username', 'hostname'],
            ['username', 'password', 'token'],
            ['User', 'Password', 'Token']
        )
);
$t->check(
    'asking for nothing gets everything the rows carry',
    [['a', 'b'], ['a', 'b']] === $pick(['a', 'b'], [], [])
);
$t->check(
    'an empty result still gets its header row, so the file is readable',
    [['username'], ['User']] === $pick([], ['username'], ['User'])
);
$t->check(
    'a missing heading falls back to the column name',
    [['a'], ['a']] === $pick(['a'], ['a'], [])
);

/*
 * 4. The file name carries the cap. This is the whole honesty mechanism for
 *    a download: there is no page left to warn on once the browser has it.
 */
// Called on Fleet_Report, not on ReportManagement: the name comes from
// static::reportTitle(), so this also proves the late static binding that
// makes one method name thirteen different files.
$name = function (array $payload, $written) {
    return FogTestHarness::callStatic(
        'FOG\Fleet_Report',
        '_exportFilename',
        [$payload, $written]
    );
};

$complete = $name(['data' => []], 12);
$t->check(
    'a complete export is named for the report and the day',
    1 === preg_match('#^fleet-report-\d{4}-\d{2}-\d{2}\.csv$#', $complete)
);
$capped = $name(['truncated' => true, 'recordsFiltered' => 52341], 5000);
$t->check(
    'a capped export says how many rows it holds, and of how many',
    false !== strpos($capped, '-first-5000-of-52341.csv')
);
$noTotal = $name(['truncated' => true], 5000);
$t->check(
    'and says the first N alone when no true total is known',
    false !== strpos($noTotal, '-first-5000.csv')
    && false === strpos($noTotal, '-of-')
);
$t->check(
    'the name is the report label, not the class or the file',
    0 === strpos($complete, 'fleet-report-')
);

/*
 * 5. The button. Reading the source here rather than driving it, because it
 *    is browser code -- so the assertions are on the three properties whose
 *    absence is silent: the POST, the lifted cap, and the CSRF field the
 *    native submit() bypasses the usual listener for.
 */
$common = (string) file_get_contents(
    $web . '/management/js/fog/fog.common.js'
);
$at = strpos($common, 'reportCsvAllButton = {');
$t->check('the CSV (All) button exists', false !== $at);
$block = false === $at
    ? ''
    : substr($common, $at, strpos($common, 'reportFileButtons =', $at) - $at);
$t->check(
    "it posts, so listem() sees the grid's own search and sort",
    false !== strpos($block, "form.method = 'post'")
    && false !== strpos($block, "params.set('sub', 'exportAll')")
);
$t->check(
    'it lifts the page cap',
    false !== strpos($block, "body.set('length', '-1')")
);
$t->check(
    'it carries the CSRF token by hand, since native submit() fires no event',
    false !== strpos($block, "body.append('_csrf'")
);
$t->check(
    'it sends the visible columns and their headings',
    false !== strpos($block, "body.append('cols[]'")
    && false !== strpos($block, "body.append('heads[]'")
);
// Plugin report tables opt in rather than inherit. The export serves
// reportRows(), so a report still overriding getList() would answer the
// button with an empty FILE -- no error, nothing in a log, a download that
// looks like it worked. Defaulting it on would hand that to every
// third-party plugin report at once.
$t->check(
    'registerReportTable() keeps the plain toolbar unless asked',
    false !== strpos(
        $common,
        'buttons: opts.fullExport ? reportFileButtons : reportButtons,'
    )
);

/*
 * 6. Who wears it. Every core report table but one, and the exception is
 *    the one whose entire content is the secret it masks on screen: the
 *    DataTables CSV button exports the DISPLAYED value, so it writes the
 *    mask, and a full server-side export would write the keys in the clear.
 */
$js = (string) file_get_contents(
    $web . '/management/js/fog/report/fog.report.file.js'
);
$cases = [];
preg_match_all("/case '([^']+)':/", $js, $cases);
$t->check('the report JS has its cases', count($cases[1]) > 5);
foreach ($cases[1] as $case) {
    $from = strpos($js, "case '" . $case . "':");
    $body = substr($js, $from, strpos($js, 'break;', $from) - $from);
    $wants = 'product keys' !== $case;
    $has = false !== strpos($body, 'buttons: reportFileButtons,');
    $t->check(
        $wants
            ? "$case: takes the full-export toolbar"
            : "$case: keeps the masked toolbar, so the keys stay masked",
        $wants === $has
    );
}

/*
 * 7. The rollups say when they hit their cap, because every tile on those
 *    pages is computed off the capped set and so is the CSV.
 */
foreach (['FleetStats', 'StorageStats', 'AuditStats', 'ImagingStats',
    'SnapinStats'] as $stats) {
    $src = (string) file_get_contents(
        $web . '/src/Audit/' . $stats . '.php'
    );
    $t->check(
        "$stats::totals() reports whether the rows were capped",
        false !== strpos($src, "'truncated'")
    );
}
foreach (['fleet_report', 'storage_report', 'audit_report', 'imaging_report',
    'snapin_report'] as $report) {
    $src = (string) file_get_contents(
        $web . '/lib/reports/' . $report . '.report.php'
    );
    $t->check(
        "$report: shows the cap banner from the shared helper",
        false !== strpos($src, 'self::renderReportCap(')
    );
}

$t->finish();
