<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteUserGroupAssoc
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteUserGroupAssoc
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserGroupAssociation extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteUserGroupAssoc';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sugaID',
        'name' => 'sugaName',
        'siteID' => 'sugaSiteID',
        'usergroupID' => 'sugaUserGroupID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'usergroupID',
        'siteID'
    ];
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'usergroup',
        'site'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'UserGroup' => [
            'id',
            'usergroupID',
            'usergroup'
        ],
        'Site' => [
            'id',
            'siteID',
            'site'
        ]
    ];
}
