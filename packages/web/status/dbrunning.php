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
session_write_close();
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
http_response_code(($ret['running'] ? 201 : 500));
$ret = json_encode($ret);
echo $ret;
exit;
