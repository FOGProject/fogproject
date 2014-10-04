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
		$Users = $this->getClass('UserManager')->find();
		foreach ($Users AS $User)
		{
			$pass = md5($password);
			$user = $username;
			if ($User->get('name') == $user && $User->get('password') == $pass)
				return $User;
		}
		return null;
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
		$History = new History(array(
			'info' => $string,
			'createdBy' => $uname,
			'createdTime' => $this->nice_date()->format('Y-m-d H:i:s'),
			'ip' => $_SERVER[REMOTE_ADDR],
		));
		$History->save();
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
		return ($OUI && $OUI->isValid() ? $OUI->get('name') : $this->foglang['n/a']);
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
		$this->DB->query("TRUNCATE TABLE ".OUI::databaseTable);
		return (!$this->DB->fetch()->get());
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
		if ($this->DB && $this->getSetting('FOG_PROXY_IP'))
		{
			foreach($this->getClass('StorageNodeManager')->find() AS $StorageNode)
				$IPs[] = $StorageNode->get('ip');
			$IPs = array_filter(array_unique($IPs));
			if (!preg_match('#('.implode('|',$IPs).')#i',$URL))
				$Proxy = $this->getSetting('FOG_PROXY_IP') . ':' . $this->getSetting('FOG_PROXY_PORT');
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

	/** resolveHostname($host)
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
		$this->fetchURL(sprintf('http://%s%s?wakeonlan=%s', $this->getSetting('FOG_WOL_HOST'), $this->getSetting('FOG_WOL_PATH'), ($mac instanceof MACAddress ? $mac->__toString() : $mac)));
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
	/** clear_screen($outputdevice)
		Clears the screen for information.
	*/
	public function clear_screen($outputdevice)
	{
		$this->out(chr(27)."[2J".chr(27)."[;H",$outputdevice);
	}
	/** wait_interface_ready($interface,$outputdevice)
		Waits for the network interface to be ready so services operate.
	*/
	public function wait_interface_ready($interface,$outputdevice)
	{
		while (true)
		{
			$retarr = array();
			exec('netstat -inN',$retarr);
			array_shift($retarr);
			array_shift($retarr);
			foreach($retarr AS $line)
			{
				$t = substr($line,0,strpos($line,' '));
				if ($t == $interface)
				{
					$this->out('Interface now ready..',$outputdevice);
					break 2;
				}
			}
			$this->out('Interface not ready, waiting..',$outputdevice);
			sleep(10);
		}
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
		return $this->nice_date()->format('m-d-y g:i:s a');
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
	public function getBanner()
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

	/** hex2bin($hex)
	* @param $hex
	* Function simple takes the data and transforms it into hexadecimal.
	* @return the hex coded data.
	*/
	public function hex2bin($hex)
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
		return $sbin;
	}
	/** getHWInfo()
	* Returns the hardware information for hwinfo link on dashboard.
	* @return $data
	*/
	public function getHWInfo()
	{
		$data['general'] = '@@general';
		$data['kernel'] = trim(shell_exec('uname -r').substr("\n",0,-2));
		$data['hostname'] = trim(shell_exec('hostname'));
		$data['uptimeload'] = trim(shell_exec('uptime'));
		$data['cputype'] = trim(shell_exec("cat /proc/cpuinfo | head -n2 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
		$data['cpucount'] = trim(shell_exec("grep '^processor' /proc/cpuinfo | tail -n 1 | awk '{print \$3+1}'"));
		$data['cpumodel'] = trim(shell_exec("cat /proc/cpuinfo | head -n5 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
		$data['cpuspeed'] = trim(shell_exec("cat /proc/cpuinfo | head -n8 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
		$data['cpucache'] = trim(shell_exec("cat /proc/cpuinfo | head -n9 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
		$data['totmem'] = $this->formatByteSize(trim(shell_exec("free -m | head -n2 | tail -n1 | awk '{ print \$2 }'"))*1024*1024);
		$data['usedmem'] = $this->formatByteSize(trim(shell_exec("free -m | head -n3 | tail -n1 | awk '{ print \$3 }'"))*1024*1024);
		$data['freemem'] = $this->formatByteSize(trim(shell_exec("free -m | head -n3 | tail -n1 | awk '{ print \$4 }'"))*1024*1024);
		$data['filesys'] = '@@fs';
		$t = shell_exec('df | grep -vE "^Filesystem|shm"');
		$l = explode("\n",$t);
		foreach ($l AS $n)
		{
			if (preg_match("/(\d+) +(\d+) +(\d+) +\d+%/",$n,$matches))
			{
				if (is_numeric($matches[1]))
					$hdtotal += $matches[1]*1024;
				if (is_numeric($matches[2]))
					$hdused += $matches[2]*1024;
			}
		}
		$data['totalspace'] = $this->formatByteSize($hdtotal);
		$data['usedspace'] = $this->formatByteSize($hdused);
		$data['nic'] = '@@nic';
		$NET = shell_exec('cat "/proc/net/dev"');
		$lines = explode("\n",$NET);
		foreach ($lines AS $line)
		{
			if (preg_match('/:/',$line))
			{
				list($dev_name,$stats_list) = preg_split('/:/',$line,2);
				$stats = preg_split('/\s+/', trim($stats_list));
				$data[$dev_name] = trim($dev_name).'$$'.$stats[0].'$$'.$stats[8].'$$'.($stats[2]+$stats[10]).'$$'.($stats[3]+$stats[11]);
			}
		}
		$data['end'] = '@@end';
		return $data;
	}
	/**
	* track($list, $c = 0, $i = 0)
	* @param $list the data to bencode.
	* @param $c completed jobs (seeders)
	* @param $i incompleted jobs (leechers)
	* @return void
	* Will "return" but through throw/catch statement.
	*/
	public function track($list, $c = 0, $i = 0)
	{
		if (is_string($list))
			return 'd14:failure reason'.strlen($list).':'.$list.'e';
		$p = '';
		foreach((array)$list AS $d)
		{
			$peer_id = '';
			if (!$_REQUEST['no_peer_id'])
				$peer_id = '7:peer id'.strlen($this->hex2bin($d[2])).':'.$this->hex2bin($d[2]);
			$p .= 'd2:ip'.strlen($d[0]).':'.$d[0].$peer_id.'4:porti'.$d[1].'ee';
		}
		return 'd8:intervali'.$this->getSetting('FOG_TORRENT_INTERVAL').'e12:min intervali'.$this->getSetting('FOG_TORRENT_INTERVAL_MIN').'e8:completei'.$c.'e10:incompletei'.$i.'e5:peersl'.$p.'ee';
	}
	/**
	* valdata($g,$fixed_size=false)
	* Function simply checks if the required data is met and valid
	* Could use for other functions possibly too.
	* @param $g the request/get/post info to validate.
	* @return void
	* Sends info back to track.
	*/
	public function valdata($g,$fixed_size=false)
	{
		try
		{
			if (!$_REQUEST[$g])
				throw new Exception($this->track('Invalid request, missing data'));
			if (!is_string($_REQUEST[$g]))
				throw new Exception($this->track('Invalid request, unkown data type'));
			if ($fixed_size && strlen($_REQUEST[$g]) != 20)
				throw new Exception($this->track('Invalid request, length on fixed argument not correct'));
			if (strlen($_REQUEST[$g]) > 80)
				throw new Exception($this->track('Request too long'));
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}
	}
}
