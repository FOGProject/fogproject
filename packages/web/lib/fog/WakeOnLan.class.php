<?php
/** \class WakeOnLan
	Builds the magic packet needed for waking systems from LAN.
*/
class WakeOnLan extends FOGBase
{
	private $strMac,$hwaddr,$packet;
	/** __construct($mac)
		Stores the MAC of which to system to wake.
	*/
	public function __construct($mac)
	{
		parent::__construct();
		$MAC = new MACAddress($mac);
		if ($MAC && $MAC->isValid())
			$this->strMac = $MAC->__toString();
	}
	/** send()
		Creates the packet and sends it to wake up the machine.
	*/
	public function send()
	{
		try
		{
			if (!$this->strMac)
				throw new Exception($foglang['InvalidMAC']);
			$mac_array = split(':', $this->strMac);
			unset($BroadCast,$this->hwaddr,$this->packet);
			foreach($mac_array AS $octet)
				$this->hwaddr[] = chr(hexdec($octet));
			for($i=0;$i<=6;$i++) 
				$this->packet[] = chr(255);
			for($i=0;$i<=16;$i++) 
				$this->packet[] = implode($this->hwaddr);
			// Always send to the main broadcast.
			$BroadCast[] = '255.255.255.255';
			// Check if WOL Plugin is active and installed
			$PluginActive = in_array('wolbroadcast',$_SESSION['PluginsInstalled']);
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
				$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
				if (!$sock)
					throw new Exception(sprintf('%s: %s :: %s',_('Socket Error'),socket_last_error(),socket_strerror(socket_last_error())));
				$options = socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
				if ($options >= 0 && socket_sendto($sock,implode($this->packet),strlen(implode($this->packet)),0,$SendTo,9))
						socket_close($sock);
			}
		}
		catch(Exception $e)
		{
			return false;
		}
		return true;
	}
} 
