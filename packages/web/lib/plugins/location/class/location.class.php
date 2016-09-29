<?php
class Location extends FOGController
{
    protected $databaseTable = 'location';
    protected $databaseFields = array(
        'id' => 'lID',
        'name' => 'lName',
        'description' => 'lDesc',
        'createdBy' => 'lCreatedBy',
        'createdTime' => 'lCreatedTime',
        'storagegroupID' => 'lStorageGroupID',
        'storageNodeID' => 'lStorageNodeID',
        'tftp' => 'lTftpEnabled',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'storagegroupID',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    public function destroy($field = 'id')
    {
        self::getClass('LocationAssociationManager')->destroy(array('locationID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save()
    {
        parent::save();
        return $this->assocSetter('Location', 'host');
    }
    public function addHost($addArray)
    {
        return $this->addRemItem('hosts', (array)$addArray, 'merge');
    }
    public function removeHost($removeArray)
    {
        return $this->addRemItem('hosts', (array)$removeArray, 'diff');
    }
    public function getStorageGroup()
    {
        return self::getClass('StorageGroup', $this->get('storagegroupID'));
    }
    public function getStorageNode()
    {
        if ($this->get('storageNodeID')) {
            return self::getClass('StorageNode', $this->get('storageNodeID'));
        }
        return $this->getStorageGroup()->getOptimalStorageNode(0);
    }
    protected function loadHosts()
    {
        $this->set('hosts', self::getSubObjectIDs('LocationAssociation', array('locationID'=>$this->get('id')), 'hostID'));
    }
    protected function loadHostsnotinme()
    {
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme', self::getSubObjectIDs('Host', $find, 'id', true));
        unset($find);
    }
}
