<?php
class TaskScheduler extends FOGService {
    public static $sleeptime = 'SCHEDULERSLEEPTIME';
    public function __construct() {
        parent::__construct();
        list($dev,$log,$zzz) = self::getSubObjectIDs('Service',array('name'=>array('SCHEDULERDEVICEOUTPUT','SCHEDULERLOGFILENAME',$sleeptime)),'value',false,'AND','name',false,'');
        static::$log = sprintf('%s%s',self::$logpath ? self::$logpath : '/opt/fog/log/',$log ? $log : 'fogscheduler.log');
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = $dev ? $dev : '/dev/tty5';
        static::$zzz = (int)($zzz ? $zzz : 60);
    }
    private function commonOutput() {
        try {
            $findWhere = array('stateID'=>$this->getQueuedState(),'wol'=>1);
            $TaskHosts = self::getSubObjectIDs('Task',$findWhere,'hostID');
            $PMHosts = self::getSubObjectIDs('PowerManagement',array('action'=>'wol','onDemand'=>1),'hostID');
            $WOLHosts = array_unique(array_merge($TaskHosts,$PMHosts));
            $taskcount = count($WOLHosts);
            self::outall(sprintf(" * %s active task%s waiting to wake up.",$taskcount,($taskcount != 1 ? 's' : '')));
            if ($taskcount) {
                self::outall(' | Sending WOL Packet(s)');
                array_map(function(&$Host) {
                    if (!$Host->isValid()) return;
                    self::outall(sprintf("\t\t- Host: %s WOL sent to all macs associated",$Host->get('name')));
                    $Host->wakeOnLan();
                    unset($Host);
                },(array)self::getClass('HostManager')->find(array('id'=>$WOLHosts)));
            }
            $findWhere = array('isActive'=>1);
            $taskCount = self::getClass('ScheduledTaskManager')->count($findWhere);
            $taskCount += self::getClass('PowerManagementManager')->count(array('action'=>'wol','onDemand'=>0));
            if ($taskCount < 1) throw new Exception(' * No tasks found!');
            self::outall(sprintf(" * %s task%s found.",$taskCount,($taskCount != 1 ? 's' : '')));
            unset($taskCount);
            array_map(function(&$Task) {
                $Timer = $Task->getTimer();
                self::outall(sprintf(" * Task run time: %s",$Timer->toString()));
                if (!$Timer->shouldRunNow()) return;
                self::outall(" * Found a task that should run...");
                self::outall(sprintf("\t\t - %s %s %s.",_('Is a'),$Task->isGroupBased() ? _('group') : _('host'),_('based task')));
                $Item = $Task->isGroupBased() ? $Task->getGroup() : $Task->getHost();
                self::outall(sprintf("\t\t - %s %s!",$Task->isMulticast() ? _('Multicast') : _('Unicast'),_('task found')));
                self::outall(sprintf("\t\t - %s %s",_(get_class($Item)),$Item->get('name')));
                $Item->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),$Task->isGroupBased(),$Task->get('other3'),false,false,(bool)$Task->get('other4'));
                self::outall(sprintf("\t\t - %s %s %s!",_('Tasks started for'),strtolower(get_class($Item)),$Item->get('name')));
                if (!$Timer->isSingleRun()) return;
                $Task->set('isActive',0)->save();
            },(array)self::getClass('ScheduledTaskManager')->find($findWhere));
            self::outall(sprintf(' * Checking enabled WOL cron tasks'));
            array_map(function(&$Task) {
                $Timer = $Task->getTimer();
                self::outall(sprintf(" * Task run time: %s",$Timer->toString()));
                if (!$Timer->shouldRunNow()) return;
                self::outall(' * Found a wake on lan task that should run...');
                $Task->getHost()->wakeOnLAN();
            },(array)self::getClass('PowerManagementManager')->find(array('action'=>'wol','onDemand'=>0)));
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
