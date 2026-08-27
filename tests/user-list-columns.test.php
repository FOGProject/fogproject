<?php
/**
 * The user grid's header and its DataTables column list must stay aligned.
 *
 * A FOG list grid is defined in two files that know nothing about each
 * other: `$this->headerData` in the page class emits the `<th>` cells, and
 * `columns:` in `management/js/fog/<node>/fog.<node>.list.js` tells
 * DataTables which JSON field fills each one. Nothing checks that they
 * agree.
 *
 * When they disagree DataTables throws a warning dialog and renders no
 * rows at all -- so the failure is loud in a browser and completely
 * invisible to source review, to the rest of this suite, and to CI. Adding
 * a column means editing both files plus bumping FOG_BCACHE_VER, and
 * forgetting any one of the three produces a grid that looks broken for
 * reasons that have nothing to do with the data.
 *
 * WHY THIS IS NOT GENERIC. Fourteen of the eighteen list pages set
 * headerData exactly once and map cleanly to one js file by their $node,
 * so a sweep across all of them looks trivially available. It is not: task
 * and host set headerData five and seven times for different sub-grids,
 * and storagenode builds its header without _() at all, so counting _()
 * calls -- the obvious heuristic -- silently reports 1 header cell against
 * 6 columns for a page that is perfectly correct. A gate that cannot tell
 * a real mismatch from its own parse failure is worse than no gate; doing
 * this properly needs the header parsed rather than pattern-counted, which
 * is a bigger job than the column that prompted it. Pinned here for the
 * grid that changed.
 *
 * Usage: php tests/user-list-columns.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('user-list-columns');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$pageSrc = file_get_contents($web . '/lib/pages/usermanagement.page.php');
$jsSrc = file_get_contents(
    $web . '/management/js/fog/user/fog.user.list.js'
);
$sysSrc = file_get_contents($web . '/src/Base/System.php');

// Header cells, comments stripped so a `_(` inside one is not counted.
preg_match('/headerData = \[(.*?)\n\s*\];/s', $pageSrc, $m);
$headerBlock = preg_replace('#//[^\n]*#', '', (string)($m[1] ?? ''));
$headers = preg_match_all('/_\(/', $headerBlock);

preg_match('/columns: \[(.*?)\n\s*\],/s', $jsSrc, $m2);
$columnBlock = (string)($m2[1] ?? '');
preg_match_all("/\{\s*data: '([a-z]+)'/", $columnBlock, $m3);
$columns = $m3[1];

$t->check('the header block was found', $headers > 0);
$t->check('the column block was found', count($columns) > 0);
$t->check(
    sprintf(
        'header cells (%d) match DataTables columns (%d)',
        $headers,
        count($columns)
    ),
    $headers === count($columns)
);
$t->check(
    'the columns are the expected four, in order',
    ['mainlink', 'display', 'api', 'apionly'] === $columns
);
$t->check(
    'the API Only? header is present',
    false !== strpos($headerBlock, "_('API Only?')")
);
$t->check(
    'every column has a width attribute entry or an empty one',
    (bool)preg_match(
        '/attributes = \[\s*\[\],\s*\[\],\s*'
        . "\['width' => 22\],\s*\['width' => 22\]\s*\];/s",
        $pageSrc
    )
);

// The renderer has to exist, or the cell shows a raw 1/0. targets: 3 is the
// apionly column; a renderer pointed at the wrong index silently restyles
// the API? column instead and leaves this one raw.
$t->check(
    'the apionly column has a renderer bound to targets: 3',
    (bool)preg_match(
        "/render: function\(data, type, row\) \{[^}]*?apiOnly[^}]*?\}"
        . ".*?targets: 3/s",
        $jsSrc
    )
);
// Deliberately a different pair from the API? column above it: neither
// value here is a fault, so "stands out" vs "ordinary" rather than
// "good" vs "bad".
$t->check(
    'API-only is marked with a warning badge, not a danger one',
    (bool)preg_match(
        "/var apiOnly = '<span class=\"badge bg-warning\">/",
        $jsSrc
    )
    && (bool)preg_match(
        "/var interactive = '<span class=\"badge bg-secondary\">/",
        $jsSrc
    )
);
// Font Awesome 4.7.0 is what ships. An icon that is not in it renders as
// an empty box with nothing logged, so the names are checked against the
// stylesheet that is actually served rather than against a list kept here.
//
// Comments are stripped first. The first version of this check scanned the
// whole file and failed on a comment in the renderer that NAMED the icon it
// had chosen not to use -- a gate tripping on prose about itself.
$faCss = file_get_contents($web . '/management/css/font-awesome.min.css');
$jsCode = preg_replace('#//[^
]*#', '', $jsSrc);
preg_match_all('/fa fa-([a-z0-9-]+)/', $jsCode, $m5);
$missing = [];
foreach (array_unique($m5[1]) as $icon) {
    if (false === strpos($faCss, '.fa-' . $icon . ':before')) {
        $missing[] = $icon;
    }
}
$t->check(
    'every icon this grid renders exists in the bundled Font Awesome'
    . (count($missing) ? ' (missing: ' . implode(', ', $missing) . ')' : ''),
    count($missing) < 1
);

// A JS change nobody can see is the other half of this defect class.
$t->check(
    'FOG_BCACHE_VER is at least 305',
    (bool)preg_match("/define\('FOG_BCACHE_VER', (\d+)\)/", $sysSrc, $m4)
    && (int)$m4[1] >= 305
);

$t->finish();
