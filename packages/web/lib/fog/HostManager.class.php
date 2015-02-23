<?php
class HostManager extends FOGManagerController
{
	public $loadQueryTemplate = "SELECT *,GROUP_CONCAT(DISTINCT `hostMAC`.`hmID`) hostMacs,`hostPriMac`.`hmMAC` hostPriMac FROM `%s` LEFT OUTER JOIN `hostMAC` ON `hostMAC`.`hmHostID`=`hosts`.`hostID` LEFT OUTER JOIN `hostMAC` hostPriMac ON `hostPriMac`.`hmHostID`=`hosts`.`hostID` %s %s AND `hostPriMac`.`hmPrimary`='1' %s GROUP BY `hostName` %s %s";
	public function getHostByMacAddresses($MACs)
	{
		foreach($this->getClass('MACAddressAssociationManager')->find(array('mac' => $MACs)) AS $MAC)
			$MACHost[] = $MAC->get('hostID');
		$Hosts = array_unique((array)$this->getClass('HostManager')->find(array('id' => array_unique((array)$MACHost))));
		if (count($Hosts) > 1)
			throw new Exception($this->foglang['ErrorMultipleHosts']);
		return current($Hosts);
	}
	public function isSafeHostName($hostname)
	{
		return (preg_match("#^[0-9a-zA-Z_\-]*$#",$hostname) && strlen($hostname) > 0 && strlen($hostname) <= 15);
	}
	public static function isHostnameSafe($name)
	{
		return (strlen($name) > 0 && strlen($name) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $name) == '');
	}
}
