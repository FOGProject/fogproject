<?php
/**
 * Grant association between site -> user group links.
 *
 * PHP version 7.4+
 *
 * @category SiteUserGroupGrant
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Grant association between site -> user group links.
 *
 * Not to be confused with SiteUserGroupMember, which holds the same two ids
 * in the same order and means the opposite thing. That one says a user group
 * IS IN a site -- it is one of the objects the site contains, visible and
 * editable to that site's admins. This one says members of this user group
 * GET this site, which is what puts them in scope at all.
 *
 * Keeping them apart is what allows granting somebody access to a site
 * without also making the group they arrived through an object inside it.
 *
 * The friendly `siteID` key matches assocSetter's derivation from the Site
 * class name, so a site's save() drives grants through this association the
 * same way it drives membership through SiteUserGroupMember.
 *
 * @category SiteUserGroupGrant
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserGroupGrant extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteUserGroupGrants';
    /**
     * The table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'suggID',
        'name' => 'suggName',
        'siteID' => 'suggSiteID',
        'grantusergroupID' => 'suggGroupID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'siteID',
        'grantusergroupID'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteUserGroupGrant', 'SiteUserGroupGrant');
