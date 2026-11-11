<?php
/**
 * Every core report table has a case in fog.report.file.js.
 *
 * A report's PHP renders an empty table. The rows come only when
 * fog.report.file.js has a case for the report that points DataTables at
 * getList. Directory Membership, Printer Deployment and User Sessions
 * shipped with no case, so each page showed its headers and no rows. No
 * error, no request, nothing in the console.
 *
 * WHAT THIS PINS: for each class in src/Reports that renders an
 * `<id>-table`, the JS has `case '<report key>':`, and that case wires
 * `$('#<id>')`.
 *
 * DB-free: it reads the files.
 *
 * Usage: php tests/report-tables-wired.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('report-tables-wired');

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';
$js = (string)file_get_contents(
    $web . '/management/js/fog/report/fog.report.file.js'
);
$t->check('the report JS is readable', '' !== $js);

$seen = 0;
foreach (glob($web . '/src/Reports/*.php') ?: [] as $file) {
    $src = (string)file_get_contents($file);
    if (!preg_match("/render\(\s*\d+\s*,\s*'([a-z-]+-table)'/", $src, $m)) {
        continue;
    }
    $seen++;
    $table = $m[1];
    // The key the page decodes from f: the file name, lower case, spaces.
    $key = strtolower(str_replace('_', ' ', basename($file, '.php')));
    $start = strpos($js, "case '" . $key . "':");
    $block = false === $start
        ? ''
        : substr($js, $start, (int)strpos($js, 'break;', $start) - $start);
    $t->check(
        basename($file) . " has case '" . $key . "' that wires #" . $table,
        false !== strpos($block, "$('#" . $table . "')")
    );
}
$t->check('the scan found the report tables', $seen >= 15);

$t->finish();
