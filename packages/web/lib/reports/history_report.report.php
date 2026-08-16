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

namespace FOG;

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

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Full History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'history-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display list of history items.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        Route::listem('history');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\History_Report', 'History_Report');
