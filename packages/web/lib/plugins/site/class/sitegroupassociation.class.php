<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteGroupAssoc
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteGroupAssoc
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteGroupAssociation extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteGroupAssoc';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sgaID',
        'name' => 'sgaName',
        'siteID' => 'sgaSiteID',
        'groupID' => 'sgaGroupID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'groupID',
        'siteID'
    ];
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'group',
        'site'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Group' => [
            'id',
            'groupID',
            'group'
        ],
        'Site' => [
            'id',
            'siteID',
            'site'
        ]
    ];
}
