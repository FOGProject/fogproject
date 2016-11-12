<?php
/**
 * User cleanup class used for legacy client.
 *
 * PHP version 5
 *
 * @category UserCleanup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * User cleanup class used for legacy client.
 *
 * @category UserCleanup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserCleanup extends FOGController
{
    /**
     * The user cleanup table.
     *
     * @var string
     */
    protected $databaseTable = 'userCleanup';
    /**
     * The user cleanup fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id'        => 'ucID',
        'name'        => 'ucName',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
}
