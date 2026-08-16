<?php
/**
 * Reports hosts and the users within.
 *
 * PHP version 5
 *
 * @category Hosts_And_Users
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Reports hosts and the users within.
 *
 * @category Hosts_And_Users
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Hosts_And_Users extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('User Logins');

        $this->headerData = [
            _('User Name'),
            _('Host Name'),
            _('Date')
        ];

        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('User Logins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'userlogin-table');
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
        Route::listem('usertracking');
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
class_alias(__NAMESPACE__ . '\\Hosts_And_Users', 'Hosts_And_Users');
