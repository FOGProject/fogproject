<?php
/**
* Class Name: Config
* Initializes default settings.
* Most notably the sql connection.
*/
class Config
{
	/**
	* Calls the required functions to define the settings.
	* method db_settings()
	* method svc_setting()
	* method init_setting()
	*/
	public function __construct()
	{
		self::db_settings();
		self::svc_setting();
		self::init_setting();
	}
	/**
	* db_settings();
	* Defines the database settings for FOG
	* @return void
	*/
	private static function db_settings()
	{
		define('DATABASE_TYPE',		'mysql');	// mysql or oracle
		define('DATABASE_HOST',		'10.0.0.10');
		define('DATABASE_NAME',		'fog');
		define('DATABASE_USERNAME',		'root');
		define('DATABASE_PASSWORD',		'');
	}
	/**
	* svn_setting()
	* Defines the service settings.
	* (e.g. FOGMulticastManager,
	*	    FOGScheduler,
	*		FOGImageReplicator)
	* @return void
	*/
	private static function svc_setting()
	{
		define( "UDPSENDERPATH", "/usr/local/sbin/udp-sender" );
		define( "MULTICASTLOGPATH", "/opt/fog/log/multicast.log" );
		define( "MULTICASTDEVICEOUTPUT", "/dev/tty2" );
		define( "MULTICASTSLEEPTIME", 10 );
		define( "MULTICASTINTERFACE", "eth0" );
		define( "UDPSENDER_MAXWAIT", null );
		define( "LOGMAXSIZE", "1000000");
		define( "REPLICATORLOGPATH", "/opt/fog/log/fogreplicator.log" );
		define( "REPLICATORDEVICEOUTPUT", "/dev/tty3" );
		define( "REPLICATORSLEEPTIME", 600 );
		define( "REPLICATORIFCONFIG", "/sbin/ifconfig" );
		define( "SCHEDULERLOGPATH", "/opt/fog/log/fogscheduler.log" );
		define( "SCHEDULERDEVICEOUTPUT", "/dev/tty4" );
		define( "SCHEDULERWEBROOT", "/data/mastaweb/var/www/fog" );
		define( "SCHEDULERSLEEPTIME", 60 );
	}
	/**
	* init_setting()
	* Initial values if fresh install are set here
	* NOTE: These values are only used on initial
	* installation to set the database values.
	* If this is an upgrade, they do not change
	* the values within the Database.
	* Please use FOG Configuration->FOG Settings
	* to change these values after everything is
	* setup.
	* @return void
	*/
	private static function init_setting()
	{
		define('TFTP_HOST', '10.0.0.10');
		define('TFTP_FTP_USERNAME', 'fog');
		define('TFTP_FTP_PASSWORD', 'password');
		define('TFTP_PXE_KERNEL_DIR', BASEPATH . WEB_ROOT .'service/ipxe/');
		define('PXE_KERNEL', 'bzImage');
		define('PXE_KERNEL_RAMDISK',127000);
		define('USE_SLOPPY_NAME_LOOKUPS',true);
		define('MEMTEST_KERNEL', 'memtest');
		define('PXE_IMAGE', 'init.xz');
		define('PXE_IMAGE_DNSADDRESS', '10.0.0.10');
		define('STORAGE_HOST', '10.0.0.10');
		define('STORAGE_FTP_USERNAME', 'fog');
		define('STORAGE_FTP_PASSWORD', 'password');
		define('STORAGE_DATADIR', '/images/');
		define('STORAGE_DATADIR_UPLOAD', '/images/dev/');
		define('STORAGE_BANDWIDTHPATH', '/fog/status/bandwidth.php');
		define('UPLOADRESIZEPCT',5);
		define('WEB_HOST', '10.0.0.10');
		define('SNAPINDIR', '/opt/fog/snapins/');
		define('QUEUESIZE', '20');
		define('CHECKIN_TIMEOUT',600);
		define('USER_MINPASSLENGTH',4);
		define('USER_VALIDPASSCHARS', '1234567890ABCDEFGHIJKLMNOPQRSTUVWZXYabcdefghijklmnopqrstuvwxyz_()^!#');
		define('NFS_ETH_MONITOR', 'eth0');
		define('UDPCAST_INTERFACE', 'eth0');
		define('UDPCAST_STARTINGPORT',63100);					//Mustbeanevennumber!recommendedbetween49152to65535
		define('FOG_MULTICAST_MAX_SESSIONS',64);
		define('FOG_JPGRAPH_VERSION', '2.3');
		define('FOG_REPORT_DIR', './reports/');
		define('FOG_THEME', 'blackeye/blackeye.css');
		define('FOG_UPLOADIGNOREPAGEHIBER',true);
	}
}
