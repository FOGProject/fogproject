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
    public function save() {
        parent::save();
        switch (true) {
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
            $insert_fields = array('hostID','printerID','isDefault');
            $insert_values = array();
            $Hosts = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            $DefHostIDs = self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$DBHostIDs,'isDefault'=>'1'),'hostID',false,'AND','hostID');
            $DefPrinterIDs = self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$DefHostIDs,'isDefault'=>'1'),'printerID',false,'AND','hostID');
            $DefHostIDs = array_combine((array)$DefHostIDs,(array)$DefPrinterIDs);
            array_walk($Hosts,function(&$hostID,$index) use ($DefHostIDs,&$insert_values) {
                $insert_values[] = array($hostID,$this->get('id'),$DefHostIDs[$hostID] == $this->get('id') ? '1' : '0');
            });
            if (count($insert_values) > 0) self::getClass('PrinterAssociationManager')->insert_batch($insert_fields,$insert_values);
        }
        return $this;
    }
    public function addHost($addArray) {
        return $this->addRemItem('hosts',(array)$addArray,'merge');
    }
    public function removeHost($removeArray) {
        return $this->addRemItem('hosts',(array)$removeArray,'diff');
    }
    protected function loadHosts() {
        $this->set('hosts',self::getSubObjectIDs('PrinterAssociation',array('printerID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme',self::getSubObjectIDs('Host',$find,'id',true));
        unset($find);
        return $this;
    }
    public function updateDefault($hostid,$onoff) {
        $AllHostsPrinter = self::getSubObjectIDs('PrinterAssociation',array('printerID'=>$this->get('id')));
        self::getSubObjectIDs('PrinterAssociationManager')->update(array('id'=>$AllHostsPrinter,'isDefault'=>'0'));
        self::getSubObjectIDs('PrinterAssociationManager')->update(array('hostID'=>$onoff,'printerID'=>$this->get('id')),'',array('isDefault'=>'1'));
        return $this;
    }
}
