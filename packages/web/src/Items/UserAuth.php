<?php
/**
 * Handler of the user as authenticated
 *
 * PHP version 7.4+
 *
 * @category UserAuth
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

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
    /**
     * Deletes expired remember-me tokens so the userAuths table does
     * not grow without bound. Called opportunistically when a new
     * remember-me token is issued (login time), which is the only point
     * at which the table grows.
     *
     * @return void
     */
    public static function reapExpired()
    {
        $now = self::niceDate()->format('Y-m-d H:i:s');
        self::$DB->query(
            'DELETE FROM `userAuths` '
            . 'WHERE `uaExpireDate` < :now '
            . 'OR `uaIsExpired` = 1',
            [],
            [':now' => $now]
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\UserAuth', 'UserAuth');
