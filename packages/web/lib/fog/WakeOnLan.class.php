<?php
/** \class WakeOnLan
	Builds the magic packet needed for waking systems from LAN.
*/
class WakeOnLan
{
	private $strMac;
	
	/** __construct($mac)
		Stores the MAC of which to system to wake.
	*/
	public function __construct( $mac )
	{
		$this->strMac = $mac;
	}
	
	/** send()
		Creates the packet and sends it to wake up the machine.
	*/
	public function send()
	{
		if ( $this->strMac != null )
		{
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
			if ( $soc !== FALSE )
			{
				if(socket_set_option($soc, SOL_SOCKET, SO_BROADCAST, TRUE)) 
				{
					if( socket_sendto($soc, $strRaw, strlen($strRaw), 0, "255.255.255.255", 9) ) 
					{
						socket_close($soc);
						return true;
					}
					else 
						return false;				
				}
				else
					new Exception( "Failed to set option!");	
			}
			else
			{
				$errCd = socket_last_error();
				$errMsg = socket_strerror($errCd);
				throw new Exception( "Socket Error: $errCd :: $errMsg" );
			}
		}
		return false;
	}
} 
