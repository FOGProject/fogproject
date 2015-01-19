<?php
class MulticastManager extends FOGBase
{
	var $dev = MULTICASTDEVICEOUTPUT;
	var $log = MULTICASTLOGPATH;
	var $zzz = MULTICASTSLEEPTIME;
	public function outall($string)
	{
		$this->FOGCore->out($string,$this->dev);
		$this->FOGCore->wlog($string,$this->log);
	}
	public function isMCTaskNew($KnownTasks, $id)
	{
		foreach((array)$KnownTasks AS $Known)
			$output[] = $Known->getID();
		return !in_array($id,$output);
	}
	public function getMCExistingTask($KnownTasks, $id)
	{
		foreach((array)$KnownTasks AS $Known)
		{
			if ($Known->getID() == $id)
				return $Known;
		}
	}
	public function removeFromKnownList($KnownTasks, $id)
	{
		unset($new);
		foreach((array)$KnownTasks AS $Known)
			$Known->getID() != $id ? $new[] = $Known : null;
		return $new;
	}
	public function getMCTasksNotInDB($KnownTasks, $AllTasks)
	{
		unset($ret);
		foreach((array)$AllTasks AS $AllTask)
		{
			if ($AllTask && $AllTask->getID())
				$allIDs[] = $AllTask->getID();
		}
		foreach((array)$KnownTasks AS $Known)
			$Known && $Known->getID() && !in_array($Known->getID(),(array)$allIDs) ? $ret[] = $Known : null;
		return $ret;
	}
	public function serviceStart()
	{
		$this->FOGCore->out($this->FOGCore->getBanner(),$this->log);
		$this->outall(sprintf(" * Starting FOG Multicast Manager Service"));
		$this->outall(sprintf(" * Checking for new tasks every %s seconds.",$this->zzz));
		$this->outall(sprintf(" * Starting service loop."));
	}
	private function serviceLoop()
	{
		while(true)
		{
			try
			{
				$StorageNode = current($this->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1,'ip' => $this->FOGCore->getIPAddress())));
				if (!$StorageNode || !$StorageNode->isValid())
					throw new Exception(sprintf(" | StorageNode Not found on this system."));
				$myroot = $StorageNode->get('path');
				$allTasks = MulticastTask::getAllMulticastTasks($myroot);
				$this->FOGCore->out(sprintf(" | %s task(s) found",count($allTasks)),$this->dev);
				$RMTasks = $this->getMCTasksNotInDB($KnownTasks,$allTasks);
				$jobcancelled = false;
				if (count($RMTasks))
				{
					$this->outall(sprintf(" | Cleaning %s task(s) removed from FOG Database.",count($RMTasks)));
					foreach((array)$RMTasks AS $RMTask)
					{
						$this->outall(sprintf(" | Cleaning Task (%s) %s",$RMTask->getID(),$RMTask->getName()));
						$Assocs = $this->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $RMTask->getID()));
						$curSession = new MulticastSessions($RMTask->getID());
						foreach($Assocs AS $Assoc)
						{
							if ($Assoc && $Assoc->isValid())
							{
								$curTaskGet = new Task($Assoc->get('taskID'));
								if ($curTaskGet->get('stateID') == 5)
									$jobcancelled = true;
							}
						}
						if ($jobcancelled || $curSession->get('stateID') == 5)
						{
							$RMTask->killTask();
							$KnownTasks = $this->removeFromKnownList($KnownTasks,$RMTask->getID());
							$this->outall(sprintf(" | Task (%s) %s has been cleaned as cancelled.",$RMTask->getID(),$RMTask->getName()));
							$this->getClass('MulticastSessionsAssociationManager')->destroy(array('msID' => $RMTask->getID()));
						}
						else
						{
							$KnownTasks = $this->removeFromKnownList($KnownTasks,$RMTask->getID());
							$this->outall(sprintf(" | Task (%s) %s has been cleaned as complete.",$RMTask->getID(),$RMTask->getName()));
							$this->getClass('MulticastSessionsAssociationManager')->destroy(array('msID' => $RMTask->getID()));
						}
					}
				}
				else if (!$allTasks)
					throw new Exception(' * No Tasks Found!');
				foreach((array)$allTasks AS $curTask)
				{
					if($this->isMCTaskNew($KnownTasks, $curTask->getID()))
					{
						$this->outall(sprintf(" | Task (%s) %s is new!",$curTask->getID(),$curTask->getName()));
						if(file_exists($curTask->getImagePath()))
						{
							$this->outall(sprintf(" | Task (%s) %s image file found.",$curTask->getID(),$curTask->getImagePath()));
							if($curTask->getClientCount() > 0)
							{
								$this->outall(sprintf(" | Task (%s) %s client(s) found.",$curTask->getID(),$curTask->getClientCount()));
								if(is_numeric($curTask->getPortBase()) && $curTask->getPortBase() % 2 == 0)
								{
									$this->outall(sprintf(" | Task (%s) %s sending on base port: %s",$curTask->getID(),$curTask->getName(),$curTask->getPortBase()));
									$this->outall(sprintf("CMD: %s",$curTask->getCMD()));
									if($curTask->startTask())
									{
										$this->outall(sprintf(" | Task (%s) %s has started.",$curTask->getID(),$curTask->getName()));
										$KnownTasks[] = $curTask;
									}
									else
									{
										$this->outall(sprintf(" | Task (%s) %s failed to start!",$curTask->getID(),$curTask->getName()));
										$this->outall(sprintf(" | * Don't panic, check all your settings!"));
										$this->outall(sprintf(" |       even if the interface is incorrect the task won't start."));
										$this->outall(sprintf(" |       If all else fails run the following command and see what it says:"));
										$this->outall(sprintf(" |  %s",$curTask->getCMD()));
										if($curTask->killTask())
											$this->outall(sprintf(" | Task (%s) %s has been cleaned.",$curTask->getID(),$curTask->getName()));
										else
											$this->outall(sprintf(" | Task (%s) %s has NOT been cleaned.",$curTask->getID(),$curTask->getName()));
									}
								}
								else
									$this->outall(sprintf(" | Task (%s) %s failed to execute, port must be even and numeric.",$curTask->getID(),$curTask->getName()));
							}
							else
								$this->outall(sprintf(" | Task (%s) %s failed to execute, no clients are included!",$curTask->getID(),$curTask->getName()));
						}
						else
							$this->outall(sprintf(" | Task (%s) %s failed to execute, image file:%s not found!",$curTask->getID(),$curTask->getName(),$curTask->getImagePath()));
					}
					else
					{
						$runningTask = $this->getMCExistingTask($KnownTasks, $curTask->getID());
						$curSession = new MulticastSessions($runningTask->getID());
						$Assocs = $this->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $curSession->get('id')));
						foreach($Assocs AS $Assoc)
						{
							if ($Assoc && $Assoc->isValid())
							{
								$curTaskGet = new Task($Assoc->get('taskID'));
								if ($curTaskGet->get('stateID') == 5)
									$jobcancelled = true;
							}
						}
						if ($runningTask->isRunning())
						{
							$this->outall(sprintf(" | Task (%s) %s is already running PID %s",$runningTask->getID(),$runningTask->getName(),$runningTask->getPID()));
							$runningTask->updateStats();
						}
						else
						{
							$this->outall(sprintf(" | Task (%s) %s is no longer running.",$runningTask->getID(),$runningTask->getName()));
							if ($jobcancelled || $curSession->get('stateID') == 5)
							{
								if ($runningTask->killTask())
								{
									$KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
									$this->outall(sprintf(" | Task (%s) %s has been cleaned as cancelled.",$runningTask->getID(),$runningTask->getName()));
								}
								else
									$this->outall(sprintf(" | Failed to kill task (%s) %s!",$runningTask->getID(),$runningTask->getName()));
							}
							else
							{
								$curSession->set('clients',0)->set('completetime',$this->nice_date()->format('Y-m-d H:i:s'))->set('name','')->set('stateID',4)->save();
								$KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
								$this->outall(sprintf(" | Task (%s) %s has been cleaned as complete.",$runningTask->getID(),$runningTask->getName()));
							}
						}
					}
				}
			}
			catch (Exception $e)
			{
				$this->outall($e->getMessage());
			}
			$this->FOGCore->out(sprintf(" +---------------------------------------------------------"), $this->dev );
			sleep(MULTICASTSLEEPTIME);
		}
	}
	public function serviceRun()
	{
		$this->FOGCore->out(sprintf(' '),$this->dev);
		$this->FOGCore->out(sprintf(' +---------------------------------------------------------'),$this->dev);
		$this->serviceLoop();
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
