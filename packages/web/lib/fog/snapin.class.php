<?php
class Snapin extends FOGController
{
    protected $databaseTable = 'snapins';
    protected $databaseFields = array(
        'id' => 'sID',
        'name' => 'sName',
        'description' => 'sDesc',
        'file' => 'sFilePath',
        'args' => 'sArgs',
        'createdTime' => 'sCreateDate',
        'createdBy' => 'sCreator',
        'reboot' => 'sReboot',
        'shutdown' => 'sShutdown',
        'runWith' => 'sRunWith',
        'runWithArgs' => 'sRunWithArgs',
        'protected' => 'snapinProtect',
        'isEnabled' => 'sEnabled',
        'toReplicate' => 'sReplicate',
        'hide' => 'sHideLog',
        'timeout' => 'sTimeout',
        'packtype' => 'sPackType',
        'hash' => 'sHash',
        'size' => 'sSize',
        'anon3' => 'sAnon3',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'file',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storagegroups',
        'storagegroupsnotinme',
        'path',
    );
    public function destroy($field = 'id')
    {
        self::getClass('SnapinJobManager')->destroy(array('id'=>self::getSubObjectIDs('SnapinTask', array('snapinID'=>$this->get('id')), 'jobID')));
        self::getClass('SnapinTaskManager')->destroy(array('snapinID'=>$this->get('id')));
        self::getClass('SnapinGroupAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        self::getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('Snapin', 'host')
            ->assocSetter('SnapinGroup', 'storagegroup');
    }
    public function deleteFile()
    {
        if ($this->get('protected')) {
            throw new Exception(self::$foglang['ProtectedSnapin']);
        }
        array_map(function (&$StorageNode) {
            if (!$StorageNode->isValid()) {
                return;
            }
            self::$FOGFTP
                ->set('host', $StorageNode->get('ip'))
                ->set('username', $StorageNode->get('user'))
                ->set('password', $StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) {
                return;
            }
            $snapinfiles = self::$FOGFTP->nlist($StorageNode->get('snapinpath'));
            $snapinfile = preg_grep(sprintf('#%s#', $this->get('file')), $snapinfiles);
            if (!count($snapinfile)) {
                return;
            }
            $delete = sprintf('/%s/%s', trim($StorageNode->get('snapinpath'), '/'), $this->get('file'));
            self::$FOGFTP
                ->delete($delete)
                ->close();
            unset($StorageNode);
        }, (array)self::getClass('StorageNodeManager')->find(array('storagegroupID'=>$this->get('storagegroups'), 'isEnabled'=>1)));
    }
    public function addHost($addArray)
    {
        return $this->addRemItem('hosts', (array)$addArray, 'merge');
    }
    public function removeHost($removeArray)
    {
        return $this->addRemItem('hosts', (array)$removeArray, 'diff');
    }
    public function addGroup($addArray)
    {
        return $this->addRemItem('storagegroups', (array)$addArray, 'merge');
    }
    public function removeGroup($removeArray)
    {
        return $this->addRemItem('storagegroups', (array)$removeArray, 'diff');
    }
    public function getStorageGroup()
    {
        if (!count($this->get('storagegroups'))) {
            $this->set('storagegroups', (array)@min(self::getSubObjectIDs('StorageGroup')));
        }
        $Group = array_map(function (&$id) {
            if ($this->getPrimaryGroup($id)) {
                return self::getClass('StorageGroup', $id);
            }
        }, (array)$this->get('storagegroups'));
        $Group = array_shift($Group);
        if ($Group instanceof StorageGroup && $Group->isValid()) {
            return $Group;
        }
        return self::getClass('StorageGroup', @min($this->get('storagegroups')));
    }
    public function getPrimaryGroup($groupID)
    {
        $primaryCount = self::getClass('SnapinGroupAssociationManager')->count(array('snapinID'=>$this->get('id'), 'primary'=>1));
        if ($primaryCount < 1) {
            $this->setPrimaryGroup(@min(self::getSubObjectIDs('StorageGroup')));
        }
        $assocID = @min(self::getSubObjectIDs('SnapinGroupAssociation', array('storagegroupID'=>$groupID, 'snapinID'=>$this->get('id'))));
        return self::getClass('SnapinGroupAssociation', $assocID)->isPrimary();
    }
    public function setPrimaryGroup($groupID)
    {
        self::getClass('SnapinGroupAssociationManager')->update(array('snapinID'=>$this->get('id'), 'storagegroupID'=>array_diff((array)$this->get('storagegroups'), (array)$groupID)), '', array('primary'=>0));
        self::getClass('SnapinGroupAssociationManager')->update(array('snapinID'=>$this->get('id'), 'storagegroupID'=>$groupID), '', array('primary'=>1));
    }
    protected function loadHosts()
    {
        if (!$this->get('id')) {
            return;
        }
        $this->set('hosts', self::getSubObjectIDs('SnapinAssociation', array('snapinID'=>$this->get('id')), 'hostID'));
    }
    protected function loadHostsnotinme()
    {
        if (!$this->get('id')) {
            return;
        }
        $find = array('id'=>$this->get('hosts'));
        $this->set('hostsnotinme', self::getSubObjectIDs('Host', $find, '', true));
        unset($find);
    }
    protected function loadStoragegroups()
    {
        $this->set('storagegroups', self::getSubObjectIDs('SnapinGroupAssociation', array('snapinID'=>$this->get('id')), 'storagegroupID'));
        if (!count($this->get('storagegroups'))) {
            $this->set('storagegroups', (array)@min(self::getSubObjectIDs('StorageGroup', '', 'id')));
        }
    }
    protected function loadStoragegroupsnotinme()
    {
        $this->set('storagegroups', self::getSubObjectIDs('SnapinGroupAssociation', array('snapinID'=>$this->get('id')), 'storagegroupID'));
        $find = array('id'=>$this->get('storagegroups'));
        $this->set('storagegroupsnotinme', self::getSubObjectIDs('StorageGroup', $find, '', true));
        unset($find);
    }
    protected function loadPath()
    {
        if (!$this->get('id')) {
            return;
        }
        $this->set('path', $this->get('file'));
        return $this;
    }
}
