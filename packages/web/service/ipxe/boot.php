<?php
/**
 * Boot page for pxe/iPXE
 *
 * PHP version 5
 *
 * @category Boot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Boot page for pxe/iPXE
 *
 * @category Boot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../../commons/base.inc.php';
header("Content-type: text/plain");
$items = array(
    'mac' => filter_input(INPUT_POST, 'mac'),
    'mac0' => filter_input(INPUT_POST, 'mac0'),
    'mac1' => filter_input(INPUT_POST, 'mac1'),
    'mac2' => filter_input(INPUT_POST, 'mac2')
);
$mac = FOGCore::fastmerge(
    explode('|', $items['mac']),
    explode('|', $items['mac0']),
    explode('|', $items['mac1']),
    explode('|', $items['mac2'])
);
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
new BootMenu();
