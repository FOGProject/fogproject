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
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\APITokenManager', 'APITokenManager');
