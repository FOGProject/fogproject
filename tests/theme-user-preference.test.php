<?php
/**
 * The color theme is a per-USER preference with THREE states.
 *
 * The states are '' (follow the operating system, either direction), 'light'
 * and 'dark'. Every mistake this gate exists to catch is silent -- the page
 * renders, nothing is logged, and the only symptom is somebody getting the
 * wrong colors:
 *
 *  - collapsing '' into 'light' gives a dark-desktop user a light page and no
 *    way to tell why. It is the failure this feature exists to prevent, and
 *    it is one `?:` away at all times.
 *  - stamping data-bs-theme on <html> for an unset preference defeats the
 *    pre-paint script, whose whole cue is the attribute's ABSENCE.
 *  - reading the theme from a cookie again makes it a property of the device,
 *    so the same person gets different colors on their other machine.
 *  - accepting an arbitrary stored string would put unvalidated text into an
 *    HTML attribute.
 *
 * Companion to output-whitespace-significant-blocks.test.php, which pins the
 * no-flash mechanics this preference rides on.
 *
 * Usage: php tests/theme-user-preference.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$base = file_get_contents($web . '/src/Base/FOGBase.php');
$shell = file_get_contents($web . '/management/other/index.php');
$js = file_get_contents($web . '/management/js/fog/theme.js');

$fails = [];

// --- the server side -----------------------------------------------------

if (false === strpos($base, "const THEME_PREF = 'display.theme';")) {
    $fails[] = 'FOGBase does not declare THEME_PREF as display.theme';
}
if (!preg_match(
    '/public static function displayTheme\(\).*?\n    \}/s',
    $base,
    $m
)) {
    $fails[] = 'FOGBase::displayTheme() is missing';
} else {
    $fn = $m[0];
    // The default has to be the empty string. A default of 'light' is the
    // collapse this whole gate is about, and it reads as harmless.
    if (!preg_match("/self::\\\$_displayTheme = '';/", $fn)) {
        $fails[] = 'displayTheme() does not default to the empty string';
    }
    // A whitelist, not a two-way branch: anything else stored -- a value from
    // a future release, or something typed into the table by hand -- has to
    // mean "no opinion", not "light".
    if (!preg_match("/'light' === \\\$pref \\|\\| 'dark' === \\\$pref/", $fn)) {
        $fails[] = 'displayTheme() does not whitelist exactly light and dark';
    }
    if (false === strpos($fn, 'THEME_PREF')) {
        $fails[] = 'displayTheme() does not read the THEME_PREF preference';
    }
    // Same guard the rest of the shell uses: no session means no preference,
    // which is the login page.
    if (false === strpos($fn, '$user->isValid()')) {
        $fails[] = 'displayTheme() does not guard on a valid signed-in user';
    }
}

// --- the page shell ------------------------------------------------------

if (false === strpos($shell, '$themePref = self::displayTheme();')) {
    $fails[] = 'the page shell does not read the theme from displayTheme()';
}
if (false !== strpos($shell, "filter_input(INPUT_COOKIE, 'fogTheme')")) {
    $fails[] = 'the page shell still reads the theme from a cookie';
}

// The attribute must be emitted only for a forced choice. Its absence is the
// signal the pre-paint script keys off, so an unconditional attribute would
// silently disable system-following for everyone.
$htmlTag = '';
foreach (preg_split('/\R/', $shell) as $line) {
    if (0 === strpos(ltrim($line), '<html')) {
        $htmlTag = $line;
        break;
    }
}
if (false === strpos($htmlTag, '$bsTheme ?')) {
    $fails[] = 'data-bs-theme is emitted unconditionally on <html>';
}
// And the value fed to that test must be the preference itself. A default
// applied here -- `$themePref ?: 'light'` -- would make the ternary above
// always true while still looking conditional.
if (false === strpos($shell, '$bsTheme = $themePref;')) {
    $fails[] = 'the stamped theme is not the stored preference verbatim';
}

// Three choices, and one of them must be the empty one.
preg_match_all('/data-theme-choice="([^"]*)"/', $shell, $choices);
$offered = $choices[1];
sort($offered);
if (['', 'dark', 'light'] !== $offered) {
    $fails[] = 'the picker does not offer exactly system, light and dark ('
        . implode(',', $offered) . ')';
}

// Visible, in the navbar, beside the timezone clock -- not buried in a
// settings page, because people assume FOG is light-only until they see it.
if (!preg_match('/navbar-nav ms-auto.*?tzToggle/s', $shell)
    || !preg_match('/navbar-nav ms-auto.*?themeToggle/s', $shell)
) {
    $fails[] = 'the theme picker is not in the navbar beside the clock';
}

// --- the client side -----------------------------------------------------

// An unset preference must consult the system, not fall back to light.
if (!preg_match(
    '/function effective\(pref\).*?systemPrefersDark\(\).*?\n    \}/s',
    $js
)) {
    $fails[] = 'theme.js does not resolve an unset preference from the system';
}
if (false === strpos($js, "fogPrefStore(PREF")) {
    $fails[] = 'theme.js does not persist the choice as a user preference';
}
// The legacy cookie may only be EXPIRED. Writing a live value back to it
// would quietly restore the device-scoped behavior this replaced.
if (preg_match('/document\.cookie = LEGACY_COOKIE \+\s*\n?\s*\'=\' \+/', $js)) {
    $fails[] = 'theme.js writes a value back into the legacy cookie';
}
if (false === strpos($js, 'max-age=0')) {
    $fails[] = 'theme.js does not expire the legacy cookie';
}
// System mode has to keep following the system while the page is open.
if (false === strpos($js, "matchMedia('(prefers-color-scheme: dark)')")
    || false === strpos($js, "addEventListener('change', onChange)")
) {
    $fails[] = 'theme.js does not follow the system preference live';
}

foreach ($fails as $fail) {
    fwrite(STDERR, 'FAIL: ' . $fail . PHP_EOL);
}
if ($fails) {
    fwrite(STDERR, count($fails) . ' failure(s)' . PHP_EOL);
    exit(1);
}
echo 'ok: the theme is a three-state per-user preference' . PHP_EOL;
exit(0);
