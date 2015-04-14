<?php
abstract class FOGBase
{
	/** $debug Standardizes the debug as an abstract variable for use later on. */
	public $debug = false;
	/** $info Prepares the information if you should want more info. */
	public $info = false;
	/** $FOGFTP the FOGFTP class */
	public $FOGFTP;
	/** $FOGCore the FOGCore class */
	public $FOGCore;
	/** $DB The database manager class */
	public $DB;
	/** $HookManager the hook manager class */
	public $HookManager;
	/** $FOGUser the currently logged in user */
	public $FOGUser;
	/** $FOGPageManager the FOGPageManager class */
	public $FOGPageManager;
	/** $foglang the language interpreted values */
	public $foglang;
	/** $imagelink the link to the image files */
	public $imagelink;
	/** $EventManager the EventManager class */
	public $EventManager;
	/** $db Legacy calls for $db/$conn */
	public $db;
	/** $conn Legacy calls for $db/$conn */
	public $conn;
	/** $searchPages sets the "isLoaded" variable */
	protected $isLoaded = array();
	/** $searchPages sets the pages that have search as a selector. */
	protected $searchPages = array();
	/** __construct() initiates the FOGBase class
	  * @return void
	  */
	public function __construct()
	{
		$this->FOGFTP = $GLOBALS['FOGFTP'];
		$this->FOGCore = $GLOBALS['FOGCore'];
		$this->DB = $GLOBALS['DB'];
		$this->FOGUser = $GLOBALS['currentUser'];
		$this->HookManager = $GLOBALS['HookManager'];
		$this->FOGPageManager = $GLOBALS['FOGPageManager'];
		$this->EventManager = $GLOBALS['EventManager'];
		$this->foglang = $GLOBALS['foglang'];
		$this->TimeZone = $GLOBALS['TimeZone'];
		$this->imagelink = $_SESSION['imagelink'];
		$this->searchPages = array('user','host','group','image','snapin','printer','tasks','hosts');
	}
	/** fatalError() prints error to the screen and exits script
	  * @param $txt the text of the error
	  * @param $data the data to parse
	  * @return void
	  */
	public function fatalError($txt, $data = array())
	{
		if (!preg_match('#/service/#', $_SERVER['PHP_SELF']) && !FOGCore::isAJAXRequest())
		{
			print sprintf('<div class="debug-error">FOG FATAL ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
			exit;
		}
	}
	
	/** error() prints the error to the screen
	  * @param $txt the text to print
	  * @param $data the data to parse
	  * @return void
	  */
	public function error($txt, $data = array())
	{
		if ((((isset($this->debug)) && $this->debug === true)) && !preg_match('#/service/#', $_SERVER['PHP_SELF']) && !FOGCore::isAJAXRequest())
			print sprintf('<div class="debug-error">FOG ERROR: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
	}
	/** debug() prints debug information
	  * @param $txt the text to print
	  * @param $data the data to parse
	  * @return void
	  */
	public function debug($txt, $data = array())
	{
		if ((!isset($this) || (isset($this->debug) && $this->debug === true)) && !FOGCore::isAJAXRequest() && !preg_match('#/service/#', $_SERVER['PHP_SELF']))
			print sprintf('<div class="debug-error">FOG DEBUG: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
	}
	/** info() prints informational messages
	  * @param $txt the text to print
	  * @param $data the data to parse
	  * @return void
	  */
	public function info($txt, $data = array())
	{
		if ((!isset($this) || (isset($this->info) && $this->info === true)) && !preg_match('#/service/#',$_SERVER['PHP_SELF']))
			print sprintf('<div class="debug-info">FOG INFO: %s: %s</div>%s', get_class($this), (count($data) ? vsprintf($txt, $data) : $txt), "\n");
	}
	/** __toString() magic function in php as defined
	  * @return the item in string format
	  */
	public function __toString()
	{
		return (string)get_class($this);
	}
	/** toString()
	  * @return the item in string format
	  */
	public function toString()
	{
		return $this->__toString();
	}
	/** isLoaded($key)
	  * @param $key the key to check if it is loaded
	  * @return whether key is loaded or not
	  */
	public function isLoaded($key)
	{
		$result = (isset($this->isLoaded[$key]) ? $this->isLoaded[$key] : 0);
		$this->isLoaded[$key]++;
		return ($result ? $result : false);
	}
	/** getClass($class)
	  * @param $class the class to get items of.
	  * @return The instance of the class.
	  */
	public function getClass($class,$data = '')
	{
		$args = func_get_args();
		array_shift($args);
		$r = new ReflectionClass($class);
		return (count($args) ? $r->newInstanceArgs($args) : $r->newInstance($data));
	}
	/** endsWith()
	  * @param $str the string to find out if it ends with
	  * @param the sub to match
	  * @return true or false if it ends with
	  */
	public function endsWith($str,$sub)
	{
		return (substr($str,strlen($str)-strlen($sub)) === $sub);
	}
	/** getFTPByteSize() get the byte size from ftp for the file requests.
	  * @param $StorageNode the storagenode to ftp to
	  * @param $file the file to get the size of.
	  * @return the size of the item prettied up from formatByteSize
	  */
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
	/** formatByteSize
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
	/** array_insert_before()
	  * Inserts a new key/value before the key in the array.
	  *
	  * @param $key
	  *   The key to insert before.
	  * @param $array
	  *   An array to insert in to.
	  * @param $new_key
	  *   The key to insert.
	  * @param $new_value
	  *   A value to insert.
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
	/** array_insert_after()
	  * Inserts a new key/value after the key in the array.
	  *
	  * @param $key
	  *   The key to insert after.
	  * @param $array
	  *   An array to insert in to.
	  * @param $new_key
	  *   The key to insert.
	  * @param $new_value
	  *   A value to insert.
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
	/** array_remove() removes specified key or keys (in array) from an array
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
	/** randomString()
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
	/** aesencrypt() aes encrypts the data sent.
	  * @param $data the data to encrypt
	  * @param $key if false, have fog generate a random key for it.
	  * @param $enctype can be set to anything but defaults to MCRYPT_RIJNDAEL_128
	  * @param $mode the mode to encrypt with defaults as MCRYPT_MODE_CBC
	  * @return  the iv and the encrypted data. If key wasn't specified it also sends the key with the return.
	  */
	public function aesencrypt($data,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC)
	{

		$iv_size = mcrypt_get_iv_size($enctype,$mode);
		if (!$key)
		{
			$addKey = true;
			$key = openssl_random_pseudo_bytes($iv_size,$cstrong);
		}
		$iv = mcrypt_create_iv($iv_size,MCRYPT_DEV_URANDOM);
		$cipher = mcrypt_encrypt($enctype,$key,$data,$mode,$iv);
		return bin2hex($iv).'|'.bin2hex($cipher).($addKey ? '|'.bin2hex($key) : '');
	}
	/** aesencrypt() aes decrypts the data sent.
	  * @param $encdata the data to decrypt
	  * @param $key if false, have fog grab it from the output.
	  * @param $enctype can be set to anything but defaults to MCRYPT_RIJNDAEL_128
	  * @param $mode the mode to encrypt with defaults as MCRYPT_MODE_CBC
	  * @return the decrypted data.
	  */
	public function aesdecrypt($encdata,$key = false,$enctype = MCRYPT_RIJNDAEL_128,$mode = MCRYPT_MODE_CBC)
	{
		$iv_size = mcrypt_get_iv_size($enctype,$mode);
		$data = explode('|',$encdata);
		$iv = pack('H*',$data[0]);
		$encoded = pack('H*',$data[1]);
		if (!$key)
			$key = pack('H*',$data[2]);
		$decipher = mcrypt_decrypt($enctype,$key,$encoded,$mode,$iv);
		return $decipher;
	}
	/** encryptpw() encrypts the passwords for us
	  * @param $pass the password to work from
	  * @return returns the encrypted password
	  */
	public function encryptpw($pass)
	{
		$decrypt = $this->aesdecrypt($pass);
		$newpass = $pass;
		if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true))
			$newpass = $decrypt;
		return $this->aesencrypt($newpass);
	}
	/** diff()
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
	/** nice_date()
	  * Simply returns the date in DateTime Class format for easier use.
	  * @param $Date the non-nice Date Sent.
	  * @return $NiceDate returns the DateTime class for the current date.
	  */
	public function nice_date($Date = 'now',$utc = false)
	{
		$NiceDate = (!$utc ? new DateTime($Date,new DateTimeZone($this->TimeZone)) : new DateTime($Date,new DateTimeZone('UTC')));
		return $NiceDate;
	}
	/** validDate()
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
	/** formatTime()
	  * @param $time the time to format
	  * @param $format what format to output the time if set.
	  * @param $utc whether to use UTC or local timezone.
	  * @return formatted time
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
	  * @return void
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
	  * @return void
	  */
	public function setRequest()
	{
		if (!$_SESSION['post_request_vals'] && $this->FOGCore->isPOSTRequest())
			$_SESSION['post_request_vals'] = $_REQUEST;
	}
	/** array_filter_recursive()
	  * @param $input the input to filter
	  * clean up arrays recursively.
	  * @return the filtered array
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
	/** byteconvert()
	  * @param $kilobytes
	  * @return $kilobytes
	  */
	public function byteconvert($kilobytes)
	{
		return (($kilobytes / 8) * 1024);
	}
	/** certEncrypt()
	  * @param $data the data to encrypt
	  * @param $Host the host to use for encrypting
	  * @return $encrypt returns the encrypted data
	  */
	public function certEncrypt($data,$Host)
	{
		if (!$Host || !$Host->isValid())
			throw new Exception('#!ih');
		if (!$Host->get('pub_key'))
			throw new Exception('#!ihc');
		return $this->aesencrypt($data,$this->hex2bin($Host->get('pub_key')));
		if (!$pub_key = openssl_pkey_get_public($Host->get('pub_key')))
			throw new Exception('#!ihc');
		$a_key = openssl_pkey_get_details($pub_key);
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
	/** hex2bin()
	  * @param $hex
	  * Function simply takes the data and transforms it into hexadecimal.
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
	/** certDecrypt()
	  * @param $data the data to decrypt
	  * @param $padding if we need it or not, defaults to needed
	  * @return $output the decrypted data
	  */
	public function certDecrypt($data,$padding = true)
	{
		if ($padding)
			$padding = OPENSSL_PKCS1_PADDING;
		else
			$padding = OPENSSL_NO_PADDING;
		$data = $this->hex2bin($data);
		$path = '/'.trim($this->FOGCore->getSetting('FOG_SNAPINDIR'),'/');
		$path = !$path ? '/opt/fog/snapins/ssl/' : $path.'/';
		if (!$priv_key = openssl_pkey_get_private(file_get_contents($path.'.srvprivate.key')))
			throw new Exception('Private Key Failed');
		$a_key = openssl_pkey_get_details($priv_key);
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
	/** parseMacList() function takes the string of the MAC addresses sent
	  *     it then tests if they are each valid macs and returns just the mac's.
	  * @param $stringlist the list of MACs to check.  Each mac is broken by a | character.
	  * @return $MAClist, returns the list of valid MACs
	  */
	public function parseMacList($stringlist,$image = false,$client = false)
	{
		$MACs = $this->getClass('MACAddressAssociationManager')->find(array('mac' => (array)explode('|',$stringlist)));
		if (count($MACs))
		{
			foreach($MACs AS $MAC)
			{
				if ($MAC && $MAC->isValid())
				{
					if ($image && !$MAC->get('imageIgnore'))
						$MAC = new MACAddress($MAC);
					else if ($client && !$MAC->get('clientIgnore'))
						$MAC = new MACAddress($MAC);
					if (!$image && !$client && !$MAC->get('pending'))
						$MAC = new MACAddress($MAC);
					if ($MAC instanceof MACAddress)
						$MAClist[] = strtolower($MAC);
				}
			}
		}
		else
		{
			$MACs = explode('|',$stringlist);
			foreach((array)$MACs AS $MAC)
			{
				$MAC = new MACAddress($MAC);
				if ($MAC && $MAC->isValid())
					$MAClist[] = strtolower($MAC);
			}
		}
		if (!count($MAClist))
			$MAClist = false;
		return $MAClist;
	}
	/** getActivePlugins() gets the active plugins.
	  * @return the array of active plugin names.
	  */
	public function getActivePlugins()
	{
		return $this->getClass('PluginManager')->find(array('installed' => 1),'','','','','','','name');
	}
	/** array_ksort()
	  * sorts the array by the keys.
	  * @param (array) $array the array to compare
	  * @param (array) $orderArray the array to sort itself
	  * @return combined array.
	  */
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
	/** getGlobalModuleStatus()
	  * @param $names returns the short and long names, otherwise returns if the long is set.  Default is false.
	  * @return the array of data as requested.
	  */
	public function getGlobalModuleStatus($names = false)
	{
		return array(
			'dircleanup' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_DIRECTORYCLEANER_ENABLED') : 'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
			'usercleanup' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_USERCLEANUP_ENABLED') : 'FOG_SERVICE_USERCLEANUP_ENABLED',
			'displaymanager' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_ENABLED') : 'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
			'autologout' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_ENABLED') : 'FOG_SERVICE_AUTOLOGOFF_ENABLED',
			'greenfog' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_GREENFOG_ENABLED') : 'FOG_SERVICE_GREENFOG_ENABLED',
			'hostnamechanger' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_HOSTNAMECHANGER_ENABLED') : 'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
			'snapinclient' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_SNAPIN_ENABLED') : 'FOG_SERVICE_SNAPIN_ENABLED',
			'clientupdater' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_CLIENTUPDATER_ENABLED') : 'FOG_SERVICE_CLIENTUPDATER_ENABLED',
			'hostregister' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_HOSTREGISTER_ENABLED') : 'FOG_SERVICE_HOSTREGISTER_ENABLED',
			'printermanager' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_PRINTERMANAGER_ENABLED') : 'FOG_SERVICE_PRINTERMANAGER_ENABLED',
			'taskreboot' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_TASKREBOOT_ENABLED') : 'FOG_SERVICE_TASKREBOOT_ENABLED',
			'usertracker' => !$names ? $this->FOGCore->getSetting('FOG_SERVICE_USERTRACKER_ENABLED') : 'FOG_SERVICE_USERTRACKER_ENABLED',
		);
	}
	/** binary_search() Searches array of objects, or array for the needle
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
				if ($needle instanceof MACAddress)
				{
					if (strtolower($values[$mid]->__toString()) == strtolower($needle->__toString()))
						return $keys[$mid];
					elseif ($values[$mid] > $needle)
						$right = $mid - 1;
					elseif ($values[$mid] < $needle)
						$left = $mid + 1;
				}
				else
				{
					if ($values[$mid]->get('id') == $needle->get('id'))
						return $keys[$mid];
					elseif ($values[$mid] > $needle)
						$right = $mid - 1;
					elseif ($values[$mid] < $needle)
						$left = $mid + 1;
				}
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
	/** getHostItem() returns the host or error of host for the service files.
	  * @param $service if the caller is a service
	  * @param $encoded if the item is base64 encoded or not.
	  * @param $hostnotrequired let us know if the host is required or not
	  * @param $returnmacs return the macs or the host
	  * @return host item
	  */
	public function getHostItem($service = true,$encoded = false,$hostnotrequired = false,$returnmacs = false,$override = false)
	{
		$MACs = $this->parseMacList(trim(!$encoded ? $_REQUEST['mac'] : base64_decode($_REQUEST['mac'])),!$service,$service);
		if (!$MACs && !$hostnotrequired) throw new Exception($service ? '#!im' : $this->foglang['InvalidMAC']);
		if ($returnmacs) return (is_array($MACs) ? $MACs : array($MACs));
		$Host = $this->getClass('HostManager')->getHostByMacAddresses($MACs);
		if (!$hostnotrequired)
		{
			if ((!$Host || !$Host->isValid() || $Host->get('pending')) && !$override)
				throw new Exception($service ? '#!ih' : _('Invalid Host'));
			if ($service && $_REQUEST['newService'] && !$Host->get('pub_key') && $this->getClass('FOGCore')->getSetting('FOG_AES_ENCRYPT'))
				throw new Exception('#!ihc');
		}
		return $Host;
	}
	/** sendData() prints the return values as needed
	  * @param $datatosend the data to send out
	  * @param $service if the caller is a service
	  * @return void
	  */
	public function sendData($datatosend,$service = true)
	{
		if ($service)
		{
			$Host = $this->getHostItem();
			if ($_REQUEST['newService'] && $this->getClass('FOGCore')->getSetting('FOG_AES_ENCRYPT'))
				print "#!enkey=".$this->certEncrypt($datatosend,$Host);
			else if ($_REQUEST['newService'] && ($Host->get('useAD') && preg_match('#hostname.php#',$_SERVER['PHP_SELF'])))
				print "#!enkey=".$this->certEncrypt($datatosend,$Host);
			else
				print $datatosend;
		}
	}

	/** getAllBlamedNodes() sets the failure of a node
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
