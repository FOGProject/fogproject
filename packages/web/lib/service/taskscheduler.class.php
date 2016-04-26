<?php
class TaskScheduler extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'SCHEDULERSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',static::$logpath,static::getSetting('SCHEDULERLOGFILENAME'));
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = static::getSetting('SCHEDULERDEVICEOUTPUT');
        static::$zzz = (int)static::getSetting(static::$sleeptime);
    }
    private function commonOutput() {
        try {
            $findWhere = array('stateID'=>$this->getQueuedState(),'wol'=>1);
            $taskcount = static::getClass('TaskManager')->count($findWhere);
            static::outall(sprintf(" * %s active task%s waiting to wake up.",$taskcount,($taskcount != 1 ? 's' : '')));
            if ($taskcount) {
                static::outall(' | Sending WOL Packet(s)');
                foreach (static::getClass('HostManager')->find(array('id'=>static::getSubObjectIDs('Task',$findWhere,'hostID'))) AS &$Host) {
                    if (!$Host->isValid()) continue;
                    static::outall(sprintf("\t\t- Host: %s WOL sent to all macs associated",$Host->get('name')));
                    $Host->wakeOnLan();
                    unset($Host);
                }
                unset($Hosts,$taskcount,$findWhere);
            }
            $findWhere = array('isActive'=>1);
            $taskCount = static::getClass('ScheduledTaskManager')->count($findWhere);
            if ($taskCount < 1) throw new Exception(' * No tasks found!');
            static::outall(sprintf(" * %s task%s found.",$taskCount,($taskCount != 1 ? 's' : '')));
            unset($taskCount);
            foreach ((array)static::getClass('ScheduledTaskManager')->find($findWhere) AS $i => &$Task) {
                $Timer = $Task->getTimer();
                static::outall(sprintf(" * Task run time: %s",$Timer->toString()));
                if (!$Timer->shouldRunNow()) continue;
                static::outall(" * Found a task that should run...");
                static::outall(sprintf("\t\t - %s %s %s.",_('Is a'),$Task->isGroupBased() ? _('group') : _('host'),_('based task')));
                $Item = $Task->isGroupBased() ? $Task->getGroup() : $Task->getHost();
                static::outall(sprintf("\t\t - %s %s!",$Task->isMulticast() ? _('Multicast') : _('Unicast'),_('task found')));
                static::outall(sprintf("\t\t - %s %s",_(get_class($Item)),$Item->get('name')));
                $Item->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),$Task->isGroupBased(),$Task->get('other3'),false,false,(bool)$Task->get('other4'));
                static::outall(sprintf("\t\t - %s %s %s!",_('Tasks started for'),strtolower(get_class($Item)),$Item->get('name')));
                if (!$Timer->isSingleRun()) continue;
                $Task->set('isActive',0)->save();
            }
            unset($Task);
        } catch (Exception $e) {
            static::outall($e->getMessage());
        }
    }
    public function serviceRun() {
        static::out(' ',static::$dev);
        static::out(' +---------------------------------------------------------',static::$dev);
        $this->commonOutput();
        static::out(' +---------------------------------------------------------',static::$dev);
    }
}
