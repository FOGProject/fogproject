<?php
/**
 * Handler of the user as authenticated
 *
 * PHP version 5
 *
 * @category UserAuth
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handler of the user as authenticated
 *
 * @category UserAuth
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserAuth extends FOGController
{
    /**
     * The users table
     *
     * @var string
     */
    protected $databaseTable = 'userAuths';
    /**
     * The user table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'uaID',
        'userID' => 'uaUserID',
        'expire' => 'uaExpireDate',
        'isExpired' => 'uaIsExpired',
        'selector' => 'uaSelectorHash',
        'password' => 'uaPasswordHash'
    ];
    /**
     * The additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'user'
    ];
    /**
     * Generates an encrypted hash
     *
     * @param string $password the password
     * @param int    $cost     cost of hash
     *
     * @return string
     */
    public static function generateHash(
        $password,
        $cost = 11
    ) {
        return User::generateHash($password, $cost);
    }
}
