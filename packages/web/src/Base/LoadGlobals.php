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

use FOG\DashboardPage;
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
        $subs = [
            'configure',
            'authorize',
            'requestClientInfo'
        ];
        if (in_array(isset($sub) ? $sub : '', $subs)) {
            new DashboardPage();
            unset($subs);
            exit;
        }
        self::$_loadedglobals = true;
        unset($subs);
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\LoadGlobals', 'LoadGlobals');
