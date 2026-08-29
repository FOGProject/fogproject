<?php
/**
 * A card header's description renders below its title, not welded to it.
 *
 * AdminLTE 4 floats `.card-title` so a header can carry `.card-tools`
 * beside it. Twelve headers in FOG also emit a `<p class="form-text">`
 * description in that same header, and a block element following a float
 * wraps AROUND it -- so the paragraph started on the title's own line with
 * no separator: "Import ReportsThis section allows you to upload user
 * defined reports...". Two of the twelve come from shared helpers, so it
 * read that way on every page they serve.
 *
 * WHAT THIS PINS IS THE PAIR, not the rule. `fog-default-ui.scss` is the
 * source and `fog-default-ui.min.css` is what a browser actually loads;
 * nothing in the build compiles one from the other, so editing the SCSS and
 * forgetting to regenerate is a change that passes every other check in the
 * tree and reaches nobody. That is the failure this exists to catch, and it
 * is silent in exactly the way a stale asset always is.
 *
 * The cache version is checked with it: a corrected stylesheet behind an
 * unbumped FOG_BCACHE_VER is served from the browser's cache, which looks
 * identical to not having made the change at all.
 *
 * Usage: php tests/card-header-description.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('card-header-description');

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$scss = (string)file_get_contents($web . '/management/css/fog-default-ui.scss');
$min = (string)file_get_contents($web . '/management/css/fog-default-ui.min.css');
$sys = (string)file_get_contents($web . '/src/Base/System.php');

/*
 * 1. The source declares it. Matched on the three declarations rather than
 *    on a formatted block, so reindenting or reordering them does not fail
 *    the build while dropping one of them does.
 */
$block = '';
if (preg_match(
    '#\.card-header\s*>\s*\.form-text\s*\{(.*?)\}#s',
    $scss,
    $m
)) {
    $block = $m[1];
}
$t->check(
    'the SCSS scopes the rule to a header\'s own description',
    '' !== $block
);
$t->check(
    'it clears the floated .card-title',
    false !== strpos($block, 'clear: both')
);
$t->check(
    'it is a block, so the description owns its line',
    false !== strpos($block, 'display: block')
);

/*
 * 2. .card-title keeps its float. Unfloating the title would fix these
 *    twelve headers by moving .card-tools buttons on every card in FOG,
 *    which is a far larger change than the one being made -- so a future
 *    edit that "simplifies" this by dropping the float should fail here
 *    rather than ship a product-wide reflow.
 */
$t->check(
    'the fix does not unfloat .card-title',
    false === strpos($scss, '.card-title')
    || 1 !== preg_match('#\.card-title\s*\{[^}]*float\s*:\s*none#s', $scss)
);

/*
 * 3. The compiled artifact carries it. This is the half that reaches a
 *    browser; the SCSS is not served. `.app-main` scoping is asserted with
 *    it because that is the block the rule lives in -- a rule that escaped
 *    it would also apply to the login page, which has its own asset list.
 */
$t->check(
    'the minified stylesheet carries the compiled rule',
    false !== strpos(
        $min,
        '.app-main .card-header>.form-text{clear:both;display:block;'
    )
);

/*
 * 4. The cache version moved past the release that shipped without it.
 */
$ver = 0;
if (preg_match("/define\('FOG_BCACHE_VER', (\d+)\)/", $sys, $m2)) {
    $ver = (int)$m2[1];
}
$t->check('FOG_BCACHE_VER is at least 330', $ver >= 330);

$t->finish();
