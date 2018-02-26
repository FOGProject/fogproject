<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteHostAssoc
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteHostAssoc
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteHostAssociation extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteHostAssoc';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'shaID',
        'name' => 'shaName',
        'siteID' => 'shaSiteID',
        'hostID' => 'shaHostID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'siteID'
    ];
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'host',
        'site'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Host' => [
            'id',
            'hostID',
            'host'
        ],
        'Site' => [
            'id',
            'siteID',
            'site'
        ]
    ];
}
