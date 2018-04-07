<?php
/**
 * Prints the inventory of all items.
 *
 * PHP Version 5
 *
 * @category Inventory_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Prints the inventory of all items.
 *
 * @category Inventory_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Inventory_Report extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Inventory List');

        $this->headerData = [
            _('Host Name'),
            _('System Serial'),
            _('System Product'),
            _('System UUID')
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
        echo _('Inventory List');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'inventory-table');
        echo '</div>';
        echo '</div>';
    }
}
