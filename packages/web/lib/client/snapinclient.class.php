<?php
class SnapinClient extends FOGClient implements FOGClientSend {
    private function jsonoutput($date) {
        $SnapinJob = $this->Host->get('snapinjob');
        $HostSnapins = $this->Host->get('snapins');
        $SnapinJob->set('stateID',$this->getCheckedInState())->save();
        if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getCheckedInState())->set('checkInTime',$date)->save();
        $vals = array();
        array_map(function(&$Snapin) use (&$vals,$SnapinJob) {
            if (!$Snapin->isValid()) return;
            $SnapinTask = self::getClass('SnapinTask',@min(self::getSubObjectIDs('SnapinTask',array('jobID'=>$SnapinJob->get('id'),'snapinID'=>$Snapin->get('id')))));
            if (!$SnapinTask->isValid()) return;
            $SnapinTask
                ->set('checkin',$date)
                ->set('stateID',$this->getCheckedInState())
                ->save();
            $StorageGroup = $Snapin->getStorageGroup();
            if (!$StorageGroup->isValid()) return;
            self::$HookManager->processEvent('SNAPIN_GROUP',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageGroup'=>&$StorageGroup));
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode->isValid()) return;
            self::$HookManager->processEvent('SNAPIN_NODE',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageNode'=>&$StorageNode));
            $path = rtrim($StorageNode->get('snapinpath'),'/');
            $file = $Snapin->get('file');
            $filepath = sprintf('%s/%s',$path,$file);
            $ip = $StorageNode->get('ip');
            $curroot = trim(trim($StorageNode->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $url = "http://$ip{$webroot}status/getsnapinhash.php";
            unset($curroot,$webroot,$ip);
            if (!self::$FOGURLRequests->isAvailable($url)) return;
            $response = self::$FOGURLRequests->process($url,'POST',array('filepath'=>$filepath));
            $data = explode('|',array_shift($response));
            $hash = array_shift($data);
            $size = array_shift($data);
            $vals[] = array(
                'jobtaskid'=>$SnapinTask->get('id'),
                'jobcreation'=>$SnapinJob->get('createdTime'),
                'name'=>$Snapin->get('name'),
                'args'=>$Snapin->get('args'),
                'action'=>$Snapin->get('reboot') ? ($Snapin->get('shutdown') ? 'shutdown' : 'reboot') : '',
                'filename'=>$Snapin->get('file'),
                'runwith'=>$Snapin->get('runWith'),
                'runwithargs'=>$Snapin->get('runWithArgs'),
                'hash'=>strtoupper($hash),
                'size'=>$size,
            );
        },(array)self::getClass('SnapinManager')->find(array('id'=>$HostSnapins)));
        return array('snapins'=>$vals);
    }
    public function send() {
        try {
            $date = $this->formatTime('','Y-m-d H:i:s');
            if ($this->Host->get('task')->isValid() && !$this->Host->get('task')->isSnapinTasking()) throw new Exception('#!it');
            if (!$this->Host->get('snapinjob')->isValid()) throw new Exception('#!ns');
            if (self::getClass('SnapinTaskManager')->count(array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) < 1) {
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getCompleteState())->save();
                $this->Host->get('snapinjob')->set('stateID',$this->getCompleteState())->save();
                self::$EventManager->notify('HOST_SNAPIN_COMPLETE',array('HostName'=>&$hostname));
                throw new Exception('#!ns');
            }
            if (!isset($_REQUEST['exitcode']) && $this->json) return $this->jsonoutput($date);
            $SnapinID = isset($_REQUEST['taskid']) ? (int)$_REQUEST['taskid'] : @min(self::getSubObjectIDs('SnapinTask',array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))));
            $SnapinTask = self::getClass('SnapinTask',isset($_REQUEST['taskid']) ? $_REQUEST['taskid'] : $SnapinID);
            if (!$SnapinTask->isValid()) throw new Exception(_('Invalid Snapin Tasking'));
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) throw new Exception(_('Invalid Snapin'));
            $StorageGroup = $Snapin->getStorageGroup();
            if (!$StorageGroup->isValid()) throw new Exception(_('Invalid Storage Group'));
            self::$HookManager->processEvent('SNAPIN_GROUP',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageGroup'=>&$StorageGroup));
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode->isValid()) throw new Exception(_('Invalid Storage Node'));
            self::$HookManager->processEvent('SNAPIN_NODE',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageNode'=>&$StorageNode));
            if (!$StorageNode->isValid()) throw new Exception(_('Failed to find a node'));
            $path = rtrim($StorageNode->get('snapinpath'),'/');
            $file = $Snapin->get('file');
            $filepath = sprintf('%s/%s',$path,$file);
            $ip = $StorageNode->get('ip');
            $curroot = trim(trim($StorageNode->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $url = "http://$ip{$webroot}status/getsnapinhash.php";
            unset($curroot,$webroot,$ip);
            if (!self::$FOGURLRequests->isAvailable($url)) return;
            $response = self::$FOGURLRequests->process($url,'POST',array('filepath'=>$filepath));
            $data = explode('|',array_shift($response));
            $hash = array_shift($data);
            $size = array_shift($data);
            if ($size < 1) {
                $SnapinTask
                    ->set('stateID',$this->getCancelledState())
                    ->set('complete',$date)
                    ->save();
                throw new Exception(_('Failed to find snapin file'));
            }
            $pass = urlencode($StorageNode->get('pass'));
            self::$FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) throw new Exception(_('FTP Failed to connect to node'));
            self::$FOGFTP->close();
            $SnapinFile = "ftp://{$StorageNode->get(user)}:$pass@{$StorageNode->get(ip)}$filepath";
            if (strlen($_REQUEST['exitcode']) > 0 && is_numeric($_REQUEST['exitcode'])) {
                $SnapinTask
                    ->set('stateID',$this->getCompleteState())
                    ->set('return',$_REQUEST['exitcode'])
                    ->set('details',$_REQUEST['exitdesc'])
                    ->set('complete',$date)
                    ->save();
                if (self::getClass('SnapinTaskManager')->count(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) < 1) {
                    if ($this->Host->get('task')->isValid()) {
                        $this->Host->get('task')
                            ->set('stateID',$this->getCompleteState())
                            ->save();
                        self::$EventManager->notify('HOST_SNAPINTASK_COMPLETE',array('Snapin'=>&$Snapin,'SnapinTask'=>&$SnapinTask));
                    }
                    $this->Host->get('snapinjob')->set('stateID',$this->getCompleteState())->save();
                }
                echo '#!ok';
            } else if (!isset($_REQUEST['taskid'])) {
                // Checks In the snapin task, and returns the info to the client.
                $this->Host->get('snapinjob')->set('stateID',$this->getCheckedInState())->save();
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getCheckedInState())->set('checkInTime',$date)->save();
                $SnapinTask->set('stateID',$this->getCheckedInState())->set('checkin',$date);
                if (!$SnapinTask->save()) throw new Exception(_('Failed to update snapin tasking'));
                $goodArray = array(
                    '#!ok',
                    sprintf('JOBTASKID=%d',$SnapinTask->get('id')),
                    sprintf('JOBCREATION=%s',$this->Host->get('snapinjob')->get('createdTime')),
                    sprintf('SNAPINNAME=%s',$Snapin->get('name')),
                    sprintf('SNAPINARGS=%s',$Snapin->get('args')),
                    sprintf('SNAPINBOUNCE=%s',$Snapin->get('reboot')),
                    sprintf('SNAPINFILENAME=%s',$Snapin->get('file')),
                    sprintf('SNAPINRUNWITH=%s',$Snapin->get('runWith')),
                    sprintf('SNAPINRUNWITHARGS=%s',$Snapin->get('runWithArgs')),
                );
                if ($this->newService) {
                    array_push($goodArray,sprintf('SNAPINHASH=%s',strtoupper($hash)));
                    array_push($goodArray,sprintf('SNAPINSIZE=%s',$size));
                }
                $this->send = implode("\n",$goodArray);
            } else if (isset($_REQUEST['taskid'])) {
                // Downloads the snapin file and sets the tasking to in-progress
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getProgressState())->set('checkInTime',$date)->save();
                $this->Host->get('snapinjob')->set('stateID',$this->getProgressState())->save();
                $SnapinTask->set('stateID',$this->getProgressState())->set('return',-1)->set('details',_('Pending...'))->save();
                while (ob_get_level()) ob_end_clean();
                header("X-Sendfile: $SnapinFile");
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=$file");
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Connection: close');
                if (($fh = fopen($SnapinFile,'rb')) === false) return;
                while (feof($fh) === false) {
                    if (($line = fread($fh,4092)) === false) break;
                    echo $line;
                }
                fclose($fh);
                flush();
                ob_flush();
                ob_end_flush();
                exit;
            }
        } catch (Exception $e) {
            if ($this->json) return array('error'=>preg_replace('/^[#][!]?/','',$e->getMessage()));
            throw new Exception($e->getMessage());
        }
    }
}
