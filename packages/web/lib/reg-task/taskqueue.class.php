<?php
class TaskQueue extends TaskingElement {
    public function checkIn() {
        try {
            $this->Task
                ->set('stateID',$this->getCheckedInState())
                ->set('checkinTime',$this->formatTime('','Y-m-d H:i:s'))
                ->save();
            if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
            if ($this->imagingTask) {
                $this->Task
                    ->getImage()->set('size','')
                    ->save();
                if ($this->Task->isMulticast()) {
                    $msID = @min(self::getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->Task->get('id')),'msID'));
                    $MulticastSession = self::getClass('MulticastSessions',$msID);
                    if (!$MulticastSession->isValid()) throw new Exception(_('Invalid Multicast Session'));
                    $MulticastSession
                        ->set('clients',$MulticastSession->get('clients') < 0 ? 1 : $MulticastSession->get('clients')+1)
                        ->set('stateID',$this->getProgressState());
                    if (!$MulticastSession->save()) throw new Exception(_('Failed to update Session'));
                    if ($this->Host->isValid()) $this->Host->set('imageID',$MulticastSession->get('image'));
                } else if ($this->Task->isForced()) {
                    self::$HookManager->processEvent('TASK_GROUP',array('StorageGroup'=>&$this->StorageGroup,'Host'=>&$this->Host));
                    $this->StorageNode = null;
                    self::$HookManager->processEvent('TASK_NODE',array('StorageNode'=>&$this->StorageNode,'Host'=>&$this->Host));
                    if (!$this->StorageNode || !$this->StorageNode->isValid()) $this->StorageNode = $this->Image->getStorageGroup()->getOptimalStorageNode($this->Host->get('imageID'));
                    if ($this->Task->isCapture()) $this->StorageNode = $this->Image->StorageGroup->getMasterStorageNode();
                } else {
                    $this->StorageNode = self::nodeFail(self::getClass('StorageNode',$this->Task->get('NFSMemberID')),$this->Host->get('id'));
                    if (!$this->StorageNode || !$this->StorageNode->isValid()) throw new Exception(_('The node trying to be used is currently unavailable. On reboot we will try to find a new node automatically.'));
                    $totalSlots = $this->StorageNode->get('maxClients');
                    $usedSlots = $this->StorageNode->getUsedSlotCount();
                    $inFront = $this->Task->getInFrontOfHostCount();
                    $groupOpenSlots = $totalSlots - $usedSlots;
                    if ($groupOpenSlots < 1) throw new Exception(sprintf('%s, %s %d %s',_('No open slots'),_('There are'),$inFront,_('before me.')));
                    if ($groupOpenSlots <= $inFront) throw new Exception(sprintf('%s %d %s',_('There are open slots, but'),$inFront,_('before me on this node')));
                }
                $this->Task
                    ->set('NFSMemberID',$this->StorageNode->get('id'));
                if (!$this->ImageLog(true)) throw new Exception(_('Failed to update/create image log'));
            }
            $this->Task
                ->set('stateID',$this->getProgressState())
                ->set('checkInTime',$this->formatTime('now','Y-m-d H:i:s'));
            if (!$this->Task->save()) throw new Exception(_('Failed to update Task'));
            if (!$this->TaskLog()) throw new Exception(_('Failed to update/create task log'));
            self::$EventManager->notify('HOST_CHECKIN',array('Host'=>&$this->Host));
            echo '##@GO';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    private function email() {
        list($emailAction,$emailAddress,$emailBinary,$fromEmail) = self::getSubObjectIDs('Service',array('name'=>array('FOG_EMAIL_ACTION','FOG_EMAIL_ADDRESS','FOG_EMAIL_BINARY','FOG_FROM_EMAIL')),'value',false,'AND','name',false,'');
        if (!$emailAction || !$emailAddress) return;
        if (!$this->Host->get('inventory')->isValid()) return;
        $SnapinJob = $this->Host->get('snapinjob');
        $SnapinTasks = self::getSubObjectIDs('SnapinTask',array('stateID'=>$this->getQueuedStates(),'jobID'=>$SnapinJob->get('id')),'snapinID');
        $SnapinNames = $SnapinJob->isValid() ? self::getSubObjectIDs('Snapin',array('id'=>$SnapinTasks),'name') : array();
        if (!$emailBinary) $emailBinary = '/usr/sbin/sendmail -t -f noreply@fogserver.com -i';
        $emailBinary = preg_replace('#\$\{server-name\}#',$this->StorageNode->isValid() ? $this->StorageNode->get('name') : 'fogserver',$emailBinary);
        if (!$fromEmail) $fromEmail = 'noreply@fogserver.com';
        $fromEmail = preg_replace('#\$\{server-name\}#',$this->StorageNode->isValid() ? $this->StorageNode->get('name') : 'fogserver',$fromEmail);
        $headers = sprintf("From: %s\r\nX-Mailer: PHP/%s",$fromEmail,phpversion());
        $engineer = ucwords($this->Task->get('createdBy'));
        $primaryUser = ucwords($this->Host->get('inventory')->get('primaryUser'));
        $email = array(
            sprintf("%s:-\n",_('Machine Details')) => '',
            sprintf("\n%s: ",_('Host Name')) => $this->Host->get('name'),
            sprintf("\n%s: ",_('Computer Model')) => $this->Host->get('inventory')->get('sysproduct'),
            sprintf("\n%s: ",_('Serial Number')) => $this->Host->get('inventory')->get('sysserial'),
            sprintf("\n%s: ",_('MAC Address')) => $this->Host->get('mac')->__toString(),
            "\n" => '',
            sprintf("\n%s: ",_('Image Used')) => $this->Task->getImage()->get('name'),
            sprintf("\n%s: ",_('Snapin Used')) => implode(', ',(array)$SnapinNames),
            "\n" => '',
            sprintf("\n%s: ",_('Imaged By')) => $engineer,
            sprintf("\n%s: ",_('Imaged For')) => $primaryUser,
        );
        self::$HookManager->processEvent('EMAIL_ITEMS',array('email'=>&$email,'Host'=>&$this->Host));
        ob_start();
        array_walk($email,function(&$val,&$key) {
            printf('%s%s',$key,$val);
            unset($val,$key);
        });
        $emailMe = ob_get_clean();
        $stat = sprintf('%s - %s',$this->Host->get('name'),_('Image Task Completed'));
        if ($this->Host->get('inventory')->get('other1')) {
            mail($emailAddress,sprintf('ISSUE=%s PROJ=1',$this->Host->get('inventory')->get('other1')),$emailMe,$headers);
            $emailMe .= sprintf("\n%s (%s): %s",_('Imaged For'),_('Call'),$this->Host->get('inventory')->get('other1'));
            $this->Host->get('inventory')->set('other1','')->save();
        }
        mail($emailAddress,$stat,$emailMe,$headers);
    }
    private function move_upload() {
        if (!$this->Task->isCapture()) return;
        $macftp = strtolower(str_replace(array(':','-','.'),'',$_REQUEST['mac']));
        $src = sprintf('%s/dev/%s',$this->StorageNode->get('ftppath'),$macftp);
        $dest = sprintf('%s/%s',$this->StorageNode->get('ftppath'),$this->Image->get('path'));
        self::$FOGFTP
            ->set('host',$this->StorageNode->get('ip'))
            ->set('username',$this->StorageNode->get('user'))
            ->set('password',$this->StorageNode->get('pass'))
            ->connect()
            ->delete($dest)
            ->rename($src,$dest)
            ->close();
        if ($this->Image->get('format') == 1) $this->Image->set('format',0);
        $this->Image->set('deployed',self::nice_date()->format('Y-m-d H:i:s'))->save();
    }
    public function checkout() {
        if ($this->Task->isSnapinTasking()) die('##');
        if ($this->Task->isMulticast()) {
            $MCTask = self::getClass('MulticastSessionsAssociation')->set('taskID',$this->Task->get('id'))->load('taskID');
            $MulticastSession = $MCTask->getMulticastSession();
            $MulticastSession->set('clients',($MulticastSession->get('clients') < 1 ? 0 : $MulticastSession->get('clients') - 1))->save();
        }
        $this->Host->set('pub_key','')->set('sec_tok','');
        if ($this->Task->isDeploy()) {
            $this->Host->set('deployed',self::nice_date()->format('Y-m-d H:i:s'));
            $this->email();
        }
        $this->move_upload();
        $this->Task
            ->set('pct',100)
            ->set('percent',100)
            ->set('stateID',$this->getCompleteState());
        if (!$this->Host->save()) throw new Exception(_('Failed to update Host'));
        if (!$this->Task->save()) throw new Exception(_('Failed to update Task'));
        if (!$this->TaskLog()) throw new Exception(_('Failed to update task log'));
        if (!$this->ImageLog(false)) throw new Exception(_('Failed to update imaging log'));
        self::$EventManager->notify('HOST_IMAGE_COMPLETE',array('HostName'=>$this->Host->get('name')));
        echo '##';
    }
}
