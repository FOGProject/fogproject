<?php
/**
 * Membership association between site -> user group links.
 *
 * PHP version 7.4+
 *
 * @category SiteUserGroupMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Membership association between site -> user group links.
 *
 * The friendly `siteID` key matches assocSetter's derivation from the Site
 * class name, so a site's save() drives membership through this association
 * the same way UserGroup drives UserGroupMember.
 *
 * @category SiteUserGroupMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserGroupMember extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteUserGroupMembers';
    /**
     * The table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sugmID',
        'name' => 'sugmName',
        'siteID' => 'sugmSiteID',
        'usergroupID' => 'sugmUserGroupID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'siteID',
        'usergroupID'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteUserGroupMember', 'SiteUserGroupMember');
