<?php
class PowerManagement extends FOGController {
    protected $databaseTable = 'powerManagement';
    protected $databaseFields = array(
        'id' => 'pmID',
        'hostID' => 'pmHostID',
        'min' => 'pmMin',
        'hour' => 'pmHour',
        'dom' => 'pmDom',
        'month' => 'pmMonth',
        'dow' => 'pmDow',
        'onDemand' => 'pmOndemand',
        'action' => 'pmAction',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'min',
        'hour',
        'dom',
        'month',
        'dow',
        'action',
    );
    protected $additionalFields = array(
        'hosts',
    );
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
        $this->set('hosts',self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID'));
    }
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        switch ($this->get('id')) {
        case ($this->isLoaded('hosts')):
            $DBHostIDs = self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID');
            $ValidHostIDs = self::getSubObjectIDs('Host');
            $notValid = array_diff((array)$DBHostIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('PowerManagementManager')->destroy(array('hostID'=>$notValid));
            unset($ValidHostIDs,$notValid);
            $DBHostIDs = self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_unique(array_diff((array)$DBHostIDs,(array)$this->get('hosts')));
            if (count($RemoveHostIDs)) {
                self::getClass('PowerManagementManager')->destroy(array('hostID'=>$RemoveHostIDs,'id'=>$this->get('id')));
                $DBHostIDs = self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            array_map(function(&$Host) {
                if (!$Host->isValid()) return;
                self::getClass('PowerManagement')
                    ->set('hostID',$Host->get('id'))
                    ->save();
                unset($Host);
            },(array)self::getClass('HostManager')->find(array('id'=>array_diff((array)$this->get('hosts'),(array)$DBHostIDs))));
        }
        return $this;
    }
    public function getActionSelect() {
        return $this->getManager()->getActionSelect($this->get('action'),true);
    }
    public function getTimer() {
        $min = trim($this->get('min'));
        $hour = trim($this->get('hour'));
        $dom = trim($this->get('dom'));
        $month = trim($this->get('month'));
        $dow = trim($this->get('dow'));
        return self::getClass('Timer',$min,$hour,$dom,$month,$dow);
    }
    public function getHost() {
        return self::getClass('Host',$this->get('hostID'));
    }
}
