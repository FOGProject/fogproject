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

namespace FOG;

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

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Host List');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'hostlist-table');
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
        Route::listem('host');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Host_List', 'Host_List');
