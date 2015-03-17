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
	/** Sets the Variables to use later on. **/
	public $FOGCore, $DB, $Hookmanager, $FOGUser, $FOGPageManager, $foglang, $imagelink, $EventManager;
	// LEGACY
	/** Legacy calls for $db/$conn */
	public $db;
	/** Legacy calls for $db/$conn */
	public $conn;
	// isLoaded counter
	/** sets the "isLoaded" variable */
	protected $isLoaded = array();
	protected $searchPages = array();
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
		$this->FOGPageManager = $GLOBALS['FOGPageManager'];
		$this->EventManager = $GLOBALS['EventManager'];
		// Language Setup
		$this->foglang = $GLOBALS['foglang'];
		// Default TimeZone to use for date fields
		$this->TimeZone = $GLOBALS['TimeZone'];
		$this->imagelink = $GLOBALS['imagelink'];
		$this->searchPages = array('user','host','group','image','snapin','printer','tasks','hosts');
	}
	/** fatalError($txt, $data = array())
		Fatal error in the case something went wrong.
		Prints to the screen so it can be easily seen.
	*/
	public function fatalError($txt, $data = array())
	{
		if (!preg_match('#/service/#', $_SERVER['PHP_SELF']) && !FOGCore::isAJAXRequest())
		{
			print sprintf('<div class="debug-error">FOG FATAL ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
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
			print sprintf('<div class="debug-error">FOG ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
	}
	// Debug - message is shown if debug is enabled for that class
	/** debug($txt, $data=array())
		Prints debug information for the use.
	*/
	public function debug($txt, $data = array())
	{
		if ((!isset($this) || (isset($this->debug) && $this->debug === true)) && !FOGCore::isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
			print sprintf('<div class="debug-error">FOG DEBUG: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
	}
	// Info - message is shown if info is enabled for that class
	/** info($txt, $data = array())
		Prints additional information for the user.
	*/
	public function info($txt, $data = array())
	{
		if ((!isset($this) || (isset($this->info) && $this->info === true)) && !preg_match('#/service/#',$_SERVER['PHP_SELF']))
			print sprintf('<div class="debug-info">FOG INFO: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
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
	public function getClass($class,$data = '')
	{
		$args = func_get_args();
		array_shift($args);
		$r = new ReflectionClass($class);
		return (count($args) ? $r->newInstanceArgs($args) : $r->newInstance($data));
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
		$units = array('%3.2f iB','%3.2f KiB','%3.2f MiB','%3.2f GiB','%3.2f TiB','%3.2f PiB','%3.2f EiB','%3.2f ZiB','%3.2f YiB');
		for($i = 0; $size >= 1024 && $i < count($units) - 1; $i++)
			$size /= 1024;
		return sprintf($units[$i],round($size,2));
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
	/** removes specified key or keys (in array) from an array
	 * @param $key the key or set of keys to remove
	 * @param $array the array to keys from
	 * @return void
	 */
	public function array_remove($key, array &$array)
	{
		if (is_array($key))
		{
			foreach($key AS $val)
				unset($array[$val]);
		}
		else
		{
			foreach($array AS &$value)
			{
				if (is_array($value))
					$this->array_remove($key,$value);
			}
		}
	}
	/*
	* Generates a random string based on the length you pass.
	*
	* @param $length
	*   The length of the returned value you want.
	* @return
	*   The string randomized.
	*/
	public function randomString($length)
	{
		$chars = array_merge(range('a','z'),range('A','Z'),range(0,9));
		shuffle($chars);
		return implode(array_slice($chars,0,$length));
	}
	public function aesencrypt($data,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC)
	{

		// Below is if we ever figure out how to use asymmetric keys
		//if (!$pub_key = openssl_pkey_get_public($data))
		//	throw new Exception('#!ihc');
		//$a_key = openssl_pkey_get_details($pub_key);
		$iv_size = mcrypt_get_iv_size($enctype,$mode);
		// Generate a one time, secure and random key if the key hasn't been entered already
		if (!$key)
			$key = openssl_random_pseudo_bytes($iv_size,$cstrong);
		$iv = mcrypt_create_iv($iv_size,MCRYPT_DEV_URANDOM);
		$cipher = mcrypt_encrypt($enctype,$key,$data,$mode,$iv);
		return bin2hex($iv)."|".bin2hex($cipher);
		// return $a_key['bits'].'|'.$iv.base64_encode($cipher);
	}
	public function aesdecrypt($encdata,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC)
	{
		$iv_size = mcrypt_get_iv_size($enctype,$mode);
		$data = explode('|',$encdata);
		$iv = pack('H*',$data[0]);
		$encoded = pack('H*',$data[1]);
		$key = pack('H*',$data[2]);
		$decipher = mcrypt_decrypt($enctype,$key,$encoded,$mode,$iv);
		return $decipher;
	}
	/**
	* diff($start,$end)
	* Simply a function to return the difference of time between the start and end.
	* @param $start Translate the sent start time to DateTime format for easy differentials.
	* @param $end Translate the sent end time to Datetime format for easy differentials.
	* @return $interval->format('%H:%I:%S') returns the datetime in number of hours, minutes, and seconds it took to perform the task.
	*/
	public function diff($start,$end)
	{
		if (!$start instanceof DateTime)
			$start = $this->nice_date($start);
		if (!$end instanceof DateTime)
			$end = $this->nice_date($end);
		$Duration = $start->diff($end);
		return $Duration->format('%H:%I:%S');
	}
	/**
	* nice_date($Date)
	* Simply returns the date in DateTime Class format for easier use.
	* @param $Date the non-nice Date Sent.
	* @return $NiceDate returns the DateTime class for the current date.
	*/
	public function nice_date($Date = 'now',$utc = false)
	{
		$NiceDate = (!$utc ? new DateTime($Date,new DateTimeZone($this->TimeZone)) : new DateTime($Date,new DateTimeZone('UTC')));
		return $NiceDate;
	}
	/**
	* validDate($Date)
	* Simply returns if the date is valid or not
	* @param $Date the date, nice or not nice
	* @return return whether Date/Time is valid or not
	*/
	public function validDate($Date,$format = '')
	{
		if ($format == 'N')
			return ($Date instanceof DateTime ? ($Date->format('N') >= 0 && $Date->format('N') <= 7) : $Date >= 0 && $Date <= 7);
		if (!$Date instanceof DateTime)
			$Date = $this->nice_date($Date);
		if (!$format)
			$format = 'm/d/Y';
		return DateTime::createFromFormat($format,$Date->format($format));
	}
	/** formatTime($time, $format = '')
		format's time information.  If format is blank,
		formats based on current date to date sent.  Otherwise
		returns the information back based on the format requested.
	*/
	public function formatTime($time, $format = false, $utc = false)
	{
		if (!$time instanceof DateTime)
			$time = $this->nice_date($time,$utc);
		// Forced format
		if ($format)
			return $time->format($format);
		$weeks = array(
			'oneday' => array(1,-1),
			'curweek' => array(2,3,4,5,6,-2,-3,-4,-5,-6),
			'1week' => array(7,8,9,10,11,12,13,-7,-8,-9,-10,-11,-12,-13),
			'2weeks' => array(14,15,16,17,18,19,20,-14,-15,-16,-17,-18,-19,-20),
			'3weeks' => array(21,22,23,24,25,26,27,-21,-22,-23,-24,-25,-26,-27),
			'4weeks' => array(28,29,30,31,-28,-29,-30,-31),
		);
		$CurrTime = $this->nice_date('now',$utc);
		if ($time < $CurrTime)
			$TimeVal = $CurrTime->diff($time);
		if ($time > $CurrTime)
			$TimeVal = $time->diff($CurrTime);
		$Datediff = $TimeVal->d;
		$NoAfter = false;
		if ($TimeVal->y)
			$RetDate = $TimeVal->y.' year'.($TimeVal->y != 1 ? 's' : '');
		else if ($TimeVal->m)
			$RetDate = $TimeVal->m.' month'.($TimeVal->m != 1 ? 's' : '');
		else if ($time->format('Y-m-d') == $CurrTime->format('Y-m-d') || !$Datediff)
		{
			$RetDate = ($time > $CurrTime ? _('Runs') : _('Ran')).' '._('today, at ').$time->format('g:ia');
			$NoAfter = true;
		}
		else if (in_array($Datediff,$weeks['oneday']))
		{
			$RetDate = ($time > $CurrTime ? _('Tomorrow at ') : _('Yesterday at ')).$time->format('g:ia');
			$NoAfter = true;
		}
		else if (in_array($Datediff,$weeks['curweek']))
		{
			$RetDate = ($time > $CurrTime ? _('This') : _('Last')).' '.$time->format('l')._(' at ').$time->format('g:ia');
			$NoAfter = true;
		}
		else if (in_array($Datediff,$weeks['1week']))
		{
			$RetDate = ($time > $CurrTime ? _('Next week') : _('Last week')).' '.$time->format('l')._(' at ').$time->format('g:ia');
			$NoAfter = true;
		}
		else if (in_array($Datediff,$weeks['2weeks']))
			$RetDate = ($time > $CurrTime ? _('2 weeks from now') : _('2 weeks ago'));
		else if (in_array($Datediff,$weeks['3weeks']))
			$RetDate = ($time > $CurrTime ? _('3 weeks from now') : _('3 weeks ago'));
		else if (in_array($Datediff,$weeks['4weeks']))
			$RetDate = ($time > $CurrTime ? _('4 weeks from now') : _('4 weeks ago'));
		if ($time < $CurrTime && !$NoAfter)
			$RetDate .= ' ago';
		if ($time > $CurrTime && !$NoAfter)
			$RetDate .= ' from now';
		return $RetDate;
	}
	/** resetRequest()
	* Simply resets the request so data, even if invalid, will populate form.
	*/
	public function resetRequest()
	{
		$_REQUESTVARS = $_REQUEST;
		unset($_REQUEST);
		foreach((array)$_SESSION['post_request_vals'] AS $key => $val)
			$_REQUEST[$key] = $val;
		foreach((array)$_REQUESTVARS AS $key => $val)
			$_REQUEST[$key] = $val;
		unset($_SESSION['post_request_vals'], $_REQUESTVARS);
	}
	/** setRequest()
	* Simply sets the session Request variables as a session variable
	*/
	public function setRequest()
	{
		if (!$_SESSION['post_request_vals'] && $this->FOGCore->isPOSTRequest())
			$_SESSION['post_request_vals'] = $_REQUEST;
	}
	/** array_filter_recursive($input) 
	* @param $input the input to filter
	* clean up arrays recursively.
	*/
	public function array_filter_recursive(&$input,$keepkeys = false)
	{
		foreach($input AS &$value)
		{
			if (is_array($value))
				$value = $this->array_filter_recursive($value);
		}
		$input = array_filter($input);
		if (!$keepkeys)
			$input = array_values($input);
		return $input;
	}
	/** byteconvert($kilobytes)
	* @param $kilobytes
	* @return $kilobytes
	**/
	public function byteconvert($kilobytes)
	{
		return (($kilobytes / 8) * 1024);
	}
	/** certEncrypt($data)
	* @param $data the data to encrypt
	* @return $encrypt returns the encrypted data
	**/
	public function certEncrypt($data,$Host)
	{
		// Get the public key of the recipient
		if (!$Host || !$Host->isValid())
			throw new Exception('#!ih');
		if (!$Host->get('pub_key'))
			throw new Exception('#!ihc');
		return $this->aesencrypt($data,$this->hex2bin($Host->get('pub_key')));
		// Below is if we ever figure out an asymmetric system.
		if (!$pub_key = openssl_pkey_get_public($Host->get('pub_key')))
			throw new Exception('#!ihc');
		$a_key = openssl_pkey_get_details($pub_key);
		// Encrypt the data in small chunks and then combine and send it.
		$chunkSize = ceil($a_key['bits'] / 8) - 11;
		$output = '';
		while ($data)
		{
			$chunk = substr($data,0,$chunkSize);
			$data = substr($data,$chunkSize);
			$encrypt = '';
			if (!openssl_public_encrypt($chunk,$encrypt,$pub_key))
				throw new Exception('Failed to encrypt data');
			$output .= $encrypt;
		}
		openssl_free_key($pub_key);
		return base64_encode($output);
	}
	/** hex2bin($hex)
	* @param $hex
	* Function simple takes the data and transforms it into hexadecimal.
	* @return the hex coded data.
	*/
	public function hex2bin($hex)
	{
		if (function_exists('hex2bin'))
			$sbin = hex2bin($hex);
		else
		{
			$n = strlen($hex);
			$i = 0;
			while ($i<$n)
			{
				$a = substr($hexstr,$i,2);
				$c = pack("H*",$a);
				if ($i == 0)
					$sbin = $c;
				else
					$sbin .= $c;
				$i += 2;
			}
		}
		return $sbin;
	}
	/** certDecrypt($data)
	* @param $data the data to decrypt
	* @return $output the decrypted data
	**/
	public function certDecrypt($data,$padding = true)
	{
		if ($padding)
			$padding = OPENSSL_PKCS1_PADDING;
		else
			$padding = OPENSSL_NO_PADDING;
		if (function_exists('hex2bin'))
			$data = hex2bin($data);
		else
			$data = $this->hex2bin($data);
		$path = '/'.trim($this->FOGCore->getSetting('FOG_SNAPINDIR'),'/');
		$path = !$path ? '/opt/fog/snapins/ssl/' : $path.'/';
		if (!$priv_key = openssl_pkey_get_private(file_get_contents($path.'.srvprivate.key')))
			throw new Exception('Private Key Failed');
		$a_key = openssl_pkey_get_details($priv_key);
		// Decrypt the data in the small chunks
		$chunkSize = ceil($a_key['bits'] / 8);
		$output = '';
		while ($data)
		{
			$chunk = substr($data, 0, $chunkSize);
			$data = substr($data,$chunkSize);
			$decrypt = '';
			if (!openssl_private_decrypt($chunk,$decrypt,$priv_key,$padding))
				throw new Exception('Failed to decrypt data');
			$output .= $decrypt;
		}
		openssl_free_key($priv_key);
		return $output;
	}
	public static function parseMacList($stringlist)
	{
		$MACs = explode('|',$stringlist);
		foreach($MACs AS $MAC)
		{
			$MAC = new MACAddress($MAC);
			if ($MAC && $MAC->isValid())
				$MAClist[] = $MAC->__toString();
		}
		if (!$MAClist)
			$MAClist = false;
		return $MAClist;
	}
	public function getActivePlugins()
	{
		return $this->getClass('PluginManager')->find(array('installed' => 1),'','','','','','','name');
	}

	public function array_ksort(Array $array,Array $orderArray) {
		$ordered = array();
		foreach($orderArray AS $key) {
			if (array_key_exists($key,$array)) {
				$ordered[$key] = $array[$key];
				unset($array[$key]);
			}
		}
		return $ordered + $array;
	}
	public function getGlobalModuleStatus()
	{
		return array(
			'dircleanup' => $this->FOGCore->getSetting('FOG_SERVICE_DIRECTORYCLEANER_ENABLED'),
			'usercleanup' => $this->FOGCore->getSetting('FOG_SERVICE_USERCLEANUP_ENABLED'),
			'displaymanager' => $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_ENABLED'),
			'autologout' => $this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_ENABLED'),
			'greenfog' => $this->FOGCore->getSetting('FOG_SERVICE_GREENFOG_ENABLED'),
			'hostnamechanger' => $this->FOGCore->getSetting('FOG_SERVICE_HOSTNAMECHANGER_ENABLED'),
			'snapinclient' => $this->FOGCore->getSetting('FOG_SERVICE_SNAPIN_ENABLED'),
			'clientupdater' => $this->FOGCore->getSetting('FOG_SERVICE_CLIENTUPDATER_ENABLED'),
			'hostregister' => $this->FOGCore->getSetting('FOG_SERVICE_HOSTREGISTER_ENABLED'),
			'printermanager' => $this->FOGCore->getSetting('FOG_SERVICE_PRINTERMANAGER_ENABLED'),
			'taskreboot' => $this->FOGCore->getSetting('FOG_SERVICE_TASKREBOOT_ENABLED'),
			'usertracker' => $this->FOGCore->getSetting('FOG_SERVICE_USERTRACKER_ENABLED'),
		);
	}
	/** binary_search Searches array of objects, or array for the needle
	  * @param $needle is the item to search for
	  * @param $haystack the array to scan within
	  * @return index
	  */
	public function binary_search($needle, $haystack)
	{
		$left = 0;
		$right = count($haystack) - 1;
		$values = array_values($haystack);
		$keys = array_keys($haystack);
		while ($left <= $right)
		{
			$mid = $left + $right >> 1;
			if (is_object($needle))
			{
				if ($values[$mid]->get('id') == $needle->get('id'))
					return $keys[$mid];
				elseif ($values[$mid] > $needle)
					$right = $mid - 1;
				elseif ($values[$mid] < $needle)
					$left = $mid + 1;
			}
			else
			{
				if ($values[$mid] == $needle)
					return $keys[$mid];
				elseif ($values[$mid] > $needle)
					$right = $mid - 1;
				elseif ($values[$mid] < $needle)
					$left = $mid + 1;
			}
		}
		return -1;
	}
	/** getHostItem returns the host or error of host for the service files.
	  * @param $service if the caller is a service
	  * @param $encoded if the item is base64 encoded or not.
	  * @param $hostnotrequired let us know if the host is required or not
	  * @param $returnmacs return the macs or the host
	  * @return host item
	  */
	public function getHostItem($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false)
	{
		$MACs = self::parseMacList(!$encoded ? $_REQUEST['mac'] : trim(base64_decode($_REQUEST['mac'])));
		if (!$MACs) throw new Exception($service ? '#!im' : $this->foglang['InvalidMAC']);
		$Host = $this->getClass('HostManager')->getHostByMacAddresses($MACs);
		if (!$hostnotrequired)
		{
			if ((!$Host || !$Host->isValid() || $Host->get('pending')))
				throw new Exception($service ? '#!ih' : _('Invalid Host'));
			if ($service && $_REQUEST['newService'] && !$Host->get('pub_key'))
				throw new Exception('#!ihc');
		}
		if ($returnmacs)
			return (is_array($MACs) ? $MACs : array($MACs));
		return $Host;
	}
	/** sendData prints the return values as needed
	  * @param $datatosend the data to send out
	  * @param $service if the caller is a service
	  * @return void
	  */
	public function sendData($datatosend,$service = true)
	{
		if ($service)
		{
			if ($_REQUEST['newService'] && $this->getClass('FOGCore')->getSetting('FOG_NEW_CLIENT'))
				print "#!enkey=".$this->certEncrypt($datatosend,$this->getHostItem());
			else
				print $datatosend;
		}
	}

	/** getAllBlamedNodes sets the failure of a node
	  * @return $nodeRet the node to return if it's already used
	  */
	public function getAllBlamedNodes()
	{
		$NodeFailures = $this->getClass('NodeFailureManager')->find(array('taskID' => $this->getHostItem(false)->get('task')->get('id'), 'hostID' => $this->getHostItem(false)->get('id')));
		$DateInterval = $this->nice_date()->modify('-5 minutes');
		foreach($NodeFailures AS $NodeFailure)
		{
			$DateTime = $this->nice_date($NodeFailure->get('failureTime'));
			if ($DateTime >= $DateInterval)
			{
				$node = $NodeFailure->get('id');
				if (!in_array($node,(array)$nodeRet))
					$nodeRet[] = $node;
			}
			else
				$NodeFailure->destroy();
		}
		return $nodeRet;
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
