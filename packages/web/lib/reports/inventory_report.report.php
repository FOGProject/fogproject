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
            // User set information
            _('Primary User'),
            _('Other Primary'),
            _('Other Secondary'),
            // System
            _('System Manufacturer'),
            _('System Product'),
            _('System Version'),
            _('System Serial'),
            _('System UUID'),
            _('System Type'),
            // BIOS
            _('BIOS Version'),
            _('BIOS Vendor'),
            _('BIOS Date'),
            // Motherboard
            _('Motherboard Manufacturer'),
            _('Motherboard Product Name'),
            _('Motherboard Version'),
            _('Motherboard Serial'),
            _('Motherboard Asset'),
            // CPU
            _('CPU Manufacturer'),
            _('CPU Version'),
            _('CPU Current Speed'),
            _('CPU Maximum Speed'),
            // Memory
            _('System Memory Available'),
            // Hard Disk
            _('Hard Disk Model'),
            _('Hard Disk Serial'),
            _('Hard Disk Firmware'),
            // Case
            _('Case Manufacturer'),
            _('Case Version'),
            _('Case Serial'),
            _('Case Asset'),
            // Name of host
            _('Hostname'),
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
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            ['width' => 40],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
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
    /**
     * Display list of history items.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        Route::listem('inventory');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
