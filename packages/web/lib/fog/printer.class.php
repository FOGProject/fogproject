<?php
class Printer extends FOGController
{
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
    public function destroy($field = 'id')
    {
        self::getClass('PrinterAssociationManager')->destroy(array('printerID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save()
    {
        parent::save();
        return $this->assocSetter('Printer', 'host');
    }
    public function addHost($addArray)
    {
        return $this->addRemItem('hosts', (array)$addArray, 'merge');
    }
    public function removeHost($removeArray)
    {
        return $this->addRemItem('hosts', (array)$removeArray, 'diff');
    }
    protected function loadHosts()
    {
        $this->set('hosts', self::getSubObjectIDs('PrinterAssociation', array('printerID'=>$this->get('id')), 'hostID'));
    }
    protected function loadHostsnotinme()
    {
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme', self::getSubObjectIDs('Host', $find, 'id', true));
        unset($find);
        return $this;
    }
    public function updateDefault($hostid, $onoff)
    {
        $AllHostsPrinter = self::getSubObjectIDs('PrinterAssociation', array('printerID'=>$this->get('id')));
        self::getSubObjectIDs('PrinterAssociationManager')->update(array('id'=>$AllHostsPrinter, 'isDefault'=>'0'));
        self::getSubObjectIDs('PrinterAssociationManager')->update(array('hostID'=>$onoff, 'printerID'=>$this->get('id')), '', array('isDefault'=>'1'));
        return $this;
    }
    public function isValid()
    {
        $validTypes = array(
            'iprint',
            'network',
            'local',
            'cups',
        );
        $curtype = $this->get('config');
        $curtype = trim($this->get('config'));
        $curtype = strtolower($curtype);
        if (!in_array($curtype, $validTypes)) {
            return false;
        }
        return parent::isValid();
    }
}
