<?php
/**
 * Reports hosts within.
 *
 * PHP version 5
 *
 * @category Host_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Reports hosts within.
 *
 * @category Host_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Host_List extends ReportManagement
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Host List');

        $this->headerData = [
            _('Host Name'),
            _('Primary MAC'),
            _('Last Deployed'),
            _('Image Name')
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
        echo _('Host LIst');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'hostlist-table');
        echo '</div>';
        echo '</div>';
    }
}
