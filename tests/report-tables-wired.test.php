<?php
/**
 * Every core report table has a case in fog.report.file.js.
 *
 * A report's PHP renders an empty table. The rows come only when
 * fog.report.file.js has a case for the report that points DataTables at
 * getList. Directory Membership, Printer Deployment and User Sessions
 * shipped with no case. Each page showed its headers and no rows. It sent
 * no request and logged no error.
 *
 * WHAT THIS PINS: for each class in src/Reports that renders an
 * `<id>-table`, the JS has `case '<report key>':`, and that case wires
 * `$('#<id>')`. Where the PHP builds each row as one array literal, the
 * case's columns are its keys, and every column escapes. A column that
 * entityLink() builds on the server is already escaped, so it may be raw.
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

foreach (glob($web . '/src/Reports/*.php') ?: [] as $file) {
    $name = basename($file);
    $src = (string)file_get_contents($file);
    $hasTable = (bool)preg_match(
        "/render\(\s*\d+\s*,\s*'([a-z-]+-table)'/",
        $src,
        $m
    );
    // Every shipped report renders one table. A file that renders none
    // fails here, so the scan cannot pass by finding nothing.
    $t->check($name . ' renders a report table', $hasTable);
    if (!$hasTable) {
        continue;
    }
    $table = $m[1];
    // The key the page decodes from f: the file name, lower case, spaces.
    $key = strtolower(str_replace('_', ' ', basename($file, '.php')));
    $start = strpos($js, "case '" . $key . "':");
    $block = false === $start
        ? ''
        : substr($js, $start, (int)strpos($js, 'break;', $start) - $start);
    $t->check(
        $name . " has case '" . $key . "' that wires #" . $table,
        false !== strpos($block, "$('#" . $table . "')")
    );

    // The columns: the row keys the PHP emits. DataTables reads a cell by
    // its data key, so the order can differ. Storage Report puts its sort
    // key `bytes` last. A key on one side only is a blank column or a lost
    // value. Only a report that builds each row as one `$data[] = [...]`
    // literal is compared. The others build rows in other shapes.
    if (!preg_match('/\$data\[\] = \[(.*?)\n\s*\];/s', $src, $rows)) {
        continue;
    }
    preg_match_all("/^\s*'(\w+)' =>/m", $rows[1], $phpKeys);
    preg_match_all("/\{data: '(\w+)'([^}]*)\}/", $block, $jsCols);
    $php = $phpKeys[1];
    $cols = $jsCols[1];
    sort($php);
    sort($cols);
    $t->check(
        $name . ' columns are its row keys (php: ' . implode(',', $php)
            . ' js: ' . implode(',', $cols) . ')',
        $php === $cols
    );
    // A cell that entityLink() builds is markup by design. The helper
    // escapes the name and casts the id on the server (FOGBase), so the
    // grid must render it as HTML. Client Authentication's hostLink is one.
    preg_match_all("/^\s*'(\w+)' => \w+::entityLink\(/m", $rows[1], $built);
    $raw = [];
    foreach ($jsCols[1] as $i => $col) {
        if (false === strpos($jsCols[2][$i], 'render.text()')
            && !in_array($col, $built[1], true)
        ) {
            $raw[] = $col;
        }
    }
    $t->check(
        $name . ' escapes every column (raw: ' . implode(',', $raw) . ')',
        [] === $raw
    );
}

$t->finish();
