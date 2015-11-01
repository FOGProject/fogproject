<?php
class SnapinClient extends FOGClient implements FOGClientSend {
    public function send() {
        // Common checks before anything is done or sent
        // Is this host in non-snapin tasking?
        if ($this->Host->get('task')->isValid() && !in_array($this->Host->get('task')->get('typeID'),array(12,13))) throw new Exception('#!it');
        // If the task is sent, is it valid?
        if (isset($_REQUEST['taskid'])) {
            $SnapinTask = $this->getClass('SnapinTask',$_REQUEST['taskid']);
            if (!$SnapinTask->isValid() || in_array($SnapinTask->get('stateID'),array(4,5))) throw new Exception(_('Invalid snapin tasking passed'));
        } else {
            // Is there actually a job for this host?
            if (!$this->Host->get('snapinjob')->isValid()) throw new Exception('#!ns');
            // Work on the current snapin task
            $SnapinTask = $this->getClass('SnapinTaskManager')->find(array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array(-1,0,1,2,3)),'','name');
            $SnapinTask = @array_shift($SnapinTask);
        }
        // Is this snapin task actually valid?
        if (!($SnapinTask instanceof SnapinTask && $SnapinTask->isValid())) {
            // If a job exists but no snapin tasks
            // remove the job.
            if ($this->getClass('SnapinTaskManager')->count(array('jobID'=>$this->Host->get('snapinjob')->get('id'),'stateID'=>array(-1,0,1,2,3))) < 1) {
                // If host has snapin tasking, update to cancelled as it does not exist
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->cancel();
                $this->Host->get('snapinjob')->set('stateID',5)->save();
            }
            throw new Exception('#!ns');
        }
        // Get this Snapin
        $Snapin = $SnapinTask->getSnapin();
        // Get the storage group
        if ($Snapin->getStorageGroup()->isValid() && $Snapin->isValid()) $StorageGroup = $Snapin->getStorageGroup();
        // Send the hook to alter the group as needed
        $this->HookManager->processEvent('SNAPIN_GROUP',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageGroup'=>&$StorageGroup));
        // If the Storage Group isn't valid, set file using legacy method
        if (!($StorageGroup instanceof StorageGroup && $StorageGroup->isValid())) {
            $SnapinFile = '/'.trim($this->getSetting('FOG_SNAPINDIR'),'/').'/';
            // If the files don't exist throw and error
            if (!file_exists($SnapinFile) && !file_exists($Snapin->get('file'))) throw new Exception('Snapin file does not exist');
        } else {
            // Get the master node
            $StorageNode = $StorageGroup->getMasterStorageNode();
            // Send the hook to alter the node as needed
            $this->HookManager->processEvent('SNAPIN_NODE',array('Host'=>&$this->Host,'Snapin'=>&$Snapin,'StorageNode'=>&$StorageNode));
            // If we cannot find a node we cannot download the file
            // Inform the client of this
            if (!$StorageNode->isValid()) throw new Exception(_('Failed to find a node'));
            // If we cannot connect to the ftp server we cannot download the file
            // Inform the client of this
            $this->FOGFTP
                ->set('host',$StorageNode->get('ip'))
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'));
            if (!$this->FOGFTP->connect()) throw new Exception(_('Failed to connect to download'));
            // Disconnect as we will download it directly
            $this->FOGFTP->close();
            // Trim the path and get the basename of the file
            $path = trim($StorageNode->get('snapinpath'),'/');
            $file = basename($Snapin->get('file'));
            // Create the file link
            $SnapinFile = "ftp://{$StorageNode->get(user)}:{$StorageNode->get(pass)}@{$StorageNode->get(ip)}/$path/$file";
            // Is the file existing and readable?
            if (!file_exists($SnapinFile) || !is_readable($SnapinFile)) {
                // Put this snapin into cancelled state so other snapins can run
                $SnapinTask->set('stateID',5)->set('complete',$this->nice_date()->format('Y-m-d H:i:s'))->save();
                throw new Exception(_('Failed to find snapin file'));
            }
            $size = filesize($SnapinFile);
        }
        // Perform checkin if the taskid is not set
        // Is snapin complete and proper?
        if (strlen($_REQUEST['exitcode']) > 0 && is_numeric($_REQUEST['exitcode'])) {
            $SnapinTask->set('stateID',4)->set('return',$_REQUEST['exitcode'])->set('details',$_REQUEST['exitdesc'])->set('complete',$this->nice_date()->format('Y-m-d H:i:s'));
            if ($SnapinTask->save()) echo '#!ok';
            // If this is the last task, update the job
            if ($this->getClass('SnapinTaskManager')->count(array('stateID'=>array(-1,0,1,2,3))) < 1) {
                // If host has snapin tasking, update to complete
                if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',4)->save();
                $this->Host->get('snapinjob')->set('stateID',4)->save();
            }
        } else if (!isset($_REQUEST['taskid'])) {
            // Update Job to in progress
            $this->Host->get('snapinjob')->set('stateID',3)->save();
            // If host has snapin tasking, update to in progress
            if ($this->Host->get('task')->isValid()) $this->Host->get('task')->set('stateID',3)->set('checkInTime',$this->nice_date()->format('Y-m-d H:i:s'))->save();
            // Update the actual Snapin Tasking
            $SnapinTask->set('stateID',2)->set('checkin',$this->nice_date()->format('Y-m-d H:i:s'));
            // If snapin tasking fails inform the client
            if (!$SnapinTask->save()) throw new Exception(_('Failed to update snapin tasking'));
            if ($this->newService) $snapinHash = strtoupper(hash_file('sha512',$SnapinFile));
            // All successful, give the client the details
            $goodArray = array(
                '#!ok',
                sprintf('JOBTASKID=%d',$SnapinTask->get('id')),
                sprintf('JOBCREATION=%s',$this->Host->get('snapinjob')->get('createdTime')),
                sprintf('SNAPINNAME=%s',$Snapin->get('name')),
                sprintf('SNAPINARGS=%s',$this->DB->sanitize($Snapin->get('args'))),
                sprintf('SNAPINBOUNCE=%s',$Snapin->get('reboot')),
                sprintf('SNAPINFILENAME=%s',$Snapin->get('file')),
                sprintf('SNAPINRUNWITH=%s',$this->DB->sanitize($Snapin->get('runWith'))),
                sprintf('SNAPINRUNWITHARGS=%s',$this->DB->sanitize($Snapin->get('runWithArgs'))),
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
