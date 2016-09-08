<?php
class SnapinClient extends FOGClient implements FOGClientSend
{
    public function json()
    {
        $date = $this->formatTime('', 'Y-m-d H:i:s');
        $HostName = $this->Host->get('name');
        $Task = $this->Host->get('task');
        $SnapinJob = $this->Host->get('snapinjob');
        if ($Task->isValid() && !$Task->isSnapinTasking()) {
            return array(
                'error' => 'it'
            );
        }
        if (!$SnapinJob->isValid()) {
            return array(
                'error' => 'ns'
            );
        }
        $STaskCount = self::getClass('SnapinTaskManager')
            ->count(
                array(
                    'jobID' => $SnapinJob->get('id'),
                    'stateID' => array_merge(
                        $this->getQueuedStates(),
                        (array)$this->getProgressState()
                    )
                )
            );
        if ($STaskCount < 1) {
            if ($Task->isValid()) {
                $Task->set('stateID', $this->getCompleteState())->save();
            }
            $SnapinJob->set('stateID', $this->getCompleteState())->save();
            self::$EventManager->notify(
                'HOST_SNAPIN_COMPLETE',
                array(
                    'Host' => &$this->Host
                )
            );
            return array('error' => 'ns');
        }
        if ($Task->isValid()) {
            $Task
                ->set('stateID', $this->getCheckedInState())
                ->set('checkInTime', $date)
                ->save();
        }
        $SnapinJob->set('stateID', $this->getCheckedInState())->save();
        if (basename(self::$scriptname) === 'snapins.checkin.php') {
            if (!isset($_REQUEST['exitcode'])
                && !(isset($_REQUEST['taskid'])
                && is_numeric($_REQUEST['taskid']))
            ) {
                $snapinIDs = self::getSubObjectIDs(
                    'SnapinTask',
                    array(
                        'stateID' => array_merge(
                            $this->getQueuedStates(),
                            (array)$this->getProgressState()
                        ),
                        'jobID' => $SnapinJob->get('id'),
                    ),
                    'snapinID'
                );
                $snapinTaskIDs = self::getSubObjectIDs(
                    'SnapinTask',
                    array(
                        'stateID' => array_merge(
                            $this->getQueuedStates(),
                            (array)$this->getProgressState()
                        ),
                        'jobID' => $SnapinJob->get('id'),
                    )
                );
                $Snapins = self::getClass('SnapinManager')
                    ->find(
                        array('id' => $snapinIDs),
                        'AND',
                        'id'
                    );
                $SnapinTasks = self::getClass('SnapinTaskManager')
                    ->find(
                        array('id' => $snapinTaskIDs),
                        'AND',
                        'id'
                    );
                foreach ((array)$Snapins as $index => &$Snapin) {
                    if (!$Snapin->isValid()) {
                        $info['snapins'] = array(
                            'error' => _('Invalid Snapin')
                        );
                        continue;
                    }
                    $SnapinTask = $SnapinTasks[$index];
                    if (!$SnapinTask->isValid()) {
                        $info['snapins'] = array(
                            'error' => _('Invalid Snapin Task')
                        );
                    }
                    $StorageNode = $StorageGroup = null;
                    self::$HookManager->processEvent(
                        'SNAPIN_GROUP',
                        array(
                            'Host' => &$this->Host,
                            'Snapin' => &$Snapin,
                            'StorageGroup' => &$StorageGroup,
                        )
                    );
                    self::$HookManager->processEvent(
                        'SNAPIN_NODE',
                        array(
                            'Host' => &$this->Host,
                            'Snapin' => &$Snapin,
                            'StorageNode' => &$StorageNode,
                        )
                    );
                    if (!($StorageGroup instanceof StorageGroup
                        && $StorageGroup->isValid())
                    ) {
                        $StorageGroup = $Snapin->getStorageGroup();
                        if (!$StorageGroup->isValid()) {
                            $info['snapins'] = array(
                                'error' => _('Invalid Storage Group')
                            );
                        }
                        $StorageNode = $StorageGroup->getMasterStorageNode();
                        if (!$StorageNode->isValid()) {
                            $info['snapins'] = array(
                                'error' => _('Invalid Storage Node')
                            );
                        }
                        $path = sprintf(
                            '/%s',
                            trim($StorageNode->get('snapinpath'), '/')
                        );
                        $file = $Snapin->get('file');
                        $filepath = sprintf(
                            '%s/%s',
                            $path,
                            $file
                        );
                        $hash = $Snapin->get('hash');
                        if (!$hash) {
                            $Snapin->set('hash', -1)->save();
                            $ip = $StorageNode->get('ip');
                            $curroot = trim(
                                trim($StorageNode->get('webroot'), '/')
                            );
                            $webroot = sprintf(
                                '/%s',
                                (
                                    strlen($curroot) > 1 ?
                                    sprintf(
                                        '%s/',
                                        $curroot
                                    ) :
                                    ''
                                )
                            );
                            $location = "http://$ip{$webroot}";
                            $url = "{$location}status/getsnapinhash.php";
                            unset($curroot, $webroot, $ip);
                            $response = self::$FOGURLRequests->process(
                                $url,
                                'POST',
                                array(
                                    'filepath' => $filepath
                                )
                            );
                            $response = array_shift($response);
                            $data = explode('|', $response);
                            $hash = (string)array_shift($data);
                            $size = array_shift($data);
                            $Snapin
                                ->set('hash', $hash)
                                ->set('size', $size)
                                ->save();
                        } else {
                            while ($hash === -1) {
                                sleep(10);
                                $hash = $Snapin->get('hash');
                            }
                        }
                        $SnapinTask
                            ->set('checkin', $date)
                            ->set('stateID', $this->getCheckedInState())
                            ->save();
                        if (empty($hash)) {
                            $info['snapins'] = array(
                                'error' => _('No hash available')
                            );
                        }
                        if ($size == 0) {
                            $info['snapins'] = array(
                                'error' => _('No size available')
                            );
                        }
                    }
                    $action = '';
                    if ($Snapin->get('shutdown')) {
                        $action = 'shutdown';
                    } elseif ($Snapin->get('reboot')) {
                        $action = 'reboot';
                    }
                    $info['snapins'] = array(
                        'pack' =>( bool)$Snapin->get('packtype'),
                        'hide' => (bool)$Snapin->get('hide'),
                        'timeout' => $Snapin->get('timeout'),
                        'jobtaskid' => $SnapinTask->get('id'),
                        'jobcreation' => $SnapinJob->get('createdTime'),
                        'name' => $Snapin->get('name'),
                        'args' => $Snapin->get('args'),
                        'action' => $action,
                        'filename' =>$Snapin->get('file'),
                        'runwith' => $Snapin->get('runWith'),
                        'runwithargs' => $Snapin->get('runWithArgs'),
                        'hash' => strtoupper($hash),
                        'size' => $size,
                        'url' => rtrim($location, '/'),
                    );
                    unset($Snapin, $SnapinTask);
                }
                return $info;
            } elseif (isset($_REQUEST['exitcode'])) {
                $tID = $_REQUEST['taskid'];
                if (!(empty($tID) && is_numeric($tID))) {
                    $info['snapins'] = array(
                        'error' => _('Invalid task id sent')
                    );
                }
                $SnapinTask = new SnapinTask($tID);
                if (!($SnapinTask->isValid()
                    && in_array(
                        $SnapinTask->get('stateID'), array(
                            $this->getCompleteState(),
                            $this->getCancelledState()
                        )
                    ))
                ) {
                    $info['snapins'] = array(
                        'error' => _('Invalid Snapin Tasking')
                    );
                }
                $Snapin = $SnapinTask->getSnapin();
                if (!$Snapin->isValid()) {
                    $info['snapins'] = array(
                        'error' => _('Invalid Snapin')
                    );
                }
                $SnapinTask
                    ->set('stateID', $this->getCompleteState())
                    ->set('return', $_REQUEST['exitcode'])
                    ->set('details', $_REQUEST['exitdesc'])
                    ->set('complete', $date)
                    ->save();
                self::$EventManager->notify(
                    'HOST_SNAPINTASK_COMPLETE',
                    array(
                        'Snapin' => &$Snapin,
                        'SnapinTask' => &$SnapinTask,
                        'Host' => &$this->Host
                    )
                );
                $STaskCount = self::getClass('SnapinTaskManager')
                    ->count(
                        array(
                            'jobID' => $SnapinJob->get('id'),
                            'stateID' => array_merge(
                                $this->getQueuedStates(),
                                (array)$this->getProgressState()
                            )
                        )
                    );
                if ($STaskCount < 1) {
                    if ($Task->isValid()) {
                        $Task->set('stateID', $this->getCompleteState())->save();
                    }
                    $SnapinJob->set('stateID', $this->getCompleteState())->save();
                    self::$EventManager->notify(
                        'HOST_SNAPIN_COMPLETE',
                        array(
                            'HostName' => &$HostName
                        )
                    );
                }
            }
        } elseif (basename(self::$scriptname) === 'snapins.file.php') {
            $tID = $_REQUEST['taskid'];
            if (!(empty($tID) && is_numeric($tID))) {
                return array(
                    'error' => _('Invalid task id sent')
                );
            }
            $SnapinTask = new SnapinTask($tID);
            if (!($SnapinTask->isValid()
                && in_array(
                    $SnapinTask->get('stateID'), array(
                        $this->getCompleteState(),
                        $this->getCancelledState()
                    )
                ))
            ) {
                return array(
                    'error' => _('Invalid Snapin Tasking')
                );
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                return array(
                    'error' => _('Invalid Snapin')
                );
            }
            $StorageNode = $StorageGroup = null;
            $StorageNode = $StorageGroup = null;
            self::$HookManager->processEvent(
                'SNAPIN_GROUP',
                array(
                    'Host' => &$this->Host,
                    'Snapin' => &$Snapin,
                    'StorageGroup' => &$StorageGroup,
                )
            );
            self::$HookManager->processEvent(
                'SNAPIN_NODE',
                array(
                    'Host' => &$this->Host,
                    'Snapin' => &$Snapin,
                    'StorageNode' => &$StorageNode,
                )
            );
            if (!($StorageGroup instanceof StorageGroup
                && $StorageGroup->isValid())
            ) {
                $StorageGroup = $Snapin->getStorageGroup();
                if (!$StorageGroup->isValid()) {
                    return array(
                        'error' => _('Invalid Storage Group')
                    );
                }
                $StorageNode = $StorageGroup->getMasterStorageNode();
                if (!$StorageNode->isValid()) {
                    return array(
                        'error' => _('Invalid Storage Node')
                    );
                }
                $path = sprintf(
                    '/%s',
                    trim($StorageNode->get('snapinpath'), '/')
                );
                $file = $Snapin->get('file');
                $filepath = sprintf(
                    '%s/%s',
                    $path,
                    $file
                );
                $host = $StorageNode->get('ip');
                $user = $StorageNode->get('user');
                $pass = urlencode($StorageNode->get('pass'));
                self::$FOGFTP
                    ->set('host', $host)
                    ->set('username', $user)
                    ->set('password', $StorageNode->get('pass'));
                if (!self::$FOGFTP->connect()) {
                    return array('error'=>_('FTP Failed to connect'));
                }
                self::$FOGFTP->close();
                $SnapinFile = sprintf(
                    'ftp://%s:%s@%s%s',
                    $user,
                    $pass,
                    $host,
                    $filepath
                );
                if ($Task->isValid()) {
                    $Task->get('task')
                        ->set('stateID', $this->getProgressState())
                        ->set('checkInTime', $date)
                        ->save();
                }
                $SnapinJob
                    ->set('stateID', $this->getProgressState())
                    ->save();
                $SnapinTask
                    ->set('stateID', $this->getProgressState())
                    ->set('return', -1)
                    ->set('details', _('Pending...'))
                    ->save();
                while (ob_get_level()) {
                    ob_end_clean();
                }
                header("X-Sendfile: $SnapinFile");
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=$file");
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                if (($fh = fopen($SnapinFile, 'rb')) === false) {
                    return array('error' => _('Could not read snapin file'));
                }
                while (feof($fh) === false) {
                    if (($line = fread($fh, 4096)) === false) {
                        break;
                    }
                    echo $line;
                    flush();
                }
                fclose($fh);
                exit;
            }
        }
    }
    public function send()
    {
        $date = $this->formatTime('', 'Y-m-d H:i:s');
        if ($this->Host->get('task')->isValid() && !$this->Host->get('task')->isSnapinTasking()) {
            throw new Exception('#!it');
        }
        if (!$this->Host->get('snapinjob')->isValid()) {
            throw new Exception('#!ns');
        }
        if (self::getClass('SnapinTaskManager')->count(array('jobID'=>$this->Host->get('snapinjob')->get('id'), 'stateID'=>array_merge($this->getQueuedStates(), (array)$this->getProgressState()))) < 1) {
            if ($this->Host->get('task')->isValid()) {
                $this->Host->get('task')->set('stateID', $this->getCompleteState())->save();
            }
            $this->Host->get('snapinjob')->set('stateID', $this->getCompleteState())->save();
            self::$EventManager->notify('HOST_SNAPIN_COMPLETE', array('HostName'=>&$HostName, 'Host'=>&$this->Host));
            throw new Exception('#!ns');
        }
        $this->Host->get('snapinjob')->set('stateID', $this->getCheckedInState())->save();
        if ($this->Host->get('task')->isValid()) {
            $this->Host->get('task')->set('stateID', $this->getCheckedInState())->set('checkInTime', $date)->save();
        }
        if (!isset($_REQUEST['exitcode']) && (!isset($_REQUEST['taskid']) || !is_numeric($_REQUEST['taskid']))) {
            $SnapinTaskID = @min(self::getSubObjectIDs('SnapinTask', array('stateID'=>array_merge($this->getQueuedStates(), (array)$this->getProgressState()), 'jobID'=>$this->Host->get('snapinjob')->get('id'), 'snapinID'=>$this->Host->get('snapins'))));
            $SnapinTask = self::getClass('SnapinTask', $SnapinTaskID);
            if (!$SnapinTask->isValid()) {
                throw new Exception(_('Invalid Snapin Tasking'));
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                throw new Exception(_('Invalid Snapin'));
            }
            $StorageGroup = null;
            self::$HookManager->processEvent('SNAPIN_GROUP', array('Host'=>&$this->Host, 'Snapin'=>&$Snapin, 'StorageGroup'=>&$StorageGroup));
            if (!$StorageGroup || !$StorageGroup->isValid()) {
                $StorageGroup = $Snapin->getStorageGroup();
            }
            if (!$StorageGroup->isValid()) {
                throw new Exception(_('Invalid Storage Group'));
            }
            $StorageNode = null;
            self::$HookManager->processEvent('SNAPIN_NODE', array('Host'=>&$this->Host, 'Snapin'=>&$Snapin, 'StorageNode'=>&$StorageNode));
            if (!$StorageNode || !$StorageNode->isValid()) {
                $StorageNode = $StorageGroup->getMasterStorageNode();
            }
            if (!$StorageNode->isValid()) {
                throw new Exception(_('Invalid Storage Node'));
            }
            $path = sprintf('/%s', trim($StorageNode->get('snapinpath'), '/'));
            $file = $Snapin->get('file');
            $filepath = sprintf('%s/%s', $path, $file);
            if ($this->newService) {
                $ip = $StorageNode->get('ip');
                $curroot = trim(trim($StorageNode->get('webroot'), '/'));
                $webroot = sprintf('/%s', (strlen($curroot) > 1 ? sprintf('%s/', $curroot) : ''));
                $url = "http://$ip{$webroot}status/getsnapinhash.php";
                unset($curroot, $webroot, $ip);
                $response = self::$FOGURLRequests->process($url, 'POST', array('filepath'=>$filepath));
                $data = explode('|', array_shift($response));
                $hash = array_shift($data);
                $size = array_shift($data);
                if ($size < 1) {
                    $SnapinTask
                        ->set('stateID', $this->getCancelledState())
                        ->set('complete', $date)
                        ->save();
                    throw new Exception(_('Failed to find the snapin file'));
                }
            }
            $goodArray = array(
                '#!ok',
                sprintf('JOBTASKID=%d', $SnapinTask->get('id')),
                sprintf('JOBCREATION=%s', $this->Host->get('snapinjob')->get('createdTime')),
                sprintf('SNAPINNAME=%s', $Snapin->get('name')),
                sprintf('SNAPINARGS=%s', $Snapin->get('args')),
                sprintf('SNAPINBOUNCE=%s', $Snapin->get('reboot')),
                sprintf('SNAPINFILENAME=%s', $file),
                sprintf('SNAPINRUNWITH=%s', $Snapin->get('runWith')),
                sprintf('SNAPINRUNWITHARGS=%s', $Snapin->get('runWithArgs')),
            );
            if ($this->newService) {
                $goodArray[] = sprintf('SNAPINHASH=%s', strtoupper($hash));
                $goodArray[] = sprintf('SNAPINSIZE=%s', $size);
            }
            $this->send = implode("\n", $goodArray);
        } elseif (isset($_REQUEST['taskid']) && is_numeric($_REQUEST['taskid']) && !isset($_REQUEST['exitcode'])) {
            $SnapinTask = self::getClass('SnapinTask', $_REQUEST['taskid']);
            if (!$SnapinTask->isValid() || in_array($SnapinTask->get('stateID'), array($this->getCompleteState(), $this->getCancelledState()))) {
                throw new Exception(_('Invalid Snapin Tasking'));
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                throw new Exception(_('Invalid Snapin'));
            }
            $StorageGroup = null;
            self::$HookManager->processEvent('SNAPIN_GROUP', array('Host'=>&$this->Host, 'Snapin'=>&$Snapin, 'StorageGroup'=>&$StorageGroup));
            if (!$StorageGroup || !$StorageGroup->isValid()) {
                $StorageGroup = $Snapin->getStorageGroup();
            }
            if (!$StorageGroup->isValid()) {
                throw new Exception(_('Invalid Storage Group'));
            }
            $StorageNode = null;
            self::$HookManager->processEvent('SNAPIN_NODE', array('Host'=>&$this->Host, 'Snapin'=>&$Snapin, 'StorageNode'=>&$StorageNode));
            if (!$StorageNode || !$StorageNode->isValid()) {
                $StorageNode = $StorageGroup->getMasterStorageNode();
            }
            if (!$StorageNode->isValid()) {
                throw new Exception(_('Invalid Storage Node'));
            }
            $path = sprintf('/%s', trim($StorageNode->get('snapinpath'), '/'));
            $file = $Snapin->get('file');
            $filepath = sprintf('%s/%s', $path, $file);
            $host = $StorageNode->get('ip');
            $user = $StorageNode->get('user');
            $pass = urlencode($StorageNode->get('pass'));
            self::$FOGFTP
                ->set('host', $host)
                ->set('username', $user)
                ->set('password', $StorageNode->get('pass'));
            if (!self::$FOGFTP->connect()) {
                throw new Exception(_('FTP Failed to connect'));
            }
            self::$FOGFTP->close();
            $SnapinFile = sprintf('ftp://%s:%s@%s%s', $user, $pass, $host, $filepath);
            $this->Host->get('snapinjob')->set('stateID', $this->getProgressState())->save();
            if ($this->Host->get('task')->isValid()) {
                $this->Host->get('task')->set('stateID', $this->getProgressState())->set('checkInTime', $date)->save();
            }
            $SnapinTask->set('stateID', $this->getProgressState())->set('return', -1)->set('details', _('Pending...'))->save();
            while (ob_get_level()) {
                ob_end_clean();
            }
            header("X-Sendfile: $SnapinFile");
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=$file");
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            if (($fh = fopen($SnapinFile, 'rb')) === false) {
                return;
            }
            while (feof($fh) === false) {
                if (($line = fread($fh, 4096)) === false) {
                    break;
                }
                echo $line;
                flush();
            }
            fclose($fh);
            exit;
        } elseif (isset($_REQUEST['exitcode'])) {
            $SnapinTask = self::getClass('SnapinTask', $_REQUEST['taskid']);
            if (!$SnapinTask->isValid() || in_array($SnapinTask->get('stateID'), array($this->getCompleteState(), $this->getCancelledState()))) {
                throw new Exception(_('Invalid Snapin Tasking'));
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                throw new Exception(_('Invalid Snapin'));
            }
            $SnapinTask
                ->set('stateID', $this->getCompleteState())
                ->set('return', $_REQUEST['exitcode'])
                ->set('details', $_REQUEST['exitdesc'])
                ->set('complete', $date)
                ->save();
            self::$EventManager->notify('HOST_SNAPINTASK_COMPLETE', array('Snapin'=>&$Snapin, 'SnapinTask'=>&$SnapinTask, 'Host'=>&$this->Host));
            if (self::getClass('SnapinTaskManager')->count(array('jobID'=>$this->Host->get('snapinjob')->get('id'), 'stateID'=>array_merge($this->getQueuedStates(), (array)$this->getProgressState()))) < 1) {
                $this->Host->get('snapinjob')->set('stateID', $this->getCompleteState())->save();
                if ($this->Host->get('task')->isValid()) {
                    $this->Host->get('task')->set('stateID', $this->getCompleteState())->save();
                }
                self::$EventManager->notify('HOST_SNAPIN_COMPLETE', array('HostName'=>&$HostName, 'Host'=>&$this->Host));
            }
        }
    }
}
