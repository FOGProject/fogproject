<?php
/**
 * The Imaging Report is wired to ImagingStats, to the panel helpers, and to
 * the right gate.
 *
 * ADR 0030's first report. Everything this pins fails SILENTLY:
 *
 *   - a report is discovered by FILENAME. lib/reports/*.report.php becomes a
 *     menu entry with underscores turned into spaces, so the file name, the
 *     class name, the `f` parameter the JS switches on and the REPORT_NODES
 *     key all have to agree. Any one out of step gives a menu entry that
 *     opens an empty page, with no error anywhere.
 *   - the column keys getList() emits must match the ones the DataTables
 *     definition asks for. A mismatch renders blank cells, not an error.
 *   - the permission. Reports share the `report` node by default, which is
 *     the defect ADR 0023 opens with; this one reads taskLog and must
 *     resolve to `task`. Getting it wrong widens access silently.
 *   - the counting rules stay in ImagingStats. A page that grows its own
 *     `taskLog` query gets a chart that disagrees with the grid under it,
 *     and both look plausible.
 *   - the embedded chart payload is a JSON block the browser does not
 *     execute. An image named `</script><script>…` would otherwise close it.
 *
 * Usage: php tests/imaging-report.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('imaging-report');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';
$report = $web . '/lib/reports/imaging_report.report.php';

$t->check('the report file exists', is_readable($report));
$src = is_readable($report) ? file_get_contents($report) : '';

/*
 * Comments stripped before anything is searched for. The prose above these
 * methods names every symbol below it -- including the `taskLog` that
 * section 4 requires to be ABSENT -- so a search over the raw file is
 * satisfied by the documentation of the rule rather than by the rule.
 */
$code = '';
foreach (token_get_all($src) as $token) {
    if (is_array($token)) {
        if (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0]) {
            continue;
        }
        $code .= $token[1];
        continue;
    }
    $code .= $token;
}

/*
 * 1. The names that have to agree.
 */
$slug = 'imaging_report';
$label = str_replace('_', ' ', $slug);
$t->check(
    'the class name matches the file name, so the autoloader finds it',
    class_exists('FOG\Imaging_Report')
);
$t->check(
    'it extends ReportManagement, so it appears in the report menu at all',
    class_exists('FOG\Imaging_Report')
    && is_subclass_of('FOG\Imaging_Report', 'FOG\ReportManagement')
);
$js = file_get_contents($web . '/management/js/fog/report/fog.report.file.js');
$t->check(
    "the JS switches on '$label', which is what the filename decodes to",
    false !== strpos($js, "case '" . $label . "':")
);
$page = file_get_contents($web . '/lib/pages/reportmanagement.page.php');
$t->check(
    'the menu label is registered for xgettext',
    false !== strpos($page, "_('Imaging Report');")
);

/*
 * 2. The columns.
 */
