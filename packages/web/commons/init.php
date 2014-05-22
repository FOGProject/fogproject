<?php
class Initiator
{
	public $HookPaths,$FOGPaths;
	/** __construct()
		Tells the initial call to load all the calls files.
	*/
	public function __construct()
	{
		$plugPaths = array_filter(glob(BASEPATH . '/lib/plugins/*'), 'is_dir');
		foreach($plugPaths AS $plugPath)
		{
			$plug_class[] = $plugPath.'/class/';
			$plug_hook[] = $plugPath.'/hooks/';
		}
		$FOGPaths = array(BASEPATH . '/lib/fog/', BASEPATH . '/lib/db/', BASEPATH . '/lib/pages/');
		$HookPaths = array(BASEPATH . '/lib/hooks/');
		$this->FOGPaths = array_merge((array)$FOGPaths,(array)$plug_class);
		$this->HookPaths = array_merge((array)$HookPaths,(array)$plug_hook);
		$GLOBALS['HookPaths'] = $this->HookPaths;
		spl_autoload_register(array($this,'FOGLoader'));
	}
	public function __destruct()
	{
		spl_autoload_unregister(array($this,'FOGLoader'));
	}
	/** startInit()
		Starts the initiation of the environment.
	*/
	public static function startInit()
	{
		set_time_limit(0);
		@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
		@header('Cache-Control: no-cache');
		session_cache_limiter('no-cache');
		session_start();
		@set_magic_quotes_runtime(0);
		self::verCheck();
		self::extCheck();
	}
	/** verCheck()
		Checks the version information is compatible with our
		FOG system.
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
		Checks that any required extensions are installed.
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
		Calls the params at the end of the init.
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
	}
	/** FOGLoader($className)
		Loads the class files as they're needed.
	*/
	private function FOGLoader($className) 
	{
		foreach($this->FOGPaths AS $path)
		{
			$filePath = (!class_exists($className) && file_exists($path.$className.'.class.php') ? $path.$className.'.class.php' : null);
			if ($filePath)
				include($filePath);
		}
	}
}
// Sanitize valid input variables
foreach(array('node','sub','printertype','id','sub','crit','sort','confirm','tab') AS $x)
	$$x = (isset($_REQUEST[$x]) ? addslashes($_REQUEST[$x]) : '');
unset($x);
$Init = new Initiator();
$Init::startInit();
// Core
$FOGFTP = new FOGFTP();
$FOGCore = new FOGCore();
// Database Load initiator
$DatabaseManager = new DatabaseManager();
$DB = $FOGCore->DB = $DatabaseManager->connect()->DB;
// HookManager
$HookManager = new HookManager();
$HookManager->load();
$Init::endInit();
