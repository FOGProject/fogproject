<?php
class ImageReplicator extends FOGBase
{
	var $dev = REPLICATORDEVICEOUTPUT;
	var $log = REPLICATORLOGPATH;
	var $zzz = REPLICATORSLEEPTIME;
	public function outall($string)
	{
		$this->FOGCore->out($string,$this->dev);
		$this->FOGCore->wlog($string,$this->log);
	}
	public function serviceStart()
	{
		$this->FOGCore->out($this->FOGCore->getBanner(),$this->dev);
		$this->outall(" * Starting FOG Image Replicator Service");
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
				$this->outall(" * Starting Image Replication.");
				$this->outall(sprintf(" * We are group ID: #%s",$StorageNode->get('storageGroupID')));
				$this->outall(sprintf(" * We have node ID: #%s",$StorageNode->get('id')));
				$StorageNodes = $this->getClass('StorageNodeManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				foreach($StorageNodes AS $OtherNode)
				{
					if ($OtherNode->get('id') != $StorageNode->get('id') && $OtherNode->get('isEnabled'))
						$StorageNodeCount[] = $OtherNode;
				}
				// Try to get the images based on this group
				$ImageAssocs = $this->getClass('ImageAssociationManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				foreach($ImageAssocs AS $ImageAssoc)
				{
					if ($ImageAssoc && $ImageAssoc->isValid())
						$Images[] = $ImageAssoc->getImage();
				}
				unset($limit);
				foreach($Images AS $Image)
				{
					foreach($Image->get('storageGroups') AS $GroupToSend)
					{
						if ($GroupToSend && $GroupToSend->isValid() && $GroupToSend->get('id') != $StorageNode->get('storageGroupID'))
						{
							$StorageNodeToSend = $GroupToSend->getMasterStorageNode();
							if ($StorageNodeToSend && $StorageNodeToSend->isValid())
							{
								$username = $StorageNodeToSend->get('user');
								$password = $StorageNodeToSend->get('pass');
								$ip = $StorageNodeToSend->get('ip');
								$remImage = rtrim($StorageNodeToSend->get('path'),'/').'/'.$Image->get('path');
								$myImage = rtrim($StorageNode->get('path'),'/').'/'.$Image->get('path');
								$limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
								$limitsend = $this->byteconvert($StorageNodeToSend->get('bandwidth'));
								if ($limitmain > 0)
									$limit = "set net:limit-total-rate 0:$limitmain;";
								if ($limitsend > 0)
									$limit .= "set net:limit-rate 0:$limitsend;";
								$this->outall(sprintf(" * Found image to transfer to %s group(s)",count($Image->get('storageGroups')) - 1));
								$this->outall(sprintf(" | Image name: %s",$Image->get('name')));
								$this->outall(sprintf(" * Syncing: %s",$StorageNodeToSend->get('name')));
								$process[] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 1;set net:timeout 30;".$limit." mirror -n --ignore-time -R -vvv --exclude 'dev/' --delete $myImage $remImage; exit\" -u $username,$password $ip 2>&1","r");
							}
						}
					}
					foreach ((array)$process AS $proc)
					{
						while (!feof($proc) && $proc != null)
						{
							$output = fgets($proc,256);
							if ($output)
								$this->outall(sprintf(" * SubProcess->%s",$output));
						}
						pclose($proc);
						$this->outall(sprintf(" * SubProcess -> Complete"));
					}
					unset($process);
				}
				unset($limit);
				$this->outall(sprintf(" * Checking nodes within my group."));
				if (count($StorageNodeCount) > 0)
				{
					$this->outall(sprintf(" * Found: %s other member(s).",count($StorageNodeCount)));
					$this->outall(sprintf(''));
					$myRoot = rtrim($StorageNode->get('path'),'/');
					$this->outall(sprintf(" * My root: %s",$myRoot));
					$this->outall(sprintf(" * Starting Sync."));
					foreach($StorageNodeCount AS $StorageNodeFTP)
					{
						if ($StorageNodeFTP->get('isEnabled'))
						{
							$username = $StorageNodeFTP->get('user');
							$password = $StorageNodeFTP->get('pass');
							$ip = $StorageNodeFTP->get('ip');
							$remRoot = rtrim($StorageNodeFTP->get('path'),'/');
							$limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
							$limitsend = $this->byteconvert($StorageNodeFTP->get('bandwidth'));
							if ($limitmain > 0)
								$limit = "set net:limit-total-rate 0:$limitmain;";
							if ($limitsend > 0)
								$limit .= "set net:limit-rate 0:$limitsend;";
							$this->outall(sprintf(" * Syncing: %s",$StorageNode->get('name')));
							$process[] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 1;set net:timeout 30;".$limit." mirror -n --ignore-time -R -vvv --exclude 'dev/' --delete $myRoot $remRoot; exit\" -u $username,$password $ip 2>&1","r");
						}
					}
					foreach ((array)$process AS $proc)
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
					unset($process);
				}
				else
					$this->outall(sprintf(" * I am the only member, no need to copy anything!."));
			}
			else
			{
				$this->FOGCore->out(" * I don't appear to be the group manager, I will check back later.",$this->dev);
				$this->FOGCore->wlog(" * I don't appear to be the group manager, I will check back later.",'/opt/fog/log/groupmanager.log');
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
		$this->FOGCore->out(' * Checking if I am the group manager.',$this->dev);
		$this->FOGCore->wlog(' * Checking if I am the group manager.','/opt/fog/log/groupmanager.log');
		$this->commonOutput();
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
	}
}
