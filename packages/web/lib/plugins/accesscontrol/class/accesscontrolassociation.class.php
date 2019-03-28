<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlAssociation
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlAssociation extends FOGController
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $databaseTable = 'roleUserAssoc';
    /**
     * Table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ruaID',
        'name' => 'ruaName',
        'accesscontrolID' => 'ruaRoleID',
        'userID' => 'ruaUserID'
    ];
    /**
     * Required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'accesscontrolID',
        'userID'
    ];
}
