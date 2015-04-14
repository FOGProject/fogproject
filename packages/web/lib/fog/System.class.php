<?php
/**
* Setups of the system.
*/
class System
{
	/**
	* __construct()
	* method called default_values()
	* @return void
	*/
	public function __construct()
	{
		self::default_values();
	}
	/**
	* default_values()
	* initializes php and version information.
	* exits if php is not compatible.
	* @return void
	*/
	private static function default_values()
	{
		(ini_get('date.timezone') ? date_default_timezone_set(ini_get('date.timezone')) : date_default_timezone_set('UTC'));
		define('FOG_VERSION', '3250');
		define('FOG_SCHEMA', 174);
		define('FOG_SVN_REVISION', '$Revision: 2868 $');
		define('FOG_SVN_LAST_UPDATE', '$LastChangedDate: 2015-01-01 14:16:56 -0500 (Thu, 01 Jan 2015) $');
		define('FOG_CLIENT_VERSION', '0.7.2');
		define('PHP_VERSION_REQUIRED', '5.3.0');
		define('PHP_COMPATIBLE', version_compare(PHP_VERSION, PHP_VERSION_REQUIRED, '>='));
		define('SPACE_DEFAULT_STORAGE', '/images');
		// PHP: Version check
		if (PHP_COMPATIBLE === false)
		{
			die(sprintf(_('Your systems PHP version is not sufficient. You have version %s, version %s is required.'), PHP_VERSION, PHP_VERSION_REQUIRED));
			exit;
		}
	}
}
