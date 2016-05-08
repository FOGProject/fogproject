<?php
class Printer extends FOGController {
    protected $databaseTable = 'printers';
    protected $databaseFields = array(
        'id' => 'pID',
        'name' => 'pAlias',
        'description' => 'pDesc',
        'port' => 'pPort',
        'file' => 'pDefFile',
        'model' => 'pModel',
        'config' => 'pConfig',
        'configFile' => 'pConfigFile',
        'ip' => 'pIP',
        'pAnon2' => 'pAnon2',
        'pAnon3' => 'pAnon3',
        'pAnon4' => 'pAnon4',
        'pAnon5' => 'pAnon5',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    public function destroy($field = 'id') {
        self::getClass('PrinterAssociationManager')->destroy(array('printerID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        switch ($this->get('id')) {
        case 0:
        case null:
        case false:
        case '0':
        case '':
            $this->destroy();
            throw new Exception(_('Printer ID was not set, or unable to be created'));
            break;
        case ($this->isLoaded('hosts')):
            $DBHostIDs = self::getSubObjectIDs('PrinterAssociation',array('printerID'=>$this->get('id')),'hostID');
            $ValidHostIDs = self::getSubObjectIDs('Host');
            $notValid = array_diff((array)$DBHostIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$notValid));
            unset($ValidHostIDs,$notValid);
            $DBHostIDs = self::getSubObjectIDs('PrinterAssociation',array('printerID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_unique(array_diff((array)$DBHostIDs,(array)$this->get('hosts')));
            if (count($RemoveHostIDs)) {
                self::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$RemoveHostIDs,'printerID'=>$this->get('id')));
                $DBHostIDs = self::getSubObjectIDs('PrinterAssociation',array('printerID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            array_map(function(&$Host) {
                if (!$Host->isValid()) return;
                $hasDefault = self::getClass('PrinterAssociationManager')->count(array('isDefault'=>1,'hostID'=>$Host->get('id')));
                self::getClass('PrinterAssociation')
                    ->set('printerID',$this->get('id'))
                    ->set('hostID',$Host->get('id'))
                    ->set('isDefault',($hasDefault != 1))
                    ->save();
                unset($Host);
            },(array)self::getClass('HostManager')->find(array('id'=>array_diff((array)$this->get('hosts'),(array)$DBHostIDs))));
        }
        return $this;
    }
    public function addHost($addArray) {
        if (!$this->get('id')) return;
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        if (!$this->get('id')) return;
        if (!$this->isLoaded('hosts')) $this->loadHosts();
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    protected function loadHosts() {
        if (!$this->get('id')) return;
        $this->set('hosts',self::getSubObjectIDs('PrinterAssociation',array('printerID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'id',true));
        unset($find);
        return $this;
    }
    public function updateDefault($hostid,$onoff) {
        if (!$this->get('id')) return;
        array_map(function(&$id) {
            self::getClass('Host',$id)->updateDefault($this->get('id'),in_array($id,(array)$onoff));
            unset($id);
        },(array)$hostid);
        return $this;
    }
}
