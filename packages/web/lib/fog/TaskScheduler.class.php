<?php
class TaskScheduler extends FOGBase
{
	var $dev = SCHEDULERDEVICEOUTPUT;
	var $log = SCHEDULERLOGPATH;
	var $zzz = SCHEDULERSLEEPTIME;
	public function outall($string)
	{
		$this->FOGCore->out($string,$this->dev);
		$this->FOGCore->wlog($string,$this->log);
	}
	public function serviceStart()
	{
		$this->FOGCore->out($this->FOGCore->getBanner(),$this->dev);
		$this->outall(" * Starting FOG Task Scheduler Service");
		sleep(5);
		$this->outall(sprintf(" * Checking for new tasks every %s seconds.",$this->zzz));
		$this->outall(sprintf(" * Starting service loop."));
	}
	private function commonOutput()
	{
		try
		{
			$Tasks = $this->FOGCore->getClass('ScheduledTaskManager')->find(array('isActive' => 1));
			if ($Tasks)
			{
				$this->outall(sprintf(" * %s task(s) found.",count($Tasks)));
				foreach($Tasks AS $Task)
				{
					$deploySnapin = (($Task->get('taskType') == 12 || $Task->get('taskType') == 13) && $Task->get('taskType') != 17 ? $Task->get('other2') : false);
					$Timer = $Task->getTimer();
					$this->outall(sprintf(" * Task run time: %s",$Timer->toString()));
					if ($Timer->shouldRunNow())
					{
						$this->outall(" * Found a task that should run...");
						if ($Task->isGroupBased())
						{
							$this->outall(sprintf("\t\t - Is a group based task."));
							$Group = $Task->getGroup();
							if ($Task->get('taskType') == 8)
							{
								$this->outall("\t\t - Multicast task found!");
								$this->outall(sprintf("\t\t - Group %s",$Group->get('name')));
								$i = 0;
								foreach((array)$Group->get('hosts') AS $Host)
								{
									$Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,true,'FOG_SCHED');
									$this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));

								}
								if ($Timer->isSingleRun())
								{
									if ($this->FOGCore->stopScheduledTask($Task))
										$this->outall("\t\t - Scheduled Task cleaned.");
									else
										$this->outall("\t\t - failed to clean task.");
								}
								else
									$this->outall("\t\t - Cron style - No cleaning!");
							}
							else
							{
								$this->outall("\t\t - Regular task found!");
								$this->outall(sprintf("\t\t - Group %s",$Group->get('name')));
								foreach((array)$Group->get('hosts') AS $Host)
								{
									$Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$deploySnapin,'FOG_SCHED');
									$this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));
								}
								if ($Timer->isSingleRun())
								{
									if ($this->FOGCore->stopScheduledTask($Task))
										$this->outall("\t\t - Scheduled Task cleaned.");
									else
										$this->outall("\t\t - failed to clean task.");
								}
								else
									$this->outall("\t\t - Cron style - No cleaning!");
							}
						}
						else
						{
							$this->outall("\t\t - Is a host based task.");
							$Host = $Task->getHost();
							$Host->createImagePackage($Task->get('taskType'),$Task->get('name'),$Task->get('shutdown'),false,$deploySnapin,'FOG_SCHED');
							$this->outall(sprintf("\t\t - Task Started for host %s!",$Host->get('name')));
							if ($Timer->isSingleRun())
							{
								if ($this->FOGCore->stopScheduledTask($Task))
									$this->outall("\t\t - Scheduled Task cleaned.");
								else
									$this->outall("\t\t - failed to clean task.");
							}
							else
								$this->outall("\t\t - Cron style - No cleaning!");
						}
					}
					else
						$this->outall(" * Task doesn't run now.");
				}
			}
			else
				$this->outall(" * No tasks found!");
		}
		catch (Exception $e)
		{
			$this->outall("\t\t - ".$e->getMessage());
		}
	}

	public function serviceRun()
	{
		$this->FOGCore->out(' ',REPLICATORDEVICEOUTPUT);
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
		$this->commonOutput();
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
	}
}
