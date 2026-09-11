<?php
/**
 * Handles exporting of csv, pdf, or DB after verification
 *
 * PHP version 7.4+
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
FOGCore::checkAuthAndCSRF();
$report = unserialize(base64_decode($_SESSION['foglastreport']));
if (!($report instanceof ReportMaker)) {
    $report = FOGCore::getClass('ReportMaker');
}
$report->outputReport();
