<?php
/**
 * The account menu looks like a menu, and says whether it is open.
 *
 * It is the only control left in the navbar, so it carries the whole burden
 * of announcing that theme, timezone, impersonation and logout are behind it.
 * A bare icon does not do that -- it reads as a link to a profile page.
 *
 * Two halves, and BOTH of them fail silently:
 *
 *  - .dropdown-toggle on the anchor is what draws the caret. Bootstrap's
 *    ::after does the drawing, so removing the class does not break a
 *    selector or throw anything; the arrow just is not there any more.
 *  - the rotation lives in fog-default-ui.scss, keyed on the .show class
 *    Bootstrap already puts on an open toggle. Bootstrap does NOT rotate its
 *    caret on its own, so without the rule the arrow points the same way open
 *    and closed.
 *
 * AND THE COMPILED FILE IS CHECKED, not just the source. fog-default-ui.scss
 * is not built at request time -- the browser is served
 * fog-default-ui.min.css, which is a committed artifact. Editing the .scss
 * and not rebuilding produces a rule that exists, reviews correctly, and does
 * nothing whatsoever. That is the generated-artifact skew this project has
 * been bitten by before, and it is the assertion here most likely to earn its
 * keep, because the .scss edit is the part a reviewer sees.
 *
 * The build IS reproducible, which is what makes the check fair rather than a
 * trap. Verified 2026-08-30:
 *
 *   npx sass@1.77.8 fog-default-ui.scss --style=compressed fog-default-ui.min.css
 *
 * reproduces the committed file byte for byte. Note --no-source-map does NOT:
 * the committed file carries its sourceMappingURL comment, and omitting the
 * flag is what matches.
 *
 * MUTATION-VERIFIED:
 *
 *   drop .dropdown-toggle from the anchor          -> red
 *   rename &.show::after in the .scss              -> red
 *   .scss keeps the rule, .min.css un-rebuilt      -> red
 *   widen the rotation to every .dropdown-toggle   -> red
 *
 * The third of those is why the compiled check asserts the selector and the
 * transform as ONE rule. Written apart, it stayed GREEN under that mutation:
 * the sheet already carries an unrelated rotate(180deg), so half the
 * assertion was being satisfied by somebody else's rule.
 *
 * Usage: php tests/account-menu-signals-its-state.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';
$shell = (string)file_get_contents($web . '/management/other/index.php');
$scss = (string)file_get_contents(
    $web . '/management/css/fog-default-ui.scss'
);
$css = (string)file_get_contents(
    $web . '/management/css/fog-default-ui.min.css'
);

$results = [];

$results[] = [
    '' !== $shell && '' !== $scss && '' !== $css,
    'the shell, the scss and the compiled css all exist',
];

/*
 * The toggle. Matched on the anchor carrying BOTH the id and the class,
 * rather than on the class appearing anywhere in the file -- the shell has
 * other dropdowns, and any of them would satisfy a loose search.
 */
$results[] = [
    (bool)preg_match(
        '#id="accountMenu"[^>]*class="[^"]*\bdropdown-toggle\b#',
        $shell
    )
    || (bool)preg_match(
        '#class="[^"]*\bdropdown-toggle\b[^"]*"[^>]*id="accountMenu"#',
        $shell
    ),
    'the account menu anchor carries .dropdown-toggle, which is what draws '
        . 'the caret at all',
];

/*
 * The rotation, in the SOURCE. Asserting the selector rather than the whole
 * declaration: the angle and the easing are taste, the state-keyed selector
 * is the contract.
 */
$results[] = [
    (bool)preg_match('#&\.show::after#', $scss)
    || false !== strpos($scss, '#accountMenu.show::after'),
    'fog-default-ui.scss rotates the caret on .show, so the arrow reports '
        . 'open vs closed rather than pointing one way forever',
];

/*
 * The rotation, in the ARTIFACT THE BROWSER ACTUALLY GETS. This is the one
 * that catches an un-rebuilt .min.css, which is invisible in review.
 *
 * Selector and declaration are matched as ONE rule, not as two searches of
 * the file. The first cut asserted '#accountMenu.show::after' and
 * 'rotate(180deg)' separately, and the compiled sheet already contains a
 * rotate(180deg) belonging to something else -- so half the assertion was
 * satisfied by an unrelated rule and would have stayed green with this rule's
 * transform stripped out.
 */
$results[] = [
    (bool)preg_match(
        '~#accountMenu\\.show::after\\{[^}]*rotate\\(180deg\\)[^}]*}~',
        $css
    ),
    'fog-default-ui.min.css does not carry the rotation the .scss declares -- '
        . 'the stylesheet was edited without being rebuilt, so the rule is '
        . 'real in source and absent from every page',
];

/*
 * Scope. Rotating EVERY toggle in FOG would change the tab dropdowns in
 * FOGPage::renderTabs() and the grid toolbars' split buttons, which is a
 * change to surfaces nobody asked about. Pinned because the tidy-minded fix
 * for "why is this rule so specific" is to delete the specificity.
 */
$results[] = [
    false === strpos($css, '.dropdown-toggle.show::after')
    && false === strpos($css, '.dropdown-toggle::after{transition'),
    'the rotation was widened to every .dropdown-toggle, which changes tab '
        . 'dropdowns and split buttons that were never in scope',
];

$failed = 0;
foreach ($results as [$passed, $why]) {
    echo $passed ? "  ok    $why\n" : "  FAIL  $why\n";
    $failed += $passed ? 0 : 1;
}
echo "\n";
if ($failed > 0) {
    echo 'FAIL (' . $failed . ' of ' . count($results) . " assertions)\n";
    exit(1);
}
echo 'ok: the account menu announces itself and its state ('
    . count($results) . " assertions)\n";
exit(0);
