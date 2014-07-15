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
		$this->FOGFTP = $GLOBALS['FOGFTP'];
		$this->FOGCore = $GLOBALS['FOGCore'];
		$this->DB = $GLOBALS['DB'];
		$this->FOGUser = $GLOBALS['currentUser'];
		$this->HookManager = $GLOBALS['HookManager'];
		// Language Setup
		$this->foglang = $GLOBALS['foglang'];
	}
	/** fatalError($txt, $data = array())
		Fatal error in the case something went wrong.
		Prints to the screen so it can be easily seen.
	*/
	public function fatalError($txt, $data = array())
	{
		if (!preg_match('#/service/#', $_SERVER['PHP_SELF']) && !FOGCore::isAJAXRequest())
		{
			printf('<div class="debug-error">FOG FATAL ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			flush();
			exit;
		}
	}
	
	// Error - results in FOG halting with an error message
	/** error($txt, $data = array())
		Prints to the screen in case of error.  Same as above it seems.
	*/
	public function error($txt, $data = array())
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
		}
	}
	// Info - message is shown if info is enabled for that class
	/** info($txt, $data = array())
		Prints additional information for the user.
	*/
	public function info($txt, $data = array())
	{
		if ($this->info === true && !FOGCore::isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
		{
			printf('<div class="debug-info">FOG INFO: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			flush();
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
	public function toString()
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
			$r = new ReflectionClass($class);
			return $r->newInstanceArgs($args);
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
	public function getFTPByteSize($StorageNode,$file)
	{
		try
		{
			if (!$StorageNode || !$StorageNode->isValid())
				throw new Exception('No Storage Node');
			$this->FOGFTP->set('username',$StorageNode->get('user'))
						 ->set('password',$StorageNode->get('pass'))
						 ->set('host',$StorageNode->get('ip'));
			if (!$this->FOGFTP->connect())
				throw new Exception("Can't connect to node.");
			$size = $this->formatByteSize((double)$this->FOGFTP->size($file));
		}
		catch (Exception $e)
		{
			$this->FOGFTP->close();
			return $e->getMessage();
		}
		$this->FOGFTP->close();
		return $size;
	}
	/* 
	* formatByteSize
	* @param $size the size in byptes to format
	* @return $size retunres the size formatted neatly.
	*/
	public function formatByteSize($size)
	{
		$kbyte = 1024;
		$mbyte = $kbyte * $kbyte;
		$gbyte = $mbyte * $kbyte;
		$tbyte = $gbyte * $kbyte;
		$pbyte = $tbyte * $kbyte;
		$ebyte = $pbyte * $kbyte;
		$zbyte = $ebyte * $kbyte;
		$ybyte = $zbyte * $kbyte;
		if ($size < $kbyte)
			$Size = sprintf('%3.2f iB',$size);
		if ($size >= $kbyte)
			$Size = sprintf('%3.2f KiB',$size/$kbyte);
		if ($size >= $mbyte)
			$Size = sprintf('%3.2f MiB',$size/$mbyte);
		if ($size >= $gbyte)
			$Size = sprintf('%3.2f GiB',$size/$gbyte);
		if ($size >= $tbyte)
			$Size = sprintf('%3.2f TiB',$size/$tbyte);
		if ($size >= $pbyte)
			$Size = sprintf('%3.2f PiB',$size/$pbyte);
		if ($size >= $ebyte)
			$Size = sprintf('%3.2f EiB',$size/$ebyte);
		if ($size >= $zbyte)
			$Size = sprintf('%3.2f ZiB',$size/$zbyte);
		if ($size >= $ybyte)
			$Size = sprintf('%3.2f YiB',$size/$ybyte);
		return $Size;
	}
	/*
	* Inserts a new key/value before the key in the array.
	*
	* @param $key
	*   The key to insert before.
	* @param $array
	*   An array to insert in to.
	* @param $new_key
	*   The key to insert.
	* @param $new_value
	*   An value to insert.
	*
	* @return
	*   The new array if the key exists, FALSE otherwise.
	*
	* @see array_insert_after()
	*/
	public function array_insert_before($key, array &$array, $new_key, $new_value)
	{
		if (array_key_exists($key, $array)) 
		{
			$new = array();
			foreach ($array as $k => $value)
			{
				if ($k === $key)
					$new[$new_key] = $new_value;
				$new[$k] = $value;
			}
			return $new;
		}
		return false;
	}
	/*
	* Inserts a new key/value after the key in the array.
	*
	* @param $key
	*   The key to insert after.
	* @param $array
	*   An array to insert in to.
	* @param $new_key
	*   The key to insert.
	* @param $new_value
	*   An value to insert.
	*
	* @return
	*   The new array if the key exists, FALSE otherwise.
	*
	* @see array_insert_before()
	*/
	public function array_insert_after($key, array &$array, $new_key, $new_value)
	{
		if (array_key_exists($key, $array)) 
		{
			$new = array();
			foreach ($array as $k => $value)
			{
				$new[$k] = $value;
				if ($k === $key)
					$new[$new_key] = $new_value;
			}
			return $new;
		}
		return false;
	}
}
