<?php
class HostManager extends FOGManagerController {
    public function getHostByMacAddresses($MACs) {
        $MACHost = $this->getSubObjectIDs('MACAddressAssociation',array('pending'=>array(0,null,''),'mac'=>$MACs),'hostID');
        if (count($MACHost) > 1) throw new Exception($this->foglang['ErrorMultipleHosts']);
        return $this->getClass('Host',@min($MACHost));
    }
}
