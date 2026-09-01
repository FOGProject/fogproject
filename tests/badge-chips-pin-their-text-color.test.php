<?php
/**
 * The grid chips must pin their text color, not just their background.
 *
 * `.badge` is not Bootstrap's alone here. fog-default-ui re-themes it:
 *
 *     .badge{background-color:var(--fog-badge-bg);color:var(--fog-text-strong)}
 *     .badge{background-color:#fff;color:var(--fog-primary)}
 *
 * Those are theme tokens, and they load after Bootstrap. So `bg-secondary`
 * -- which is `!important` on the BACKGROUND only -- leaves the text color
 * to whichever of those rules wins, and the answer differs between light
 * and dark mode. The host list's group chips shipped that way and came out
 * near-unreadable in dark mode while light mode looked fine, which is the
 * worst version of this bug: whoever builds it never sees it.
 *
 * `text-bg-secondary` sets both, both `!important`:
 *
 *     .text-bg-secondary{color:#fff!important;background-color:RGBA(...)!important}
 *
 * so the chip is the same in either theme and no stylesheet load order can
 * change it. That is the invariant here -- not the particular color.
 *
 * Pinned rather than left to the comment beside it because "bg-secondary is
 * shorter" is a plausible tidy-up, and the thing it breaks is invisible to
 * anyone not using the other theme.
 *
 * Usage: php tests/badge-chips-pin-their-text-color.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('badge-chips-text-color');

$t = new FogChecks();
$js = dirname(__DIR__) . '/packages/web/management/js/fog';

$chips = [
    'host list group chips' => $js . '/host/fog.host.list.js',
    'group list grants badges' => $js . '/group/fog.group.list.js'
];

foreach ($chips as $what => $path) {
    $src = (string)file_get_contents($path);
    $t->check("the $what file was read", '' !== $src);
    $t->check(
        "the $what pin the text color with text-bg-",
        false !== strpos($src, 'badge text-bg-secondary')
    );
}

// And nowhere in the product, because this is not a property of two
// renderers -- every badge on every grid had it, and fixing only the two
// that were reported leaves the same complaint waiting on the next list.
//
// The tones are named rather than matched as [a-z]+ so bg-body,
// bg-transparent and the rest are not swept in: there is no text-bg- for
// those, and pinning a class that does not exist renders as nothing.
$tones = 'primary|secondary|success|danger|warning|info|light|dark';
$roots = [
    dirname(__DIR__) . '/packages/web/management/js/fog',
    dirname(__DIR__) . '/packages/web/src'
];
$offenders = [];
foreach ($roots as $root) {
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root)
    );
    foreach ($it as $file) {
        $ext = $file->getExtension();
        if ('js' !== $ext && 'php' !== $ext) {
            continue;
        }
        if (false !== strpos($file->getFilename(), '.min.')) {
            continue;
        }
        $src = (string)file_get_contents($file->getPathname());
        // badge then a bare tone background, in either order, with other
        // utility classes allowed between them -- plus the concatenated
        // form, where the tone is a variable.
        if (preg_match('/badge[\w\- ]*\sbg-(?:' . $tones . ')\b/', $src)
            || preg_match('/\bbg-(?:' . $tones . ')\s[\w\- ]*badge\b/', $src)
            || preg_match('/badge\s+bg-[\'"]/', $src)
        ) {
            $offenders[] = $file->getFilename();
        }
    }
}
sort($offenders);
$t->check(
    'no badge anywhere sets a background without its text color'
    . (count($offenders) ? ': ' . implode(', ', $offenders) : ''),
    0 === count($offenders)
);

// The utility has to exist in the bundle we ship, or this pins a class
// name that renders as nothing at all.
$css = dirname(__DIR__) . '/packages/web/management/css/bootstrap5.min.css';
$bundle = (string)file_get_contents($css);
$t->check(
    'text-bg-secondary is defined in the shipped bootstrap bundle',
    false !== strpos($bundle, '.text-bg-secondary{')
);
$t->check(
    'and it is the definition that sets color, not only background',
    (bool)preg_match(
        '/\.text-bg-secondary\{color:#[0-9a-f]{3,6}!important;background-color:/',
        $bundle
    )
);

$t->finish();
