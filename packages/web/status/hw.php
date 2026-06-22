<?php
/**
 * Presents Hardware/Software information of the server.
 *
 * PHP version 5
 *
 * @category HardwareInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents Hardware/Software information of the server.
 *
 * @category HardwareInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');

// Allow local authenticated users and trusted node-to-node requests.
$isAuthorizedUser = FOGCore::is_authorized(true);
$remoteIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$isTrustedCaller = FOGCore::isTrustedNodeIp($remoteIP);
if (!$isAuthorizedUser && !$isTrustedCaller) {
    http_response_code(HTTPResponseCodes::HTTP_UNAUTHORIZED);
    echo json_encode(_('Unauthorized'));
    exit;
}

$hwinfo = FOGCore::getHWInfo();
foreach ((array)$hwinfo as $index => $val) {
    echo "$val\n";
}
