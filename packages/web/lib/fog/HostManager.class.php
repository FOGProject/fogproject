<?php
class HostManager extends FOGManagerController {
    public function getHostByMacAddresses($MACs) {
        $MACHost = $this->getClass(MACAddressAssociationManager)->find(array(mac=>$MACs,pending=>0),'','','','','','','hostID');
        if ($this->getClass(HostManager)->count(array(id=>$MACHost)) > 1) throw new Exception($this->foglang[ErrorMultipleHosts]);
        $hostID = @array_shift($MACHost);
        return $this->getClass(Host,$hostID);
    }
    public function isSafeHostName($hostname) {
        return (preg_match("#^[0-9a-zA-Z_\-]*$#",$hostname) && strlen($hostname) > 0 && strlen($hostname) <= 15);
    }
    public static function isHostnameSafe($name) {
        return (strlen($name) > 0 && strlen($name) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $name) == '');
    }
}
