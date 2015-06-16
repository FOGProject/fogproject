<?php
class Printer extends FOGController {
    // Table
    public $databaseTable = 'printers';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'pID',
        'name' => 'pAlias',
        'port' => 'pPort',
        'file' => 'pDefFile',
        'model' => 'pModel',
        'config' => 'pConfig',
        'ip' => 'pIP',
        'pAnon2' => 'pAnon2',
        'pAnon3' => 'pAnon3',
        'pAnon4' => 'pAnon4',
        'pAnon5' => 'pAnon5',
    );
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'id',
        'name',
    );
    public function load($field = 'id') {
        parent::load($field);
        foreach(get_class_methods($this) AS $method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
    }
    // Overrides
    private function loadHosts() {
        if (!$this->isLoaded('hosts') && $this->get('id')) {
            $HostIDs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $this->get('id')),'','','','','','','hostID');
            foreach ($this->getClass('HostManager')->find(array('id' => $HostIDs)) AS $Host) {
                if ($Host->isValid()) $this->add('hosts',$Host);
            }
            foreach ($this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','',true) AS $Host) {
                if ($Host->isValid()) $this->add('hostsnotinme',$Host);
            }
        }
        return $this;
    }
    public function get($key = '') {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        return parent::get($key);
    }
    public function set($key, $value) {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) {
            $this->loadHosts();
            foreach((array)$value AS $Host) $newValue[] = ($Host instanceof Host ? $Host : new Host($Host));
            $value = (array)$newValue;
        }
        // Set
        return parent::set($key,$value);
    }
    public function add($key, $value) {
        if (in_array($this->key($key),array('hosts','hostsnotinme')) && !($value instanceof Host)) {
            $this->loadHosts();
            $value = new Host($value);
        }
        // Add
        return parent::add($key, $value);
    }
    public function remove($key, $object) {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        // Remove
        return parent::remove($key, $object);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded('hosts')) {
            // Remove all old entries.
            $this->getClass('PrinterAssociationManager')->destroy(array('printerID' => $this->get('id')));
            // Create new Assocs
            $i = 0;
            foreach((array)$this->get('hosts') AS $Host) {
                if (($Host instanceof Host) && $Host->isValid()) {
                    $this->getClass('PrinterAssociation')
                        ->set('printerID',$this->get('id'))
                        ->set('hostID', $Host->get('id'))
                        ->set('isDefault', ($i === 0 ? 1 : 0))
                        ->save();
                }
                $i++;
            }
        }
        return $this;
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $item) $this->add('hosts', $item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $remove) $this->remove('hosts', ($remove instanceof Host ? $remove : new Host((int)$remove)));
        // Return
        return $this;
    }
    public function updateDefault($hostid,$onoff) {
        foreach((array)$hostid AS $id) {
            $Host = $this->getClass('Host',$id);
            if ($Host && $Host->isValid()) $Host->updateDefault($this->get('id'),in_array($Host->get('id'),$onoff));
        }
        return $this;
    }
    public function destroy($field = 'id') {
        // Remove all Host associations
        $this->getClass('PrinterAssociationManager')->destroy(array('printerID' => $this->get('id')));
        // Return
        return parent::destroy($field);
    }
    public function isValid() {
        $ret = false;
        if ($this->get('config') == 'Network') $ret = ($this->get('name') ? true : false);
        else if ($this->get('config') == 'iPrint') $ret = ($this->get('name') && $this->get('port') ? true : false);
        else if ($this->get('config') == 'Local') $ret = ($this->get('name') && $this->get('port') && $this->get('file') && $this->get('model') ? true : false);
        return $ret;
    }
}
