<?php

/**
 * PHPStan-only stub for the constants FOG defines at runtime.
 *
 * Two sources, neither visible to static analysis:
 *   - commons/config.class.php, written by the installer and not in git
 *     (DATABASE_*, STORAGE_*, UDPSENDERPATH, ...)
 *   - define() calls made conditionally at boot in commons/init.php,
 *     commons/base.inc.php and src/Base/System.php (BASEPATH, FOG_*).
 *
 * Values are placeholders; only the TYPE matters to PHPStan. Add a constant
 * here when a new one starts being read before it is statically defined.
 *
 * Never loaded by FOG itself -- referenced only from phpstan.neon.
 */

define('BASEPATH', '');
define('CAPTURERESIZEPCT', '');
define('CHECKIN_TIMEOUT', 0);
define('DATABASE_HOST', '');
define('DATABASE_NAME', '');
define('DATABASE_PASSWORD', '');
define('DATABASE_TYPE', '');
define('DATABASE_USERNAME', '');
define('FOG_BASE_DIR', '');
define('FOG_BCACHE_VER', 0);
define('FOG_CACHE_DIR', '');
define('FOG_CAPTUREIGNOREPAGEHIBER', '');
define('FOG_CHANNEL', '');
define('FOG_CLIENT_VERSION', '');
define('FOG_CSP_NONCE', '');
define('FOG_JPGRAPH_VERSION', '');
define('FOG_LOG_DIR', '');
define('FOG_MULTICAST_MAX_SESSIONS', '');
define('FOG_PLUGIN_DIR', '');
define('FOG_REPORT_DIR', '');
define('FOG_SCHEMA', 0);
define('FOG_SCHEMA_INSTALL_TOKEN', '');
define('FOG_SESSION_DIR', '');
define('FOG_VERSION', '');
define('MEMTEST_KERNEL', '');
define('NFS_ETH_MONITOR', '');
define('PXE_IMAGE', '');
define('PXE_KERNEL', '');
define('PXE_KERNEL_RAMDISK', '');
define('SNAPINDIR', '');
define('STORAGE_BANDWIDTHPATH', '');
define('STORAGE_DATADIR', '');
define('STORAGE_DATADIR_CAPTURE', '');
define('STORAGE_FTP_PASSWORD', '');
define('STORAGE_FTP_USERNAME', '');
define('STORAGE_HOST', '');
define('STORAGE_INTERFACE', '');
define('TFTP_FTP_PASSWORD', '');
define('TFTP_FTP_USERNAME', '');
define('TFTP_HOST', '');
define('TFTP_PXE_KERNEL_DIR', '');
define('UDPCAST_INTERFACE', '');
define('UDPCAST_STARTINGPORT', '');
define('UDPSENDERPATH', '');
define('USER_MINPASSLENGTH', '');
define('USE_SLOPPY_NAME_LOOKUPS', '');
define('WEBROOT', '');
define('WEB_HOST', '');
define('WOL_HOST', '');
define('WOL_INTERFACE', '');
define('WOL_PATH', '');
