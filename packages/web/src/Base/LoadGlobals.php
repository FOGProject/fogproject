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

use FOG\Db\DatabaseManager;
use FOG\Items\User;
use FOG\Net\FOGFTP;
use FOG\Net\FOGSSH;

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
        $GLOBALS['HookManager'] = FOGCore::getClass('HookManager');
        $GLOBALS['EventManager'] = FOGCore::getClass('EventManager');
        $GLOBALS['FOGURLRequests'] = FOGCore::getClass('FOGURLRequests');
        FOGCore::setEnv();
        $userID = 0;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $userID = isset($_SESSION['FOG_USER']) ? (int)$_SESSION['FOG_USER'] : 0;
        }
        $GLOBALS['currentUser'] = new User($userID);
        $GLOBALS['HookManager']->load();
        $GLOBALS['EventManager']->load();
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
