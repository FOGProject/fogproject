<?php
/** Class Name: FOGCore
	An extension of FOGBase, again, don't edit unless
	you know what you're doing.
	Used by many methods.

	Legacy items need to be checked and removed, but 
	this just verifies things for us.
*/
class FOGCore extends FOGBase
{
	/** attemptLogin($username,$password)
		Checks the login and returns the user or nothing if not valid/not exist.
	*/
	public function attemptLogin($username,$password)
	{
		$User = current($this->getClass('UserManager')->find(array('name' => $username,'password' => md5($password))));
		if ($User && $User->isValid())
			return $User;
		return null;
	}

	/** cleanOldUnrunScheduledTasks()
		Cleans out old scheduled delayed tasks.
	*/
	private function cleanOldUnrunScheduledTasks()
	{
		$ScheduledTasks = $this->getClass('ScheduledTaskManager')->find(array('type' => 'S', 'scheduleTime' => strtotime(180)),'AND');
		foreach($ScheduledTasks AS $ScheduledTask)
			$ScheduledTask->set('isActive', 0)->save();
	}
	
	/** stopScheduledTask($task)
		Stops the scheduled task.
	*/
	public function stopScheduledTask($task)
	{
		$ScheduledTask = new ScheduledTask($task->get('id'));
		return $ScheduledTask->set('isActive',0)->save();
	}
	
	/** redirect($url = '')
		Redirect the page.
	*/
	public function redirect($url = '')
	{
		if ($url == '')
			$url = $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
		if (headers_sent())
			printf('<meta http-equiv="refresh" content="0; url=%s">', $url);
		else
			header("Location: $url");
		exit;
	}
	
	/** setMessage(,$txt, $data = array())
		Sets the message at the top of the screen (e.g. 14 Active Tasks Found)
	*/
	public function setMessage($txt, $data = array())
	{
		$_SESSION['FOG_MESSAGES'] = (!is_array($txt) ? array(vsprintf($txt, $data)) : $txt);
		return $this;
	}
	
	/** getMessage()
		Get's the current message in the store to display to the screen
	*/
	public function getMessages()
	{
		print "\n\t<!-- FOG Variables -->";
		
		foreach ((array)$_SESSION['FOG_MESSAGES'] AS $message)
		{
			// Hook
			$GLOBALS['HookManager']->processEvent('MessageBox', array('data' => &$message));
			// Message Box
			printf('<div class="fog-message-box">%s</div>%s', $message, "\n");
		}
		unset($_SESSION['FOG_MESSAGES']);
	}
	
	/** logHistory($string)
		Logs the actions to the database.
	*/
	public function logHistory($string)
	{
		global $conn, $currentUser;
		$uname = "";
		if ( $currentUser != null )
			$uname = $this->DB->sanitize($currentUser->get('name'));
		$sql = "insert into history( hText, hUser, hTime, hIP ) values( '".$this->DB->sanitize($string)."', '".$uname."', NOW(), '".$_SERVER[REMOTE_ADDR]."')";
		$this->DB->query($sql);
	}
	
	/** searchManager($manager = 'Host', $keyword = '*')
		Searchs items using the Manager of the associated class.  If nothing is chosen searches all hosts.
	*/
	public function searchManager($manager = 'Host', $keyword = '*')
	{
		$manager = ucwords(strtolower($manager)) . 'Manager';
		
		//$Manager = new $manager();
		// TODO: Replace this when all Manager classes no longer need the database connection passed
		$Manager = new $manager( $GLOBALS['conn'] );
		
		return $Manager->search($keyword);
	}
	
	/** getSetting($key)
		Get's global Setting Values
	*/
	public function getSetting($key)
	{
		$Service = current($this->getClass('ServiceManager')->find(array('name' => $key)));
		return $Service && $Service->isValid() ? $Service->get('value') : '';
	}
	
	/** setSetting($key, $value)
		Set's a new default value.
	*/
	public function setSetting($key, $value)
	{
		return current($this->getClass('ServiceManager')->find(array('name' => $key)))->set('value', $value)->save();
	}
	
	/** isAJAXRequest()
		Returns true if ajax is requesting, otherwise false
	*/
	public static function isAJAXRequest()
	{
		return (strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ? true : false);
	}
	
	/** isPOSTRequest()
		Returns true if form is method="post"
	*/
	public function isPOSTRequest()
	{
		return (strtolower(@$_SERVER['REQUEST_METHOD']) == 'post' ? true : false);
	}
	
