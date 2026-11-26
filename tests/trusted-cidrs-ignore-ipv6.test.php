<?php
/**
 * A server's IPv6 addresses never enable a group's trusted CIDRs.
 *
 * getLocalGroupTrustedCIDRs() finds this server's storage node row from the
 * addresses getIPAddress() lists, then reads that group's trustedcidrs, which
 * status/freespace.php and status/hw.php honor. getIPAddress() now lists
 * global IPv6 addresses too. Matching an IPv6 node row would newly enable
 * those CIDRs, an access change nobody approved, so the function must see
 * only what it saw before: IPv4 addresses and names.
 *
 * Usage: php tests/trusted-cidrs-ignore-ipv6.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('trusted-cidrs-ignore-ipv6');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

$class = new \ReflectionClass('FOG\Base\FOGBase');
$ipsProp = $class->getProperty('ips');
$ipsProp->setAccessible(true);
$method = $class->getMethod('getLocalGroupTrustedCIDRs');
$method->setAccessible(true);

/**
 * Runs the method with the server holding these addresses.
 *
 * @param array $ips The addresses getIPAddress() returns.
 *
 * @return array [the result, every bound value of every query]
 */
function trustedFor(array $ips)
{
    global $db, $ipsProp, $method;
    $ipsProp->setValue(null, $ips);
    $bound = [];
    $db->responder = function ($sql, $params) use (&$bound) {
        foreach ($params as $v) {
            $bound = array_merge($bound, (array)$v);
        }
        return null;
    };
    $res = $method->invoke(null);
    $db->responder = null;
    return [$res, $bound];
}

list($res, $bound) = trustedFor(['fd00::1']);
$t->check(
    'an IPv6-only server matches no node row, so no CIDRs apply',
    [] === $res && [] === $bound
);

list($res, $bound) = trustedFor(['192.0.2.5', 'fd00::1']);
$t->check(
    'the IPv4 address is still looked up',
    in_array('192.0.2.5', $bound, true)
);
$t->check(
    'the IPv6 address is never looked up',
    !in_array('fd00::1', $bound, true)
);

$t->finish();
