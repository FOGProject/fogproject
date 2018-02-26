<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteAssoc
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteAssoc
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserAssociation extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteUserAssoc';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'suaID',
        'name' => 'suaName',
        'siteID' => 'suaSiteID',
        'userID' => 'suaUserID',
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'userID',
        'siteID'
    ];
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'user',
        'site'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'User' => [
            'id',
            'userID',
            'user'
        ],
        'Site' => [
            'id',
            'siteID',
            'site'
        ]
    ];
}
