<?php
/**
 * "Not Registered Hosts" submits 0, not its label.
 *
 * GH-1830. The "Show with" select is built by FOGBase's $buildSelectBox
 * closure through array_walk(), keyed 0..7. The closure tested the key for
 * truthiness, so key 0 fell back to the label: the form posted
 * regmenu=Not Registered Hosts, the INT column pxeRegOnly rejected it, and
 * the edit failed with "Menu update failed!".
 *
 * DB-free: regSelect() only renders.
 *
 * Usage: php tests/ipxe-regselect-not-registered.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('ipxe-regselect-not-registered');

$t = new FogChecks();

$manager = new \FOG\Managers\PXEMenuOptionsManager();
$html = $manager->regSelect(0, 'regmenu');

$t->check(
    'Not Registered Hosts is submitted as 0',
    1 === preg_match('/<option value="0"[^>]*>Not Registered Hosts</', $html)
);
$t->check(
    'no option uses its label as its value',
    0 === preg_match('/<option value="[^"0-9]/', $html)
);
$t->check(
    'a stored 0 is shown selected',
    false !== strpos($html, '<option value="0" selected>')
);
$t->check(
    'a stored 2 is shown selected, and only 2',
    1 === substr_count($manager->regSelect(2, 'regmenu'), ' selected')
        && false !== strpos(
            $manager->regSelect(2, 'regmenu'),
            '<option value="2" selected>'
        )
);

$t->finish();
