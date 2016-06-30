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
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    protected function loadHosts() {
        $this->set('hosts',self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID'));
    }
    public function save() {
        parent::save();
        switch (true) {
        case ($this->get('hosts')):
            $DBHostIDs = self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID');
            $ValidHostIDs = self::getSubObjectIDs('Host');
            $notValid = array_diff((array)$DBHostIDs,(array)$ValidHostIDs);
            if (count($notValid)) self::getClass('PowerManagementManager')->destroy(array('hostID'=>$notValid));
            unset($ValidHostIDs,$DBHostIDs);
            $DBHostIDs = self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                self::getClass('PowerManagementManager')->destroy(array('id'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = self::getSubObjectIDs('PowerManagement',array('id'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            $insert_fields = array('hostID');
            $insert_values = array();
            $DBHostIDs = array_diff((array)$this->get('hosts'),(array)$DBHostIDs);
            array_walk($DBHostIDs,function(&$hostID,$index) use (&$insert_values) {
                $insert_values[] = array($hostID);
            });
            if (count($insert_values) > 0) self::getClass('PowerManagementManager')->insert_batch($insert_fields,$insert_values);
            unset($DBHostIDs,$RemoveHostIDs);
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
