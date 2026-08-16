<?php
/**
 * Membership association between site -> host links.
 *
 * PHP version 5
 *
 * @category SiteHostMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Membership association between site -> host links.
 *
 * The friendly `siteID` key matches assocSetter's derivation from the Site
 * class name, so a site's save() drives membership through this association
 * the same way UserGroup drives UserGroupMember.
 *
 * @category SiteHostMember
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteHostMember extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteHostMembers';
    /**
     * The table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'shmID',
        'name' => 'shmName',
        'siteID' => 'shmSiteID',
        'hostID' => 'shmHostID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'siteID',
        'hostID'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteHostMember', 'SiteHostMember');
