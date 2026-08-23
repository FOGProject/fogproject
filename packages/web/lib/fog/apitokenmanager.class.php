<?php
/**
 * API token manager.
 *
 * PHP version 7.4+
 *
 * @category APITokenManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * API token manager.
 *
 * @category APITokenManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class APITokenManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'apiTokens';

    /**
     * Every token belonging to one user, newest first.
     *
     * Queried directly rather than through Route::getList(), which is the
     * usual way a page fetches a set of rows. That path validates its class
     * against Route::$validClasses, and APIToken is deliberately absent from
     * it -- a token-management REST surface would let one API credential mint
     * another (see the model's docblock). So the REST layer cannot answer
     * this question, and it should not be taught to.
     *
     * Direct SQL in a manager is the established shape for exactly that
     * situation; Retention and Site do the same for the same reason.
     *
     * @param int $userID The owner.
     *
     * @return array APIToken objects, empty when the user has none. Never
     *               null, so a foreach over the result is always safe.
     */
    public function forUser($userID)
    {
        $userID = (int)$userID;
        if ($userID < 1) {
            return [];
        }
        $rows = self::$DB
            ->query(
                'SELECT `atID` FROM `apiTokens` WHERE `atUserID` = :uid'
                . ' ORDER BY `atCreatedTime` DESC, `atID` DESC',
                [],
                [':uid' => $userID]
            )
            ->fetch('', 'fetch_all')
            ->get();

        $tokens = [];
        foreach ((array)$rows as $row) {
            $token = self::getClass('APIToken', (int)$row['atID']);
            // A row that will not load is skipped rather than returned
            // half-built: every caller reads get('enabled') off these and an
            // invalid object answers '' to everything, which would render as
            // a disabled token that cannot be re-enabled.
            if ($token->isValid()) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    /**
     * Every token on the server the acting user is allowed to see.
     *
     * The question the per-user tab cannot answer: "what credentials exist
     * here, and which has nothing touched in months". Returns plain rows
     * rather than model objects because this is a read-only inventory of
     * potentially hundreds, and the owner's name comes from a join that no
     * model carries.
     *
     * SITE SCOPING. A scoped admin must not learn the account names and
     * integration names of users outside their scope -- an inventory is a
     * disclosure surface even when every value in it is a hash. The tri-state
     * here is the trap, and it is the one this codebase has been bitten by
     * before: allInScopeIDs() returns [] BOTH for "this user is unscoped"
     * and for "this user is in no site", which mean opposite things. So
     * isUnscoped() is asked FIRST, and only after it says no does an empty
     * list get read as "sees nothing".
     *
     * @param int $actingUserID Whose visibility applies.
     *
     * @return array Rows: id, userID, userName, name, enabled, createdTime,
     *               createdBy, lastUsed. Empty when the user may see none.
     */
    public function visibleTo($actingUserID)
    {
        $actingUserID = (int)$actingUserID;
        $where = '';

        if (!SiteScope::isUnscoped($actingUserID)) {
            $ids = SiteScope::allInScopeIDs('user', $actingUserID);
            if (count($ids) < 1) {
                // Scoped, and in scope of nothing. Deny rather than fall
                // through to an unfiltered query -- the empty list means
                // "no users", not "no filter".
                return [];
            }
            $where = ' WHERE `t`.`atUserID` IN ('
                . implode(',', array_map('intval', $ids))
                . ')';
        }

        // LEFT JOIN, not INNER: a token whose owner has gone is exactly the
        // row an administrator most needs to see. resolve() already refuses
        // such a token, but refusing it silently and never showing it would
        // leave a credential nobody can find in order to delete it.
        $sql = 'SELECT `t`.`atID`, `t`.`atUserID`, `t`.`atName`,'
            . ' `t`.`atEnabled`, `t`.`atCreatedTime`, `t`.`atCreatedBy`,'
            . ' `t`.`atLastUsed`, `u`.`uName`'
            . ' FROM `apiTokens` `t`'
            . ' LEFT JOIN `users` `u` ON `u`.`uID` = `t`.`atUserID`'
            . $where
            . ' ORDER BY `u`.`uName` ASC, `t`.`atCreatedTime` DESC';

        $rows = self::$DB->query($sql)->fetch('', 'fetch_all')->get();

        $out = [];
        foreach ((array)$rows as $row) {
            $out[] = [
                'id' => (int)$row['atID'],
                'userID' => (int)$row['atUserID'],
                // The owner is gone but the token is not. Named rather than
                // blanked so the row is actionable.
                'userName' => (string)($row['uName'] ?? '') !== ''
                    ? (string)$row['uName']
                    : _('(deleted user)'),
                'name' => (string)$row['atName'],
                'enabled' => '1' === (string)$row['atEnabled'],
                'createdTime' => (string)$row['atCreatedTime'],
                'createdBy' => (string)$row['atCreatedBy'],
                'lastUsed' => trim((string)$row['atLastUsed'])
            ];
        }
        return $out;
    }

    /**
     * One token, but only if the acting user is allowed to touch it.
     *
     * The central pane posts ids from a form, and a form is an untrusted
     * list. Every mutation goes through this rather than loading the posted
     * id directly, so a scoped admin cannot disable or delete a credential
     * belonging to a user they cannot even see.
     *
     * @param int $tokenID      The token.
     * @param int $actingUserID Whose visibility applies.
     *
     * @return APIToken|null The token, or null when out of scope/absent.
     */
    public function visibleToken($tokenID, $actingUserID)
    {
        $tokenID = (int)$tokenID;
        if ($tokenID < 1) {
            return null;
        }
        $token = self::getClass('APIToken', $tokenID);
        if (!$token->isValid()) {
            return null;
        }
        $actingUserID = (int)$actingUserID;
        if (SiteScope::isUnscoped($actingUserID)) {
            return $token;
        }
        $ids = SiteScope::allInScopeIDs('user', $actingUserID);
        // Same tri-state as visibleTo(): scoped with an empty list sees
        // nothing, so in_array against [] correctly denies.
        if (!in_array((int)$token->get('userID'), $ids, true)) {
            return null;
        }
        return $token;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\APITokenManager', 'APITokenManager');
