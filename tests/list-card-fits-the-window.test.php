<?php
/**
 * The first card on a list page must fit inside the window, footer included.
 *
 * fogSizeScroller() gives the DataTables scroll body an explicit max-height so
 * a long list scrolls inside its card instead of scrolling the page. The height
 * it picks is "whatever is left of the window below the top of the scroll body,
 * minus whatever sits below the scroll body". That second term is the one that
 * was wrong.
 *
 * It measured to the bottom of `dt.table().container()` -- the DataTables
 * wrapper, which ends at the info line ("Showing 1 to 21 of 86 entries"). The
 * card carries a FOOTER below that, outside the wrapper, and on a list page
 * that footer is the toolbar: Delete selected, Mass edit, Queue Task, Add to
 * group, Add. So the body was sized as though the footer were not there and the
 * card overflowed the window by exactly the footer's height -- every button on
 * the page below the fold, on every load, at every window size. Measured on a
 * 1280x720 window against the real nesting: 49px of overflow with the wrapper
 * as the reference, 20px of clearance with the card.
 *
 * Making the window taller did not help, which is what made it look like a
 * style bug rather than an arithmetic one: Scroller asks for a page of rows
 * proportional to the height it is given, so the extra height went into rows
 * and the footer stayed exactly as far below the fold as before.
 *
 * Two halves, and the fix is only correct while BOTH hold, so both are pinned:
 *
 *  - the JS must measure to the nearest `.card` ancestor, falling back to the
 *    wrapper when there is no card (a bare grid in a modal or a report); and
 *  - the page render must keep the footer INSIDE that card. A refactor that
 *    lifts the toolbar out to a sibling of the card would put it back below the
 *    fold with the JS unchanged and nothing else complaining.
 *
 * Usage: php tests/list-card-fits-the-window.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);

$fails = [];

// Comments stripped: a check its own docblock can satisfy proves nothing, and
// the explanation above the fix names every term this file looks for.
$strip = function ($text) {
    return preg_replace('#^\s*//.*$#m', '', $text);
};

$sizer = '';
if (preg_match('#function fogSizeScroller\(dt, release\)\s*\{(.*?)\n\}\n#s', $js, $m)) {
    $sizer = $strip($m[1]);
} else {
    $fails[] = 'fogSizeScroller() is gone or has changed signature: nothing '
        . 'sizes a list grid to the window, so either the page scrolls instead '
        . 'of the grid or the card overflows -- this file cannot check either';
}

if ('' !== $sizer) {
    // The reference. closest() is what walks up out of the DataTables wrapper
    // and into the card; without it there is nothing to measure the footer with.
    if (!preg_match('#closest\(\s*([\'"])\.card\1\s*\)#', $sizer)) {
        $fails[] = 'fogSizeScroller() no longer resolves the nearest .card '
            . 'ancestor: it is measuring to the DataTables wrapper again, so '
            . 'the card footer -- the list toolbar -- sits below the fold';
    }

    // The fallback. A grid with no card ancestor must still be sized, not
    // skipped and not measured against undefined.
    if (!preg_match('#closest\(\s*[\'"]\.card[\'"]\s*\)\[0\]\s*\|\|\s*container#', $sizer)) {
        $fails[] = 'the .card lookup has no `|| container` fallback: a grid '
            . 'outside a card (a modal, a report) gets undefined here and '
            . 'throws on getBoundingClientRect(), taking the sizing pass with it';
    }

    // belowBody must be measured from the resolved outer box, not from the
    // wrapper. This is the actual bug: `container.getBoundingClientRect()` on
    // this line is what shipped, and it reads as correct.
    if (!preg_match('#belowBody\s*=\s*outer\.getBoundingClientRect\(\)\.bottom#', $sizer)) {
        $fails[] = 'belowBody is not measured from the resolved outer box: '
            . 'whatever the .card lookup found is being ignored, which is the '
            . 'original bug with a variable in front of it';
    }
    if (preg_match('#belowBody\s*=\s*container\.getBoundingClientRect\(\)#', $sizer)) {
        $fails[] = 'belowBody is measured from the DataTables container: that '
            . 'box ends at the info line, so the card footer is not counted and '
            . 'the card overflows the window by the footer\'s height';
    }

    // The height must still be a window-relative budget. If this stops being
    // computed from innerHeight the checks above are measuring nothing.
    if (!preg_match('#avail\s*=\s*window\.innerHeight\s*-\s*bodyRect\.top\s*-\s*belowBody#', $sizer)) {
        $fails[] = 'the available height is no longer window.innerHeight minus '
            . 'the body top minus belowBody: the reference checks above no '
            . 'longer describe what the code computes';
    }
}

// The other half of the contract: the toolbar lives in a .card-footer INSIDE
// the same .card as the grid. indexDivDisplay() is the generic list page --
// Hosts, Groups, Images, Snapins and the rest all render through it.
$page = file_get_contents($root . '/packages/web/src/Base/FOGPage.php');
$index = '';
if (preg_match(
    '#public function indexDivDisplay\(.*?\n    \}\n#s',
    $page,
    $m
)) {
    $index = $m[0];
} else {
    $fails[] = 'indexDivDisplay() is gone: the generic list page render this '
        . 'file pins no longer exists';
}

if ('' !== $index) {
    // Order is the whole point -- card, then body, then footer, then the card
    // closes. A footer emitted after the card's closing div is a sibling, and
    // the JS would size the card to the window with the toolbar underneath it.
    $marks = [];
    foreach (
        [
            'card' => '#<div class="card"#',
            'body' => '#<div class="card-body"#',
            'footer' => '#<div class="card-footer"#'
        ] as $key => $re
    ) {
        if (preg_match($re, $index, $mm, PREG_OFFSET_CAPTURE)) {
            $marks[$key] = $mm[0][1];
        }
    }
    if (count($marks) !== 3) {
        $fails[] = 'indexDivDisplay() no longer emits a .card wrapping a '
            . '.card-body and a .card-footer: the structure fogSizeScroller() '
            . 'measures against is not there';
    } elseif (!($marks['card'] < $marks['body'] && $marks['body'] < $marks['footer'])) {
        $fails[] = 'indexDivDisplay() emits the .card-footer before the '
            . '.card-body: the toolbar is no longer below the grid inside the '
            . 'card and the measured box is not what the JS assumes';
    }

    // Count the closing divs after the footer opens: the footer and the card
    // must both close AFTER the footer's content, i.e. the footer is nested.
    // A structural regression that closed the card first would leave the
    // footer outside it in the DOM even with the emit order above intact.
    $afterFooter = substr($index, $marks['footer'] ?? 0);
    if (
        isset($marks['footer'])
        && preg_match_all("#echo '</div>';#", $afterFooter) < 2
    ) {
        $fails[] = 'fewer than two closing divs follow the .card-footer in '
            . 'indexDivDisplay(): the footer and the card cannot both be '
            . 'closing, so the footer is not nested inside the card';
    }
}

if ($fails) {
    fwrite(STDERR, "FAIL list-card-fits-the-window:\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "PASS list-card-fits-the-window\n";
exit(0);
