<?php
class HostManager extends FOGManagerController
{
	public function getHostByMacAddresses($MACs)
	{
		foreach($this->getClass('MACAddressAssociationManager')->find(array('mac' => $MACs)) AS $MAC)
			$MACHost[] = $MAC->get('hostID');
		$Hosts = array_unique($this->getClass('HostManager')->find(array('id' => array_unique($MACHost))));
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
