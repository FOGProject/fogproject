<?php
/**
 * A report's menu label is data, not its file name.
 *
 * The report menu was built as `_(ucwords(strtolower($filename)))`, which
 * has two failures worth a gate. It could not spell anything a file name
 * cannot spell -- "Pending Mac List", "Hosts And Users" -- and it disagreed
 * with the page it opened: seven of the shipped reports rendered a heading
 * that was not their menu entry, so the same screen had two names.
 *
 * ReportManagement::reportTitles() is now the ONE definition. The sidebar
 * reads it and each report reads its own $this->title back out of it. What
 * this pins is the pair of holes that leaves:
 *
 *   - a report on disk with no row in the map falls back to the old
 *     derivation, which is right for an uploaded or plugin report and wrong
 *     for a shipped one. Nothing would break; the label would just quietly
 *     be ucwords() again.
 *   - a row in the map naming a report that no longer exists is a label
 *     nobody sees, and the msgid stays in the catalog forever.
 *
 * Also pins the fallback itself, because that is what keeps every report
 * outside core working exactly as it did before the map existed.
 *
 * Usage: php tests/report-titles.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('report-titles');

use FOG\ReportManagement;

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$titles = ReportManagement::reportTitles();

/*
 * 1. The map and the directory describe the same set of reports.
 */
$onDisk = [];
foreach ((array) glob($web . '/lib/reports/*.report.php') as $file) {
    $onDisk[] = str_replace(
        '_',
        ' ',
        basename($file, '.report.php')
    );
}
sort($onDisk);
$t->check(
    'there are reports on disk to check at all',
    count($onDisk) > 5
);
foreach ($onDisk as $report) {
    $t->check(
        "$report: has a label in reportTitles()",
        isset($titles[$report]) && '' !== trim((string) $titles[$report])
    );
}
foreach (array_keys($titles) as $report) {
    $t->check(
        "$report: the label names a report that is on disk",
        in_array($report, $onDisk, true)
    );
}

/*
 * 2. No shipped report hardcodes its own title beside the map. Two
 *    definitions is the state this replaced, and it is invisible until
 *    somebody notices the sidebar and the heading disagree.
 */
foreach ((array) glob($web . '/lib/reports/*.report.php') as $file) {
    $src = (string) file_get_contents($file);
    $name = basename($file);
    $t->check(
        "$name: takes its title from reportTitle()",
        false !== strpos($src, '$this->title = self::reportTitle();')
    );
    $t->check(
        "$name: does not also set a literal title",
        1 !== preg_match('/\$this->title\s*=\s*_\(/', $src)
    );
}

/*
 * 3. The sidebar asks the map. Checked by driving titleFor(), not by
 *    reading the menu builder: a grep for the call site passes with the
 *    call made and its result thrown away.
 */
$t->check(
    'titleFor() answers a mapped report with its label',
    ReportManagement::titleFor('pending mac list')
        === $titles['pending mac list']
);
$t->check(
    'titleFor() is case insensitive, as the sub resolver is',
    ReportManagement::titleFor('PENDING MAC LIST')
        === $titles['pending mac list']
);
$t->check(
    'an unmapped report still gets the old derivation, not an empty entry',
    'Some Plugin Report' === ReportManagement::titleFor('some plugin report')
);

/*
 * 4. The labels the map exists for. These are the ones a file name could
 *    not spell, and the ones the old derivation got wrong -- so they are
 *    the assertion that the map is doing its job rather than merely being
 *    present.
 */
$t->check(
    'the MAC report says MAC rather than Mac',
    false !== strpos((string) $titles['pending mac list'], 'MAC')
);
$t->check(
    'no shipped label is just ucwords() of its own file name',
    (string) $titles['hosts and users'] !== 'Hosts And Users'
    && (string) $titles['history report'] !== 'History Report'
);

/*
 * 5. Host List is gone, and gone from both sides. The report duplicated
 *    Host Management's own grid and, because it was not in REPORT_NODES,
 *    served every host's name, primary MAC, image and deploy date to a
 *    report.view holder -- the ADR 0023 defect, in the one screen nobody
 *    had looked at.
 */
$t->check(
    'the Host List report is gone',
    !file_exists($web . '/lib/reports/host_list.report.php')
);
$js = (string) file_get_contents(
    $web . '/management/js/fog/report/fog.report.file.js'
);
$t->check(
    'and its table wiring went with it',
    false === strpos($js, "case 'host list':")
    && false === strpos($js, 'hostlist-table')
);

$t->finish();
