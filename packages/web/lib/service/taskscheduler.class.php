<?php
class TaskScheduler extends FOGService {
    public $dev = '';
    public $log = '';
    public $zzz = '';
    public $sleeptime = 'SCHEDULERSLEEPTIME';
    public function __construct() {
        parent::__construct();
        $this->log = sprintf('%s%s',$this->logpath,$this->getSetting('SCHEDULERLOGFILENAME'));
        $this->dev = $this->getSetting('SCHEDULERDEVICEOUTPUT');
        $this->zzz = (int)$this->getSetting($this->sleeptime);
    }
    private function commonOutput() {
        try {
            $findWhere = array('stateID'=>$this->getQueuedState(),'typeID'=>array_merge(range(1,11),range(14,24)));
            $taskcount = $this->getClass('TaskManager')->count($findWhere);
            $this->outall(sprintf(" * %s active task%s awaiting check-in.",$taskcount,($taskcount != 1 ? 's' : '')));
            if ($taskcount) {
                $this->outall(' | Sending WOL Packet(s)');
                foreach ($this->getClass('HostManager')->find(array('id'=>$this->getSubObjectIDs('Task',$findWhere,'hostID'))) AS &$Host) {
                    if (!$Host->isValid()) continue;
                    $this->outall(sprintf("\t\t- Host: %s WOL sent to all macs associated",$Host->get('name')));
                    $Host->wakeOnLan();
                    unset($Host);
                }
                unset($Hosts,$taskcount,$findWhere);
            }
            $findWhere = array('isActive'=>1);
            $taskCount = $this->getClass('ScheduledTaskManager')->count($findWhere);
            if ($taskCount < 1) throw new Exception(' * No tasks found!');
            $this->outall(sprintf(" * %s task%s found.",$taskCount,($taskCount != 1 ? 's' : '')));
            unset($taskCount);
            foreach ((array)$this->getClass('ScheduledTaskManager')->find($findWhere) AS $i => &$Task) {
                $Timer = $Task->getTimer();
                $this->outall(sprintf(" * Task run time: %s",$Timer->toString()));
                if (!$Timer->shouldRunNow()) continue;
                $this->outall(" * Found a task that should run...");
                $this->outall(sprintf("\t\t - %s %s %s.",_('Is a'),$Task->isGroupBased() ? _('group') : _('host'),_('based task')));
                $Item = $Task->isGroupBased() ? $Task->getGroup() : $Task->getHost();
                $this->outall(sprintf("\t\t - %s %s!",$Task->isMulticast() ? _('Multicast') : _('Unicast'),_('task found')));
                $this->outall(sprintf("\t\t - %s %s",_(get_class($Item)),$Item->get('name')));
                $Item->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),$Task->isGroupBased(),$Task->get('other3'));
                $this->outall(sprintf("\t\t - %s %s %s!",_('Tasks started for'),strtolower(get_class($Item)),$Item->get('name')));
                if (!$Timer->isSingleRun()) continue;
                $Task->set('isActive',0)->save();
            }
            unset($Task);
        } catch (Exception $e) {
            $this->outall($e->getMessage());
        }
    }
    public function serviceRun() {
        $this->out(' ',$this->dev);
        $this->out(' +---------------------------------------------------------',$this->dev);
        $this->commonOutput();
        $this->out(' +---------------------------------------------------------',$this->dev);
    }
}
