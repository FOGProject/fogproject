<?php
/**
 * System, the basic system layout.
 *
 * PHP Version 5
 *
 * This just presents the system variables
 *
 * @category System
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * System, the basic system layout.
 *
 * @category System
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class System
{
    const PHP_MINIMUM = '5.5.0';
    const PHP_MAXIMUM = '9';
    /**
     * Checks the php version against what we require.
     *
     * @return void
     */
    private static function _versionCompare()
    {
        $msg = '';
        if (
            !(version_compare(PHP_VERSION, self::PHP_MINIMUM, '>=')
            && version_compare(PHP_VERSION, self::PHP_MAXIMUM, '<'))
        ) {
            $msg = _('You are currently running PHP Version')
                . ': '
                . PHP_VERSION
                . ', '
                . _('FOG Needs at least')
                . ': '
                . self::PHP_MINIMUM
                . ', '
                . _('and below')
                . ': '
                . self::PHP_MAXIMUM;
        }
        if ($msg) {
            die($msg);
        }
    }
    /**
     * Constructs the system variables.
     */
    public function __construct()
    {
        self::_versionCompare();
        define('FOG_VERSION', '1.6.0-beta.3157');
        define('FOG_CHANNEL', 'Beta');
        // Every added element of $this->schema in commons/schema.php must bump
        // this by one. It is what DatabaseManager and schemaNeedsDeploy() test
        // (`mySchema < FOG_SCHEMA`) to decide an update is outstanding; the
        // updater page's own `count($this->schema) <= mySchema` check only ever
        // runs once something has sent the admin (or the installer's token)
        // there. Leaving it behind therefore does not half-apply a step -- it
        // silently applies nothing at all, on every already-installed server,
        // while a fresh install still gets the step and looks fine. That is
        // what happened to schema step 323 (task type 25 -> mode=enrollsb):
        // added without this bump, so no existing 1.6 server would have picked
        // it up.
        define('FOG_SCHEMA', 323);
        define('FOG_BCACHE_VER', 272);
        define('FOG_CLIENT_VERSION', '0.13.0');
        // GH-959: iPXE lives in FOGProject/fog-ipxe and its binaries arrive as
        // a release asset. Pinned here rather than tracked as "latest" so a
        // given FOG release ships a known iPXE -- the installer uses this both
        // to pick the download and to check out the matching source when an
        // HTTPS install has to rebuild with its own CA.
        define('FOG_IPXE_VERSION', 'v2.0.0-fog.3');
        // GH-850: FOG_BASE_DIR is now installer-driven. Initiator loads
        // commons/fogpaths.php (written from the installer's $fogprogramdir)
        // before the autoloader runs, so in a normal boot these are already
        // defined by the time this class loads.
        //
        // It cannot come from a globalSetting: getSetting() needs the cache
        // dir, which is derived from this, before the DB is up.
        //
        // These stay here, guarded, as the last-resort defaults for any entry
        // point that reaches System without going through Initiator.
        if (!defined('FOG_BASE_DIR')) {
            define('FOG_BASE_DIR', '/opt/fog');
        }
        if (!defined('FOG_CACHE_DIR')) {
            define('FOG_CACHE_DIR', FOG_BASE_DIR . DS . 'cache');
        }
        if (!defined('FOG_LOG_DIR')) {
            define('FOG_LOG_DIR', FOG_BASE_DIR . DS . 'log');
        }
    }
}
