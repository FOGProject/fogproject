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
class Inventory_Report extends ReportManagementPage
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Full Inventory Export');
        array_walk(
            self::$inventoryCsvHead,
            function (&$classGet, &$csvHeader) {
                $this->ReportMaker->addCSVCell($csvHeader);
                unset($classGet, $csvHeader);
            }
        );
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Memory'),
            _('System Product'),
            _('System Serial')
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array()
        );
        Route::listem('host');
        $Hosts = json_decode(
            Route::getData()
        );
        $Hosts = $Hosts->hosts;
        foreach ((array)$Hosts as &$Host) {
            $Image = $Host->image;
            $Inventory = $Host->inventory;
            $this->data[] = array(
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'memory' => $Inventory->memory,
                'sysprod' => $Inventory->sysproduct,
                'sysser' => $Inventory->sysserial,
            );
            foreach (self::$inventoryCsvHead as $head => &$classGet) {
                switch ($head) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell($Host->id);
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($Host->name);
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell($Host->primac);
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell($Host->description);
                    break;
                case _('Memory'):
                    $this->ReportMaker->addCSVCell($Inventory->memory);
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Inventory->$classGet);
                    break;
                }
                unset($classGet, $head);
            }
            $this->ReportMaker->endCSVLine();
            unset($Inventory, $Host);
        }
        $this->ReportMaker->appendHTML($this->process(12));
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if (count($this->data) > 0) {
            echo '<div class="text-center">';
            printf(
                $this->reportString,
                'InventoryReport',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'InventoryReport',
                _('Export PDF'),
                _('Export PDF'),
                self::$pdffile
            );
            echo '</div>';
        }
        $this->ReportMaker->outputReport(0, true);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
