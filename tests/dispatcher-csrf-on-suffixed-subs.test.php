<?php
/**
 * Every suffixed handler gets the CSRF check, however the sub is spelled.
 *
 * THE BUGS THIS EXISTS FOR. FOGPageManager::render() ran checkAuthAndCSRF()
 * only where the dispatcher APPENDED the Ajax or Post suffix. Two requests
 * skipped it:
 *
 *   - sub=pendingAgentsAjax names the suffixed method itself. It passes
 *     method_exists() on the bare name, so no suffix is appended and no
 *     check ran;
 *   - sub=removeagentseatajax. method_exists() ignores case, so it reaches
 *     the handler, but a case-sensitive suffix test misses it. The role
 *     permission lowercases the sub, so that gate let it through.
 *
 * render() now asks _isSuffixedHandler() once, after both suffix blocks.
 * This test calls that helper, so it checks behavior: the names that must be
 * checked, and the names that must not. It cannot drive render() itself,
 * because render() needs a signed-in user and a failed CSRF check ends the
 * process. So one structural check pins that render() calls the helper before
 * the permission gate.
 *
 * Usage: php tests/dispatcher-csrf-on-suffixed-subs.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('dispatcher-csrf-on-suffixed-subs');

$t = new FogChecks();

$helper = new \ReflectionMethod(\FOG\Base\FOGPageManager::class, '_isSuffixedHandler');
$helper->setAccessible(true);
$suffixed = function ($name) use ($helper) {
    return $helper->invoke(null, $name);
};

foreach (['pendingAgentsAjax', 'pendingagentsajax', 'REMOVEAGENTSEATAJAX', 'editPost', 'EDITPOST', 'removeAgentSeatAjax'] as $name) {
    $t->check("$name is CSRF-checked", true === $suffixed($name));
}
foreach (['edit', 'index', 'list', 'membership'] as $name) {
    $t->check("$name is not a suffixed handler", false === $suffixed($name));
}

$src = (string)file_get_contents(dirname(__DIR__) . '/packages/web/src/Base/FOGPageManager.php');
$start = strpos($src, 'public function render()');
$call = strpos($src, 'self::_isSuffixedHandler($method)', (int)$start);
$gate = strpos($src, 'Authorization::requirePagePermission(', (int)$start);
$t->check(
    'render() calls the helper before the permission gate',
    false !== $start && false !== $call && false !== $gate && $call < $gate
);

$t->finish();
