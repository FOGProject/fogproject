<?php
/**
 * Check if the node exists and return it
 *
 * PHP version 5
 *
 * @category Check_Node_Exists
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Check if the node exists and return it
 *
 * PHP version 5
 *
 * @category Check_Node_Exists
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
$val = '';
$exists = FOGCore::getClass('StorageNodeManager')
    ->exists($_POST['ip'], '', 'ip');
if ($exists) {
    $val = 'exists';
}
echo $val;
exit;
