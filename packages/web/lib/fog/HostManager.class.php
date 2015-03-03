<?php
class HostManager extends FOGManagerController
{
	public function getHostByMacAddresses($MACs)
	{
		foreach($this->getClass('MACAddressAssociationManager')->find(array('mac' => $MACs)) AS $MAC)
			$MACHost[] = $MAC->get('hostID');
		$Hosts = $this->getClass('HostManager')->find(array('id' => array_unique((array)$MACHost)));
		if (count($Hosts) > 1)
			throw new Exception($this->foglang['ErrorMultipleHosts']);
		if (count($Hosts))
		{
			foreach($Hosts AS $Host)
			{
				if ($Host && $Host->isValid())
					return $Host;
			}
		}
		return false;
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
