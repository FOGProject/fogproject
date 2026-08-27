<?php
/**
 * Boot page for pxe/iPXE
 *
 * PHP version 7.4+
 *
 * @category Boot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGCore;
use FOG\Boot\IpxeBootMenu;

/**
 * Boot page for pxe/iPXE
 *
 * @category Boot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a booting NIC, FOS, the fog-client
 * or a storage node, none of which can present a credential. Declared per
 * file rather than inferred from the absence of one -- see
 * Authorization::_hasNoPrincipal() for what it licenses and why the
 * distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../../commons/base.inc.php';
header("Content-type: text/plain");
/*
 * Every mac* field the iPXE scripts can post, unioned into one candidate list
 * for the host lookup. The field NAMES carry no meaning -- getHostItem()
 * matches a host on any MAC associated with it -- so this is purely "collect
 * every address this machine can be identified by".
 *
 * 'macboot' is ${netX/mac}, the NIC iPXE actually booted from. It is an
 * ADDITION to mac0 rather than a replacement: netX is a pointer at one of
 * net0..netN, so a machine booting off net1 would post net1's MAC twice and
 * net0's not at all. array_unique() below makes the overlap free.
 *
 * mac3..mac7 widen an enumeration that stopped at net2, which made a host
 * registered under only its fourth NIC unfindable. Absent fields come back
 * null from filter_input() and array_filter() drops them, so a one-NIC
 * machine posting only mac0 behaves exactly as before.
 */
$macFields = [
    'mac',
    'mac0',
    'macboot',
    'mac1',
    'mac2',
    'mac3',
    'mac4',
    'mac5',
    'mac6',
    'mac7',
];
$macLists = [];
foreach ($macFields as $macField) {
    $macLists[] = explode('|', (string)filter_input(INPUT_POST, $macField));
}
$mac = FOGCore::fastmerge(...$macLists);
$mac = implode(
    '|',
    array_values(
        array_unique(
            array_filter($mac)
        )
    )
);
FOGCore::getHostItem(
    false,
    false,
    true,
    false,
    false,
    $mac
);
new IpxeBootMenu();
