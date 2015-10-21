<?php
class TaskScheduler extends FOGService {
    public $dev = SCHEDULERDEVICEOUTPUT;
    public $log = SCHEDULERLOGPATH;
    public $zzz = SCHEDULERSLEEPTIME;
    private function commonOutput() {
        try {
            $findWhere = array('stateID'=>1,'typeID'=>array_merge(range(1,11),range(14,24)));
            $taskcount = $this->getClass('TaskManager')->count($findWhere);
            if ($taskcount) {
                $this->outall(sprintf(" * %s active task(s) awaiting check-in.",$taskcount));
                $this->outall(' | Sending WOL Packet(s)');
                $Hosts = $this->getSubObjectIDs('Task',$findWhere,'hostID');
                foreach($Hosts AS $i => &$Host) {
                    $this->outall(sprintf("\t\t- Host: %s WOL sent to all macs associated",$Host->get(name)));
                    $Host->wakeOnLan();
                    usleep(500000);
                }
                unset($Hosts,$taskcount,$findWhere);
            } else $this->outall(" * 0 active task(s) awaiting check-in.");
            $findWhere = array('isActive'=>1);
            $taskcount = $this->getClass('ScheduledTaskManager')->count($findWhere);
            $Tasks = $this->getClass('ScheduledTaskManager')->find($findWhere);
            if (!$taskcount) throw new Exception(' * No tasks found!');
            $this->outall(sprintf(" * %s task(s) found.",count($Tasks)));
            foreach($Tasks AS $i => &$Task) {
                $Timer = $Task->getTimer();
                $this->outall(sprintf(" * Task run time: %s",$Timer->toString()));
                if (!$Timer->shouldRunNow()) {
                    $this->outall(' * Task does not run now');
                    continue;
                }
                $this->outall(" * Found a task that should run...");
                if ($Task->isGroupBased()) {
                    $this->outall(sprintf("\t\t - Is a group based task."));
                    $Group = $Task->getGroup();
                    if ($Task->get('taskType') == 8) $this->outall("\t\t - Multicast task found!");
                    else $this->outall("\t\t - Regular task found!");
                    $this->outall(sprintf("\t\t - Group %s",$Group->get('name')));
                    if ($Group->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),true,$Task->get('other3'))) $this->outall(sprintf("\t\t - Tasks started for group %s!",$Group->get('name')));
                    if ($Timer->isSingleRun()) {
                        if ($this->FOGCore->stopScheduledTask($Task)) $this->outall("\t\t - Scheduled Task cleaned.");
                        else $this->outall("\t\t - failed to clean task.");
                    } else $this->outall("\t\t - Cron style - No cleaning!");
                } else {
                    $this->outall("\t\t - Is a host based task.");
                    $Host = $Task->getHost();
                    $Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),false,$Task->get('other3'));
                    $this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));
                }
                if ($Timer->isSingleRun()) {
                    if ($this->FOGCore->stopScheduledTask($Task)) $this->outall("\t\t - Scheduled Task cleaned.");
                    else $this->outall("\t\t - failed to clean task.");
                } else $this->outall("\t\t - Cron style - No cleaning!");
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
