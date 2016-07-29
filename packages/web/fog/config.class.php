<?php
class Config {
    /** @function __construct() Calls the required functions to define items
     * @return void
     */
    public function __construct() {
        self::db_settings();
        self::svc_setting();
        if ($_REQUEST['node'] == 'schema') self::init_setting();
    }
    /** @function db_settings() Defines the database settings for FOG
     * @return void
     */
    private static function db_settings() {
        define('DATABASE_TYPE','mysql'); // mysql or oracle
        define('DATABASE_HOST','10.0.1.2');
        define('DATABASE_NAME','fog');
        define('DATABASE_USERNAME','fogstorage');
        define('DATABASE_PASSWORD',"fs39330822929");
    }
    /** @function svc_setting() Defines the service settings
     * (e.g. FOGMulticastManager)
     * @return void
     */
    private static function svc_setting() {
        define('UDPSENDERPATH','/usr/local/sbin/udp-sender');
        define('MULTICASTINTERFACE','eno16777728');
        define('UDPSENDER_MAXWAIT',null);
    }
    /** @function init_setting() Initial values if fresh install are set here
     * NOTE: These values are only used on initial
     * installation to set the database values.
     * If this is an upgrade, they do not change
     * the values within the Database.
     * Please use FOG Configuration->FOG Settings
     * to change these values after everything is
     * setup.
     * @return void
     */
    private static function init_setting() {
        define('TFTP_HOST', "10.0.7.1");
        define('TFTP_FTP_USERNAME', "fog");
        define('TFTP_FTP_PASSWORD', "My7s/+7LqorMg3fqUML8SYBbwPfeQDr35vZrVFlFzrw=");
        define('TFTP_PXE_KERNEL_DIR', "/var/www/html/fog//service/ipxe/");
        define('PXE_KERNEL', 'bzImage');
        define('PXE_KERNEL_RAMDISK',127000);
        define('USE_SLOPPY_NAME_LOOKUPS',true);
        define('MEMTEST_KERNEL', 'memtest.bin');
        define('PXE_IMAGE', 'init.xz');
        define('PXE_IMAGE_DNSADDRESS', "");
        define('STORAGE_HOST', "10.0.7.1");
        define('STORAGE_FTP_USERNAME', "fog");
        define('STORAGE_FTP_PASSWORD', "My7s/+7LqorMg3fqUML8SYBbwPfeQDr35vZrVFlFzrw=");
        define('STORAGE_DATADIR', '/images/');
        define('STORAGE_DATADIR_UPLOAD', '/images/dev');
        define('STORAGE_BANDWIDTHPATH', '/fog/status/bandwidth.php');
        define('STORAGE_INTERFACE','eno16777728');
        define('UPLOADRESIZEPCT',5);
        define('WEB_HOST', "10.0.7.1");
        define('WOL_HOST', "10.0.7.1");
        define('WOL_PATH', '/fog/wol/wol.php');
        define('WOL_INTERFACE', "eno16777728");
        define('SNAPINDIR', "/opt/fog/snapins/");
        define('QUEUESIZE', '10');
        define('CHECKIN_TIMEOUT',600);
        define('USER_MINPASSLENGTH',4);
        define('USER_VALIDPASSCHARS','1234567890ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_()^!#-');
        define('NFS_ETH_MONITOR', "eno16777728");
        define('UDPCAST_INTERFACE', "eno16777728");
        define('UDPCAST_STARTINGPORT', 63100 ); // Must be an even number! recommended between 49152 to 65535
        define('FOG_MULTICAST_MAX_SESSIONS',64);
        define('FOG_JPGRAPH_VERSION', '2.3');
        define('FOG_REPORT_DIR', './reports/');
        define('FOG_UPLOADIGNOREPAGEHIBER',true);
        define('FOG_DONATE_MINING', "0");
    }
}
