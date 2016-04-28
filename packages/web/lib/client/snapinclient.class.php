<?php
class SnapinClient extends FOGClient implements FOGClientSend {
    private function jsonoutput() {
        $this->Host->get('snapinjob')->set('stateID',$this->getCheckedInState())->save();
        if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getCheckedInState())->set('checkInTime',self::nice_date()->format('Y-m-d H:i:s'))->save();
        $HostSnapins = $this->Host->get('snapins');
        array_map(function(&$Snapin) use (&$vals) {
            if (!$Snapin->isValid()) return;
            $SnapinTask = self::getClass('SnapinTask',@min(self::getSubObjectIDs('SnapinTask',array('jobID'=>$this->Host->get('snapinjob')->get('id'),'snapinID'=>$Snapin->get('id')))));
            if (!$SnapinTask->isValid()) return;
            $SnapinTask->set('stateID',$this->getCheckedInState())->save();
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
            $vals['jobtaskid'] = $SnapinTask->get('id');
            $vals['jobcreation'] = $this->Host->get('snapinjob')->get('createdTime');
            $vals['name'] = $Snapin->get('name');
            $vals['args'] = $Snapin->get('args');
            $vals['action'] = $Snapin->get('reboot') ? ($Snapin->get('shutdown') ? 'shutdown' : 'reboot') : '';
            $vals['filename'] = $Snapin->get('file');
            $vals['runwith'] = $Snapin->get('runWith');
            $vals['runwithargs'] = $Snapin->get('runWithArgs');
            $vals['hash'] = strtoupper($hash);
            $vals['size'] = $size;
        },(array)self::getClass('SnapinManager')->find(array('id'=>$HostSnapins)));
        return array('snapins'=>array($vals));
    }
    public function send() {
        try {
            if ($this->Host->get('task')->isValid() && !$this->Host->get('task')->isSnapinTasking()) throw new Exception('#!it');
            if (!$this->Host->get('snapinjob')->isValid()) throw new Exception('#!ns');
            if (self::getClass('SnapinTaskManager')->count(array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) < 1) {
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getCompleteState())->save();
                $this->Host->get('snapinjob')->set('stateID',$this->getCompleteState())->save();
                self::$EventManager->notify('HOST_SNAPIN_COMPLETE',array('HostName'=>&$hostname));
                throw new Exception('#!ns');
            }
            if ($this->json) return $this->jsonoutput();
            $SnapinTask = self::getClass('SnapinTask',isset($_REQUEST['taskid']) ? $_REQUEST['taskid'] : @min(self::getSubObjectIDs('SnapinTask',array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))));
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
                    ->set('complete',self::nice_date()->format('Y-m-d H:i:s'))
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
                    ->set('return',htmlentities($_REQUEST['exitcode'],ENT_QUOTES,'utf-8'))
                    ->set('details',htmlentities($_REQUEST['exitdesc'],ENT_QUOTES,'utf-8'))
                    ->set('complete',self::nice_date()->format('Y-m-d H:i:s'))
                    ->save();
                if (self::getClass('SnapinTaskManager')->count(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) < 1) {
                    if ($this->Host->get('task')->isValid()) {
                        $this->Host->get('task')
                            ->set('stateID',$this->getCompleteState())
                            ->save();
                    }
                    $this->Host->get('snapinjob')->set('stateID',$this->getCompleteState())->save();
                }
                echo '#!ok';
            } else if (!isset($_REQUEST['taskid'])) {
                // Checks In the snapin task, and returns the info to the client.
                $this->Host->get('snapinjob')->set('stateID',$this->getCheckedInState())->save();
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getCheckedInState())->set('checkInTime',self::nice_date()->format('Y-m-d H:i:s'))->save();
                $SnapinTask->set('stateID',$this->getCheckedInState())->set('checkin',self::nice_date()->format('Y-m-d H:i:s'));
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
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',$this->getProgressState())->set('checkInTime',self::nice_date()->formate('Y-m-d H:i:s'))->save();
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
