<?php
/**
 * Checks the database is running
 *
 * PHP version 5
 *
 * @category Dbrunning
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Checks the database is running
 *
 * @category Dbrunning
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
header('Content-type: application/json');
ignore_user_abort(true);
set_time_limit(0);
$link = DatabaseManager::getLink();
$redirect = false;
if ($link) {
    $redirect = FOGCore::getClass('Schema', 1)
        ->get('version') == FOG_SCHEMA;
}
$ret = [
    'running' => (bool)$link,
    'redirect' => (bool)$redirect,
];
/**
 * When the database is unreachable, expose the underlying connection error
 * (e.g. SQLSTATE[HY000] [2002] Permission denied) so the cause is diagnosable.
 * The message is sanitized by connectError() -- the SQLSTATE/reason is kept
 * while identifiers (db user/host/name) are redacted -- so it is safe to
 * return even though this page is reachable pre-authentication.
 */
if (!$link) {
    $ret['error'] = DatabaseManager::getDB()->connectError();
}
http_response_code(
    $ret['running'] ?
    HTTPResponseCodes::HTTP_SUCCESS :
    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
);
$ret = json_encode($ret);
echo $ret;
exit;
