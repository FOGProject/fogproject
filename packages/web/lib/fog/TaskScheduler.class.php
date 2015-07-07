<?php
class TaskScheduler extends FOGService {
    public $dev = SCHEDULERDEVICEOUTPUT;
    public $log = SCHEDULERLOGPATH;
    public $zzz = SCHEDULERSLEEPTIME;
    private function commonOutput() {
        try {
            $DateInterval = $this->nice_date('-30 minutes');
            $Hosts = $this->getClass(HostManager)->find();
            foreach($Hosts AS $i => &$Host) {
                if ($Host->isValid()) {
                    if ($this->validDate($Host->get('sec_time'))) {
                        $DateTime = $this->nice_date($Host->get('sec_time'));
                        if ($DateTime->format('Y-m-d H:i:s') >= $DateInterval->format('Y-m-d H:i:s')) $Host->set('pub_key',null)->set('sec_time',null)->save();
                    }
                }
            }
            unset($Host);
            $Tasks = $this->getClass(TaskManager)->find(array('stateID' => 1,'typeID' => array(1,15,17)));
            if ($Tasks) {
                $this->outall(sprintf(" * %s active task(s) awaiting check-in sending WOL request(s).",$this->getClass('TaskManager')->count(array('stateID' => 1,'typeID' => array(1,15,17)))));
                foreach($Tasks AS $i => &$Task) {
                    $Host = new Host($Task->get('hostID'));
                    $this->FOGCore->wakeOnLan($Host->get('mac'));
                    $this->outall(sprintf("\t\t- Host: %s WOL sent using MAC: %s",$Host->get('name'),$Host->get('mac')));
                    usleep(500000);
                }
                unset($Task);
            } else $this->outall(" * 0 active task(s) awaiting check-in.");
            $Tasks = $this->getClass(ScheduledTaskManager)->find(array('isActive' => 1));
            if ($Tasks) {
                $this->outall(sprintf(" * %s task(s) found.",count($Tasks)));
                foreach($Tasks AS $i => &$Task) {
                    $Timer = $Task->getTimer();
                    $this->outall(sprintf(" * Task run time: %s",$Timer->toString()));
                    if ($Timer->shouldRunNow()) {
                        $this->outall(" * Found a task that should run...");
                        if ($Task->isGroupBased()) {
                            $this->outall(sprintf("\t\t - Is a group based task."));
                            $Group = $Task->getGroup();
                            $Hosts = $this->getClass(HostManager)->find(array('id' => $Group->get(hosts)));
                            if ($Task->get('taskType') == 8) {
                                $this->outall("\t\t - Multicast task found!");
                                $this->outall(sprintf("\t\t - Group %s",$Group->get('name')));
                                $i = 0;
                                foreach($Hosts AS $i => &$Host) {
                                    $Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,true,'FOG_SCHED');
                                    $this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));
                                }
                                unset($Host);
                                if ($Timer->isSingleRun()) {
                                    if ($this->FOGCore->stopScheduledTask($Task)) $this->outall("\t\t - Scheduled Task cleaned.");
                                    else $this->outall("\t\t - failed to clean task.");
                                } else $this->outall("\t\t - Cron style - No cleaning!");
                            } else {
                                $this->outall("\t\t - Regular task found!");
                                $this->outall(sprintf("\t\t - Group %s",$Group->get('name')));
                                foreach($Hosts AS $i => &$Host) {
                                    $Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),true,$Task->get('other3'));
                                    $this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));
                                }
                                unset($Host);
                                if ($Timer->isSingleRun()) {
                                    if ($this->FOGCore->stopScheduledTask($Task)) $this->outall("\t\t - Scheduled Task cleaned.");
                                    else $this->outall("\t\t - failed to clean task.");
                                } else $this->outall("\t\t - Cron style - No cleaning!");
                            }
                        } else {
                            $this->outall("\t\t - Is a host based task.");
                            $Host = $Task->getHost();
                            $Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$Task->get('other2'),false,$Task->get('other3'));
                            $this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));
                            if ($Timer->isSingleRun()) {
                                if ($this->FOGCore->stopScheduledTask($Task)) $this->outall("\t\t - Scheduled Task cleaned.");
                                else $this->outall("\t\t - failed to clean task.");
                            } else $this->outall("\t\t - Cron style - No cleaning!");
                        }
                    } else $this->outall(" * Task doesn't run now.");
                }
                unset($Task);
            } else $this->outall(" * No tasks found!");
        } catch (Exception $e) {
            $this->outall("\t\t - ".$e->getMessage());
        }
    }
    public function serviceRun() {
        $this->FOGCore->out(' ',$this->dev);
        $this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
        $this->commonOutput();
        $this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
    }
}
