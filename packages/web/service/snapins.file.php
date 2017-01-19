<?php
/**
 * Snapin client file download
 *
 * PHP version 5
 *
 * @category Snapin_File
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin client file download
 *
 * @category Snapin_File
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new SnapinClient(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
