<?php
class SnapinClient extends FOGClient implements FOGClientSend {
    public function send() {
        if ($this->Host->get('task')->isValid() && !in_array($this->Host->get('task')->get('typeID'),array(12,13))) throw new Exception('#!it');
        if (isset($_REQUEST['taskid'])) {
            $SnapinTask = $this->getClass('SnapinTask',intval($_REQUEST['taskid']));
            if (!$SnapinTask->isValid() || in_array($SnapinTask->get('stateID'),array(4,5))) throw new Exception(_('Invalid snapin tasking passed'));
        } else {
            if (!$this->Host->get('snapinjob')->isValid()) throw new Exception('#!ns');
            $SnapinTask = $this->getClass('SnapinTaskManager')->find(array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array(-1,0,1,2,3)),'','name');
            $SnapinTask = @array_shift($SnapinTask);
        }
        if (!($SnapinTask instanceof SnapinTask && $SnapinTask->isValid())) {
            if ($this->getClass('SnapinTaskManager')->count(array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array(-1,0,1,2,3))) < 1) {
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->cancel();
                $this->Host->get('snapinjob')->set('stateID',5)->save();
            }
            throw new Exception('#!ns');
        }
        $Snapin = $SnapinTask->getSnapin();
        if ($Snapin->getStorageGroup()->isValid() && $Snapin->isValid()) $StorageGroup = $Snapin->getStorageGroup();
        $this->HookManager->processEvent('SNAPIN_GROUP',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageGroup'=>&$StorageGroup));
        if (!($StorageGroup instanceof StorageGroup && $StorageGroup->isValid())) {
            $SnapinFile = sprintf('%s%s',dirname(trim($this->getSetting('FOG_SNAPINDIR'))),DIRECTORY_SEPARATOR);
            if (!file_exists($SnapinFile) && !file_exists($Snapin->get('file'))) throw new Exception('Snapin file does not exist');
        } else {
            $StorageNode = $StorageGroup->getMasterStorageNode();
            $this->HookManager->processEvent('SNAPIN_NODE',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageNode'=>&$StorageNode));
            if (!$StorageNode->isValid()) throw new Exception(_('Failed to find a node'));
            $this->FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!$this->FOGFTP->connect()) throw new Exception(_('Failed to connect to download'));
            $this->FOGFTP->close();
            $path = dirname($StorageNode->get('snapinpath'));
            $file = basename($Snapin->get('file'));
            $SnapinFile = "ftp://{$StorageNode->get(user)}:{$StorageNode->get(pass)}@{$StorageNode->get(ip)}$path/$file";
            if (!file_exists($SnapinFile) || !is_readable($SnapinFile)) {
                $SnapinTask->set('stateID',5)->set('complete',$this->nice_date()->format('Y-m-d H:i:s'))->save();
                throw new Exception(_('Failed to find snapin file'));
            }
            $size = filesize($SnapinFile);
        }
        if (strlen($_REQUEST['exitcode']) > 0 && is_numeric($_REQUEST['exitcode'])) {
            $SnapinTask->set('stateID',4)->set('return',$_REQUEST['exitcode'])->set('details',$_REQUEST['exitdesc'])->set('complete',$this->nice_date()->format('Y-m-d H:i:s'));
            if ($SnapinTask->save()) echo '#!ok';
            if ($this->getClass('SnapinTaskManager')->count(array('stateID'=>array(-1,0,1,2,3))) < 1) {
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',4)->save();
                $this->Host->get('snapinjob')->set('stateID',4)->save();
            }
        } else if (!isset($_REQUEST['taskid']) || !is_numeric($_REQUEST['taskid'])) {
            $this->Host->get('snapinjob')->set('stateID',3)->save();
            if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',3)->set('checkInTime',$this->nice_date()->format('Y-m-d H:i:s'))->save();
            $SnapinTask->set('stateID',2)->set('checkin',$this->nice_date()->format('Y-m-d H:i:s'));
            if (!$SnapinTask->save()) throw new Exception(_('Failed to update snapin tasking'));
            if ($this->newService) $snapinHash = strtoupper(hash_file('sha512',$SnapinFile));
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
                array_push($goodArray,sprintf('SNAPINHASH=%s',$snapinHash));
                array_push($goodArray,sprintf('SNAPINSIZE=%s',$size));
            }
            $this->send = implode("\n",$goodArray);
        } else if (isset($_REQUEST['taskid'])) {
            while (ob_get_level()) ob_end_clean();
            header("X-Sendfile: $SnapinFile");
            header('Content-Type: application/octet-stream');
            header("Content-Length: $size");
            header("Content-Disposition: attachment; filename=$file");
            if (false !== ($handle = fopen($SnapinFile,'rb'))) {
                while (!feof($handle)) echo fread($handle,4*1024*1024);
            }
            if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',3)->save();
            $SnapinTask->set('stateID',3)->set('return',-1)->set('details',_('Pending...'))->save();
            exit;
        }
    }
}
