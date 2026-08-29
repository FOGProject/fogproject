<?php
/**
 * User preference manager class.
 *
 * PHP version 7.4+
 *
 * @category UserPrefManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Items\UserPref;

/**
 * User preference manager class.
 *
 * Every method takes the owning user id as its first argument and filters on
 * it, always. That is the entire access-control story for preferences: no
 * call in this class can reach a row belonging to somebody else, whatever key
 * it is handed. The route layer supplies the id from the session and never
 * from the request, so the two halves cannot be talked out of agreeing.
 *
 * @category UserPrefManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserPrefManager extends FOGManagerController
{
    /**
     * Reads one preference.
     *
     * @param int    $userID The user the preference belongs to.
     * @param string $key    The preference's key.
     *
     * @return string The stored value, or '' when there is none.
     */
    public function fetch($userID, $key)
    {
        $userID = (int)$userID;
        $key = (string)$key;
        if ($userID < 1 || '' === $key) {
            return '';
        }
        $row = self::$DB
            ->query(
                'SELECT `upValue` FROM `userPrefs`'
                . ' WHERE `upUserID` = :uid AND `upKey` = :key',
                [],
                [':uid' => $userID, ':key' => $key]
            )
            ->fetch()
            ->get();

        return (string)($row['upValue'] ?? '');
    }

    /**
     * Reads every preference a user holds, as key => value.
     *
     * @param int $userID The user.
     *
     * @return array
     */
    public function fetchAll($userID)
    {
        $userID = (int)$userID;
        if ($userID < 1) {
            return [];
        }
        $rows = self::$DB
            ->query(
                'SELECT `upKey`, `upValue` FROM `userPrefs`'
                . ' WHERE `upUserID` = :uid ORDER BY `upKey` ASC',
                [],
                [':uid' => $userID]
            )
            ->fetch('', 'fetch_all')
            ->get();

        $prefs = [];
        foreach ((array)$rows as $row) {
            $prefs[(string)$row['upKey']] = (string)($row['upValue'] ?? '');
        }

        return $prefs;
    }

    /**
     * Writes one preference, replacing any previous value.
     *
     * An empty value DELETES rather than storing emptiness. Resetting a grid
     * to its defaults should leave no row behind: otherwise somebody who has
     * tidied up still carries a row saying "no opinion", which no later
     * reader can tell from never having had one.
     *
     * @param int    $userID The user the preference belongs to.
     * @param string $key    The preference's key.
     * @param string $value  The value to store.
     *
     * @return bool Whether the write happened.
     */
    public function store($userID, $key, $value)
    {
        $userID = (int)$userID;
        $key = (string)$key;
        $value = (string)$value;
        if ($userID < 1 || '' === $key) {
            return false;
        }
        if (strlen($key) > UserPref::MAX_KEY_BYTES) {
            // The column is varchar(190), so a longer key would be
            // truncated -- and two different keys truncated to the same 190
            // bytes collide on the UNIQUE index, where the upsert below then
            // overwrites an unrelated preference. Refuse instead.
            return false;
        }
        if (strlen($value) > UserPref::MAX_VALUE_BYTES) {
            return false;
        }
        if ('' === $value) {
            return $this->clear($userID, $key);
        }

        // ON DUPLICATE KEY UPDATE against the (user, key) UNIQUE index.
        // Elsewhere in FOG that combination is a bug -- a create silently
        // overwriting an existing row -- but replacing the previous value of
        // the same preference for the same user is precisely the operation,
        // and doing it in one statement is what stops two tabs saving at
        // once from racing into a duplicate-key error.
        return (bool)self::$DB->query(
            'INSERT INTO `userPrefs`'
            . ' (`upUserID`, `upKey`, `upValue`, `upModifiedTime`)'
            . ' VALUES (:uid, :key, :val, NOW())'
            . ' ON DUPLICATE KEY UPDATE'
            . ' `upValue` = VALUES(`upValue`),'
            . ' `upModifiedTime` = VALUES(`upModifiedTime`)',
            [],
            [':uid' => $userID, ':key' => $key, ':val' => $value]
        );
    }

    /**
     * Removes one preference.
     *
     * @param int    $userID The user the preference belongs to.
     * @param string $key    The preference's key.
     *
     * @return bool
     */
    public function clear($userID, $key)
    {
        $userID = (int)$userID;
        $key = (string)$key;
        if ($userID < 1 || '' === $key) {
            return false;
        }

        return (bool)self::$DB->query(
            'DELETE FROM `userPrefs`'
            . ' WHERE `upUserID` = :uid AND `upKey` = :key',
            [],
            [':uid' => $userID, ':key' => $key]
        );
    }
}
