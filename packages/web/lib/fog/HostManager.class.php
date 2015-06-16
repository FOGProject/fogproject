<?php
class HostManager extends FOGManagerController {
	public function getHostByMacAddresses($MACs) {
		foreach($this->getClass('MACAddressAssociationManager')->find(array('mac' => $MACs)) AS $MAC) {
			if ($MAC && $MAC->isValid()) {
				$macTest = new MACAddress($MAC);
				if ($macTest->isValid()) $MACHost[] = $MAC->get('hostID');
			}
		}
		if ($this->getClass('HostManager')->count(array('id' => $MACHost)) > 1) throw new Exception($this->foglang[ErrorMultipleHosts]);
		return current($this->getClass('HostManager')->find(array('id' => $MACHost)));
	}
	public function isSafeHostName($hostname) {return (preg_match("#^[0-9a-zA-Z_\-]*$#",$hostname) && strlen($hostname) > 0 && strlen($hostname) <= 15);}
	public static function isHostnameSafe($name) {return (strlen($name) > 0 && strlen($name) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $name) == '');}
}
