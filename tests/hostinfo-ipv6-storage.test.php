<?php
/**
 * service/hostinfo.php brackets an IPv6 storage node address in storage.
 *
 * fogproject#2152. The reply sent storage=fd00:f06:0:1::1:/images/dev/ for a
 * storage node with an IPv6 address. The busybox mount in FOS splits the
 * source at the first colon, so it could not mount the share. FOS reads this
 * reply. An IPv6 literal must be in brackets in host:/path, the same as in
 * every other storage= emitter.
 *
 * ADR 0023: hostinfo.php builds storage with the code that builds the kernel
 * line. This test renders a scenario through the boot menu harness and reads
 * the fields the script's own merge block produces.
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

/**
 * The storage field for one node address and task type.
 *
 * @param array  $taskType the task type
 * @param string $ip       the node address
 *
 * @return string
 */
function storageFor(array $taskType, $ip)
{
    $f = hostinfoFields(parityScenario($taskType, [], [], ['storageNodeIp' => $ip]));
    return (string)($f['storage'] ?? '(absent)');
}

$t->check(
    'an IPv6 node address is in brackets on a capture',
    '[fd00:f06:0:1::1]:/images/dev/' === storageFor(PARITY_CAPTURE, 'fd00:f06:0:1::1')
);
$t->check(
    'an IPv6 node address is in brackets on a deploy',
    '[fd00:f06:0:1::1]:/images/' === storageFor(PARITY_DEPLOY, 'fd00:f06:0:1::1')
);
$t->check(
    'an IPv4 node address is unchanged',
    '10.0.0.1:/images/dev/' === storageFor(PARITY_CAPTURE, '10.0.0.1')
);
$t->check(
    'a host name is unchanged',
    'storage.lan:/images/dev/' === storageFor(PARITY_CAPTURE, 'storage.lan')
);
$t->check(
    'an address with surrounding spaces is still trimmed',
    '10.0.0.1:/images/' === storageFor(PARITY_DEPLOY, '  10.0.0.1 ')
);

$t->finish();