	/** getMACManufacturer($macprefix)
		Returns the Manufacturer of the prefix sent if the tables are loaded.
	*/
	public function getMACManufacturer($macprefix)
	{
		$OUI = current($this->getClass('OUIManager')->find(array('prefix' => $macprefix)));
		return ($OUI && $OUI->isValid() ? $OUI->get('name') : _('n/a'));
	}
	
	/** addUpdateMACLookupTable($macprefix,$strMan)
		Updates/add's MAC Manufacturers
	*/
	public function addUpdateMACLookupTable($macprefix,$strMan)
	{
		$OUI = current($this->getClass('OUIManager')->find(array('prefix' => $macprefix)));
		if ($OUI)
		{
			$OUI->set('prefix',$macprefix)
				->set('name',$strMan);
			return $OUI->save();
		}
		else
		{
			$OUI = new OUI(array(
				'prefix' => $macprefix,
				'name' => $strMan,
			));
			return $OUI->save();
		}
		return false;
	}
	
	/** clearMACLookupTable()
		Clear's all entries in the table.
	*/
	public function clearMACLookupTable()
	{
		if ($this->getClass('OUIManager')->destroy())
			return true;
		return false;
	}
	
	/** getMACLookupCount()
		returns the number of MAC's loaded.
	*/
	public function getMACLookupCount()
	{
		return $this->getClass('OUIManager')->count();
	}
	
	// Blackout - 10:26 AM 25/05/2011
	// Used from one of my classes - hacked to make it work
	// TODO: Make a FOG Utilities Class - include this
	/** fetchURL($URL)
		fetches information from external addresses.
	*/
	public function fetchURL($URL)
	{
		if ($this->DB && $GLOBALS['FOGCore']->getSetting('FOG_PROXY_IP'))
		{
			$Proxy = $GLOBALS['FOGCore']->getSetting('FOG_PROXY_IP') . ':' . $GLOBALS['FOGCore']->getSetting('FOG_PROXY_PORT');
		}
		
		$userAgent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.6.12) Gecko/20110319 Firefox/4.0.1 ( .NET CLR 3.5.30729; .NET4.0E)';
		$timeout = 10;
		$maxRedirects = 20;
		
		$contextOptions = array(
					'ssl'	=> array(
							'allow_self_signed' => true
							),
					'http'	=> array(
							'method' 	=> 'GET',
							'user_agent' 	=> $userAgent,
							'timeout' 	=> $timeout,
							'max_redirects'	=> $maxRedirects,
							'header' 	=> array(
										'Accept-language: en',
										'Pragma: no-cache'
									)
							)
					);

		// Proxy
		if ($Proxy)
		{
			$contextOptions['http']['proxy'] = 'tcp://' . $Proxy;
			$contextOptions['http']['request_fulluri'] = true;
			if ($this->getSetting('FOG_PROXY_USERNAME'))
			{
				$auth = base64_encode($this->getSetting('FOG_PROXY_USERNAME').':'.$this->getSetting('FOG_PROXY_PASSWORD'));
				$contextOptions['http']['header'] = "Proxy-Authorization:Basic $auth";
			}
		}

