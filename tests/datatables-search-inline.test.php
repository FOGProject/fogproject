<?php
/**
 * A grid's search box sits on the same line as its buttons, on the right.
 *
 * The shared DataTables `dom` string in fogDefaults() used to give search a
 * row of its own ABOVE the buttons: `<'row'<'col-sm-6'l><'col-sm-6'f>>B`.
 * That spent a full line of height on one input and left the left half of
 * the line blank on every grid in the product. Length menu, buttons and
 * search now share one flex row, with search pushed right.
 *
 * THE TRAP THIS PINS is the explicit min-width on the search wrapper. A flex
 * item's default `min-width: auto` is its content's min-content width -- here
 * the input's intrinsic ~280px -- and THAT, not the 10rem flex basis, decides
 * whether it fits on the line. Without it the host list's 1100px button bar
 * pushes search onto a line of its own below the buttons at a 1600px window,
 * which is the same wasted line moved down a row. Measured on the lab server:
 * toolbar 73px without the min-width, 33px with it.
 *
 * The compiled stylesheet is checked as well as the source, for the reason
 * card-header-description.test.php gives: nothing builds one from the other.
 *
 * Usage: php tests/datatables-search-inline.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('datatables-search-inline');

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$js = (string)file_get_contents($web . '/management/js/fog/fog.common.js');
$scss = (string)file_get_contents($web . '/management/css/fog-default-ui.scss');
$min = (string)file_get_contents($web . '/management/css/fog-default-ui.min.css');
$sys = (string)file_get_contents($web . '/src/Base/System.php');

/*
 * 1. Both dom strings -- paged and infinite scroll -- put search inside the
 *    toolbar that holds the buttons. Paged carries the length menu first.
 */
$t->check(
    'the paged dom string puts length, buttons and search in one toolbar',
    1 === preg_match(
        "#dom: \"<'fog-dt-toolbar [^']*'lB<'fog-dt-search [^']*'f>>#",
        $js
    )
);
$t->check(
    'the infinite-scroll dom string puts buttons and search in one toolbar',
    1 === preg_match(
        "#defaults\\.dom = \"<'fog-dt-toolbar [^']*'B<'fog-dt-search [^']*'f>>#",
        $js
    )
);
$t->check(
    'no dom string gives search a column of its own any more',
    false === strpos($js, "'f>>B")
);

/*
 * 2. The source declares the shrink, including the explicit min-width.
 */
$block = '';
if (preg_match('#\.fog-dt-toolbar \.fog-dt-search\s*\{(.*?)\n\}#s', $scss, $m)) {
    $block = $m[1];
}
$t->check('the SCSS declares the toolbar search rule', '' !== $block);
$t->check(
    'the search wrapper sets an explicit min-width',
    1 === preg_match('#^\s*min-width:\s*10rem;#m', $block)
);
$t->check(
    'the input inside it can shrink',
    1 === preg_match('#input\s*\{[^}]*min-width:\s*0#s', $block)
);

/*
 * 3. The compiled artifact carries it -- the half a browser loads.
 */
$t->check(
    'the minified stylesheet carries the wrapper rule',
    false !== strpos(
        $min,
        '.fog-dt-toolbar .fog-dt-search{flex:1 1 10rem;min-width:10rem;max-width:20rem}'
    )
);
$t->check(
    'the minified stylesheet carries the input rule',
    false !== strpos(
        $min,
        '.fog-dt-toolbar .fog-dt-search div.dt-search input{flex:1 1 auto;min-width:0}'
    )
);

/*
 * 4. The cache version moved past the release that shipped without it.
 */
$ver = 0;
if (preg_match("/define\('FOG_BCACHE_VER', (\d+)\)/", $sys, $m2)) {
    $ver = (int)$m2[1];
}
$t->check('FOG_BCACHE_VER is at least 368', $ver >= 368);

$t->finish();
