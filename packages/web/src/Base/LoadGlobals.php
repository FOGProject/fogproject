<?php
/**
 * Loads our global values
 *
 * PHP version 7.4+
 *
 * @category LoadGlobals
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

use FOG\Auth\Identity;
use FOG\Db\DatabaseManager;
use FOG\Items\User;
use FOG\Net\FOGFTP;
use FOG\Net\FOGSSH;
use FOG\Net\FOGURLRequests;

/**
 * Loads our global values
 *
 * @category LoadGlobals
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LoadGlobals extends FOGBase
{
    /**
     * Used to tell if it has already been loaded.
     *
     * @var bool
     */
    private static $_loadedglobals;
    /**
     * Initialize the class.
     *
     * @return void
     */
    private static function _init()
    {
        if (self::$_loadedglobals) {
            return;
        }
        $GLOBALS['FOGFTP'] = new FOGFTP();
        $GLOBALS['FOGSSH'] = new FOGSSH();
        $GLOBALS['FOGCore'] = new FOGCore();
        DatabaseManager::establish();
        $GLOBALS['DB'] = DatabaseManager::getDB();
        if (!$GLOBALS['DB']) {
            return;
        }
        $GLOBALS['HookManager'] = new HookManager();
        $GLOBALS['EventManager'] = new EventManager();
        $GLOBALS['FOGURLRequests'] = new FOGURLRequests();
        FOGCore::setEnv();
        $userID = 0;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $userID = isset($_SESSION['FOG_USER']) ? (int)$_SESSION['FOG_USER'] : 0;
        }
        $GLOBALS['currentUser'] = new User($userID);
        $GLOBALS['HookManager']->load();
        $GLOBALS['EventManager']->load();
        // Impersonation binds the MASK over the real user, and it has to
        // happen here rather than three lines up: Identity::bind() rechecks
        // the subset tests on every request, those expand permission
        // wildcards against Authorization::registry(), and the registry is
        // core-only until PERMISSION_REGISTRY_DATA has had listeners to fire
        // at. Bound any earlier, an administrator's `oidc.*` would expand to
        // nothing while the target's literal `oidc.view` survived, and every
        // legal impersonation on an install with plugin permissions would be
        // refused. See ADR 0033.
        //
        // $_SESSION['FOG_USER'] still holds the REAL administrator either
        // way; this reassignment is what every "what does this user see"
        // read follows, because FOGBase binds self::$FOGUser as a reference
        // to this global.
        Identity::bind();
        // A vestigial copy of the configure/authorize/requestClientInfo
        // dispatch used to sit here, guarded by isset($sub) on a variable
        // this scope never declared -- so it has never run, on this branch
        // or on 1.5. That was load-bearing. Initiator::startInit() populates
        // the global $sub from GET/POST *before* base.inc.php constructs
        // LoadGlobals, so adding the `global $sub;` that the isset() invites
        // would have made the branch live and answered all three subs with a
        // dashboard render plus exit -- pre-empting FOGPage::_init(), which
        // dispatches exactly these three to their real handlers
        // (FOGPage::requestClientInfo() and friends). Removed rather than
        // commented so the trap cannot be sprung by tidying it.
        self::$_loadedglobals = true;
    }
    /**
     * Initializes directly.
     *
     * @return void
     */
    public function __construct()
    {
        self::_init();
        parent::__construct();
    }
}
