<?php
class RegisterClient extends FOGClient implements FOGClientSend {
    public function json() {
        $maxPending = 0;
        $MACs = $this->getHostItem(true,false,false,true);
        list($enforce,$maxPending) = self::getSubObjectIDs('Service',array('name'=>array('FOG_ENFORCE_HOST_CHANGES','FOG_QUICKREG_MAX_PENDING_MACS')),'value',false,'AND','name',false,'');
        $hostname = trim($_REQUEST['hostname']);
        if (!$this->Host->isValid()) {
            $this->Host = self::getClass('Host')->set('name',$hostname)->load('name');
            if (!($this->Host->isValid() && !$this->Host->get('pending'))) {
                if (!self::getClass('Host')->isHostnameSafe($hostname)) throw new Exception('#!ih');
                $PriMAC = @array_shift($MACs);
                $this->Host = self::getClass('Host')
                    ->set('name',$hostname)
                    ->set('description',_('Pending Registration created by FOG_CLIENT'))
                    ->set('pending',1)
                    ->set('enforce',(int)$enforce)
                    ->addModule(self::getSubObjectIDs('Module',array('isDefault'=>1)))
                    ->addPriMAC($PriMAC)
                    ->addAddMAC($MACs);
                if (!$this->Host->save()) throw new Exception('#!db');
            }
        }
        if (count($MACs) > $maxPending + 1) throw new Exception(_('Too many MACs'));
        $MACs = $this->parseMacList($MACs,false,true);
        $KnownMACs = $this->Host->getMyMacs(false);
        $MACs = array_unique(array_diff((array)$MACs,(array)$KnownMACs));
        $lowerAndTrim = function($element) {
            return strtolower(trim($element));
        };
        $MACs = array_map($lowerAndTrim,$MACs);
        if (count($MACs)) {
            $this->Host->addPendMAC($MACs);
            if (!$this->Host->save()) throw new Exception('#!db');
            throw new Exception('#!ok');
        }
        throw new Exception('#!ig');
    }
    public function send() {
        $maxPending = 0;
        $MACs = $this->getHostItem(true,false,true,true);
        list($enforce,$maxPending) = self::getSubObjectIDs('Service',array('name'=>array('FOG_ENFORCE_HOST_CHANGES','FOG_QUICKREG_MAX_PENDING_MACS')),'value',false,'AND','name',false,'');
        if ($this->newService) {
            $hostname = trim($_REQUEST['hostname']);
            if (!$this->Host->isValid()) {
                $this->Host = self::getClass('Host')->set('name',$hostname)->load('name');
                if (!($this->Host->isValid() && !$this->Host->get('pending'))) {
                    if (!self::getClass('Host')->isHostnameSafe($hostname)) throw new Exception('#!ih');
                    $PriMAC = @array_shift($MACs);
                    $this->Host = self::getClass('Host')
                        ->set('name',$hostname)
                        ->set('description',_('Pending Registration created by FOG_CLIENT'))
                        ->set('pending',1)
                        ->set('enforce',(int)$enforce)
                        ->addModule(self::getSubObjectIDs('Module',array('isDefault'=>1)))
                        ->addPriMAC($PriMAC)
                        ->addAddMAC($MACs);
                    if (!$this->Host->save()) throw new Exception('#!db');
                }
            }
        }
        if (count($MACs) > $maxPending + 1) throw new Exception(_('Too many MACs'));
        $MACs = $this->parseMacList($MACs,false,true);
        $KnownMACs = $this->Host->getMyMacs(false);
        $MACs = array_unique(array_diff((array)$MACs,(array)$KnownMACs));
        $lowerAndTrim = function($element) {
            return strtolower(trim($element));
        };
        $MACs = array_map($lowerAndTrim,$MACs);
        if (count($MACs)) {
            $this->Host->addPendMAC($MACs);
            if (!$this->Host->save()) throw new Exception('#!db');
            throw new Exception('#!ok');
        }
        throw new Exception('#!ig');
    }
}