$wanted = [];
$at = strpos($js, "case '" . $label . "':");
if (false !== $at) {
    $block = substr($js, $at, strpos($js, 'break;', $at) - $at);
    if (preg_match_all("/\{data: '([a-zA-Z]+)'/", $block, $m)) {
        $wanted = $m[1];
    }
}
$t->check('the JS names its columns', count($wanted) > 0);
foreach ($wanted as $col) {
    $t->check(
        "getList() emits the '$col' key the grid asks for",
        false !== strpos($code, "'" . $col . "' =>")
    );
}
$t->check(
    'the table id in the JS is the one the page renders',
    false !== strpos($js, '#imaging-table')
    && false !== strpos($code, "'imaging-table'")
);
$t->check(
    'the header row has one cell per column',
    count($wanted) === substr_count(
        substr($code, strpos($code, '$this->headerData'), 400),
        '_('
    )
);
$t->check(
    'every column escapes -- taskLog holds names people typed',
    count($wanted) === substr_count(
        (string)($block ?? ''),
        '$.fn.dataTable.render.text()'
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
 * 4. The counting rules did not come back here.
 *
 * ImagingStats exists because `taskLog` records one row per state
 * TRANSITION, so counting it needs three rules that are wrong in three
 * different quiet ways if restated. A page holding its own query would give
 * a grid that disagrees with the chart above it (ADR 0030 decision 3).
 */
$t->check(
    'the page has no taskLog query of its own',
    false === strpos($code, '`taskLog`')
);
foreach (['totals(', 'runsPerDay(', 'runsByImage(', 'runs('] as $call) {
    $t->check(
        "it reads ImagingStats::$call)",
        false !== strpos($code, 'ImagingStats::' . $call)
    );
}

/*
 * 5. The window, and this report's own default.
 *
 * A month rather than Run History's day, because a trend needs enough days
 * to be a trend. The default is the report's; the parsing is shared.
 */
$t->check(
    'the window is read through the shared parser',
    false !== strpos($code, 'ReportWindow::fromRequest(self::DEFAULT_WINDOW)')
);
$t->check(
    'this report defaults to a month',
    '-30 days' === constant('FOG\Imaging_Report::DEFAULT_WINDOW')
);
$t->check(
    'and does not re-implement the malformed-bound rule',
    false === strpos($code, 'strtotime($v)')
);

/*
 * 6. `Finished` is only a finish.
 *
 * The fold's `ended` is MAX(createTime) over the run's rows, which is a
 * finish time only once the run has finished. For one still in progress it
 * is the last transition, and printing that under "Finished" says a deploy
 * completed while it is still copying. ADR 0022 decision 2: state is
 * authoritative for WHAT, timestamps for WHEN.
 */
$t->check(
    'the finish time is gated on a terminal state',
    1 === preg_match(
        "/'ended'\s*=>\s*in_array\(\s*\\\$stateID,\s*\\\$terminal,\s*true\s*\)/",
        $code
    )
);
foreach (['getCompleteState', 'getCancelledState', 'getFailedState'] as $st) {
    $t->check(
        "and `$st` counts as terminal",
        false !== strpos($code, 'TaskState::' . $st . '()')
    );
}

/*
 * 7. The chart payload is embedded, and cannot become script.
 *
 * Behavioral, not a grep: the helper is called with an image name that would
 * close the JSON block if it were written raw, and the rendered panel is
 * searched for the escape. json_encode's JSON_HEX_TAG is what stops it, and
 * an assertion on the flag alone would pass if the block stopped being a
 * script element in the first place.
 */
$panel = \FOG\Base\FOGPage::renderChartPanel(
    'test-panel',
    'Title',
    [
        'type' => 'doughnut',
        'labels' => ['</script><script>alert(1)</script>'],
        'series' => [['label' => 'Runs', 'data' => [1]]]
    ]
);
$t->check(
    'the panel carries its own data block',
    false !== strpos($panel, 'id="test-panel-data"')
);
$t->check(
    'a hostile label cannot close the data block',
    1 === substr_count($panel, '</script>')
);
$t->check(
    'and reaches the browser with no raw angle bracket in the payload',
    false === strpos(
        substr($panel, strpos($panel, '-data">')),
        '<script>'
    )
);
$t->check(
    'the payload is still readable JSON',
    is_array(
        json_decode(
            substr(
                $panel,
                strpos($panel, '-data">') + 7,
                strpos($panel, '</script>') - strpos($panel, '-data">') - 7
            ),
            true
        )
    )
);
$t->check(
    'the chart is drawn from that block rather than fetched',
    false === strpos($code, 'sub=panels')
    && false !== strpos(
        file_get_contents(
            $web . '/management/js/fog/report/fog.report.panels.js'
        ),
        "document.getElementById(id + '-data')"
    )
);

/*
 * 8. Chart.js is actually on the page, and before the file that uses it.
 *
 * A missing library here is a silent blank panel: fog.report.panels.js
 * returns early when `Chart` is undefined, exactly so a report with no
 * chart is not a console error -- which also means a broken load looks
 * identical to a report with nothing to draw.
 */
$pagesrc = file_get_contents($web . '/src/Base/Page.php');
$chartAt = strpos($pagesrc, "'js/Chart/chart.umd.min.js'", strpos($pagesrc, "'report' === \$node"));
$panelAt = strpos($pagesrc, "'js/fog/report/fog.report.panels.js'");
$t->check(
    'the report node loads Chart.js',
    false !== $chartAt
);
$t->check(
    'and the panel renderer after it',
    false !== $panelAt && $chartAt < $panelAt
);

$t->finish();
