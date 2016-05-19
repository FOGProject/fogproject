<?php
class TaskScheduler extends FOGService {
    public static $sleeptime = 'SCHEDULERSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',self::$logpath,self::getSetting('SCHEDULERLOGFILENAME'));
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = self::getSetting('SCHEDULERDEVICEOUTPUT');
        static::$zzz = (int)self::getSetting(self::$sleeptime);
    }
    private function commonOutput() {
        try {
            $findWhere = array('stateID'=>$this->getQueuedState(),'wol'=>1);
            $taskcount = self::getClass('TaskManager')->count($findWhere);
            self::outall(sprintf(" * %s active task%s waiting to wake up.",$taskcount,($taskcount != 1 ? 's' : '')));
            if ($taskcount) {
                self::outall(' | Sending WOL Packet(s)');
                foreach (self::getClass('HostManager')->find(array('id'=>self::getSubObjectIDs('Task',$findWhere,'hostID'))) AS &$Host) {
                    if (!$Host->isValid()) continue;
                    self::outall(sprintf("\t\t- Host: %s WOL sent to all macs associated",$Host->get('name')));
                    $Host->wakeOnLan();
                    unset($Host);
                }
                unset($Hosts,$taskcount,$findWhere);
            }
            $findWhere = array('isActive'=>1);
            $taskCount = self::getClass('ScheduledTaskManager')->count($findWhere);
            if ($taskCount < 1) throw new Exception(' * No tasks found!');
            self::outall(sprintf(" * %s task%s found.",$taskCount,($taskCount != 1 ? 's' : '')));
            unset($taskCount);
            foreach ((array)self::getClass('ScheduledTaskManager')->find($findWhere) AS $i => &$Task) {
                $Timer = $Task->getTimer();
                self::outall(sprintf(" * Task run time: %s",$Timer->toString()));
                if (!$Timer->shouldRunNow()) continue;
                self::outall(" * Found a task that should run...");
                self::outall(sprintf("\t\t - %s %s %s.",_('Is a'),$Task->isGroupBased() ? _('group') : _('host'),_('based task')));
                $Item = $Task->isGroupBased() ? $Task->getGroup() : $Task->getHost();
                self::outall(sprintf("\t\t - %s %s!",$Task->isMulticast() ? _('Multicast') : _('Unicast'),_('task found')));
                self::outall(sprintf("\t\t - %s %s",_(get_class($Item)),$Item->get('name')));
                $Item->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),$Task->isGroupBased(),$Task->get('other3'),false,false,(bool)$Task->get('other4'));
                self::outall(sprintf("\t\t - %s %s %s!",_('Tasks started for'),strtolower(get_class($Item)),$Item->get('name')));
                if (!$Timer->isSingleRun()) continue;
                $Task->set('isActive',0)->save();
            }
            unset($Task);
        } catch (Exception $e) {
            self::outall($e->getMessage());
        }
    }
    public function serviceRun() {
        self::out(' ',static::$dev);
        self::out(' +---------------------------------------------------------',static::$dev);
        $this->commonOutput();
        self::out(' +---------------------------------------------------------',static::$dev);
    }
}
