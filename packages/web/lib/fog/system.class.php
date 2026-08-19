<?php
/**
 * System, the basic system layout.
 *
 * PHP version 7.4+
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
    const PHP_REQUIRED = '5.6.0';
    /**
     * Checks the php version against what we require.
     *
     * @return void
     */
    private static function _versionCompare()
    {
        $msg = '';
        if (false === version_compare(PHP_VERSION, self::PHP_REQUIRED, '>=')) {
            $msg = sprintf(
                '%s. %s %s, %s %s %s.',
                _('Your system PHP Version is not sufficient'),
                _('You have version'),
                PHP_VERSION,
                _('version'),
                self::PHP_REQUIRED,
                _('is required')
            );
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
        define('FOG_VERSION', '1.5.10.2294');
        define('FOG_SCHEMA', 280);
        define('FOG_BCACHE_VER', 143);
        define('FOG_CLIENT_VERSION', '0.13.0');
        // GH-959: iPXE lives in FOGProject/fog-ipxe and its binaries arrive as
        // a release asset. Pinned here rather than tracked as "latest" so a
        // given FOG release ships a known iPXE -- the installer uses this both
        // to pick the download and to check out the matching source when an
        // HTTPS install has to rebuild with its own CA.
        define('FOG_IPXE_VERSION', 'v2.0.0-fog.7');
        // GH-850: the base path is installer-driven. Initiator loads the
        // generated commons/fogpaths.php (written from the installer's
        // $fogprogramdir) before the autoloader runs, so in a normal boot this
        // is already defined by the time this class loads.
        //
        // It cannot come from a globalSetting: the Secure Boot helpers in
        // FOGPage need it, and a path used to locate the server's own files
        // has no business round-tripping through the database.
        //
        // This stays here, guarded, as the last-resort default for an install
        // predating fogpaths.php and for any entry point that reaches System
        // without going through Initiator. It is not decoration: the consumer
        // half of GH-850 was ported to this branch ahead of the producer half,
        // and with nothing defining the constant PHP 8 raised a fatal Error --
        // not a PHP 7 warning that degrades to the bareword string -- taking
        // every kernel/init update through the web UI down with an HTTP 500
        // (forum topic 18211, broken in 1.5.10.2179).
        if (!defined('FOG_BASE_DIR')) {
            define('FOG_BASE_DIR', '/opt/fog');
        }
    }
}
