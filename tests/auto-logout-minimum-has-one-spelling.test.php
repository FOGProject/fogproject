<?php
/**
 * The auto-logout minimum is stated once and read everywhere.
 *
 * `HostAutoLogout::MIN_MINUTES` is the rule: below it, auto-logout is OFF.
 * It is consulted from four places -- the host page's validator, the host
 * page's own POST handler, the group page's constant, and the `data-alo-min`
 * the group form hands the browser -- and one of them spelled it as the
 * literal `$tme > 4`. A magic number is not wrong until the constant moves,
 * at which point three surfaces change and one silently does not, and the
 * symptom is a value the form accepts and the save discards.
 *
 * SOURCE-ANCHORED, deliberately. The behavior is identical either way while
 * the constant is 5 -- that is the whole reason the drift is invisible -- so
 * a behavioral assertion here would pass against the literal it exists to
 * forbid. What can be checked is that no second spelling exists.
 *
 * Usage: php tests/auto-logout-minimum-has-one-spelling.test.php
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

$webroot = dirname(__DIR__) . '/packages/web';

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

$item = (string)@file_get_contents(
    $webroot . '/src/Items/HostAutoLogout.php'
);
$check(
    'HostAutoLogout declares the minimum',
    1 === preg_match('/const MIN_MINUTES = (\d+);/', $item, $m)
        && (int)$m[1] > 0
);

/*
 * Every comparison of a submitted auto-logout time must name the constant.
 * `$tme` is the field name on both pages' forms, so a comparison against a
 * bare number is the drift this file is for.
 */
$pages = [
    'src/Pages/HostManagement.php',
    'src/Pages/GroupManagement.php',
];
foreach ($pages as $rel) {
    $src = (string)@file_get_contents($webroot . '/' . $rel);
    $check(
        basename($rel) . ' compares $tme against a named minimum, not a literal',
        0 === preg_match('/\$tme\s*[<>]=?\s*\d/', $src)
    );
    $check(
        basename($rel) . ' still gates on the auto-logout minimum at all',
        false !== strpos($src, 'MIN_MINUTES')
    );
}

// The group page hands the same number to the browser. A form that lets a
// value through which the save then discards is the visible half of this
// drift, so the attribute has to come from the constant too.
$group = (string)@file_get_contents(
    $webroot . '/src/Pages/GroupManagement.php'
);
$check(
    'the group form gets data-alo-min from the constant',
    1 === preg_match(
        '/data-alo-min="\'\s*\.\s*self::ALO_MIN_MINUTES/',
        $group
    )
);
$check(
    'the group page aliases the constant rather than restating it',
    1 === preg_match(
        '/const ALO_MIN_MINUTES = HostAutoLogout::MIN_MINUTES;/',
        $group
    )
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the auto-logout minimum has drifted:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf(
    "PASS  auto-logout minimum has one spelling: %d checks\n",
    $checks
);
exit(0);
