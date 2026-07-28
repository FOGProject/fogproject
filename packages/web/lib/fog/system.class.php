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
        define('FOG_VERSION', '1.6.0-beta.3028');
        define('FOG_CHANNEL', 'Beta');
        define('FOG_SCHEMA', 318);
        define('FOG_BCACHE_VER', 268);
        define('FOG_CLIENT_VERSION', '0.13.0');
        // FOG_BASE_DIR is intentionally hardcoded here. Deriving it from a setting would
        // create a circular dependency (getSetting() needs the cache dir before DB is up).
        // See GH-850 for the future work to make this installer-driven.
        // Guarded because Initiator defines these earlier (the boot file-list cache
        // needs the cache dir before this class loads); this remains the canonical home.
        if (!defined('FOG_BASE_DIR')) {
            define('FOG_BASE_DIR', '/opt/fog');
        }
        if (!defined('FOG_CACHE_DIR')) {
            define('FOG_CACHE_DIR', FOG_BASE_DIR . DS . 'cache');
        }
    }
}
