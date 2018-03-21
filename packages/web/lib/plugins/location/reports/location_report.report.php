<?php
/**
 * Test report
 *
 * PHP Version 5
 *
 * @category Location_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Test report
 *
 * @category Location_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Location_Report extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->headerData = [];
        $this->templates = [];
        $this->attributes = [];

        $obj = self::getClass('LocationManager');
        foreach ($obj->getColumns() as $common => &$real) {
            array_push($this->headerData, $common);
            array_push($this->templates, '');
            array_push($this->attributes, []);
            unset($real);
        }

        $this->title = _('Export Locations');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Locations');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'location-export-table');
        echo '</div>';
        echo '</div>';
    }
}
