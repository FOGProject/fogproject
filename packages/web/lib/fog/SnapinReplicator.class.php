<?php
class SnapinReplicator extends FOGBase
{
	var $dev = SNAPINREPDEVICEOUTPUT;
	var $log = SNAPINREPLOGPATH;
	var $zzz = SNAPINREPSLEEPTIME;
	public function outall($string)
	{
		$this->FOGCore->out($string,$this->dev);
		$this->FOGCore->wlog($string,$this->log);
	}
	public function serviceStart()
	{
		$this->FOGCore->out($this->FOGCore->getBanner(),$this->dev);
		$this->outall(" * Starting FOG Snapin Replicator Service");
		sleep(5);
		$this->outall(sprintf(" * Checking for new tasks every %s seconds.",$this->zzz));
		$this->outall(sprintf(" * Starting service loop."));
	}
	private function commonOutput()
	{
		$StorageNode = current($this->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1, 'ip' => $this->FOGCore->getIPAddress())));
		try
		{
			if ($StorageNode)
			{
				$this->FOGCore->out(' * I am the group manager.',$this->dev);
				$this->FOGCore->wlog(' * I am the group manager.','/opt/fog/log/groupmanager.log');
				$this->outall(" * Starting Snapin Replication.");
				$this->outall(sprintf(" * We are group ID: #%s",$StorageNode->get('storageGroupID')));
				$this->outall(sprintf(" * We have node ID: #%s",$StorageNode->get('id')));
				$StorageNodes = $this->getClass('StorageNodeManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				foreach($StorageNodes AS $OtherNode)
				{
					if ($OtherNode->get('id') != $StorageNode->get('id') && $OtherNode->get('isEnabled'))
						$StorageNodeCount[] = $OtherNode;
				}
				// Try to get the snapins based on this group
				$SnapinAssocs = $this->getClass('SnapinGroupAssociationManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				unset($limit);
				if ($SnapinAssocs)
				{
					foreach($SnapinAssocs AS $SnapinAssoc)
					{
						if ($SnapinAssoc && $SnapinAssoc->isValid())
							$Snapins[] = $SnapinAssoc->getSnapin();
					}
					foreach($Snapins AS $Snapin)
					{
						if ($Snapin && $Snapin->isValid())
						{
							foreach((array)$Snapin->get('storageGroups') AS $StorageGroupSnap)
								$StorageGroups[] = $StorageGroupSnap;
							$mySnapFile[] = '-i '.$Snapin->get('file').' ';
						}
					}
					if ($StorageGroups)
						$StorageGroups = array_unique($StorageGroups);
					$StorageGroups = array_values($StorageGroups);
					foreach((array)$StorageGroups AS $GroupToSend)
					{
						if ($GroupToSend && $GroupToSend->isValid() && $GroupToSend->get('id') != $StorageNode->get('storageGroupID'))
						{
							$StorageNodeToSend = $GroupToSend->getMasterStorageNode();
							if ($StorageNodeToSend && $StorageNodeToSend->isValid())
							{
								$username = $StorageNodeToSend->get('user');
								$password = $StorageNodeToSend->get('pass');
								$ip = $StorageNodeToSend->get('ip');
								$remSnapin = rtrim($StorageNodeToSend->get('snapinpath'),'/');
								$mySnapin = rtrim($StorageNode->get('snapinpath'),'/');
								$limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
								$limitsend = $this->byteconvert($StorageNodeToSend->get('bandwidth'));
								if ($limitmain > 0)
									$limit = "set net:limit-total-rate 0:$limitmain;";
								if ($limitsend > 0)
									$limit .= "set net:limit-rate 0:$limitsend;";
								$this->outall(sprintf(" * Found snapin to transfer to %s group(s)",count($Snapin->get('storageGroups')) -1));
								$this->outall(sprintf(" | Snapin name: %s",$Snapin->get('name')));
								$this->outall(sprintf(" * Syncing: %s",$StorageNodeToSend->get('name')));
								$process[] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 1;set net:timeout 30;".$limit." mirror ".implode($mySnapFile)." -n --ignore-time -R -vvv --delete-first $mySnapin $remSnapin; exit\" -u $username,$password $ip 2>&1","r");
							}
						}
					}
					foreach((array)$process AS $proc)
					{
						while(!feof($proc) && $proc != null)
						{
							$output = fgets($proc,256);
							if ($output)
								$this->outall(sprintf(" * SubProcess -> %s",$output));
						}
						pclose($proc);
						$this->outall(sprintf(" * SubProcess -> Complete"));
					}
					unset($process,$limit);
					$this->outall(sprintf(" * Checking nodes within my group."));
					if (count($StorageNodeCount) > 0)
					{
						$this->outall(sprintf(" * Found: %s other member(s).",count($StorageNodeCount)));
						$this->outall(sprintf(''));
						$myRoot = rtrim($StorageNode->get('snapinpath'),'/');
						$this->outall(sprintf(" * My root: %s",$myRoot));
						$this->outall(sprintf(" * Starting Sync."));
						foreach($Snapins AS $Snapin)
							$mySnapFile[] = '-i '.$Snapin->get('file');
						foreach($StorageNodeCount AS $StorageNodeFTP)
						{
							if ($StorageNodeFTP->get('isEnabled'))
							{
								$username = $StorageNodeFTP->get('user');
								$password = $StorageNodeFTP->get('pass');
								$ip = $StorageNodeFTP->get('ip');
								$remRoot = rtrim($StorageNodeFTP->get('snapinpath'),'/');
								$limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
								$limitsend = $this->byteconvert($StorageNodeFTP->get('bandwidth'));
								if ($limitmain > 0)
									$limit = "set net:limit-total-rate 0:$limitmain;";
								if ($limitsend > 0)
									$limit .= "set net:limit-rate 0:$limitsend;";
								$this->outall(sprintf(" * Syncing: %s",$StorageNodeFTP->get('name')));
								$process[] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 1;set net:timeout 30;".$limit." mirror ".implode($mySnapFile)." -n --ignore-time -R -vvv --delete-first $myRoot $remRoot; exit\" -u $username,$password $ip 2>&1","r");
							}
						}
						foreach((array)$process AS $proc)
						{
							while(!feof($proc) && $proc != null)
							{
								$output = fgets($proc,256);
								if ($output)
									$this->outall(sprintf(" * SubProcess -> %s",$output));
							}
							pclose($proc);
							$this->outall(sprintf(" * SubProcess -> Complete"));
						}
					}
					else
						$this->outall(sprintf(" * I am the only member, no need to copy anything!"));
				}
				else
					$this->outall(sprintf(" * There are no snapins to replicate!"));
			}
		}
		catch (Exception $e)
		{
			$this->outall(' * '.$e->getMessage());
		}
	}
	public function serviceRun()
	{
		$this->FOGCore->out(' ',$this->dev);
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
		$this->commonOutput();
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
	}
}
