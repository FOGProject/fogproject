<?php
/**
 * Pending MAC report.
 *
 * PHP Version 5
 *
 * @category Pending_MAC_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pending MAC report.
 *
 * @category Pending_MAC_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Pending_MAC_List extends ReportManagement
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

        $obj = self::getClass('MACAddressAssociationManager');
        foreach ($obj->getColumns() as $common => &$real) {
            switch (strtolower($common)) {
            case 'id':
                $common = _('ID');
                break;
            case 'hostid':
                $common = _('Host Name');
                break;
            case 'description':
            case 'pending':
            case 'primary':
            case 'clientignore':
            case 'imageignore':
                continue 2;
            case 'mac':
                $common = _('MAC Address');
                break;
            }
            array_push($this->headerData, $common);
            array_push($this->templates, '');
            array_push($this->attributes, []);
            unset($real);
        }

        $this->title = _('Export Pending MAC Addresses');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Pending MAC Addresses');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'pending-export-table');
        echo '</div>';
        echo '</div>';
    }
}
