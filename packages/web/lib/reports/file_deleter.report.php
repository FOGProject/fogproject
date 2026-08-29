<?php
/**
 * File Deleter report
 *
 * PHP version 7.4+
 *
 * @category File_Deleter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Router\Route;

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
        $this->title = self::reportTitle();

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
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same
     * query -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        Route::listem('filedeletequeue');

        // Decoded rather than echoed straight through, because exportAll()
        // needs the rows as data and Route hands its payload back encoded.
        // getList() re-encodes; that round trip costs a fraction of the
        // query it wraps and buys one read path instead of two.
        return (array) json_decode(Route::getData(), true);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\File_Deleter', 'File_Deleter');
