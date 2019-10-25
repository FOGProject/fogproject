<?php
/**
 * Handles snapins for the host
 *
 * PHP version 5
 *
 * @category SnapinClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles snapins for the host
 *
 * @category SnapinClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinClient extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'snapinclient';
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        $HostName = self::$Host->get('name');
        $Task = self::$Host->get('task');
        $SnapinJob = self::$Host->get('snapinjob');
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
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    )
                )
            );
        if ($STaskCount < 1) {
            if ($Task->isValid()) {
                $Task->set('stateID', self::getCompleteState())->save();
            }
            $SnapinJob->set('stateID', self::getCompleteState())->save();
            self::$EventManager->notify(
                'HOST_SNAPIN_COMPLETE',
                array(
                    'Host' => &self::$Host,
                    'HostName' => &$HostName
                )
            );
            return array('error' => 'ns');
        }
        if ($Task->isValid()) {
            $Task
                ->set('stateID', self::getCheckedInState())
                ->set('checkInTime', self::niceDate()->format('Y-m-d H:i:s'))
                ->save();
        }
        $SnapinJob->set('stateID', self::getCheckedInState())->save();
        global $sub;
        if ($sub === 'requestClientInfo'
            || basename(self::$scriptname) === 'snapins.checkin.php'
        ) {
            if (!isset($_REQUEST['exitcode'])) {
                $snapinIDs = self::getSubObjectIDs(
                    'SnapinTask',
                    array(
                        'stateID' => self::fastmerge(
                            self::getQueuedStates(),
                            (array)self::getProgressState()
                        ),
                        'jobID' => $SnapinJob->get('id'),
                    ),
                    'snapinID'
                );
                $snapinIDs = self::getSubObjectIDs(
                    'Snapin',
                    array('id' => $snapinIDs)
                );
                if (count($snapinIDs) < 1) {
                    $SnapinJob
                        ->set('stateID', self::getCancelledState())
                        ->save();
                    return array(
                        'error' => _('No valid tasks found')
                    );
                }
                $info = array();
                $info['snapins'] = array();
                foreach ((array)self::getClass('SnapinManager')
                    ->find(
                        array('id' => $snapinIDs)
                    ) as &$Snapin
                ) {
                    $snapinTaskID = self::getSubObjectIDs(
                        'SnapinTask',
                        array(
                            'snapinID' => $Snapin->get('id'),
                            'jobID' => $SnapinJob->get('id'),
                            'stateID' => self::fastmerge(
                                self::getQueuedStates(),
                                (array)self::getProgressState()
                            )
                        )
                    );
                    $snapinTaskID = array_shift($snapinTaskID);
                    $SnapinTask = new SnapinTask($snapinTaskID);
                    if (!$SnapinTask->isValid()) {
                        continue;
                    }
                    $StorageNode = $StorageGroup = null;
                    self::$HookManager->processEvent(
                        'SNAPIN_GROUP',
                        array(
                            'Host' => &self::$Host,
                            'Snapin' => &$Snapin,
                            'StorageGroup' => &$StorageGroup,
                        )
                    );
                    self::$HookManager->processEvent(
                        'SNAPIN_NODE',
                        array(
                            'Host' => &self::$Host,
                            'Snapin' => &$Snapin,
                            'StorageNode' => &$StorageNode,
                        )
                    );
                    if (!($StorageGroup instanceof StorageGroup
                        && $StorageGroup->isValid())
                    ) {
                        $StorageGroup = $Snapin->getStorageGroup();
                        if (!$StorageGroup->isValid()) {
                            continue;
                        }
                    }
                    if (!($StorageNode instanceof StorageNode
                        && $StorageNode->isValid())
                    ) {
                        $StorageNode = $StorageGroup->getMasterStorageNode();
                        if (!$StorageNode->isValid()) {
                            continue;
                        }
                    }
                    $location = sprintf(
                        'http://%s/%s',
                        $StorageNode->get('ip'),
                        $StorageNode->get('webroot')
                    );
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
                    $SnapinTask
                        ->set('checkin', self::niceDate()->format('Y-m-d H:i:s'))
                        ->set('stateID', self::getCheckedInState())
                        ->save();
                    $action = '';
                    if ($Snapin->get('shutdown')) {
                        $action = 'shutdown';
                    } elseif ($Snapin->get('reboot')) {
                        $action = 'reboot';
                    }
                    $info['snapins'][] = array(
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
                $this->_closeout($Task, $SnapinJob, $date, $HostName);
            }
        } elseif (basename(self::$scriptname) === 'snapins.file.php') {
            $this->_downloadfile($Task, $SnapinJob, $date, $HostName);
        }
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $HostName = self::$Host->get('name');
        $Task = self::$Host->get('task');
        $SnapinJob = self::$Host->get('snapinjob');
        if ($Task->isValid() && !$Task->isSnapinTasking()) {
            throw new Exception('#!it');
        }
        if (!$SnapinJob->isValid()) {
            throw new Exception('#!ns');
        }
        $STaskCount = self::getClass('SnapinTaskManager')
            ->count(
                array(
                    'jobID' => $SnapinJob->get('id'),
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    )
                )
            );
        if ($STaskCount < 1) {
            if ($Task->isValid()) {
                $Task->set('stateID', self::getCompleteState())->save();
            }
            $SnapinJob->set('stateID', self::getCompleteState())->save();
            self::$EventManager->notify(
                'HOST_SNAPIN_COMPLETE',
                array(
                    'Host' => &self::$Host,
                    'HostName' => &$HostName
                )
            );
            throw new Exception('#!ns');
        }
        if ($Task->isValid()) {
            $Task
                ->set('stateID', self::getCheckedInState())
                ->set('checkInTime', self::niceDate()->format('Y-m-d H:i:s'))
                ->save();
        }
        $SnapinJob->set('stateID', self::getCheckedInState())->save();
        if (basename(self::$scriptname) === 'snapins.checkin.php') {
            if (!isset($_REQUEST['exitcode'])) {
                $snapinIDs = self::getSubObjectIDs(
                    'SnapinTask',
                    array(
                        'stateID' => self::fastmerge(
                            self::getQueuedStates(),
                            (array)self::getProgressState()
                        ),
                        'jobID' => $SnapinJob->get('id'),
                    ),
                    'snapinID'
                );
                $snapinIDs = self::getSubObjectIDs(
                    'Snapin',
                    array('id' => $snapinIDs)
                );
                if (count($snapinIDs) < 1) {
                    $SnapinJob
                        ->set('stateID', self::getCancelledState())
                        ->save();
                    throw new Exception(
                        sprintf(
                            '%s: %s',
                            '#!er',
                            _('No valid tasks found')
                        )
                    );
                }
                $Snapins = self::getClass('SnapinManager')
                    ->find(
                        array('id' => $snapinIDs)
                    );
                $Snapin = array_shift($Snapins);
                if (!($Snapin instanceof Snapin && $Snapin->isValid())) {
                    $SnapinJob
                        ->set('stateID', self::getCancelledState())
                        ->save();
                    throw new Exception(
                        sprintf(
                            '%s: %s',
                            '#!er',
                            _('Snapin is invalid')
                        )
                    );
                }
                $snapinTaskID = self::getSubObjectIDs(
                    'SnapinTask',
                    array(
                        'snapinID' => $Snapin->get('id'),
                        'jobID' => $SnapinJob->get('id'),
                        'stateID' => self::fastmerge(
                            self::getQueuedStates(),
                            (array)self::getProgressState()
                        )
                    )
                );
                $snapinTaskID = @max($snapinTaskID);
                $SnapinTask = new SnapinTask($snapinTaskID);
                if (!$SnapinTask->isValid()) {
                    throw new Exception(
                        sprintf(
                            '%s: %s',
                            '#!er',
                            _('Snapin Task is invalid')
                        )
                    );
                }
                $StorageNode = $StorageGroup = null;
                self::$HookManager->processEvent(
                    'SNAPIN_GROUP',
                    array(
                        'Host' => &self::$Host,
                        'Snapin' => &$Snapin,
                        'StorageGroup' => &$StorageGroup,
                    )
                );
                self::$HookManager->processEvent(
                    'SNAPIN_NODE',
                    array(
                        'Host' => &self::$Host,
                        'Snapin' => &$Snapin,
                        'StorageNode' => &$StorageNode,
                    )
                );
                $file = $Snapin->get('file');
                $goodArray = array(
                    '#!ok',
                    sprintf(
                        'JOBTASKID=%d',
                        $SnapinTask->get('id')
                    ),
                    sprintf(
                        'JOBCREATION=%s',
                        $SnapinJob->get('createdTime')
                    ),
                    sprintf(
                        'SNAPINNAME=%s',
                        $Snapin->get('name')
                    ),
                    sprintf(
                        'SNAPINARGS=%s',
                        $Snapin->get('args')
                    ),
                    sprintf(
                        'SNAPINBOUNCE=%s',
                        $Snapin->get('reboot')
                    ),
                    sprintf(
                        'SNAPINFILENAME=%s',
                        $file
                    ),
                    sprintf(
                        'SNAPINRUNWITH=%s',
                        $Snapin->get('runWith')
                    ),
                    sprintf(
                        'SNAPINRUNWITHARGS=%s',
                        $Snapin->get('runWithArgs')
                    )
                );
                $this->send = implode("\n", $goodArray);
            } elseif (isset($_REQUEST['exitcode'])) {
                $this->_closeout($Task, $SnapinJob, $date, $HostName);
            }
        } elseif (basename(self::$scriptname) === 'snapins.file.php') {
            $this->_downloadfile($Task, $SnapinJob, $date, $HostName);
        }
    }
    /**
     * Closes out the snapin tasks
     *
     * @param object $Task      the task object
     * @param object $SnapinJob the snapin job object
     * @param string $date      the current date
     * @param string $HostName  the hostname
     *
     * @return void
     */
    private function _closeout($Task, $SnapinJob, $date, $HostName)
    {
        $tID = $_REQUEST['taskid'];
        if (!(empty($td) && is_numeric($tID))) {
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid task id sent')
                )
            );
        }
        $SnapinTask = new SnapinTask($tID);
        if (!($SnapinTask->isValid()
            && !in_array(
                $SnapinTask->get('stateID'),
                array(
                    self::getCompleteState(),
                    self::getCancelledState()
                )
            ))
        ) {
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin Tasking')
                )
            );
        }
        $Snapin = $SnapinTask->getSnapin();
        if (!$Snapin->isValid()) {
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin')
                )
            );
        }
        $SnapinTask
            ->set('stateID', self::getCompleteState())
            ->set('return', $_REQUEST['exitcode'])
            ->set('details', $_REQUEST['exitdesc'])
            ->set('complete', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
        self::$EventManager->notify(
            'HOST_SNAPINTASK_COMPLETE',
            array(
                'Snapin' => &$Snapin,
                'SnapinTask' => &$SnapinTask,
                'Host' => &self::$Host,
                'HostName' => &$HostName
            )
        );
        $STaskCount = self::getClass('SnapinTaskManager')
            ->count(
                array(
                    'jobID' => $SnapinJob->get('id'),
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    )
                )
            );
        if ($STaskCount < 1) {
            if ($Task->isValid()) {
                $Task->set('stateID', self::getCompleteState())->save();
            }
            $SnapinJob->set('stateID', self::getCompleteState())->save();
            self::$EventManager->notify(
                'HOST_SNAPIN_COMPLETE',
                array(
                    'HostName' => &$HostName,
                    'Host' => &self::$Host
                )
            );
        }
    }
    /**
     * Downloads the client file
     *
     * @param object $Task      the task object
     * @param object $SnapinJob the snapin job object
     * @param string $date      the current date
     * @param string $HostName  the hostname
     *
     * @return void
     */
    private function _downloadfile($Task, $SnapinJob, $date, $HostName)
    {
        $tID = $_REQUEST['taskid'];
        if (!(!empty($tID) && is_numeric($tID))) {
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid task id')
                )
            );
        }
        $SnapinTask = new SnapinTask($tID);
        if (!$SnapinTask->isValid()) {
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Invalid Snapin Tasking object')
                )
            );
        }
        $Snapin = $SnapinTask->getSnapin();
        if (!$Snapin->isValid()) {
            throw new Exception(_('Invalid Snapin'));
        }
        $StorageGroup = $StorageNode = null;
        self::$HookManager->processEvent(
            'SNAPIN_GROUP',
            array(
                'Host' => &self::$Host,
                'Snapin' => &$Snapin,
                'StorageGroup' => &$StorageGroup
            )
        );
        self::$HookManager->processEvent(
            'SNAPIN_NODE',
            array(
                'Host' => &self::$Host,
                'Snapin' => &$Snapin,
                'StorageNode' => &$StorageNode
            )
        );
        if (!($StorageGroup instanceof StorageGroup
            && $StorageGroup->isValid())
        ) {
            $StorageGroup = $Snapin->getStorageGroup();
            if (!$StorageGroup->isValid()) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        '#!er',
                        _('Invalid Storage Group')
                    )
                );
            }
        }
        if (!($StorageNode instanceof StorageNode
            && $StorageNode->isValid())
        ) {
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!($StorageNode instanceof StorageNode
                && $StorageNode->isValid())
            ) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        '#!er',
                        _('Invalid Storage Node')
                    )
                );
            }
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
        $pass = $StorageNode->get('pass');
        self::$FOGFTP
            ->set('host', $host)
            ->set('username', $user)
            ->set('password', $pass);
        if (!self::$FOGFTP->connect()) {
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Cannot connect to ftp server')
                )
            );
        }
        self::$FOGFTP->close();
        $SnapinFile = sprintf(
            'ftp://%s:%s@%s%s',
            $user,
            urlencode($pass),
            $host,
            $filepath
        );
        if ($Task->isValid()) {
            $Task
                ->set('stateID', self::getProgressState())
                ->set('checkInTime', self::niceDate()->format('Y-m-d H:i:s'))
                ->save();
        }
        $SnapinJob
            ->set('stateID', self::getProgressState())
            ->save();
        $SnapinTask
            ->set('stateID', self::getProgressState())
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
            throw new Exception(
                sprintf(
                    '%s: %s',
                    '#!er',
                    _('Could not read snapin file')
                )
            );
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
