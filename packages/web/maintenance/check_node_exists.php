<?php
/**
 * Check if the node exists and return it
 *
 * PHP version 7.4+
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
 * PHP version 7.4+
 *
 * @category Check_Node_Exists
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

// Restrict to same-machine requests only.
$_remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$_serverIp = $_SERVER['SERVER_ADDR'] ?? '';
if ($_remoteIp !== '127.0.0.1'
    && $_remoteIp !== '::1'
    && $_remoteIp !== $_serverIp
) {
    http_response_code(403);
    exit;
}
unset($_remoteIp, $_serverIp);

// Require the fogverified sentinel (paired with -d "fogverified" in the
// registerStorageNode() curl call in lib/common/functions.sh).
if (!isset($_POST['fogverified'])) {
    http_response_code(403);
    exit;
}

$ip = filter_input(INPUT_POST, 'ip', FILTER_VALIDATE_IP);
if (!$ip) {
    http_response_code(400);
    exit;
}

$val = '';
$exists = FOGCore::getClass('StorageNodeManager')
    ->exists($ip, '', 'ip');
if ($exists) {
    $val = 'exists';
}
echo $val;
exit;
