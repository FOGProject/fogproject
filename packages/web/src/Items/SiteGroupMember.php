<?php
/**
 * Membership association between site -> host group links.
 *
 * PHP version 7.4+
 *
 * @category SiteGroupMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Membership association between site -> host group links.
 *
 * The friendly `siteID` key matches assocSetter's derivation from the Site
 * class name, so a site's save() drives membership through this association
 * the same way UserGroup drives UserGroupMember.
 *
 * @category SiteGroupMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteGroupMember extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteGroupMembers';
    /**
     * The table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sgmID',
        'name' => 'sgmName',
        'siteID' => 'sgmSiteID',
        'groupID' => 'sgmGroupID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'siteID',
        'groupID'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteGroupMember', 'SiteGroupMember');
