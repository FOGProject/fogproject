<?php
/** \class WakeOnLan
	Builds the magic packet needed for waking systems from LAN.
*/
class WakeOnLan extends FOGBase
{
	private $strMac;
	/** __construct($mac)
		Stores the MAC of which to system to wake.
	*/
	public function __construct($mac)
	{
		$this->strMac = $mac;
		parent::__construct();
	}
	/** send()
		Creates the packet and sends it to wake up the machine.
	*/
	public function send()
	{
		if (!$this->strMac)
			return false;
		$arByte = explode(':', $this->strMac);
		$strAddr = null;
		for ($i=0; $i<count( $arByte); $i++) 
			$strAddr .= chr(hexdec($arByte[$i]));
		$strRaw = null;
		for ($i=0; $i<6; $i++) 
			$strRaw .= chr(255);
		for ($i=0; $i<16; $i++) 
			$strRaw .= $strAddr;
		$soc = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if (!$soc)
		{
			$errCd = socket_last_error();
			$errMsg = socket_strerror($errCd);
			throw new Exception(sprintf('%s: %s :: %s',_('Socket Error'),$errCd,$errMsg));
		}
		if (socket_set_option($soc,SOL_SOCKET,SO_BROADCAST,true))
		{
			// Always send to the main broadcast.
			$BroadCast[] = '255.255.255.255';
			// Check if WOL Plugin is active and installed
			$PluginActive = current($this->getClass('PluginManager')->find(array('name' => 'wolbroadcast','state' => 1,'installed' => 1)));
			if ($PluginActive)
			{
				foreach($this->getClass('WolbroadcastManager')->find() AS $Broadcast)
				{
					if ($Broadcast && $Broadcast->isValid())
						$BroadCast[] = $Broadcast->get('broadcast');
				}
			}
			foreach((array)$BroadCast AS $SendTo)
			{
				if (socket_sendto($soc,$strRaw,strlen($strRaw),0,$SendTo,9))
					socket_close($soc);
			}
		}
		else
			throw new Exception(_('Failed to set option'));
	}
} 
