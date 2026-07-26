<?php
/**
 * LDAP report.
 *
 * PHP Version 5
 *
 * @category LDAP_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * LDAP report.
 *
 * @category LDAP_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAP_Report extends ReportManagement
{
    /**
     * The page to display.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Export LDAP Servers');

        $this->headerData = [
            _('LDAP Connection Name'),
            _('Description'),
            _('Created By'),
            _('Created Time'),
            _('LDAP Server'),
            _('Port'),
            _('Search Base DN'),
            _('User Name Attribute'),
            _('Group Name Attribute'),
            _('Group Member Attribute'),
            // Admin Group / User Group removed: the two group buckets were
            // replaced by per-group LDAPGroups mappings, so the columns are
            // no longer writable and only ever showed pre-upgrade leftovers.
            _('Search Scope'),
            _('Bind DN'),
            // Bind Password removed: the export handed out the directory
            // service account credential in cleartext.
            _('Group Search DN'),
            _('Use Group Match'),
            _('Display Name Enabled'),
            _('Display Name Attribute'),
            _('Nested Groups'),
            _('Nested Depth'),
            // LDAPS certificate verification (#893). A path, not a secret, so
            // unlike the bind password it is fine to export.
            _('Certificate Verification'),
            _('CA Certificate Path')
        ];
        // One entry per headerData column, so this shrinks and grows with it.
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
            []
        ];

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Export LDAP Servers');
        echo '</h4>';
        echo '<p class="form-text">';
        echo _('Use the selector to choose how many items you want exported');
        echo '</p>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $this->render(12, 'ldap-report-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Returns the JSON data for this report.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        Route::listem('ldap');
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo Route::getData();
        exit;
    }
}
