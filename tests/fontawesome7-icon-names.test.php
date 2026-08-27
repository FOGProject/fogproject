<?php
/**
 * Every icon class FOG emits must exist in the Font Awesome build we ship.
 *
 * FOG vendors Font Awesome 7 and deliberately ships NO v4-shims.css: shims are
 * a compatibility layer that, once present, can never be removed, because
 * nothing ever tells you when the last consumer of it goes away. Dropping them
 * is only safe if something checks the call sites, which is what this file is.
 *
 * The failure this catches is silent by construction. An FA4 class name under
 * FA7 is not an error: the element renders, the CSS loads, the console stays
 * clean, and the user sees an empty gap where an icon should be. There is no
 * request to 404 and no exception to log -- the only signal is somebody looking
 * at the page. That is why this is pinned in the suite rather than trusted to
 * review.
 *
 * WHAT IS PINNED:
 *
 *  1. No FA4-only class name survives anywhere in core -- meaning any name that
 *     is not what FA7 calls the icon today, whether or not it happens to still
 *     resolve. Measured against the shipped build, those 26 names split three
 *     ways, and the split is worth knowing because it is NOT intuitive:
 *
 *       8  have no class in the stylesheet at all and render nothing:
 *          fa-check-square-o, fa-circle-o, fa-file-excel-o, fa-hdd-o,
 *          fa-money, fa-moon-o, fa-square-o, fa-sun-o. Note the pattern --
 *          these are the FA4 "-o" outline variants, whose glyphs moved to the
 *          regular family and whose old names were not kept.
 *       2  resolve to a codepoint in the BRANDS font while `.fa` selects the
 *          solid one, so they draw a tofu box: fa-windows, fa-slack. A wrong
 *          glyph, not an absent one, which is arguably worse to eyeball.
 *      16  still render correctly, because FA7 kept the old name as an alias
 *          (fa-refresh, fa-magic, fa-warning, fa-dashboard, ...).
 *
 *     The last group is why this check exists at all rather than trusting a
 *     visual pass: two thirds of the renames LOOK fine in a browser today and
 *     would break only when FontAwesome eventually drops the aliases.
 *  2. No bare `fa fa-*` two-token form. `.fa` still resolves to solid in FA7 so
 *     these do render, but leaving some call sites on the old prefix while the
 *     rest move is the inconsistency the migration existed to remove.
 *  3. The shim stylesheet is NOT vendored and NOT loaded. If it reappears,
 *     check 1 stops being able to fail and the whole file goes quiet.
 *  4. The webfonts the vendored CSS references are actually present. A missing
 *     woff2 is the other way to get a page full of blank boxes.
 *  5. Every emitted icon name resolves in the shipped stylesheet -- including
 *     the ones a rename pass would not have touched, and including pro-only
 *     names, which are absent from the free build and render blank.
 *
 * Usage: php tests/fontawesome7-icon-names.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('fontawesome7-icon-names');

$t = new FogChecks();

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$css = $web . '/management/css/font-awesome.min.css';

// ---------------------------------------------------------------------------
// 1. The vendored build.
// ---------------------------------------------------------------------------
$t->check('the Font Awesome stylesheet is vendored', is_file($css));

$cssSrc = is_file($css) ? file_get_contents($css) : '';
$t->check(
    'it is Font Awesome 7',
    (bool)preg_match('/Font Awesome Free 7\./', $cssSrc)
);
// Shims are the thing whose absence every other check here depends on.
$t->check(
    'no v4 shim stylesheet is vendored',
    [] === glob($web . '/management/css/*v4-shims*')
);
$t->check(
    'and nothing loads one',
    false === strpos(
        file_get_contents($web . '/src/Base/Page.php'),
        'v4-shims'
    )
);
// The FA4 webfont set is gone; its replacements are present.
$t->check(
    'the FA4 webfonts are no longer shipped',
    [] === glob($web . '/management/fonts/fontawesome-webfont.*')
    && !is_file($web . '/management/fonts/FontAwesome.otf')
);
foreach (['fa-solid-900', 'fa-regular-400', 'fa-brands-400'] as $font) {
    $t->check(
        sprintf('webfont %s.woff2 is shipped', $font),
        is_file($web . '/management/webfonts/' . $font . '.woff2')
    );
}
// Every font the stylesheet asks for must exist, or those glyphs are blank.
preg_match_all('#url\(\.\./webfonts/([a-z0-9.-]+)\)#i', $cssSrc, $m);
$missing = [];
foreach (array_unique($m[1]) as $file) {
    if (!is_file($web . '/management/webfonts/' . $file)) {
        $missing[] = $file;
    }
}
$t->check(
    sprintf(
        'every webfont the stylesheet references exists (%d referenced)%s',
        count(array_unique($m[1])),
        [] === $missing ? '' : ' -- MISSING: ' . implode(', ', $missing)
    ),
    [] === $missing
);

// ---------------------------------------------------------------------------
// 2. The call sites.
//
// lib/plugins is excluded deliberately: it is gitignored staging for the
// separate fog-plugins repo, which carries its own copy of this check. Core
// cannot fix a name that lives there, and failing on it would make this file
// unfixable from inside this repo.
// ---------------------------------------------------------------------------
$files = [];
$it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web, \FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    $path = $f->getPathname();
    if (false !== strpos($path, '/vendor/')
        || false !== strpos($path, '/lib/plugins/')
    ) {
        continue;
    }
    // Vendored bundles carry their own icon names and we do not get to rename
    // them -- pnotify.min.js names FA4 icons in its styling presets. Ours live
    // in management/js/fog/; everything directly under management/js/ is a
    // third-party bundle. Where a vendored preset would render blank, FOG
    // overrides it at its own call site (see the PNotify styling block in
    // fog.common.js, pinned below) rather than editing the bundle, because
    // editing it means the next upgrade silently reverts the fix.
    if (preg_match('#/management/js/[^/]+\.js$#', $path)) {
        continue;
    }
    if (preg_match('/\.(php|js|css|scss)$/', $path)) {
        $files[] = $path;
    }
}
$t->check('source files were found to scan', count($files) > 100);

$bare = [];
$used = [];
foreach ($files as $path) {
    $src = file_get_contents($path);
    if (preg_match_all('/\bfa fa-([a-z0-9-]+)/', $src, $mm)) {
        foreach ($mm[1] as $name) {
            $bare[$name][] = str_replace($root . '/', '', $path);
        }
    }
    if (preg_match_all('/\bfa[bsrl] fa-([a-z0-9-]+)/', $src, $mm)) {
        foreach ($mm[1] as $name) {
            $used[$name] = true;
        }
    }
}
$t->check(
    sprintf(
        'no call site uses the bare "fa fa-*" form%s',
        [] === $bare ? '' : ' -- FOUND: ' . implode(', ', array_keys($bare))
    ),
    [] === $bare
);

// ---------------------------------------------------------------------------
// 3. Every emitted name resolves in the shipped stylesheet.
//
// This is the check that catches a hand-written icon nobody ran the migration
// over, and a pro-only name -- which is absent from the free build and renders
// exactly as blank as an FA4 one.
// ---------------------------------------------------------------------------
$modifiers = [
    'fw', 'lg', 'sm', 'xs', 'spin', 'pulse', 'border', 'li', 'inverse',
    'stack', 'stack-1x', 'stack-2x', 'rotate-90', 'rotate-180', 'rotate-270',
    'flip-horizontal', 'flip-vertical', 'flip-both', 'beat', 'fade', 'bounce',
    'shake', 'spin-pulse', 'spin-reverse', 'pull-left', 'pull-right',
    '2x', '3x', '4x', '5x', '6x', '7x', '8x', '9x', '10x', 'solid', 'regular',
    'brands', 'classic', 'sharp'
];
$unresolved = [];
foreach (array_keys($used) as $name) {
    if (in_array($name, $modifiers, true)) {
        continue;
    }
    // FA emits one rule per icon; an alias gets its own selector too, so a
    // name that resolves at all appears here.
    if (!preg_match('/\.fa-' . preg_quote($name, '/') . '[,:{ ]/', $cssSrc)) {
        $unresolved[] = $name;
    }
}
$t->check(
    sprintf(
        'every emitted icon name resolves in the shipped CSS (%d distinct)%s',
        count($used),
        [] === $unresolved ? '' : ' -- UNRESOLVED: ' . implode(', ', $unresolved)
    ),
    [] === $unresolved
);

// ---------------------------------------------------------------------------
// 4. Toast icons.
//
// These used to be an override of a vendored library's icon presets. FOG
// carried PNotify 3.2.0, whose "bootstrap3" styling emits glyphicon classes --
// dropped by Bootstrap 4 -- so from the BS3 to BS5 move every toast rendered
// its icon slot as an empty box, and PNotify's alternative "fontawesome"
// preset names FA4 icons, which are equally blank here. The names had to be
// patched at FOG's call site, because editing the vendored bundle means the
// next upgrade of it silently reverts the fix.
//
// PNotify is gone: toasts are Bootstrap's own Toast component built from FOG's
// markup, so the icon names are ordinary FOG source and section 3 above
// already checks that each one resolves. What is pinned here is that every
// type a caller can pass still HAS an icon -- an unmapped type falls back to
// success, which would put a green tick on a failure.
// ---------------------------------------------------------------------------
$common = file_get_contents($web . '/management/js/fog/fog.common.js');
$toastTypes = [];
if (preg_match('/var TOAST_TYPES = \{(.*?)\};/s', $common, $tm)) {
    preg_match_all(
        "/(\w+): \['([a-z]+)', '([a-z]+ fa-[a-z0-9-]+)'[^\]]*\]/",
        $tm[1],
        $rows,
        PREG_SET_ORDER
    );
    foreach ($rows as $row) {
        $toastTypes[$row[1]] = $row[3];
    }
}
// The five $.notify() understands. 'warning' is reachable only through
// $.notifyFromAPI(), which is how it went unnoticed that PNotify had no such
// type and rendered every warning with plain notice styling.
$missingTypes = array_diff(
    ['success', 'error', 'warning', 'info', 'notice'],
    array_keys($toastTypes)
);
$t->check(
    sprintf(
        'every toast type has an icon%s',
        [] === $missingTypes ? '' : ' -- MISSING: ' . implode(', ', $missingTypes)
    ),
    [] === $missingTypes
);

// ---------------------------------------------------------------------------
// 5. Icon names that live in the DATABASE, not in a class attribute.
//
// The check above scans source for a literal `fas fa-name`, so it is blind to
// the icons FOG stores as data. taskTypes.ttIcon and taskStates.tsIcon hold a
// bare icon name -- no prefix -- seeded by commons/schema.php and rendered by
// fog.task.list.js and the host/group task menus as `fas fa-<stored name>`.
//
// That blind spot shipped: the FA7 migration renamed every class in core and
// left seven seeded values on FA4 outline names FA7 dropped (plus-square-o,
// hdd-o, arrow-circle-o-down, arrow-circle-o-up, hourglass-o, flag-o and
// bookmark-o), so six task types and the Queued state rendered blank on every
// upgraded and every fresh install. Nothing in the suite could see it, because
// nothing in the suite reads the seed.
//
// Pinned against the shipped stylesheet, the same authority section 3 uses.
// The final value for an id is whichever step sets it last, so the seed is
// replayed in file order rather than collected -- taking every literal would
// fail on the historical steps that are SUPPOSED to hold the old names, which
// are deliberately never edited (an install that has run them never replays
// them, so a correction has to be appended instead).
// ---------------------------------------------------------------------------
$schemaSrc = file_get_contents($root . '/packages/web/commons/schema.php');
// Statements in this file are written as PHP string concatenations wrapped
// across lines, so the glue is folded away first -- otherwise the pattern
// below silently sees only the steps that happen to fit on one line, which is
// every historical step and none of the appended corrections. That reads as
// the bug still being present.
$schemaSrc = preg_replace('/[\x27"]\s*\.\s*[\x27"]/', '', $schemaSrc);
$seeded = [];
$pattern = '/`(taskTypes|taskStates)`\s+SET\s+`(ttIcon|tsIcon)`\s*=\s*'
    . "'([^']+)'\s+WHERE\s+`(ttID|tsID)`\s*=\s*(\d+)/i";
if (preg_match_all($pattern, $schemaSrc, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $row) {
        // Last write wins, exactly as a replay from step 0 would leave it.
        $seeded[$row[1] . '#' . $row[5]] = $row[3];
    }
}
$t->check(
    sprintf('the seeded icon names were found (%d)', count($seeded)),
    count($seeded) >= 20
);

$deadSeed = [];
foreach ($seeded as $where => $value) {
    // A stored value may carry modifiers -- taskStates 3 is
    // "spinner fa-pulse fa-fw" -- and only the first token is the icon.
    $name = strtok(trim($value), ' ');
    if (!preg_match('/\.fa-' . preg_quote($name, '/') . '[,:{ ]/', $cssSrc)) {
        $deadSeed[] = $where . ' => ' . $value;
    }
}
$t->check(
    sprintf(
        'every seeded icon name resolves in the shipped CSS%s',
        [] === $deadSeed ? '' : ' -- DEAD: ' . implode(', ', $deadSeed)
    ),
    [] === $deadSeed
);

// The renderers that compose those values must not be left on the old prefix
// either. They evade the bare-form check in section 2 because the name is
// concatenated on, so the literal ends at the quote and matches nothing.
$concat = [];
foreach ($files as $path) {
    if (preg_match_all('/\bfa fa-[\x27"]/', file_get_contents($path))) {
        $concat[] = str_replace($root . '/', '', $path);
    }
}
$t->check(
    sprintf(
        'no renderer concatenates onto the bare "fa fa-" prefix%s',
        [] === $concat ? '' : ' -- FOUND: ' . implode(', ', $concat)
    ),
    [] === $concat
);

// ---------------------------------------------------------------------------
// 6. Where NEW icon names come from.
//
// Section 5 pins the values commons/schema.php seeds. It cannot see the other
// way a value reaches taskTypes.ttIcon: an administrator choosing one from the
// Task Type edit form, whose dropdown TaskType::iconlist() builds.
//
// That dropdown used to be built from management/other/_variables.scss -- a
// Font Awesome *4.7.0* variables file no stylesheet imported and nothing
// regenerated. The FA7 migration did not touch it, so it went on offering 786
// v4 names of which 148 no longer exist, including all seven the schema steps
// in section 5 had just repaired. Fixing the data while leaving the picker
// able to rewrite it is not a fix.
//
// So this pins the SOURCE rather than the values: the names offered must all
// resolve in the shipped stylesheet, which makes a second, drifting copy of
// the icon list fail here instead of on somebody's screen.
//
// The real method is executed, not read. A regex over the source would pass
// on code that reads the right file and parses it wrongly -- and parsing is
// where the next FA version bump will break it. _faIcons() depends on nothing
// but BASEPATH and that file, so lifting the two methods into a bare host
// class runs the committed code with no FOG boot. Extraction failing is a
// hard failure: it means the methods were renamed and somebody should look,
// not that the check should quietly pass.
// ---------------------------------------------------------------------------
$taskTypeSrc = file_get_contents($web . '/src/Items/TaskType.php');
$from = strpos($taskTypeSrc, '    private static $_faIcons');
$to = strpos($taskTypeSrc, '     * Returns the icon for this task or type.');
$to = false === $to ? false : strrpos(substr($taskTypeSrc, 0, $to), '    /**');
$t->check(
    'the icon-picker methods can be located in tasktype.class.php',
    false !== $from && false !== $to && $to > $from
);
if (false !== $from && false !== $to && $to > $from) {
    if (!defined('BASEPATH')) {
        define('BASEPATH', $web . '/');
    }
    if (!class_exists('Initiator')) {
        eval(
            'class Initiator { public static function e($v) { return htmlspecialchars('
            . '(string)($v ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8", false); } }'
        );
    }
    eval('class FogIconPickerHost { ' . substr($taskTypeSrc, $from, $to - $from) . ' }');
    $host = new FogIconPickerHost();
    $html = $host->iconlist('');
    preg_match_all('/<option value="([a-z0-9-]+)"/', $html, $om);
    $offered = $om[1];

    $t->check(
        sprintf('the picker offers a plausible number of icons (%d)', count($offered)),
        count($offered) > 500
    );

    // The same authority section 5 uses, asked of every name on offer.
    $unresolvable = [];
    foreach ($offered as $name) {
        if (!preg_match('/\.fa-' . preg_quote($name, '/') . '[,:{ ]/', $cssSrc)) {
            $unresolvable[] = $name;
        }
    }
    $t->check(
        sprintf(
            'every icon the picker offers resolves in the shipped CSS%s',
            [] === $unresolvable
                ? ''
                : ' -- ' . count($unresolvable) . ' do not: '
                    . implode(', ', array_slice($unresolvable, 0, 8))
        ),
        [] === $unresolvable
    );

    // Brands resolve in the stylesheet but live only in the Brands font, and
    // FOG renders a stored name as `fas fa-<name>` -- so an offered brand
    // draws a tofu box, which section 1 already calls out as worse to eyeball
    // than an absent icon.
    $brandsAt = preg_match(
        '/font-family:"Font Awesome \d+ Brands"/',
        $cssSrc,
        $bm,
        PREG_OFFSET_CAPTURE
    ) ? $bm[0][1] : false;
    $t->check('the CSS still marks where the brands section starts', false !== $brandsAt);
    $brandsOffered = [];
    if (false !== $brandsAt) {
        foreach ($offered as $name) {
            $at = strpos($cssSrc, '.fa-' . $name . ',');
            $at = false === $at ? strpos($cssSrc, '.fa-' . $name . '{') : $at;
            if (false !== $at && $at > $brandsAt) {
                $brandsOffered[] = $name;
            }
        }
    }
    $t->check(
        sprintf(
            'the picker offers no brand-only icon%s',
            [] === $brandsOffered
                ? ''
                : ' -- ' . count($brandsOffered) . ' offered: '
                    . implode(', ', array_slice($brandsOffered, 0, 8))
        ),
        [] === $brandsOffered
    );

    // Aliases are names too. FA7 declares them by grouping selectors --
    // `.fa-ban,.fa-cancel{--fa:"\f05e"}` is two usable names in one rule --
    // so a parser that reads only the first class of each rule silently drops
    // hundreds of them and every check above still passes, because everything
    // it DOES offer is valid. The expectation is derived from the stylesheet
    // here rather than written down, so it survives a version bump: find a
    // grouped rule and require all of its names.
    $grouped = [];
    if (preg_match_all(
        '/([^{}]+)\{--fa:\s*"\\\\[0-9a-f]+"/i',
        false === $brandsAt ? $cssSrc : substr($cssSrc, 0, $brandsAt),
        $gm,
        PREG_SET_ORDER
    )) {
        foreach ($gm as $rule) {
            preg_match_all('/\.fa-([a-z0-9-]+)(?=[,{:\s]|$)/', $rule[1], $gn);
            if (count($gn[1]) > 1) {
                $grouped = $gn[1];
                break;
            }
        }
    }
    $t->check(
        sprintf('the stylesheet still groups aliases (%s)', implode(', ', $grouped)),
        count($grouped) > 1
    );
    $missingAlias = array_diff($grouped, $offered);
    $t->check(
        sprintf(
            'the picker offers every alias in a grouped rule%s',
            [] === $missingAlias ? '' : ' -- MISSING: ' . implode(', ', $missingAlias)
        ),
        [] === $missingAlias
    );

    // The glyph beside each name has to be a character. The previous code
    // emitted `&#xf02b` with no trailing semicolon and then html-escaped it,
    // so htmlspecialchars could not recognise it as an entity even with
    // double_encode off and every row rendered the literal text.
    $t->check(
        'the picker renders glyphs, not escaped entity text',
        false === strpos($html, '&amp;#x')
    );

    // Nothing may reintroduce a second, static copy of the icon list.
    // Both halves matter: the class has to name the stylesheet, and the file
    // it used to read has to be gone -- a check on the source alone passes on
    // a comment that merely mentions it, which this one did.
    $t->check(
        'the picker reads the shipped stylesheet',
        false !== strpos($taskTypeSrc, 'management/css/font-awesome.min.css')
    );
    $t->check(
        'the Font Awesome 4 variables file is gone',
        !file_exists($web . '/management/other/_variables.scss')
    );
}

$t->finish();
