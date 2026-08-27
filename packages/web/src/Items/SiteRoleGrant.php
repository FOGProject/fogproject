<?php
/**
 * Grant association between site -> role links.
 *
 * PHP version 7.4+
 *
 * @category SiteRoleGrant
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Grant association between site -> role links.
 *
 * A GRANT, not a membership, and the difference is the reason this table
 * exists rather than being folded into one of the four `site*Members`
 * tables. A membership says an object IS IN a site and may be seen and
 * edited by that site's admins. A grant says holders of this role GET this
 * site -- it is what puts a user in scope in the first place. The two hold
 * the same pair of ids and answer opposite questions.
 *
 * The friendly `siteID` key matches assocSetter's derivation from the Site
 * class name, so a site's save() drives grants through this association the
 * same way it drives membership through SiteUserMember.
 *
 * @category SiteRoleGrant
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteRoleGrant extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteRoleGrants';
    /**
     * The table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'srgID',
        'name' => 'srgName',
        'siteID' => 'srgSiteID',
        'grantroleID' => 'srgRoleID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'siteID',
        'grantroleID'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteRoleGrant', 'SiteRoleGrant');
