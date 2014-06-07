<?php
/**	Class Name: FOGBase
	The "foundation" of the FOG GUI system.
	This File is the base of all of the FOG GUI/Tasks systems.
	Please limit modification to this file as you may not know
	what will break with you editing it.
*/
abstract class FOGBase
{
	// Debug & Info
	/** Standardizes the debug as an abstract variable for use later on. */
	public $debug = false;
	/** Prepares the information if you should want more info. */
	public $info = false;
	// Class variables
	/** Sets the $this->FOGCore/$FOGCore calls in other classes. */
	public $FOGCore;
	/** Set the $this->DB/$DB calls in other files. */
	public $DB;
	/** Sets the $this->HookManager/$HookManager calls in other files. */
	public $HookManager;
	/** Sets the $this->FOGUser/$FOGUser calls in other files. */
	public $FOGUser;
	//Language Variable
	/** Sets the language variable for other files. */
	public $foglang; 
	// LEGACY
	/** Legacy calls for $db/$conn */
	public $db;
	/** Legacy calls for $db/$conn */
	public $conn;
	// isLoaded counter
	/** sets the "isLoaded" variable */
	protected $isLoaded = array();
	// Construct
	/** __construct()
	 FOGBase's constructor so variables that are needed
	 get passed properly as many of them are the same
	 anyway.
	 FOGCore gives access to the FOGCore class.
	 DB gives access to the DB Class as a variable.
	 HookManager gives access to the HookManager class.
	 FOGUser gives access to the FOGUser class.
	 FOGFTP not really needed here, but later is useful.

	 foglang is new, but meant to be the holder for all things
	 that need to be translated to other languages.  In its infancy
	 right now.
	*/
	public function __construct()
	{
		// Class setup
		$this->FOGCore = $GLOBALS['FOGCore'];
		$this->DB = $this->FOGCore->DB;
		$this->HookManager = $GLOBALS['HookManager'];
		$this->FOGUser = $GLOBALS['currentUser'];
		$this->FOGFTP = $GLOBALS['FOGFTP'];
		// Language Setup
		$this->foglang = $GLOBALS['foglang'];
		// LEGACY
		$this->db = $this->FOGCore->DB;
		$this->conn = $GLOBALS['conn'];
		//printf('Creating Class: %s', get_class($this));
	}
	// Error - results in FOG halting with an error message
	/** fatalError($txt, $data = array())
		Fatal error in the case something went wrong.
		Prints to the screen so it can be easily seen.
	*/
	public function fatalError($txt, $data = array())
	{
		//if (!$this->isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
		if (!preg_match('#/service/#', $_SERVER['PHP_SELF']) && !FOGCore::isAJAXRequest())
		{
			printf('<div class="debug-error">FOG FATAL ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			flush();
			exit;
		}
		// TODO: Log to Database
	}
	
	// Error - results in FOG halting with an error message
	/** error($txt, $data = array())
		Prints to the screen in case of error.  Same as above it seems.
	*/
	public static function error($txt, $data = array())
	{
		if ((((isset($this->debug)) && $this->debug === true)) && !preg_match('#/service/#', $_SERVER['PHP_SELF']) && !FOGCore::isAJAXRequest())
		{
			printf('<div class="debug-error">FOG ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			flush();
		}
	}
	// Debug - message is shown if debug is enabled for that class
	/** debug($txt, $data=array())
		Prints debug information for the use.
	*/
	public function debug($txt, $data = array())
	{
		if ((!isset($this) || (isset($this->debug) && $this->debug === true)) && !FOGCore::isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
		{
			printf('<div class="debug-error">FOG DEBUG: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			flush();
			//ob_flush();
		}
	}
	// Info - message is shown if info is enabled for that class
	/** info($txt, $data = array())
		Prints additional information for the user.
	*/
	public function info($txt, $data = array())
	{
		//printf('Info: %s', ($this->info === true ? 'true' : 'false'));
		
		// !isset gets used when a call is made statically. i.e. FOGCore::info('foo bah');
		//if ((!isset($this) || (isset($this->info) && $this->info === true)) && !FOGCore::isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
		if ($this->info === true && !FOGCore::isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
		{
			printf('<div class="debug-info">FOG INFO: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			flush();
			//ob_flush();
		}
	}
	/** __toString()
		Returns data as a string.
	*/
	public function __toString()
	{
		return (string)get_class($this);
	}
	/** toString()
		Returns data as a string.
	*/
	function toString()
	{
		return $this->__toString();
	}
	/** isLoaded($key)
		This sets the isLoaded flag.  If a key is loaded, it's true, otherwise false.
		It's used in the primary class files to check if fields are loaded.
	*/
	public function isLoaded($key)
	{
		$result = (isset($this->isLoaded[$key]) ? $this->isLoaded[$key] : 0);
		$this->isLoaded[$key]++;
		return ($result ? $result : false);
	}
	/** getClass($class)
		Used primarily with FOGCore to get the classes by name.
	*/
	public function getClass($class)
	{
		$args = func_get_args();
		array_shift($args);
		if (count($args))
		{
			// TODO: Make this work
			// http://au.php.net/ReflectionClass
			$r = new ReflectionClass($class);
			return $r->newInstanceArgs($args);
			//return new $class((count($args) === 1 ? $args[0] : $args));
		}
		else
			return new $class();
	}
	/** endsWith($str,$sub)
		Returns true if the sub and str match the ending stuff.
	*/
	public function endsWith($str,$sub)
	{
		return (substr($str,strlen($str)-strlen($sub)) === $sub);
	}
}
