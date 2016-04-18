<?php
class MulticastManager extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'MULTICASTSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',static::$logpath,$this->getSetting('MULTICASTLOGFILENAME'));
        static::$dev = $this->getSetting('MULTICASTDEVICEOUTPUT');
        static::$zzz = (int)$this->getSetting(static::$sleeptime);
    }
    private function isMCTaskNew($KnownTasks, $id) {
        foreach((array)$KnownTasks AS $i => &$Known) $output[] = $Known->getID();
        unset($Known);
        return !in_array($id,(array)$output);
    }
    private function getMCExistingTask($KnownTasks, $id) {
        foreach((array)$KnownTasks AS $i => &$Known) {
            if ($Known->getID() == $id) return $Known;
        }
        unset($Known);
    }
    private function removeFromKnownList($KnownTasks, $id) {
        $new = array();
        foreach((array)$KnownTasks AS $i => $Known) {
            if ($Known->getID() != $id) $new[] = $Known;
        }
        unset($Known);
        return array_filter((array)$new);
    }
    private function getMCTasksNotInDB($KnownTasks, $AllTasks) {
        $ret = $allIDs = array();
        foreach ((array)$AllTasks AS $i => &$AllTask) {
            if ($AllTask && $AllTask->getID()) $allIDs[] = $AllTask->getID();
            unset($AllTask);
        }
        foreach ((array)$KnownTasks AS $i => &$Known) {
            if (!in_array($Known->getID(),(array)$allIDs)) $ret[] = $Known;
            unset($Known);
        }
        return array_filter((array)$ret);
    }
    private function serviceLoop() {
        while(true) {
            try {
                $StorageNode = $this->checkIfNodeMaster();
                $myroot = $StorageNode->get('path');
                $taskCount = self::getClass('MulticastSessionsManager')->count(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())));
                if ($taskCount != $oldCount) $allTasks = self::getClass('MulticastTask')->getAllMulticastTasks($myroot,$StorageNode->get('id'));
                $RMTasks = $this->getMCTasksNotInDB($KnownTasks,$allTasks);
                if (!count($RMTasks) && (!$taskCount || $taskCount < 0)) throw new Exception(' * No tasks found!');
                $jobcancelled = false;
                static::outall(sprintf(" | %d task%s to be cleaned",count($RMTask),count($RMTask) != 1 ? 's' : ''));
                if (count($RMTasks)) {
                    static::outall(sprintf(" | Cleaning %s task(s) removed from FOG Database.",count($RMTasks)));
                    foreach ((array)$RMTasks AS $i => &$RMTask) {
                        static::outall(sprintf(" | Cleaning Task (%s) %s",$RMTask->getID(),$RMTask->getName()));
                        $KnownTasks = $this->removeFromKnownList($KnownTasks,$RMTask->getID());
                        $taskIDs = $this->getSubObjectIDs('MulticastSessionsAssociation',array('msID'=>$RMTask->getID()),'taskID');
                        if (self::getClass('TaskManager')->count(array('id'=>$taskIDs,'stateID'=>$this->getCancelledState()) > 0)) $jobcancelled = true;
                        if ($jobcancelled || self::getClass('MulticastSessions',$RMTask->getID())->get('stateID') == $this->getCancelledState()) {
                            $RMTask->killTask();
                            static::outall(sprintf(" | Task (%s) %s has been cleaned as cancelled.",$RMTask->getID(),$RMTask->getName()));
                            self::getClass('MulticastSessionsAssociationManager')->destroy(array('msID'=>$RMTask->getID()));
                        } else {
                            static::outall(sprintf(" | Task (%s) %s has been cleaned as complete.",$RMTask->getID(),$RMTask->getName()));
                            self::getClass('MulticastSessionsAssociationManager')->destroy(array('msID'=>$RMTask->getID()));
                        }
                        unset($RMTask);
                    }
                }
                if ($taskCount > 0) static::outall(sprintf(' | %s task%s found',$taskCount,($taskCount > 1 || !$taskCount ? 's' : '')));
                if (count($allTasks)) {
                    foreach ((array)$allTasks AS $i => &$curTask) {
                        if ($this->isMCTaskNew($KnownTasks, $curTask->getID())) {
                            static::outall(sprintf(" | Task (%s) %s is new!",$curTask->getID(),$curTask->getName()));
                            if(!file_exists($curTask->getImagePath())) throw new Exception(sprintf(" Task (%s) %s failed to execute, image file:%s not found!",$curTask->getID(),$curTask->getName(),$curTask->getImagePath()));
                            if (!$curTask->getClientCount()) throw new Exception(sprintf(" Task (%s) %s failed to execute, no clients are included!",$curTask->getID(),$curTask->getName()));
                            if (!is_numeric($curTask->getPortBase()) || !($curTask->getPortBase() % 2 == 0)) throw new Exception(sprintf(" Task (%s) %s failed to execute, port must be even and numeric.",$curTask->getID(),$curTask->getName()));
                            if (!$curTask->startTask()) {
                                static::outall(sprintf(" | Task (%s) %s failed to start!",$curTask->getID(),$curTask->getName()));
                                static::outall(sprintf(" | * Don't panic, check all your settings!"));
                                static::outall(sprintf(" |       even if the interface is incorrect the task won't start."));
                                static::outall(sprintf(" |       If all else fails run the following command and see what it says:"));
                                static::outall(sprintf(" |  %s",$curTask->getCMD()));
                                $curTask->killTask();
                                throw new Exception(" Task (%s) %s has been cleaned.");
                            }
                            static::outall(sprintf(" | Task (%s) %s has been cleaned.",$curTask->getID(),$curTask->getName()));
                            static::outall(sprintf(" | Task (%s) %s image file found.",$curTask->getID(),$curTask->getImagePath()));
                            static::outall(sprintf(" | Task (%s) %s client(s) found.",$curTask->getID(),$curTask->getClientCount()));
                            static::outall(sprintf(" | Task (%s) %s sending on base port: %s",$curTask->getID(),$curTask->getName(),$curTask->getPortBase()));
                            static::outall(sprintf(" | CMD: %s",$curTask->getCMD()));
                            static::outall(sprintf(" | Task (%s) %s has started.",$curTask->getID(),$curTask->getName()));
                            $KnownTasks[] = $curTask;
                        } else {
                            $runningTask = $this->getMCExistingTask($KnownTasks, $curTask->getID());
                            $taskIDs = $this->getSubObjectIDs('MulticastSessionsAssociation',array('msID'=>$runningTask->getID()),'taskID');
                            if (self::getClass('TaskManager')->count(array('id'=>$taskIDs,'stateID'=>$this->getCancelledState()) > 0)) $jobcancelled = true;
                            if ($runningTask->isRunning($runningTask->procRef)) {
                                static::outall(sprintf(" | Task (%s) %s is already running PID %s",$runningTask->getID(),$runningTask->getName(),$runningTask->getPID($runningTask->procRef)));
                                $runningTask->updateStats();
                            } else {
                                static::outall(sprintf(" | Task (%s) %s is no longer running.",$runningTask->getID(),$runningTask->getName()));
                                if ($jobcancelled || self::getClass('MulticastSessions',$runningTask->getID())->get('stateID') == $this->getCancelledState()) {
                                    $KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
                                    if (!$runningTask->killTask()) throw new Exception(sprintf(" Failed to kill task (%s) %s PID:%s!",$runningTask->getID(),$runningTask->getName(),$runningTask->getPID($runningTask->procRef)));
                                    static::outall(sprintf(" | Task (%s) %s has been cleaned as cancelled.",$runningTask->getID(),$runningTask->getName()));
                                } else {
                                    self::getClass('MulticastSessions',$runningTask->getID())->set('clients',0)->set('completetime',self::nice_date()->format('Y-m-d H:i:s'))->set('name','')->set('stateID',$this->getCompleteState())->save();
                                    $KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
                                    static::outall(sprintf(" | Task (%s) %s has been cleaned as complete.",$runningTask->getID(),$runningTask->getName()));
                                }
                            }
                        }
                        unset($curTask);
                    }
                }
            } catch(Exception $e) {
                static::outall($e->getMessage());
            }
            static::out(' +---------------------------------------------------------',static::$dev);
            $tmpTime = (int)$this->getSetting(static::$sleeptime);
            if (static::$zzz != $tmpTime) {
                static::$zzz = $tmpTime;
                static::outall(sprintf(" | Sleep time has changed to %s seconds",static::$zzz));
            }
            sleep(static::$zzz);
            $oldCount = $taskCount;
        }
    }
    public function serviceRun() {
        static::out(' ',static::$dev);
        static::out(' +---------------------------------------------------------',static::$dev);
        self::serviceLoop();
    }
}
