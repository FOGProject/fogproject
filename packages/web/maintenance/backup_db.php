<?php
/**
 * Backs up the db for us
 *
 * PHP version 5
 *
 * @category Backup_DB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Backs up the db for us
 *
 * @category Backup_DB
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

// Restrict to same-machine requests only. This dumps the entire database with
// no authentication of any kind, and the installer is its only caller -- it
// posts here from the server's own IP, so loopback and SERVER_ADDR both pass
// while anything off-box does not. installfog.sh removes this whole directory
// when it finishes, so the exposure is bounded by an install's duration, but
// an unauthenticated full dump is not something to leave reachable for even
// that long.
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

FOGCore::getClass('ReportMaker')->outputReport(3, true);
