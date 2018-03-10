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
header('Content-type: application/json');
/*if (FOGCore::getSetting('FOG_REAUTH_ON_EXPORT')) {
    $user = filter_input(INPUT_POST, 'fogguiuser');
    if (empty($user)) {
        $user = $currentUser->get('name');
    }
    $pass = filter_input(INPUT_POST, 'fogguipass');

    $validate = FOGCore::getClass('User')
        ->passwordValidate(
            $user,
            $pass,
            true
        );
    if (!$validate) {
        echo json_encode(
            [
                'error' => $foglang['InvalidLogin'],
                'title' => _('Unable to Authenticate')
            ]
        );
        http_response_code(HTTPResponseCode::HTTP_UNAUTHORIZED);
        exit;
    }
}*/
$report = unserialize($_SESSION['foglastreport']);
if (!($report instanceof ReportMaker)) {
    $report = FOGCore::getClass('ReportMaker');
}
$report->outputReport();
exit;
