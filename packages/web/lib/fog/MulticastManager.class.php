<?php
class MulticastManager extends FOGService {
    public $dev = MULTICASTDEVICEOUTPUT;
    public $log = MULTICASTLOGPATH;
    public $zzz = MULTICASTSLEEPTIME;
    public function isMCTaskNew($KnownTasks, $id) {
        foreach((array)$KnownTasks AS $i => &$Known) $output[] = $Known->getID();
        unset($Known);
        return !in_array($id,$output);
    }
    public function getMCExistingTask($KnownTasks, $id) {
        foreach((array)$KnownTasks AS $i => &$Known) {
            if ($Known->getID() == $id) return $Known;
        }
        unset($Known);
    }
    public function removeFromKnownList($KnownTasks, $id) {
        $new = array();
        foreach((array)$KnownTasks AS $i => $Known) {
            if ($Known->getID() != $id) $new[] = $Known;
        }
        unset($Known);
        return array_filter($new);
    }
    public function getMCTasksNotInDB($KnownTasks, $AllTasks) {
        $ret = array();
        $allIDs = array();
        foreach((array)$AllTasks AS $i => &$AllTask) {
            if ($AllTask && $AllTask->getID()) $allIDs[] = $AllTask->getID();
        }
        unset($AllTask);
        foreach((array)$KnownTasks AS $i => &$Known) {
            if (!in_array($Known->getID(),(array)$allIDs)) $ret[] = $Known;
        }
        unset($Known);
        return array_filter($ret);
    }
    private function serviceLoop() {
        while(true) {
            try {
                $StorageNode = $this->checkIfNodeMaster();
                $myroot = $StorageNode->get(path);
                $taskCount = MulticastTask::getSession('count');
                $this->out(sprintf(' | %s task%s found',$taskCount,($taskCount > 1 || !$taskCount ? 's' : '')),$this->dev);
                if (!$taskCount || $taskCount < 0) throw new Exception(' * No tasks found!');
                $RMTasks = $this->getMCTasksNotInDB($KnownTasks,$allTasks);
                if (!$oldCount || $oldCount != $taskCount) $allTasks = MulticastTask::getAllMulticastTasks($myroot);
                $jobcancelled = false;
                if (count($RMTasks)) $this->outall(sprintf(" | Cleaning %s task(s) removed from FOG Database.",count($RMTasks)));
                foreach((array)$RMTasks AS $i => &$RMTask) {
                    $this->outall(sprintf(" | Cleaning Task (%s) %s",$RMTask->getID(),$RMTask->getName()));
                    $taskIDs = array_unique($this->getClass(MulticastSessionsAssociationManager)->find(array(msID=>$RMTask->getID()),'','','','','','','taskID'));
                    if ($this->getClass(TaskManager)->count(array(id=>$taskIDs,stateID=>5))) $jobcancelled = true;
                    $curSession = $this->getClass(MulticastSessions,$RMTask->getID());
                    if ($jobcancelled || $this->getClass(MulticastSessions,$RMTask->getID())->get(stateID) == 5) {
                        $RMTask->killTask();
                        $KnownTasks = $this->removeFromKnownList($KnownTasks,$RMTask->getID());
                        $this->outall(sprintf(" | Task (%s) %s has been cleaned as cancelled.",$RMTask->getID(),$RMTask->getName()));
                        $this->getClass(MulticastSessionsAssociationManager)->destroy(array(msID=>$RMTask->getID()));
                    } else {
                        $KnownTasks = $this->removeFromKnownList($KnownTasks,$RMTask->getID());
                        $this->outall(sprintf(" | Task (%s) %s has been cleaned as complete.",$RMTask->getID(),$RMTask->getName()));
                        $this->getClass(MulticastSessionsAssociationManager)->destroy(array(msID=>$RMTask->getID()));
                    }
                }
                unset($RMTask);
                foreach((array)$allTasks AS $i => &$curTask) {
                    if($this->isMCTaskNew($KnownTasks, $curTask->getID())) {
                        $this->outall(sprintf(" | Task (%s) %s is new!",$curTask->getID(),$curTask->getName()));
                        if(!file_exists($curTask->getImagePath())) throw new Exception(sprintf(" Task (%s) %s failed to execute, image file:%s not found!",$curTask->getID(),$curTask->getName(),$curTask->getImagePath()));
                        if (!$curTask->getClientCount()) throw new Exception(sprintf(" Task (%s) %s failed to execute, no clients are included!",$curTask->getID(),$curTask->getName()));
                        if (!is_numeric($curTask->getPortBase()) || !($curTask->getPortBase() % 2 == 0)) throw new Exception(sprintf(" Task (%s) %s failed to execute, port must be even and numeric.",$curTask->getID(),$curTask->getName()));
                        if (!$curTask->startTask()) {
                            $this->outall(sprintf(" | Task (%s) %s failed to start!",$curTask->getID(),$curTask->getName()));
                            $this->outall(sprintf(" | * Don't panic, check all your settings!"));
                            $this->outall(sprintf(" |       even if the interface is incorrect the task won't start."));
                            $this->outall(sprintf(" |       If all else fails run the following command and see what it says:"));
                            $this->outall(sprintf(" |  %s",$curTask->getCMD()));
                            $curTask->killTask();
                            throw new Exception(" Task (%s) %s has been cleaned.");
                        }
                        $this->outall(sprintf(" | Task (%s) %s has been cleaned.",$curTask->getID(),$curTask->getName()));
                        $this->outall(sprintf(" | Task (%s) %s image file found.",$curTask->getID(),$curTask->getImagePath()));
                        $this->outall(sprintf(" | Task (%s) %s client(s) found.",$curTask->getID(),$curTask->getClientCount()));
                        $this->outall(sprintf(" | Task (%s) %s sending on base port: %s",$curTask->getID(),$curTask->getName(),$curTask->getPortBase()));
                        $this->outall(sprintf(" | CMD: %s",$curTask->getCMD()));
                        $this->outall(sprintf(" | Task (%s) %s has started.",$curTask->getID(),$curTask->getName()));
                        $KnownTasks[] = $curTask;
                    } else {
                        $runningTask = $this->getMCExistingTask($KnownTasks, $curTask->getID());
                        $curSession = new MulticastSessions($runningTask->getID());
                        $Assocs = $this->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $curSession->get('id')));
                        foreach($Assocs AS $i => &$Assoc) {
                            if ($Assoc && $Assoc->isValid()) {
                                $curTaskGet = new Task($Assoc->get('taskID'));
                                if ($curTaskGet->get('stateID') == 5) $jobcancelled = true;
                            }
                        }
                        unset($Assoc);
                        if ($runningTask->isRunning()) {
                            $this->outall(sprintf(" | Task (%s) %s is already running PID %s",$runningTask->getID(),$runningTask->getName(),$runningTask->getPID()));
                            $runningTask->updateStats();
                        } else {
                            $this->outall(sprintf(" | Task (%s) %s is no longer running.",$runningTask->getID(),$runningTask->getName()));
                            if ($jobcancelled || $curSession->get('stateID') == 5) {
                                $KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
                                if (!$runningTask->killTask()) throw new Exception(sprintf(" Failed to kill task (%s) %s PID:%s!",$runningTask->getID(),$runningTask->getName(),$runningTask->getPID()));
                                $this->outall(sprintf(" | Task (%s) %s has been cleaned as cancelled.",$runningTask->getID(),$runningTask->getName()));
                            } else {
                                $curSession->set('clients',0)->set('completetime',$this->nice_date()->format('Y-m-d H:i:s'))->set('name','')->set('stateID',4)->save();
                                $KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
                                $this->outall(sprintf(" | Task (%s) %s has been cleaned as complete.",$runningTask->getID(),$runningTask->getName()));
                            }
                        }
                    }
                }
                unset($curTask);
            } catch(Exception $e) {
                $this->outall($e->getMessage());
            }
            $this->out(sprintf(" +---------------------------------------------------------"), $this->dev );
            sleep(MULTICASTSLEEPTIME);
            $oldCount = $taskCount;
        }
    }
    public function serviceRun() {
        $this->out(sprintf(' '),$this->dev);
        $this->out(sprintf(' +---------------------------------------------------------'),$this->dev);
        $this->serviceLoop();
    }
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
