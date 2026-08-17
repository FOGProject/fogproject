<?php
/**
 * Imaging Log report
 *
 * PHP version 7.4+
 *
 * @category Imaging_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Imaging Log report
 *
 * @category Imaging_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Imaging_Log extends ReportManagement
{
    /**
     * Initial display
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Imaging Log');

        $this->headerData = [
            _('Host Name'),
            _('Start Time'),
            _('End Time'),
            _('Duration'),
            _('Image Name'),
            _('Type')
        ];
        $this->attributes = [
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
        echo _('Imaging Log');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'imaginglog-table');
        echo '</div>';
        echo '<div class="card-footer">';
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
        Route::listem('imaginglog');
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
class_alias(__NAMESPACE__ . '\\Imaging_Log', 'Imaging_Log');
