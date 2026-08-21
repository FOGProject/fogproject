<?php
/**
 * Prints the history of all items.
 *
 * PHP version 7.4+
 *
 * @category History_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Prints the history of all items.
 *
 * @category History_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class History_Report extends ReportManagement
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        // The activity viewer replaced this screen (ADR 0023). The class,
        // this URL and getList() below all stay -- a bookmark keeps working
        // and anything scripting the endpoint keeps answering -- but a person
        // arriving here is sent to the page that is maintained.
        //
        // Guarded on the permission, not unconditional: `activity` is a NEW
        // registry node, so on the day an install upgrades nobody holds
        // activity.view except '*'. An unconditional redirect would take a
        // report.view holder from a working report to a denial, which is a
        // regression dressed as a migration. Without the grant they get the
        // old page, unchanged, until an administrator grants it.
        if (Authorization::can('activity.view')) {
            // 302, not the default 308. A permanent redirect is cacheable,
            // and this one is conditional on a permission an administrator
            // can revoke -- a browser that had cached it would keep sending
            // the user to a page they no longer hold.
            self::redirect(
                '../management/index.php?node=activity&source=history',
                302
            );
        }

        $this->title = _('Full History');

        $this->headerData = [
            _('User'),
            _('Time'),
            _('Information'),
            _('IP')
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
        echo _('Full History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'history-table');
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
        Route::listem('history');
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
class_alias(__NAMESPACE__ . '\\History_Report', 'History_Report');
