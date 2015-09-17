<?php
class Printer extends FOGController {
    // Table
    public $databaseTable = 'printers';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'pID',
        'name' => 'pAlias',
        'description' => 'pDesc',
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
        $methods = get_class_methods($this);
        foreach ($methods AS $i => &$method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
        unset($method);
        return $this;
    }
    // Overrides
    private function loadHosts() {
        if (!$this->isLoaded(hosts) && $this->get(id)) {
            $HostIDs = $this->getClass(PrinterAssociationManager)->find(array(printerID=>$this->get(id)),'','','','','','','hostID');
            $this->set(hosts,$this->getClass(HostManager)->find(array(id=>$HostIDs),'','','','','','','id'));
            $this->set(hostsnotinme,$this->getClass(HostManager)->find(array(id=>$HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    public function get($key = '') {
        if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
        return parent::get($key);
    }
    public function set($key, $value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        // Set
        return parent::set($key,$value);
    }
    public function add($key, $value) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        // Add
        return parent::add($key, $value);
    }
    public function remove($key, $object) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        // Remove
        return parent::remove($key, $object);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded(hosts)) {
            // Remove all old entries.
            $this->getClass(PrinterAssociationManager)->destroy(array(printerID=>$this->get(id)));
            // Create new Assocs
            $i = 0;
            foreach($this->get(hosts) AS $i => &$Host) {
                $this->getClass(PrinterAssociation)
                    ->set(printerID,$this->get(id))
                    ->set(hostID,$Host)
                    ->set(isDefault,($i === 0 ? 1 : 0))
                    ->save();
                $i++;
            }
            unset($Host);
        }
        return $this;
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS $i => &$item) $this->add(hosts,$item);
        unset($item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach((array)$removeArray AS $i => &$remove) $this->remove(hosts,$remove);
        unset($remove);
        // Return
        return $this;
    }
    public function updateDefault($hostid,$onoff) {
        foreach((array)$hostid AS $i => &$id) {
            $Host = $this->getClass(Host,$id);
            $Host->updateDefault($this->get(id),in_array($Host->get(id),$onoff));
        }
        unset($id);
        return $this;
    }
    public function destroy($field = 'id') {
        // Remove all Host associations
        $this->getClass(PrinterAssociationManager)->destroy(array(printerID=>$this->get(id)));
        // Return
        return parent::destroy($field);
    }
    public function isValid() {
        $name = $this->get(name);
        $port = $this->get(port);
        $file = $this->get('file');
        $ip = $this->get(ip);
        $model = $this->get(model);
        if ($this->get(config) == 'Network') return isset($name);
        else if ($this->get(config) == 'iPrint') return (isset($name) && isset($port));
        else if ($this->get(config) == 'Local') return (isset($name) && isset($port) && isset($file) && isset($model));
        else if ($this->get(config) == 'Cups') return (isset($name) && isset($ip) && isset($file));
    }
}
