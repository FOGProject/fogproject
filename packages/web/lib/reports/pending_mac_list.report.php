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
        $this->title = _('Pending MAC Addresses');

        $this->headerData = [
            _('Host Name'),
            _('MAC Address')
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Pending MAC Addresses');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'pendingmac-table');
        echo '</div>';
        echo '</div>';
    }
}
