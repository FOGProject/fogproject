<?php
/**
 * Subnet Group report.
 *
 * PHP Version 5
 *
 * @category SubnetGroup_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Subnet Group report.
 *
 * @category SubnetGroup_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetGroup_Report extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Export Subnet Groups');

        $this->headerData = [
            _('Name'),
            _('Assigned Group')
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Export Subnet Groups');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'subnetgroup-report-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Returns the JSON data for this report.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        Route::listem('subnetgroup');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
