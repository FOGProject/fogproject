<?php
/**
 * The task log tab has to line up with the endpoint that feeds it.
 *
 * taskLog has been written since 1.2 and displayed never, so the columns,
 * the table id and the filter values had no existing consumer to keep them
 * honest. All three fail the same silent way: DataTables asks for a key the
 * JSON does not carry and draws a blank column, or a filter radio posts a
 * value the endpoint's switch does not know and quietly falls back to the
 * default -- a pane that looks like it is working and is showing the wrong
 * rows.
 *
 * Usage: php tests/task-log-view.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$page = file_get_contents($web . '/src/Pages/TaskManagement.php');
$js = file_get_contents(
    $web . '/management/js/fog/task/fog.task.list.js'
);

$fails = [];

// ------------------------------------------------------------- the tab

if (!preg_match("#'id' => 'logs'#", $page)) {
    $fails[] = 'Task Management has no logs tab, so nothing shows taskLog';
}
if (!preg_match('#logs:\s*\{[^}]*build:\s*buildLogs#s', $js)) {
    $fails[] = 'the logs pane is not in the JS pane registry, so the tab'
        . ' renders an empty table that never initializes';
}

// The table id is the only thing tying the rendered markup to the builder.
preg_match("#render\(\s*12,\s*'([a-z-]+)'\s*\)#", $page, $rendered);
$tableId = '';
foreach (['task-logs-table'] as $expected) {
    if (false === strpos($page, "'" . $expected . "'")
        || false === strpos($js, '#' . $expected)
    ) {
        $fails[] = "the logs table id '$expected' is not used by both the pane"
            . ' and its builder, so the grid never binds';
    }
    $tableId = $expected;
}

// ---------------------------------------------------------- the columns

// Every key the builder asks DataTables for has to be a `dt` name the
// endpoint emits, or that column is silently blank.
preg_match('#function buildLogs\(.*?\n  \}#s', $js, $builder);
preg_match_all("#\{data: '([^']+)'\}#", $builder[0] ?? '', $wanted);
preg_match(
    '#public function getTaskLogs\(\).*?\n    \}#s',
    $page,
    $endpoint
);
preg_match_all("#'dt' => '([^']+)'#", $endpoint[0] ?? '', $emitted);
$have = array_flip($emitted[1]);
if (count($wanted[1]) < 1 || count($emitted[1]) < 1) {
    $fails[] = 'could not read the logs builder or its endpoint; the column'
        . ' contract is unchecked';
}
foreach ($wanted[1] as $key) {
    if (!isset($have[$key])) {
        $fails[] = "the logs grid asks for '$key', which getTaskLogs() does"
            . ' not emit, so that column draws blank';
    }
}
// The render callbacks reach for these off the row rather than through
// `data`, so they are just as load-bearing and just as silent when missing.
foreach (['hostid', 'tasktypeicon', 'taskstateicon'] as $key) {
    if (false === strpos($builder[0] ?? '', $key)) {
        continue;
    }
    if (!isset($have[$key])) {
        $fails[] = "a logs render callback reads row.$key, which getTaskLogs()"
            . ' does not emit';
    }
}

// ---------------------------------------------------------- the filters

// Every radio the pane offers has to be a case the endpoint handles.
preg_match('#private function _logsPane\(\).*?\n    \}#s', $page, $pane);
preg_match_all("#'([a-z]+)' => _\(#", $pane[0] ?? '', $offered);
preg_match_all("#case '([a-z]+)':#", $endpoint[0] ?? '', $handled);
$handledSet = array_flip($handled[1]);
// 'reports' is the default arm, so it is deliberately not a case.
$handledSet['reports'] = true;
foreach ($offered[1] as $value) {
    if (!isset($handledSet[$value])) {
        $fails[] = "the logs pane offers a '$value' filter that getTaskLogs()"
            . ' does not handle, so picking it silently shows the default';
    }
}
if (count($offered[1]) < 2) {
    $fails[] = 'could not read the logs filter list; the filter contract is'
        . ' unchecked';
}

// The default must be the reports, not everything: state rows outnumber
// them several-fold and would bury the thing the tab exists to show.
if (!preg_match("#value=' \. \\\$value \. '\"'\s*\.\s*' autocomplete=\"off\"'\s*\.\s*\(\\\$value == 'reports' \? ' checked' : ''\)#s", $pane[0] ?? '')
    && false === strpos($pane[0] ?? '', "\$value == 'reports' ? ' checked'")
) {
    $fails[] = 'the logs pane does not default to reports, so FOS reports are'
        . ' buried under one state row per task transition';
}
if (!preg_match('#default:\s*\$types = \$reports;#', $endpoint[0] ?? '')) {
    $fails[] = 'getTaskLogs() does not default to reports, so the pane and the'
        . ' endpoint disagree about what is selected on arrival';
}

// ------------------------------------------------------------ the typing

// A column DEFAULT does not type these rows: FOGController::save() writes
// every declared field, so a writer that sets no type stores ''. The model
// has to supply it, or every state row written after schema 338 is missing
// from the 'state' filter -- which is the one view built to show them.
$model = file_get_contents($web . '/src/Items/TaskLog.php');
if (!preg_match(
    '#__construct.*?get\(\'type\'\).*?set\(\'type\', self::TYPE_STATE\)#s',
    $model
)) {
    $fails[] = 'TaskLog does not default its own type, so every row written by'
        . ' a caller that sets none stores an empty string and disappears from'
        . ' the state filter';
}
$schema = file_get_contents($web . '/commons/schema.php');
if (!preg_match(
    "#UPDATE `taskLog`.*?SET `logType` = 'state'.*?WHERE `logType` = ''#s",
    $schema
)) {
    $fails[] = 'no schema step retypes the rows written untyped before the'
        . ' model was fixed, so they stay invisible to the state filter';
}

// ------------------------------------------------------------- the modal

// The message column truncates, and a FOS report's value is its full text --
// the script it came from and the arguments it was passed.
if (false === strpos($page, "'task-log-modal'")) {
    $fails[] = 'the logs pane has no detail modal, so a truncated message'
        . ' cannot be read in full';
}
if (false === strpos($js, "#task-log-modal")
    || false === strpos($js, "#task-logs-table tbody tr")
) {
    $fails[] = 'nothing opens the log detail modal from a row, so the markup'
        . ' is emitted and unreachable';
}
// Every column in this grid renders through $.escapeHtml, and the message
// column is the one that must: DataTables writes cell content with
// innerHTML, and this column alone is fed by taskerror.class.php -- an
// endpoint FOS reaches without authenticating. A bare `{data: 'logtext'}`
// with no render is a stored-XSS sink an unauthenticated caller can fill.
// Comments stripped first: the prose above this columnDef names the very
// thing being looked for, and would satisfy the search on its own.
// Scoped to buildLogs() FIRST. This file builds four grids and `targets: 5`
// occurs in more than one of them -- searching the whole file found another
// pane's column and reported on that instead, which is a check that passes
// while looking at the wrong thing.
//
// Comments stripped too: the prose above this columnDef names the very thing
// being looked for, and would satisfy the search on its own.
$jsBare = preg_replace('#^\s*//.*$#m', '', $js);
preg_match('#function buildLogs\(.*?function showLogDetail\(#s', $jsBare, $lb);
$logsFn = $lb[0] ?? '';
$msgCol = '' === $logsFn ? false : strpos($logsFn, 'targets: 5');
// Bounded below by the PREVIOUS `targets:`, so the window cannot reach into
// the neighboring columnDef. A fixed character count did: deleting the
// render outright still passed, on column 4's escapeHtml.
$prevCol = false === $msgCol
    ? false
    : strrpos(substr($logsFn, 0, $msgCol), 'targets:');
