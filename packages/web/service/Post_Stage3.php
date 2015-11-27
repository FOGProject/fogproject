<?php
require('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    $Task = $Host->get('task');
    if (!$Task || !$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'),$Host->get('mac')->__toString()));
    $TaskType = $FOGCore->getClass('TaskType',$Task->get('typeID'));
    if (!in_array($Task->get('typeID'),array(12,13))) $Task->set('stateID',4)->set('pct',100)->set('percent',100);
    $Host->set('deployed',$FOGCore->nice_date()->format('Y-m-d H:i:s'))->save();
    $id = @max($FOGCore->getSubObjectIDs('ImagingLog',array('hostID' => $Host->get('id'))));
    $FOGCore->getClass('ImagingLog',$id)
        ->set('finish',$FOGCore->nice_date()->format('Y-m-d H:i:s'))
        ->save();
    $FOGCore->getClass('TaskLog',$Task)
        ->set(taskID,$Task->get(id))
        ->set(taskStateID,$Task->get(stateID))
        ->set(createdTime,$Task->get(createdTime))
        ->set(createdBy,$Task->get(createdBy))
        ->save();
    if (!$Task->save()) {
        $EventManager->notify('HOST_IMAGE_Fail', array(HostName=>$Host->get(name)));
        throw new Exception('Failed to update task.');
    }
    $EventManager->notify('HOST_IMAGE_COMPLETE', array(HostName=>$Host->get(name)));
    ////============================== Email Notification Start ==============================
    if ($FOGCore->getSetting(FOG_EMAIL_ACTION)) {
        $Inventory = current($FOGCore->getClass(InventoryManager)->find(array('hostID' => $Host->get(id)))); //Get inventory Data
        if ($Inventory && $Inventory->isValid()) {
            $SnapinJob = $Host->get(snapinjob); //Get Snapin(s) Used/Queued
            if ($SnapinJob && $SnapinJob->isValid()) {
                $SnapinTasks = $FOGCore->getClass(SnapinTaskManager)->find(array('stateID' => array(-1,0,1),'jobID' => $SnapinJob->get(id)));
                foreach($SnapinTasks AS $SnapinTask) {
                    if ($SnapinTask && $SnapinTask->isValid()) {
                        $Snapin = $FOGCore->getClass(Snapin,$SnapinTask->get(snapinID));
                        if ($Snapin->isValid()) $SnapinNames[] = $Snapin->get(name);
                    }
                }
            }
            $StorageNode = $Task->getStorageNode();
            $emailbinary = ($FOGCore->getSetting(FOG_EMAIL_BINARY) ? preg_replace('#\$\{server-name\}#',($StorageNode->isValid() ? $StorageNode->get(name) : 'fogserver'),$FOGCore->getSetting(FOG_EMAIL_BINARY)) : '/usr/sbin/sendmail -t -f noreply@fogserver.com -i');
            ini_set('sendmail_path',$emailbinary);
            $snpusd = implode(', ',(array)$SnapinNames); //to list snapins as 1, 2, 3,  etc
            $engineer = ucwords($Task->get(createdBy)); //ucwords purely aesthetics
            $puser = ucwords($Inventory->get(primaryUser)); //ucwords purely aesthetics
            $to = $FOGCore->getSetting(FOG_EMAIL_ADDRESS); //Email address(es) to be used
            $headers = 'From: '.$FOGCore->getSetting(FOG_FROM_EMAIL)."\r\n".'X-Mailer: PHP/'.phpversion();
            $headers = preg_replace('#\$\{server-name\}#',($StorageNode->isValid() ? $StorageNode->get(name) : 'fogserver'),$headers);
            //$Email - is just the context of the email put in variable saves repeating
            $email = array(
                "Machine Details:-\n" => '',
                "\nHostName: " => $Host->get(name),
                "\nComputer Model: " => $Inventory->get(sysproduct),
                "\nSerial Number: " => $Inventory->get(sysserial),
                "\nMAC Address: " => $Host->get(mac),
                "\n" => '',
                "\nImage Used: " => $FOGCore->getClass(ImagingLog,$id)->get(image),
                "\nSnapin Used: " => $snpusd,
                "\n" => '',
                "\nImaged By (Engineer): " => $engineer,
                ($puser ? "\nImaged For (User): " : '') => ($puser ? $puser : ''),
            );
            $HookManager->processEvent(EMAIL_ITEMS,array(email=>&$email,Host=>&$Host));
            $emailMe = '';
            foreach($email AS $key => $val) $emailMe .= $key.$val;
            unset($email);
            $email = $emailMe;
            if (!$Inventory->get(other1)) //if there isn't an existing call number in the system
                mail($to, $Host->get(name). " - Image Task Completed", $email,$headers);
            else {
                mail($to,"ISSUE=" .$Inventory->get(other1). " PROJ=1", $email, $headers);
                mail($to, $Host->get(name). " - Image Task Completed", "$email \nImaged For (Call): " .$Inventory->get(other1),$headers);
                $Inventory->set(other1,'')->save(); //clear call number otherwise if a new "existing" call exists later on down the line it'll just update original
            }
        }
    }
    ////============================== Email Notification End	==============================
    echo '##';
    // If it's a multicast job, decrement the client count, though not fully needed.
    if ($Task->get(typeID) == 8) {
        $MyMulticastTask = current($FOGCore->getClass(MulticastSessionsAssociationManager)->find(array(taskID=>$Task->get(id))));
        if ($MyMulticastTask && $MyMulticastTask->isValid()) {
            $MulticastSession = $FOGCore->getClass(MulticastSessions,$MyMulticastTask->get(msID));
            $MulticastSession
                ->set(clients,$MulticastSession->get(clients) - 1)
                ->save();
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
