<?php
/**
 * Loads our global values
 *
 * PHP version 5
 *
 * @category LoadGlobals
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        $GLOBALS['DB'] = FOGCore::getClass('DatabaseManager')
            ->establish()
            ->getDB();
        $GLOBALS['FOGCore'] = new FOGCore();
        FOGCore::setSessionEnv();
        $GLOBALS['TimeZone'] = $_SESSION['TimeZone'];
        if (isset($_SESSION['FOG_USER'])) {
            $GLOBALS['currentUser'] = new User($_SESSION['FOG_USER']);
        } else {
            $GLOBALS['currentUser'] = new User(0);
        }
        $GLOBALS['HookManager'] = FOGCore::getClass('HookManager');
        $GLOBALS['HookManager']
            ->load();
        $GLOBALS['EventManager'] = FOGCore::getClass('EventManager');
        $GLOBALS['EventManager']
            ->load();
        $GLOBALS['FOGURLRequests'] = FOGCore::getClass('FOGURLRequests');
        $subs = array(
            'configure',
            'authorize',
            'requestClientInfo'
        );
        if (in_array($sub, $subs)) {
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
