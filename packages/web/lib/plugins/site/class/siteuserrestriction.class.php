<?php
/**
 * Site plugin
 *
 * PHP version 7
 *
 * @category SiteUserRestriction
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteUserRestriction
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserRestriction extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'siteUserRestriction';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'surID',
        'userID' => 'surUserID',
        'isRestricted' => 'surRestricted'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'userID'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'user'
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
       ]
    ];
}