		// Get data
		if ($response = trim(@file_get_contents($URL, false, stream_context_create($contextOptions))))
			return $response;
		else
			return false;
	}

	/** resolvHostname($host)
		Returns the hostname.  Useful for Hostname dns translating for the server (e.g. fogserver instead of 127.0.0.1) in the address
		bar.
	*/
	public function resolveHostname($host)
	{
		return ($this->getSetting('FOG_USE_SLOPPY_NAME_LOOKUPS') ? gethostbyname($host) : $host);
	}
	
	/** makeTempFilePath()
		creates the temporary file.
	*/
	public function makeTempFilePath()
	{
		return tempnam(sys_get_temp_dir(), 'FOG');
	}
	
	/** wakeOnLAN($mac)
		Wakes systems up with the magic packet.
	*/
	public function wakeOnLAN($mac)
	{
		// HTTP request to WOL script
		$this->fetchURL(sprintf('http://%s%s?wakeonlan=%s', $this->getSetting('FOG_WOL_HOST'), $this->getSetting('FOG_WOL_PATH'), ($mac instanceof MACAddress ? $mac->getMACWithColon() : $mac)));
	}
	
	/** formatTime($time, $format = '')
		format's time information.  If format is blank,
		formats based on current date to date sent.  Otherwise
		returns the information back based on the format requested.
	*/
	public function formatTime($time, $format = '')
	{
		// Convert to unix date if not already
		if (!is_numeric($time))
			$time = strtotime($time);
		// Forced format
		if ($format)
			return date($format, $time);
		// Today
		if (date('d-m-Y', $time) == date('d-m-Y'))
			return 'Today, ' . date('g:ia', $time);
		// Yesterday
		elseif (date('d-m-Y', $time) == date('d-m-Y', strtotime('-1 day')))
			return 'Yesterday, ' . date('g:i a', $time);
		// Short date
		elseif (date('m-Y', $time) == date('m-Y'))
			return date('jS, g:ia', $time);
		// Long date
		return date('m-d-Y g:ia', $time);
	}
	
	// Blackout - 2:40 PM 25/05/2011
	/** SystemUptime()
		Returns the uptime of the server.
	*/
	public function SystemUptime()
	{
		$data = trim(shell_exec('uptime'));
	    
        $tmp = explode(' load average: ', $data);
		$load = end($tmp);
		
		$tmp = explode(' up ',$data);
		$tmp = explode(',', end($tmp));
		$uptime = $tmp;
		$uptime = (count($uptime) > 1 ? $uptime[0] . ', ' . $uptime[1] : 'uptime not found');
		
		return array('uptime' => $uptime, 'load' => $load);
	}
	// The below functions are from the FOG Service Scripts Data writing and checking.
	/** out($sting, $device, $blLog=false,$blNewLine=true)
		prints the information to the service log files.
	*/
	public function out($string,$device,$blLog=false,$blNewLine=true)
	{
		($blNewLine ? $strOut = $string."\n" : null);
		if (!$hdl = fopen($device,'w')) return;
		if (fwrite($hdl,$strOut) === FALSE) return;
		fclose($hdl);
	}
	/** getDateTime()
		Returns the date format used at the start of each line in the service lines.
	*/
	public function getDateTime()
	{
		return date('m-d-y g:i:s a');
	}
	/** wlog($string, $path)
		Writes to the log file and clears if needed.
	*/
	public function wlog($string, $path)
	{
		if (filesize($path) > LOGMAXSIZE) unlink($path);
		if (!$hdl = fopen($path,'a'))
		{
			$this->out("\n");
			$this->out(" * Error: Unable to open file: $path");
			$this->out("\n");
		}
		if (fwrite($hdl,sprintf('[%s] %s%s',$this->getDateTime(),$string,"\n")) === FALSE)
		{
			$this->out("\n");
			$this->out(" * Error: Unable to write to file: $path");
			$this->out("\n");
		}
	}
	/** getIPAddress()
		Gets the service server's IP address.
	*/
	public function getIPAddress()
	{
		$arR = null;
		$retVal = null;
		$output = array();
		exec("/sbin/ifconfig | grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'| cut -d':' -f 2 | cut -d' ' -f1", $arR, $retVal);
		foreach ($arR AS $IP)
		{
			$IP = trim($IP);
			if ($IP != "127.0.0.1")
			{
				if (($bIp = ip2long($IP)) !== false)
					$output[] = $IP;
			}
		}
		return $output;
	}
	/** getBanner()
		Prints the FOG banner
	*/
	function getBanner()
	{
		$str  = "        ___           ___           ___      \n";
		$str .= "       /\  \         /\  \         /\  \     \n";
		$str .= "      /::\  \       /::\  \       /::\  \    \n";
		$str .= "     /:/\:\  \     /:/\:\  \     /:/\:\  \   \n";
		$str .= "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  \n";
		$str .= "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ \n";
		$str .= "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ \n";
		$str .= "        \:\__\    \:\  /:/  /   \:\ \:\__\   \n";
		$str .= "         \/__/     \:\/:/  /     \:\/:/  /   \n";
		$str .= "                    \::/  /       \::/  /    \n";
		$str .= "                     \/__/         \/__/     \n";
		$str .= "\n";
		$str .= "  ###########################################\n";
		$str .= "  #     Free Computer Imaging Solution      #\n";
		$str .= "  #                                         #\n";
		$str .= "  #     Created by:                         #\n";
		$str .= "  #         Chuck Syperski                  #\n";
		$str .= "  #         Jian Zhang                      #\n";
		$str .= "  #         Tom Elliott                     #\n";
		$str .= "  #                                         #\n";
		$str .= "  #     GNU GPL Version 3                   #\n";
		$str .= "  ###########################################\n";
		$str .= "\n";
		return $str;
	}	
}
