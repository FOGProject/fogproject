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
     * Is this token a live remember-me credential for the given user?
     *
     * True only when the row belongs to $userID, is not flagged or past its
     * expiry, and both cookie halves verify against the stored hashes. The
     * user check matters: without it, any user's cookie would keep a
     * different user's session alive past the inactivity timeout.
     *
     * @param int    $userID   the user the session belongs to
     * @param string $selector the foguserauthsel cookie value
     * @param string $password the foguserauthpass cookie value
     *
     * @return bool
     */
    public function matches($userID, $selector, $password)
    {
        if (!$this->isValid()
            || $userID < 1
            || (int)$this->get('userID') !== (int)$userID
            || (int)$this->get('isExpired')
            || $selector === ''
            || $password === ''
        ) {
            return false;
        }
        $expire = self::niceDate((string)$this->get('expire'));
        if ($expire <= self::niceDate()) {
            return false;
        }
        return password_verify($selector, (string)$this->get('selector'))
            && password_verify($password, (string)$this->get('password'));
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
