<?php
/** Class Name: HostManager
	Extends FOGManagerController
*/
class HostManager extends FOGManagerController
{
	// Search query
	public $searchQuery = 'SELECT hosts.* FROM hosts
				LEFT OUTER JOIN
					(SELECT * FROM hostMAC WHERE hmMAC LIKE "%${keyword}%") hostMAC
					ON (hmHostID=hostID)
				LEFT OUTER JOIN
					inventory
					ON (iHostID=hostID)
				LEFT OUTER JOIN
					(SELECT * FROM groups INNER JOIN groupMembers ON (gmGroupID=groupID) WHERE groupName LIKE "%${keyword}%" OR groupDesc LIKE "%${keyword}%") groupMembers
					ON (gmHostID=hostID)
				LEFT OUTER JOIN
					images
					ON (hostImage=imageID)
				WHERE 
					hostID LIKE "%${keyword}%" OR
					hostName LIKE "%${keyword}%" OR 
					hostDesc LIKE "%${keyword}%" OR
					hostIP LIKE "%${keyword}%" OR
					hostMAC LIKE "%${keyword}%" OR
					groupID LIKE "%${keyword}%" OR
					groupName LIKE "%${keyword}%" OR
					groupDesc LIKE "%${keyword}%" OR
					imageName LIKE "%${keyword}%" OR
					imageDesc LIKE "%${keyword}%" OR
					iSysserial LIKE "%${keyword}%" OR
					iCaseserial LIKE "%${keyword}%" OR
					iMbserial LIKE "%${keyword}%" OR
					iPrimaryUser LIKE "%${keyword}%" OR
					iOtherTag LIKE "%${keyword}%" OR
					iOtherTag1 LIKE "%${keyword}%" OR
					iSysman LIKE "%${keyword}%" OR
					iSysproduct LIKE "%${keyword}%" 
				GROUP BY 	
					hostID DESC';
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
	public function addMACToPendingForHost($host,$mac)
	{
		// make sure it doesn't exist in the pending table
		$macs = $this->getPendingMacAddressesForHost( $host );
		if ($macs)
		{
			foreach($macs AS $MAC)
			{
				if ($mac->getMACWithColon() == $mac->getMACWithColon())
					return false;
			}
		}
		$PendingMAC = new PendingMAC(array(
			'pending' => $mac,
			'hostID' => $host->get('id'),
		));
		return $PendingMAC->save();
	}
	public function deletePendingMacAddressForHost($host,$mac)
	{
		$PendingMAC = current($this->FOGCore->getClass('PendingMACManager')->find(array('pending' => $mac,'hostID' => $host->get('id'))));
		return $PendingMAC->destroy();
	}
	public function getAllHostsWithPendingMacs()
	{
		$PendingMACs = $this->FOGCore->getClass('PendingMACManager')->find();
		foreach($PendingMACs AS $PendingMAC)
			$HostIDs[] = $PendingMAC->get('hostID');
		if ($HostIDs)
		{
			$HostIDs = array_unique($HostIDs);
			foreach($HostIDs AS $HostID)
				$Hosts[] = new Host($HostID);
			return $Hosts;
		}
		return null;
	}
	public function getPendingMacAddressesForHost($Host)
	{
		$PendingMACs = $this->FOGCore->getClass('PendingMACManager')->find(array('hostID' => $Host->get('id')));
		if ($PendingMACs)
		{
			foreach($PendingMACs AS $PendingMAC)
			{
				$MAC = new MACAddress($PendingMAC->get('pending'));
				if ($MAC->isValid())
					$MACs[] = $MAC;
			}
			return $MACs;
		}
		return null;
	}
	public function getHostByMacAddress($mac,$primaryOnly = false)
	{
		if (!is_object($mac))
			$mac = new MACAddress($mac);
		if ($mac->isValid())
		{
			if (!$primaryOnly)
			{
				$HostMAC = current($this->FOGCore->getClass('MACAddressAssociationManager')->find(array('mac' => $mac)));
				$Host = current($this->FOGCore->getClass('HostManager')->find(array('mac' => $mac)));
				if ($Host && $Host->isValid())
					return $Host;
				else if ((!$Host || !$Host->isValid()) && ($HostMAC && $HostMAC->isValid()))
					return new Host($HostMAC->get('hostID'));
			}
			else
			{
				$Host = current($this->FOGCore->getClass('HostManager')->find(array('mac' => $mac)));
				if ($Host && $Host->isValid())
					return $Host;
			}
		}
		return new Host(array('id' => 0));
	}
	public function doesHostExistWithMac( $mac, $ignoringHostId=-1 )
	{
		$host = $this->getHostByMacAddress( $mac );
		if ( $host == null )
			return false;
		else
		{	
			if ( $ignoringHostId == -1 )
				return true;
			else
				return  $host->get('id') != $ignoringHostId;
		} 
	}
	public function getHostByMacAddresses($MACs)
	{
		if ($MACs)
		{
			if (is_array($MACs))
			{
				$hostReturn = null;
				foreach($MACs as $MAC)
				{
					if ($MAC && $MAC->isValid())
					{
						$tmpHost = $this->getHostByMacAddress($MAC);
						if ($hostReturn == null)
							$hostReturn = $tmpHost;
						else
						{
							if ($hostReturn->get('id') != $tmpHost->get('id'))
								throw new Exception(_('Error multiple hosts returned for list of mac addresses!'));
						}
					}
				}
				return $hostReturn;
			}
			else
				return $this->getHostByMacAddress($MACs);
		}
		return null;
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
