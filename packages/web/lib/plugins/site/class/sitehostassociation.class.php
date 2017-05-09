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
    protected $databaseFields = array(
        'id' => 'shaID',
        'name' => 'shaName',
        'siteID' => 'shaSiteID',
        'hostID' => 'shaHostID'
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'id',
        'hostID',
        'siteID'
    );
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'host',
        'site'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Host' => array(
            'id',
            'hostID',
            'host'
        ),
        'Site' => array(
            'id',
            'siteID',
            'site'
        )
    );
}
