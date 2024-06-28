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
$unauthorized = !(isset($currentUser) && $currentUser->isValid()) || empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest';

if ($unauthorized) {
    echo _('Unauthorized');
    exit;
}

$report = unserialize($_SESSION['foglastreport']);
if (!($report instanceof ReportMaker)) {
    $report = FOGCore::getClass('ReportMaker');
}
$report->outputReport();
