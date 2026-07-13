<?php
/**
 * Site report.
 *
 * PHP Version 5
 *
 * @category Site_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site report.
 *
 * @category Site_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Site_Report extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Export Sites');

        $this->headerData = [
            _('Name'),
            _('Description')
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Export Sites');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'site-report-table');
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
        Route::listem('site');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
