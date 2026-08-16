<?php
/**
 * File Deleter report
 *
 * PHP Version 5
 *
 * @category File_Deleter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * File Deleter report
 *
 * @category File_Deleter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class File_Deleter extends ReportManagement
{
    /**
     * Initial display
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Files Deleted List');

        $this->headerData = [
            _('File Path Name'),
            _('File Path Type'),
            _('State'),
            _('Created Time'),
            _('Completed Time'),
            _('Created By'),
        ];

        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            []
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
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'filedeleterlist-table');
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
        Route::listem('filedeletequeue');
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
class_alias(__NAMESPACE__ . '\\File_Deleter', 'File_Deleter');
