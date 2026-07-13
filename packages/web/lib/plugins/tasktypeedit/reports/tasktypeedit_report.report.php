<?php
/**
 * Task Type report.
 *
 * PHP Version 5
 *
 * @category Tasktypeedit_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task Type report.
 *
 * @category Tasktypeedit_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Tasktypeedit_Report extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Export Task Types');

        $this->headerData = [
            _('Name'),
            _('Description'),
            _('Icon'),
            _('Kernel'),
            _('Kernel Args'),
            _('Type'),
            _('Is Advanced'),
            _('Access'),
            _('Init')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Export Task Types');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'tasktypeedit-report-table');
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
        Route::listem('tasktypeedit');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
