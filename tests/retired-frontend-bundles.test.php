<?php
/**
 * AdminLTE 2 and Bootstrap 3 are gone and must stay gone.
 *
 * FOG moved to AdminLTE 4 / Bootstrap 5, but the old bundles were left in
 * the tree. Nothing loaded them -- Page declares every stylesheet and
 * script in one place -- so they were ~1.2 MB of vendored code that shipped
 * in every release, sat in every backup, and answered every "which
 * bootstrap is this" grep with the wrong version.
 *
 * They also actively misled. bootstrap5.min.css carries
 * `sourceMappingURL=bootstrap.min.css.map`, and the map of that name in the
 * tree was BOOTSTRAP 3's -- its `sources` begin `less/normalize.less`,
 * which is the LESS build BS3 used and BS5 does not. Devtools therefore
 * resolved BS5 rules against BS3 sources.
 *
 * WHAT IS PINNED:
 *
 *  1. The retired files do not come back. A vendored asset returns by
 *     being copied in wholesale, which no review catches by reading a diff
 *     of minified CSS.
 *  2. Nothing references them. A reference without the file is a 404 on
 *     every page load; the file without a reference is what got us here.
 *  3. THE REPLACEMENTS SURVIVE. Three of these bundles are dead because
 *     something in FOG's own code took over their job, and if that code is
 *     removed the call sites break with no library to fall back to:
 *       - js/fog/datetimepicker-shim.js re-implements $.fn.datetimepicker
 *         for the four call sites that still use the BS3-era API
 *       - shade() in js/fog/dashboard/fog.dashboard.js replaces
 *         jQuery.Color().lightness() for bandwidth shading
 *       - css/datatables.min.css is a combined DataTables build that
 *         already contains the Bootstrap 5 integration, which is what made
 *         the standalone datatables.bootstrap5 files redundant
 *     Each is checked by the thing it provides, not by the file existing,
 *     so gutting the file still fails.
 *
 * Usage: php tests/retired-frontend-bundles.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('retired-frontend-bundles');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$mgmt = $web . '/management';

$retired = [
    // AdminLTE 2
    'css/AdminLTE.min.css',
    'css/adminlte-skins.min.css',
    'js/adminlte.min.js',
    // Bootstrap 3, its sourcemap and its LESS sources
    'css/bootstrap.min.css',
    'css/bootstrap.min.css.map',
    'js/bootstrap.min.js',
    'css/less',
    // Superseded by js/fog/datetimepicker-shim.js
    'css/bootstrap-datetimepicker.min.css',
    'js/bootstrap-datetimepicker.min.js',
    // Superseded by shade() in fog.dashboard.js
    'js/jquery.color.min.js',
    // Redundant with the combined datatables.min build
    'css/datatables.bootstrap5.min.css',
    'js/datatables.bootstrap5.min.js',
    // Glyphicons shipped with Bootstrap 3; no loaded stylesheet defines a
    // .glyphicon rule any more, so the faces had nothing to render.
    'fonts/glyphicons-halflings-regular.ttf',
    'fonts/glyphicons-halflings-regular.woff',
    'fonts/glyphicons-halflings-regular.woff2'
];

foreach ($retired as $rel) {
    $t->check(
        "$rel is not in the tree",
        !file_exists($mgmt . '/' . $rel)
    );
}

// 2. Nothing names them. Searched over the source FOG actually writes --
// a sourceMappingURL inside another vendored bundle is not something this
// project controls and is not what this is guarding.
$sources = [];
$it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web, \FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    $path = $file->getPathname();
    if (!preg_match('/\.(php|scss)$/', $path)) {
        continue;
    }
    $sources[] = $path;
}
$t->check('there are source files to search', count($sources) > 50);

$referenced = [];
foreach ($sources as $path) {
    $src = file_get_contents($path);
    foreach ($retired as $rel) {
        $base = basename($rel);
        if ('less' === $base) {
            continue;
        }
        if (false !== strpos($src, $base)) {
            $referenced[] = basename($path) . ' -> ' . $base;
        }
    }
}
$t->check(
    'no PHP or SCSS source names a retired bundle'
    . (count($referenced) ? ' (' . implode('; ', $referenced) . ')' : ''),
    count($referenced) < 1
);

// 3. The replacements survive -- checked by what they PROVIDE.
$shim = @file_get_contents($mgmt . '/js/fog/datetimepicker-shim.js');
$t->check(
    'the datetimepicker shim still defines $.fn.datetimepicker',
    false !== $shim && false !== strpos($shim, '$.fn.datetimepicker =')
);
$dash = @file_get_contents($mgmt . '/js/fog/dashboard/fog.dashboard.js');
$t->check(
    'the dashboard still defines its own shade()',
    false !== $dash && (bool)preg_match('/function shade\(hex, lightness\)/', $dash)
);
$dtCss = @file_get_contents($mgmt . '/css/datatables.min.css');
$t->check(
    'the combined DataTables build still carries the Bootstrap 5 integration',
    false !== $dtCss && false !== strpos($dtCss, '--dt-row-selected')
);

// The call sites the shim exists for. If these ever go away the shim can go
// too -- but while they are here, so must it.
$callSites = 0;
foreach (glob($mgmt . '/js/fog/*/*.js') as $js) {
    $callSites += substr_count((string)file_get_contents($js), '.datetimepicker(');
}
$t->check(
    'the shim still has call sites to serve (found ' . $callSites . ')',
    $callSites > 0
);

// The IE8 conditional in the page shell went with them. It shipped on
// EVERY page -- other/index.php is the shell for all of them -- and pointed
// at dist/js/html5shiv.min.js and dist/js/respond.min.js, neither of which
// has existed in this tree. Bootstrap 5 dropped IE support outright, so
// there is nothing left for it to help.
$shell = file_get_contents($web . '/management/other/index.php');
$t->check(
    'the page shell carries no IE conditional comment',
    false === strpos($shell, '[if lt IE')
);
$t->check(
    'and does not reference the IE8 polyfills',
    false === strpos($shell, 'html5shiv')
    && false === strpos($shell, 'respond.min.js')
);

$t->finish();
