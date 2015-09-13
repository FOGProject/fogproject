<?php
class RegisterClient extends FOGClient implements FOGClientSend {
    public function send() {
        $maxPending = 0;
        $MACs = array();
        $maxPending = $this->FOGCore->getSetting(FOG_QUICKREG_MAX_PENDING_MACS);
        $MACs = $this->getHostItem(true,false,false,true);
        foreach ($MACs AS $i => &$MAC) $this->getClass(MACAddress,$MAC)->isValid() ? $macs[] = strtolower($MAC) : null;
        unset($MAC);
        $MACs = $macs;
        unset($macs);
        if ($this->newService) {
            if (!($this->Host instanceof Host && $this->Host->isValid())) {
                $this->Host = $this->getClass(HostManager)->find(array(name=>$_REQUEST[hostname]));
                $this->Host = @array_shift($this->Host);
                if (!($this->Host instanceof Host && $this->Host->isValid() && !$this->Host->get(pending))) {
                    $_REQUEST[hostname] = trim($_REQUEST[hostname]);
                    if ($this->getClass(Host)->isHostnameSafe($_REQUEST[hostname])) throw new Exception('#!ih');
                    $ModuleIDs = $this->getClass(ModuleManager)->find(array(isDefault=>1),'','','','','','','id');
                    $PriMAC = @array_shift($MACs);
                    $this->Host = $this->getClass(Host)
                        ->set(name,$_REQUEST[hostname])
                        ->set(description,_('Pending Registration created by FOG_CLIENT'))
                        ->set(pending,1)
                        ->addModule($ModuleIDs)
                        ->addPriMAC($PriMAC)
                        ->addAddMAC($MACs);
                    if (!$this->Host->save()) throw new Exception('#!db');
                    throw new Exception('#!ok');
                }
            }
        }
        if (count($MACs) > $maxPending + 1) throw new Exception('#!er: Too many MACs');
        foreach ($MACs AS $i => &$MAC) $AllMACs[] = strtolower($MAC);
        unset($MAC);
        $this->Host->load();
        $KnownMACs = $this->Host->getMyMacs(false);
        $MACs = array_unique(array_diff((array)$AllMACs,(array)$KnownMACs));
        if (count($MACs)) {
            $this->Host->addPendMAC($MACs);
            if (!$this->Host->save()) throw new Exception('#!db');
            throw new Exception('#!ok');
        }
        throw new Exception('#!ig');
    }
}
