<?php
/**
 * A module settings tab is wired end to end, or it silently discards saves.
 *
 * Global Module Settings is built from four places that must agree, and
 * nothing at runtime notices when they do not:
 *
 *   - ServiceConfigurationPage::service<Name>() renders the tab;
 *   - ServiceConfigurationPage::service<Name>Post() writes the settings;
 *   - editPost()'s switch routes `service-<name>` to that Post method;
 *   - fog.service.list.js pairs `#<name>-update` with `#<name>update-form`
 *     so the button posts anything at all.
 *
 * Miss the last one and the tab renders perfectly, the button reports
 * success, and every save is thrown away -- which is exactly what the first
 * version of the Software tab did. Miss the switch case and the POST is
 * accepted and ignored. Neither leaves a log line.
 *
 * The tab list is NOT hard-coded here. It is read off the class, so a tab
 * added tomorrow is held to the same contract without editing this file.
 *
 * Also checked: the id prefix each tab hands _renderModuleTab() is unique.
 * Every tab renders into one document, so two tabs sharing a prefix emit
 * two elements with the same id, and a <label for> then binds to whichever
 * came first -- Printer Manager and Power Management both used 'pm', so
 * Power Management's "Module Enabled" label toggled Printer Manager's
 * checkbox.
 *
 * Usage: php tests/service-tabs-are-wired.test.php
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

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$pagePath = $webroot . '/src/Pages/ServiceConfigurationPage.php';
$jsPath = $webroot . '/management/js/fog/service/fog.service.list.js';

foreach ([$pagePath, $jsPath] as $needed) {
    if (!is_readable($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

$source = file_get_contents($pagePath);
$js = file_get_contents($jsPath);

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

// The tabs, straight off the class. serviceHome is the one tab that is not
// a module: it has no settings, no Post and no update button.
preg_match_all(
    '/public function service([A-Z][A-Za-z]*)\(\)/',
    $source,
    $m
);
$all = $m[1];
$tabs = [];
foreach ($all as $name) {
    if ('Home' === $name || 'Post' === substr($name, -4)) {
        continue;
    }
    $tabs[] = $name;
}

$check('the page exposes module tabs at all', count($tabs) > 0);

foreach ($tabs as $name) {
    $short = strtolower($name);
    $check(
        "service{$name}Post() exists to write what service{$name}() renders",
        in_array($name . 'Post', $all, true)
    );
    $check(
        "editPost() routes service-{$short} to its Post method",
        false !== strpos($source, "case 'service-{$short}':")
            && false !== strpos($source, "\$this->service{$name}Post();")
    );
    $check(
        "fog.service.list.js posts #{$short}-update, so the button is not dead",
        false !== strpos($js, "'#{$short}-update'")
            && false !== strpos($js, "'#{$short}update-form'")
    );
}

// Every id prefix handed to _renderModuleTab(), in call order. Comment
// lines between the arguments are skipped so a documented choice still
// reads as a choice.
$prefixes = [];
$offset = 0;
while (false !== ($at = strpos($source, '$this->_renderModuleTab(', $offset))) {
    $offset = $at + 1;
    $tail = substr($source, $at, 1200);
    $tail = preg_replace('#^\s*//[^\n]*$#m', '', $tail);
    if (preg_match_all("/'([^']*)'/", $tail, $args) && count($args[1]) >= 3) {
        $prefixes[] = [$args[1][0], $args[1][2]];
    }
}

$check(
    'every module tab was found to pass an id prefix',
    count($prefixes) === count($tabs)
);

$seen = [];
foreach ($prefixes as list($key, $prefix)) {
    $check(
        "the id prefix '{$prefix}' ({$key}) is not already used by "
        . ($seen[$prefix] ?? ''),
        !isset($seen[$prefix])
    );
    $seen[$prefix] = $key;
}

if (count($failures)) {
    fwrite(STDERR, "FAIL: a module settings tab is not wired end to end:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  service tabs are wired: %d checks\n", $checks);
exit(0);
