<?php
/**
 * Handles exporting of csv, pdf, or DB after verification
 *
 * PHP version 5
 *
 * @category Export
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles exporting of csv, pdf, or DB after verification
 *
 * @category Export
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
if (FOGCore::getSetting('FOG_REAUTH_ON_EXPORT')) {
    $user = $_POST['fogguiuser'];
    $pass = $_POST['fogguipass'];
    $tmpUser = FOGCore::getClass('User')->passwordValidate($user, $pass);
    if (!$tmpUser) {
        die('###'.$foglang['InvalidLogin']);
    }
}
$report = unserialize($_SESSION['foglastreport']);
if (!($report instanceof ReportMaker)) {
    $report = FOGCore::getClass('ReportMaker');
}
$report->outputReport();
