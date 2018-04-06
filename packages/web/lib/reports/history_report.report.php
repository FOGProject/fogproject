<?php
/**
 * Prints the history of all items.
 *
 * PHP Version 5
 *
 * @category History_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Prints the history of all items.
 *
 * @category History_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class History_Report extends ReportManagement
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Full History');

        $this->headerData = [
            _('User'),
            _('Time'),
            _('Information'),
            _('IP')
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Full History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'history-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display list of history items.
     *
     * @return void
     */
    public function getHistoryList()
    {
        header('Content-type: application/json');
        Route::listem('history');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
