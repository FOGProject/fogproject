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
            _('System Serial'),
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        foreach ((array)self::getClass('HostManager')
            ->find() as &$Host
        ) {
            $Image = $Host->getImage();
            $Inventory = $Host->get('inventory');
            $this->data[] = array(
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac'),
                'memory' => $Inventory->getMem(),
                'sysprod' => $Inventory->get('sysproduct'),
                'sysser' => $Inventory->get('sysserial'),
            );
            foreach (self::$inventoryCsvHead as $head => &$classGet) {
                switch ($head) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell($Host->get('id'));
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($Host->get('name'));
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell($Host->get('mac'));
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell($Host->get('description'));
                    break;
                case _('Memory'):
                    $this->ReportMaker->addCSVCell($Inventory->getMem());
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Inventory->get($classGet));
                    break;
                }
                unset($classGet, $head);
            }
            $this->ReportMaker->endCSVLine();
            unset($Inventory, $Host);
        }
        $this->ReportMaker->appendHTML($this->__toString());
        $this->ReportMaker->outputReport(false);
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
