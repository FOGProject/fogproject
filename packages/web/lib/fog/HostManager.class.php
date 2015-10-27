<?php
class HostManager extends FOGManagerController {
    public function getHostByMacAddresses($MACs) {
        $MACHost = $this->getSubObjectIDs('MACAddressAssociation',array('pending'=>0,'mac'=>$MACs),'hostID');
        if (count($MACHost) > 1) throw new Exception($this->foglang['ErrorMultipleHosts']);
        return $this->getClass('Host',@min($MACHost));
    }
}