if (false === $msgCol || false === $prevCol) {
    $fails[] = 'buildLogs() has no column 5, so the message column this'
        . ' checks has moved and the check no longer sees its subject';
} elseif (false === strpos(
    substr($logsFn, $prevCol, $msgCol - $prevCol),
    '$.escapeHtml'
)) {
    $fails[] = 'the logs grid message column does not escape its data, so a'
        . ' report from an unauthenticated caller renders as HTML in an'
        . " administrator's browser";
}
// The modal is where the stored line breaks are meant to show. Bootstrap's
// .text-wrap is `white-space: normal !important`, which overrides a <pre>
// and collapses them -- so its absence here is load-bearing.
if (!preg_match('#<pre class="mb-0"[^>]*white-space:pre-wrap#', $js)) {
    $fails[] = 'the modal does not render the message with pre-wrap, so the'
        . ' line breaks the report is now stored with are collapsed and the'
        . ' trace reads as one paragraph';
}
if (preg_match('#<pre[^>]*text-wrap#', $js)) {
    $fails[] = 'the modal still carries .text-wrap, whose'
        . ' `white-space: normal !important` overrides the <pre>';
}
if (false === strpos($js, "closest('a').length")) {
    $fails[] = 'the row click does not defer to the links inside it, so'
        . ' clicking through to a host opens the modal instead';
}
// Filled, not merely referenced: dropping the write leaves the modal showing
// whatever the last click put there, which reads as the wrong row's detail
// rather than as a bug.
if (false === strpos($js, '$(\'#task-log-detail\')')
    || false === strpos($js, '$dl.html(')
) {
    $fails[] = 'the modal is opened without being filled, so it shows the'
        . ' previous row (or nothing) whatever was clicked';
}

if ($fails) {
    echo 'FAIL: ' . count($fails) . " problem(s):\n";
    foreach ($fails as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "ok: the task log tab and its endpoint agree\n";
exit(0);
