<?php
class MulticastManager extends FOGBase
{
	public function outall($string)
	{
		$this->FOGCore->out($string,MULTICASTDEVICEOUTPUT);
		$this->FOGCore->wlog($string,MULTICASTLOGPATH);
	}
	public function isMCTaskNew($KnownTasks, $id)
	{
		if ($KnownTasks)
		{
			foreach($KnownTasks AS $Known)
			{
				if ($Known->getID() == $id)
					return false;
			}
		}
		return true;
	}
	public function getMCExistingTask($KnownTasks, $id)
	{
		if ($KnownTasks)
		{
			foreach($KnownTasks AS $Known)
			{
				if ($Known->getID() == $id)
					return $Known;
			}
		}
		return null;
	}
	public function removeFromKnownList($KnownTasks, $id)
	{
		if ($KnownTasks)
		{
			foreach($KnownTasks AS $Known)
			{
				if ($Known->getID() != $id)
					$new[] = $Known;
			}
		}
		return $new;
	}
	public function getMCTasksNotInDB($KnownTasks, $AllTasks)
	{
		if ($KnownTasks)
		{
			foreach ($KnownTasks AS $Known)
			{
				if ($Known && $Known->getID())
				{
					$kID = $Known->getID();
					$blFound = false;
					foreach($AllTasks AS $All)
					{
						if ($kID == $All->getID())
						{
							$blFound = true;
							break;
						}
					}
					if (!$blFound)
						$ret[] = $Known;
				}
			}
		}
		return $ret;
	}
	public function serviceStart()
	{
		$this->FOGCore->out($this->FOGCore->getBanner(),MULTICASTDEVICEOUTPUT);
		$this->outall(sprintf(" * Starting FOG Multicast Manager Service"));
		sleep(5);
		$this->outall(sprintf(" * Checking for new tasks every %s seconds.",MULTICASTSLEEPTIME));
		$this->outall(sprintf(" * Starting service loop."));
	}
	private function serviceLoop()
	{
		while(true)
		{
			$StorageNode = current($this->FOGCore->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1, 'ip' => current($this->FOGCore->getIPAddress()))));
			$myroot = $StorageNode->get('path');
			$allTasks = MulticastTask::getAllMulticastTasks($myroot);
			$this->FOGCore->out(sprintf(" | %s task(s) found",count($allTasks)),MULTICASTDEVICEOUTPUT);
			if ($allTasks)
			{
				foreach($allTasks AS $curTask)
				{
					if($this->isMCTaskNew($KnownTasks, $curTask->getID()))
					{
						$this->outall(sprintf(" | Task (%s) %s is new!",$curTask->getID(),$curTask->getName()));
						if(file_exists($curTask->getImagePath()))
						{
							$this->outall(sprintf(" | Task (%s) %s image file found.",$curTask->getID(),$curTask->getName()));
							if($curTask->getClientCount() > 0)
							{
								$this->outall(sprintf(" | Task (%s) %s client(s) found.",$curTask->getID(),$curTask->getName()));
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
						if ($runningTask->isRunning())
						{
							$this->outall(sprintf(" | Task (%s) %s is already running PID %s",$runningTask->getID(),$runningTask->getName(),$runningTask->getPID()));
							$pct = $runningTask->updateStats();
							if ($pct != -1)
								$this->outall(sprintf(" | Task (%s) %s %s%% complete.",$runningTask->getID(),$runningTask->getName(),$pct));
							else
								$this->outall(sprintf(" | Task (%s) %s failed to update statistics.",$runningTask->getID(),$runningTask->getName()));
						}
						else
						{
							$this->outall(sprintf(" | Task (%s) %s is no longer running.",$runningTask->getID(),$runningTask->getName()));
							$runningTask->flagAsDead();
							if ($runningTask->canBeSafelyKilled())
							{
								if ($runningTask->killTask())
								{
									$KnownTasks = $this->removeFromKnownList($KnownTasks,$runningTask->getID());
									$this->outall(sprintf(" | Task (%s) %s has been cleaned.",$runningTask->getID(),$runningTask->getName()));
								}
								else
								{
									$this->outall(sprintf(" | Task (%s) %s is no longer running.",$runningTask->getID(),$runningTask->getName()));
									$runningTask->flagAsDead();
									if ($runningTask->canBeSafelyKilled())
									{
										if ($runningTask->killTask())
											$this->outall(sprintf(" | Task (%s) %s has been cleaned.",$runningTask->getID(),$runningTask->getName()));
										else
											$this->outall(sprintf(" | Task (%s) %s has NOT been cleaned.",$runningTask->getID(),$runningTask->getName()));
									}
									else
										$this->outall(sprintf(" | Task (%s) %s will not be cleaned yet (5 min delay).",$runningTask->getID(),$runningTask->getName()));
								}
							}
						}
						$RMTasks = $this->getMCTasksNotInDB($KnownTasks,$allTasks);
						if (count($RMTasks))
						{
							$this->outall(sprintf(" | Cleaning %s task(s) removed from FOG Database.",count($RMTasks)));
							if ($RMTasks)
							{
								foreach($RMTasks AS $RMTask)
								{
									$this->outall(sprintf(" | Cleaning Task (%s) %s",$RMTask->getID(),$RMTask->getName()));
									$RMTask->killTask();
									$KnownTasks = $this->removeFromKnownList($KnownTasks,$RMTask->getID());
									$this->outall(sprintf(" | Task (%s) %s has been cleaned.",$RMTask->getID(),$RMTask->getName()));
								}
							}
						}
					}
				}
			}
			else
				$this->outall(sprintf(" * No tasks found!"));
			$this->FOGCore->out(sprintf(" +---------------------------------------------------------"), MULTICASTDEVICEOUTPUT );
			sleep(MULTICASTSLEEPTIME);
		}
	}
	public function serviceRun()
	{
		$this->FOGCore->out(sprintf(' '),REPLICATORDEVICEOUTPUT);
		$this->FOGCore->out(sprintf(' +---------------------------------------------------------'),REPLICATORDEVICEOUTPUT);
		$this->serviceLoop();
	}
}
