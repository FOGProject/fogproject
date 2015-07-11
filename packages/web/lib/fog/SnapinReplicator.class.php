<?php
class SnapinReplicator extends FOGService {
	public $dev = SNAPINREPDEVICEOUTPUT;
	public $log = SNAPINREPLOGPATH;
	public $zzz = SNAPINREPSLEEPTIME;
	private function commonOutput() {
		$StorageNode = current($this->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1,'ip' => $this->FOGCore->getIPAddress())));
		try {
			if ($StorageNode) {
				$this->FOGCore->out(' * I am the group manager.',$this->dev);
				$this->FOGCore->wlog(' * I am the group manager.','/opt/fog/log/groupmanager.log');
				$this->outall(" * Starting Snapin Replication.");
				$this->outall(sprintf(" * We are group ID: #%s",$StorageNode->get('storageGroupID')));
				$this->outall(sprintf(" * We have node ID: #%s",$StorageNode->get('id')));
				$StorageNodes = $this->getClass('StorageNodeManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				foreach($StorageNodes AS $i => &$OtherNode) {
					if ($OtherNode->get('id') != $StorageNode->get('id') && $OtherNode->get('isEnabled')) $StorageNodeCount[] = $OtherNode;
                }
                unset($OtherNode);
				// Try to get the snapins based on this group
				$SnapinAssocs = $this->getClass('SnapinGroupAssociationManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				// Make sure we have clean limit setting.
				unset($limit);
				// Only do tasks if snapin assocs exist.
				if ($SnapinAssocs) {
					// Loop through each of the assocs
					// If valid, setup the snapins.
					foreach((array)$SnapinAssocs AS $i => &$SnapinAssoc) {
						if ($SnapinAssoc && $SnapinAssoc->isValid()) $Snapins[] = $SnapinAssoc->getSnapin();
                    }
                    unset($SnapinAssoc);
					// Loop each of the snapins.
					foreach((array)$Snapins AS $i => &$Snapin) {
						// Only if the snapin is valid do the jobs as well.
						if ($Snapin && $Snapin->isValid()) {
							// Setup the file maker.
							$mySnapFile = $Snapin->get('file');
							// Loop all the groups of this snapin.
							foreach((array)$Snapin->get(storageGroups) AS $i => &$GroupToSend) {
								// If the group is valid and not of the same groupid as this node has, then send the files.
								if ($GroupToSend && $GroupToSend->isValid() && $GroupToSend->get('id') != $StorageNode->get('storageGroupID')) {
									// Get the master node to send to.
									$StorageNodeToSend = $GroupToSend->getMasterStorageNode();
									if ($StorageNodeToSend && $StorageNodeToSend->isValid()) {
										$username = $StorageNodeToSend->get('user');
										$password = $StorageNodeToSend->get('pass');
										$ip = $StorageNodeToSend->get(ip);
										$remSnapin = rtrim($StorageNodeToSend->get('snapinpath'),'/');
										$mySnapin = rtrim($StorageNode->get('snapinpath'),'/');
										$limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
										$limitsend = $this->byteconvert($StorageNodeToSend->get('bandwidth'));
										if ($limitmain > 0)	$limit = "set net:limit-total-rate 0:$limitmain;";
										if ($limitsend > 0) $limit .= "set net:limit-rate 0:$limitsend;";
										$this->outall(sprintf(" * Found snapin to transfer to %s group(s)",count($Snapin->get('storageGroups')) -1));
										$this->outall(sprintf(" | Snapin name: %s",$Snapin->get('name')));
										$process[$StorageNodeToSend->get('name')] = popen("lftp -e 'set ftp:list-options -a;set net:max-retries 10;set net:timeout 30;$limit mirror -R --ignore-time -i $mySnapFile --exclude 'ssl/' --exclude 'dev/' --exclude 'CA/' -vvv --delete-first $mySnapin $remSnapin; exit' -u $username,$password $ip 2>&1","r");
									}
								}
                            }
                            unset($GroupToSend);
                        }
					}
                    unset($Snapin);
					foreach((array)$process AS $nodename => &$proc) {
						stream_set_blocking($proc,false);
						while(!feof($proc) && $proc != null) {
							$output = fgets($proc,256);
							if ($output) $this->outall(sprintf(" * %s - SubProcess -> %s",$nodename,$output));
						}
						pclose($proc);
						$this->outall(sprintf(" * %s - SubProcess -> Complete",$nodename));
					}
					unset($process,$limit,$mySnapFile,$proc);
					$this->outall(sprintf(" * Checking nodes within my group."));
					if (count($StorageNodeCount) > 0) {
						$this->outall(sprintf(" * Found: %s other member(s).",count($StorageNodeCount)));
						$this->outall(sprintf(''));
						$myRoot = rtrim($StorageNode->get('snapinpath'),'/');
						$this->outall(sprintf(" * My root: %s",$myRoot));
						$this->outall(sprintf(" * Starting Sync."));
						foreach($Snapins AS $i => &$Snapin) {
							$mySnapFile = $Snapin->get('file');
							foreach($StorageNodeCount AS $i => &$StorageNodeFTP) {
								if ($StorageNodeFTP->get('isEnabled')) {
									$username = $StorageNodeFTP->get('user');
									$password = $StorageNodeFTP->get('pass');
									$ip = $StorageNodeFTP->get(ip);
									$remRoot = rtrim($StorageNodeFTP->get('snapinpath'),'/');
									$limitmain = $this->byteconvert($StorageNode->get('bandwidth'));
									$limitsend = $this->byteconvert($StorageNodeFTP->get('bandwidth'));
									if ($limitmain > 0) $limit = "set net:limit-total-rate 0:$limitmain;";
									if ($limitsend > 0) $limit .= "set net:limit-rate 0:$limitsend;";
									$process[$StorageNodeFTP->get('name')] = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 10;set net:timeout 30;".$limit." mirror -n --ignore-time -R -vvv --delete-first $myRoot $remRoot; exit\" -u $username,$password $ip 2>&1","r");
								}
                            }
                            unset($StorageNodeFTP);
                        }
                        unset($Snapin);
						foreach((array)$process AS $nodename => &$proc) {
							stream_set_blocking($proc,false);
							while(!feof($proc) && $proc != null) {
								$output = fgets($proc,256);
								if ($output) $this->outall(sprintf(" * %s - SubProcess -> %s",$nodename,$output));
							}
							pclose($proc);
							$this->outall(sprintf(" * %s - SubProcess -> Complete",$nodename));
                        }
                        unset($proc);
					} else $this->outall(sprintf(" * I am the only member, no need to copy anything!"));
				} else $this->outall(sprintf(" * There are no snapins to replicate!"));
			}
		} catch (Exception $e) {
			$this->outall(' * '.$e->getMessage());
		}
	}
	public function serviceRun() {
		$this->FOGCore->out(' ',$this->dev);
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
		$this->commonOutput();
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
	}
}
