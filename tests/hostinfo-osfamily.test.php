<?php
/**
 * service/hostinfo.php sends the image OS's family to FOS as osfamily.
 *
 * fogproject#2083. FOS decided "Windows, Linux or macOS" from a hardcoded
 * list of osID numbers, so every new os row needed a FOS release. The
 * server now reads the family from the os row and sends it next to osid.
 * An empty family means "not sent" to FOS, so an image with no valid OS, and
 * a task that has no image data, send osfamily='' and raise no error.
 *
 * ADR 0023: hostinfo.php builds osid and osfamily with the code that builds
 * the kernel line. This test renders a scenario through the boot menu harness
 * and reads the fields the script's own merge block produces.
 *
 * Requires no database.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-test-harness.php';
require_once __DIR__ . '/lib/hostinfo-parity.php';

$t = new \FogChecks();

$image = function ($osID) {
    return ['image' => parityImage(['osID' => $osID])];
};

$f = hostinfoFields(parityScenario(PARITY_DEPLOY, [], $image(10)));
$t->check('an image with osID 10 sends osfamily windows', 'windows' === ($f['osfamily'] ?? null));
$t->check('the osid field is still sent', '10' === (string)($f['osid'] ?? ''));
$f = hostinfoFields(parityScenario(PARITY_DEPLOY, [], $image(50)));
$t->check('an image with osID 50 sends osfamily linux', 'linux' === ($f['osfamily'] ?? null));
$f = hostinfoFields(parityScenario(PARITY_DEPLOY, [], $image(0)));
$t->check('an image with no valid OS sends an empty osfamily', '' === ($f['osfamily'] ?? null));
$f = hostinfoFields(parityScenario(PARITY_NO_INIT));
$t->check(
    'a task with no image data sends an empty osfamily and raises no error',
    [] !== $f && '' === ($f['osfamily'] ?? null)
);

$t->finish();
