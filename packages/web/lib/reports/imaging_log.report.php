<?php
/**
 * Imaging Log report
 *
 * PHP Version 5
 *
 * @category Imaging_Log
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Imaging Log');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'imaginglog-table');
        echo '</div>';
        echo '<div class="box-footer with-border">';
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
