<?php
class Group extends FOGController {
    // Table
    public $databaseTable = 'groups';
    // Name -> Database field name
    public $databaseFields = array(
        'id'		=> 'groupID',
        'name'		=> 'groupName',
        'description'	=> 'groupDesc',
        'createdBy'	=> 'groupCreateBy',
        'createdTime'	=> 'groupDateTime',
        'building'	=> 'groupBuilding',
        'kernel'	=> 'groupKernel',
        'kernelArgs'	=> 'groupKernelArgs',
        'kernelDevice'	=> 'groupPrimaryDisk',
    );
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    // Overides
    private function loadHosts() {
        if (!$this->isLoaded('hosts') && $this->get('id')) {
            $HostIDs = $this->getClass('GroupAssociationManager')->find(array('groupID' => $this->get('id')),'','','','','','','hostID');
            $this->set('hosts',$HostIDs);
            $this->set('hostsnotinme',$this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','',true,'id'));
        }
        return $this;
    }
    public function getHostCount() {return $this->getClass('GroupAssociationManager')->count(array('groupID' => $this->get('id')));}
        public function get($key = '') {
            if (in_array($this->key($key),array('hosts','hostsnotinme'))) $this->loadHosts();
            return parent::get($key);
        }
    public function set($key, $value) {
        if ($this->key($key) == 'hosts') {
            $this->loadHosts();
            foreach((array)$value AS &$Host) $newValue[] = ($Host instanceof Host ? $Host : $this->getClass('Host',$Host));
            unset($Host);
            $value = (array)$newValue;
        }
        // Set
        return parent::set($key, $value);
    }
    public function add($key, $value) {
        if ($this->key($key) == 'hosts' && !($value instanceof Host)) {
            $this->loadHosts();
            $value = $this->getClass('Host',$value);
        }
        // Add
        return parent::add($key, $value);
    }
    public function remove($key, $object) {
        if ($this->key($key) == 'hosts') $this->loadHosts();
        // Remove
        return parent::remove($key, $object);
    }
    public function load($field = 'id') {
        parent::load($field);
        foreach(get_class_methods($this) AS &$method) {
            if (strlen($method) > 5 && strpos($method,'load'))
                $this->$method();
        }
        unset($method);
    }
    public function save() {
        parent::save();
        if ($this->isLoaded('hosts')) {
            // Remove old rows
            $this->getClass('GroupAssociationManager')->destroy(array('groupID' => $this->get('id')));
            // Create assoc
            foreach ($this->get('hosts') AS &$Host) {
                if(($Host instanceof Host) && $Host->isValid()) {
                    $this->getClass('GroupAssociation')
                        ->set('hostID',$Host->get('id'))
                        ->set('groupID', $this->get('id'))
                        ->save();
                }
            }
            unset($Host);
        }
        return $this;
    }
    public function addHost($addArray) {
        // Add
        foreach((array)$addArray AS &$item) $this->add('hosts', $item);
        unset($item);
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        // Iterate array (or other as array)
        foreach ((array)$removeArray AS &$remove) $this->remove('hosts', ($remove instanceof Host ? $remove : $this->getClass('Host',(int)$remove)));
        unset($remove);
        // Return
        return $this;
    }
    public function addImage($imageID) {
        if (!$imageID) throw new Exception(_('Select an image'));
        $Image = ($imageID instanceof Image ? $imageID : $this->getClass('Image',(int)$imageID));
        foreach($this->get('hosts') AS &$Host) {
            if ($Host->isValid()) {
                if ($Host->get('task') && $Host->get('task')->isValid()) throw new Exception(_('There is a host in a tasking'));
                if (!$Image || !$Image->isValid()) throw new Exception(_('Image is not valid'));
                else $Host->set('imageID', $Image->get('id'));
                $Host->save();
            }
        }
        unset($Host);
        return $this;
    }
    public function addSnapin($snapArray) {
        foreach($this->get('hosts') AS &$Host) $Host->addSnapin($snapArray)->save();
        unset($Host);
        return $this;
    }
    public function removeSnapin($snapArray) {
        foreach($this->get('hosts') AS &$Host) $Host->removeSnapin($snapArray)->save();
        unset($Host);
        return $this;
    }
    public function setAD($useAD, $domain, $ou, $user, $pass) {
        foreach($this->get('hosts') AS &$Host) $Host->setAD($useAD,$domain,$ou,$user,$pass);
        unset($Host);
        return $this;
    }
    public function addPrinter($printAdd,$printDel,$level = 0) {
        foreach($this->get('hosts') AS &$Host) {
            if ($Host->isValid()) {
                $Host->set('printerLevel',$level)
                    ->addPrinter($printAdd)
                    ->removePrinter($printDel)
                    ->save();
                if ($default)
                    $Host->updateDefault($default);
            }
        }
        unset($Host);
        return $this;
    }
    public function updateDefault($printerid,$onoff) {
        foreach($this->get('hosts') AS &$Host) {
            if ($Host->isValid()) {
                foreach($printerid AS &$printer) {
                    $Printer = $this->getClass('Printer',$printer);
                    if ($Printer && $Printer->isValid()) {
                        if ($Printer->get('id') == $onoff) $Host->updateDefault($Printer->get('id'),1);
                        else $Host->updateDefault($Printer->get('id'),0);
                    }
                }
                unset($printer);
            }
        }
        unset($Host);
        return $this;
    }
    // Custom Variables
    public function doMembersHaveUniformImages() {
        foreach ($this->get('hosts') AS &$Host) $images[] = $Host->get('imageID');
        unset($Host);
        $images = array_unique((array)$images);
        return (count($images) == 1 ? true : false);
    }
    public function destroy($field = 'id') {
        // Remove All Host Associations
        $this->getClass('GroupAssociationManager')->destroy(array('groupID' => $this->get('id')));
        // Return
        return parent::destroy($field);
    }
}
