<?php
class Initiator
{
	/** __construct()
		Tells the initial call to load all the calls files.
	*/
	public function __construct()
	{
		spl_autoload_register(array($this,'loader'));
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
			if (!version_compare(phpversion(), '5.2.1', '>='))
				throw new Exception('FOG Requires PHP v5.2.1 or higher.  You have PHP v'.phpversion());
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
	/** loader($className)
		Loads the class files as they're needed.
	*/
	private function loader($className) 
	{
		$plugPaths = array_filter(glob(BASEPATH . '/lib/plugins/*'), 'is_dir');
		$paths = array(BASEPATH . '/lib/fog', BASEPATH . '/lib/db', BASEPATH . '/lib/pages');
		$paths = array_merge((array)$paths,(array)$plugPaths);
		foreach ($paths as $path)
		{
			$fileName = $className . '.class.php';
			$filePath = rtrim($path, '/') . '/' . $fileName;
			if (!class_exists($className) && file_exists($filePath))
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
// Hook Manager - Init & Load Hooks
$HookManager = new HookManager();
$HookManager->load();
// Database Load initiator
$DatabaseManager = new DatabaseManager();
$DB = $FOGCore->DB = $DatabaseManager->connect()->DB;
$Init::endInit();
