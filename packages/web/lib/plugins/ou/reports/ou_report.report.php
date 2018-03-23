<?php
/**
 * Test report
 *
 * PHP Version 5
 *
 * @category OU_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Test report
 *
 * @category OU_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OU_Report extends ReportManagement
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

        $obj = self::getClass('OUManager');
        foreach ($obj->getColumns() as $common => &$real) {
            $this->headerData[] = $common;
            $this->templates[] = '';
            $this->attributes[] = [];
            unset($real);
        }

        $this->title = _('Export OUs');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export OUs');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'ou-export-table');
        echo '</div>';
        echo '</div>';
    }
}
