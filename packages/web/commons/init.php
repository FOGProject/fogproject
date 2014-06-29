<?php
/**
* \class Initiator
*
* Initiates the FOG System.
* @param $HookPaths The Hooks system paths to locate hooks.
* @param $FOGPaths The Main system paths to locate system files.
* @param $PagePaths The Page system paths to locate Pages.
* @param $plugPaths The plugin system paths to locate plugins and their
* pages, classes, and hooks.
*/
class Initiator
{
	public $HookPaths,$FOGPaths,$PagePaths, $plugPaths;
	/** __construct()
	* Tells the initial call to load all the calls files.
	* 
	* @method self::init_system() initiates the system class.
	* @method self::init_config() loads the configuration.
	* @param $this->plugPaths where to locate plugins.
	* @param $plugPath iterates through the plugin paths and
	* returns the related path individually.
	* @param $plug_class the array of plugins and the class folder.
	* @param $plug_hook the array of plugins and the hook folder.
	* @param $plug_page the array of plugins and the page folder.
	* @param $FOGPaths the default store for main system.
	* @param $HookPaths the default store to look for Hooks.
	* @param $PagePaths the default store to look for Pages.
	* @param $this->FOGPaths the merged store of plugins and default FOG system files.
	* @param $this->HookPaths the merged store of plugins and hook system files.
	* @param $this->PagePaths the merged store of plugins and page system files.
	* @function spl_autolaod_register(array($this,'FOGLoader')) the Autoloader function
	* to load the main system and plugin path information.
	* @function spl_autoload_register(array($this,'FOGPages')) the Autoloader function
	* to load the main pages and plugin pages information.
	* @function spl_autoload_register(array($this,'FOGHooks')) the Autoloader function
	* to load the main hooks and plugin hook information.
	* @return void
	*/
	public function __construct()
	{
		self::init_system();
		$this->plugPaths = array_filter(glob(BASEPATH . '/lib/plugins/*'), 'is_dir');
		foreach($this->plugPaths AS $plugPath)
		{
			$plug_class[] = $plugPath.'/class/';
			$plug_hook[] = $plugPath.'/hooks/';
			$plug_page[] = $plugPath.'/pages/';
		}
		$FOGPaths = array(BASEPATH . '/lib/fog/', BASEPATH . '/lib/db/');
		$HookPaths = array(BASEPATH . '/lib/hooks/');
		$PagePaths = array(BASEPATH . '/lib/pages/');
		$this->FOGPaths = array_merge((array)$FOGPaths,(array)$plug_class);
		$this->HookPaths = array_merge((array)$HookPaths,(array)$plug_hook);
		$this->PagePaths = array_merge((array)$PagePaths,(array)$plug_page);
		spl_autoload_register(array($this,'FOGLoader'));
		spl_autoload_register(array($this,'FOGPages'));
		spl_autoload_register(array($this,'FOGHooks'));
	}
	/**
	* __destruct()
	* Used to unload the autoload functions as needed.
	* @return void
	*/
	public function __destruct()
	{
		spl_autoload_unregister(array($this,'FOGLoader'));
		spl_autoload_unregister(array($this,'FOGPages'));
		spl_autoload_unregister(array($this,'FOGHooks'));
	}
	/**
	* init_system()
	* Load the system information.
	* @return void
	*/
	private static function init_system()
	{
		include('system.php');
		new System();
	}
	/** startInit()
	* Starts the initiation of the environment.
	* sanitizes global information.
	* calls method verCheck()
	* calls method extCheck()
	* @return void
	*/
	public static function startInit()
	{
		set_time_limit(0);
		@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
		@header('Cache-Control: no-cache');
		@session_cache_limiter('no-cache');
		@session_set_cookie_params(0);
		@session_start();
		@set_magic_quotes_runtime(0);
		self::verCheck();
		self::extCheck();
		// Sanitize valid input variables
		foreach(array('node','sub','printertype','id','sub','crit','sort','confirm','tab') AS $x)
		{
			global $$x;
			$$x = (isset($_REQUEST[$x]) ? addslashes($_REQUEST[$x]) : '');
		}
		unset($x);
	}
	/** verCheck()
	* Checks the php version information is compatible with our
	* FOG system.
	* exits if it's not compatible.
	* @return void
	*/
	private static function verCheck()
	{
		try
		{
			if (!version_compare(phpversion(), '5.3.0', '>='))
				throw new Exception('FOG Requires PHP v5.3.0 or higher.  You have PHP v'.phpversion());
		}
		catch (Exception $e)
		{
			print $e->getMessage();
			exit;
		}
	}
	/** extCheck()
	* Checks that any required extensions are installed.
	* exits if any are missing.
	* @return voide
	*/
	private static function extCheck()
	{
		$requiredExtensions = array('gettext');
		foreach($requiredExtensions AS $extension)
		{
			if (!in_array($extension, get_loaded_extensions()))
				$missingExtensions[] = $extension;
		}
		try
		{
			if (count((array)$missingExtensions))
				throw new Exception('Missing Extensions: '. implode(', ',$missingExtensions));
		}
		catch (Exception $e)
		{
			print $e->getMessage();
			exit;
		}
	}
	/** endInit()
	* Calls the params at the end of the init.
	* Set's the system locale
	* @return void
	*/
	public static function endInit()
	{
		// Locale
		if ($_SESSION['locale'])
		{
			putenv('LC_ALL='.$_SESSION['locale']);
			setlocale(LC_ALL, $_SESSION['locale']);
		}
		// Languages
		bindtextdomain('messages', 'languages');
		textdomain('messages');
		@session_write_close();
	}
	/** FOGLoader($className)
	* Loads the class files as they're needed.
	* @param $className the class to include as called.
	* @return void
	*/
	private function FOGLoader($className) 
	{
		foreach($this->FOGPaths AS $path)
			(!class_exists($className) && file_exists($path.$className.'.class.php') ? include($path.$className.'.class.php') : null);
	}
	/** FOGPages($className)
	* Loads the page files as they're needed.
	* @param $className the class to include as called.
	* @return void
	*/
	private function FOGPages($className)
	{
		foreach($this->PagePaths as $path)
			(!class_exists($className) && file_exists($path.$className.'.class.php') ? require_once($path.$className.'.class.php') : null);
	}
	/** FOGHooks($className)
	* Loads the hook files as they're needed.
	* @param $className the class to include as called.
	* @return void
	*/
	private function FOGHooks($className) 
	{
		global $HookManager;
		foreach($this->HookPaths AS $path)
			(!class_exists($className) && file_exists($path.$className.'.hook.php') ? include($path.$className.'.hook.php') : null);
	}
}
// Initialize everything.
$Init = new Initiator();
$Init::startInit();
$Config = new Config();
// Core
$FOGFTP = new FOGFTP();
$FOGCore = new FOGCore();
// Database Load initiator
$DatabaseManager = new DatabaseManager();
$DB = $FOGCore->DB = $DatabaseManager->connect()->DB;
// HookManager
$HookManager = new HookManager();
// FOGPageManager
$FOGPageManager = new FOGPageManager();
$Init::endInit();
