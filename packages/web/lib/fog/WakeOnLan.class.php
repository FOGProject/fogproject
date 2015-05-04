<?php
class WakeOnLan extends FOGBase {
	private $arrMAC;
	private $hwaddr;
	private $packet;
	/** __construct($mac)
		Stores the MAC of which to system to wake.
	*/
	public function __construct($mac) {
		parent::__construct();
		$this->arrMAC = array();
		foreach ((array)$mac AS $MAC) {
			$MAC = $this->getClass('MACAddress',$MAC);
			if ($MAC->isValid()) $this->arrMAC[] = $MAC->__toString();
		}
	}
	/** send()
		Creates the packet and sends it to wake up the machine.
	*/
	public function send() {
		try {
			if (!count($this->arrMAC)) throw new Exception($foglang['InvalidMAC']);
			foreach ((array)$this->arrMAC AS $MAC) {
				$mac_array = split(':',$MAC);
				unset($BroadCast,$this->hwaddr,$this->packet);
				foreach($mac_array AS $octet) $this->hwaddr .= chr(hexdec($octet));
				for($i=0;$i<=6;$i++) $this->packet .= chr(255);
				for($i=0;$i<=16;$i++) $this->packet .= $this->hwaddr;
				// Always send to the main broadcast.
				$BroadCast[] = '255.255.255.255';
				$this->HookManager->processEvent('BROADCAST_ADDR',array('broadcast' => &$BroadCast));
				foreach((array)$BroadCast AS $SendTo) {
					$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
					if (!$sock) throw new Exception(sprintf('%s: %s :: %s',_('Socket Error'),socket_last_error(),socket_strerror(socket_last_error())));
					$options = socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
					if ($options >= 0 && socket_sendto($sock,$this->packet,strlen($this->packet),0,$SendTo,9)) socket_close($sock);
				}
			}
		} catch(Exception $e) {
			return false;
		}
		return true;
	}
} 
