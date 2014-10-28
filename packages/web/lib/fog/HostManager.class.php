<?php
/** Class Name: HostManager
	Extends FOGManagerController
*/
class HostManager extends FOGManagerController
{
	// Custom functions
	public static function parseMacList( $stringlist )
	{
		if ( $stringlist != null && strlen( $stringlist ) > 0 )
		{
			$arParts = explode("|",$stringlist );
			$arMacs = array();
			for( $i = 0; $i < count( $arParts ); $i++ )
			{
				$part = trim($arParts[$i]);
				if ( $part != null && strlen( $part ) > 0 )
				{
					$tmpMac = new MACAddress( $part );
					if ( $tmpMac->isValid()  )
						$arMacs[] = $tmpMac;
				} 
			}
			return $arMacs;
		}
		return null;
	}
	public function getHostByMacAddresses($MACs)
	{
		foreach((array)$this->getClass('MACAddressAssociationManager')->find(array('mac' => $MACs)) AS $MAC)
		{
			$MACHost = $MAC->get('hostID');
			$HostTask = new Host($MACHost);
			$MAC = new MACAddress($MAC->get('mac'));
			if ($HostTask && $HostTask->isValid() && $HostTask->get('task') && $HostTask->get('task')->isValid())
			{
				if ($MAC && $MAC->isValid() && !$MAC->isImageIgnored() && !$MAC->isPending())
					$HostIDs[] = $MACHost;
			}
			if ($HostTask && $HostTask->isValid() && (!$HostTask->get('task') || !$HostTask->get('task')->isValid()))
			{
				if ($MAC && $MAC->isValid() && !$MAC->isClientIgnored() && !$MAC->isPending())
					$HostIDs[] = $MACHost;
			}
		}
		$HostIDs = array_unique((array)$HostIDs);
		if (count($HostIDs) > 1)
			throw new Exception($this->foglang['ErrorMultipleHosts']);
		$Host = new Host(implode((array)$HostIDs));
		return $Host;
	}
	/** isSafeHostName($hsotname)
		Checks that the hostname is safe as in string length and characters.
	*/
	public function isSafeHostName($hostname)
	{
		return (preg_match("#^[0-9a-zA-Z_\-]*$#",$hostname) && strlen($hostname) > 0 && strlen($hostname) <= 15);
	}
	/** isHostnameSafe($name)
		Checks if the hostname is safe, if not returns null
	*/
	public static function isHostnameSafe($name)
	{
		return (strlen($name) > 0 && strlen($name) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $name) == '');
	}
}
