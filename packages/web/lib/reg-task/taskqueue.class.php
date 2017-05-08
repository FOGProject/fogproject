<?php
/**
 * The queue handling system for FOG's checkin/checkout processes.
 *
 * PHP version 5
 *
 * @category TaskQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The queue handling system for FOG's checkin/checkout processes.
 *
 * @category TaskQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskQueue extends TaskingElement
{
    /**
     * Handles task checkin
     *
     * @throws Exception
     * @return void
     */
    public function checkIn()
    {
        try {
            $this->Task
                ->set('stateID', self::getCheckedInState())
                ->set('checkinTime', self::formatTime('now', 'Y-m-d H:i:s'))
                ->save();
            if (!$this->Task->save()) {
                throw new Exception(_('Failed to update task'));
            }
            if ($this->imagingTask) {
                $this->Task
                    ->getImage()->set('size', '')
                    ->save();
                if ($this->Task->isMulticast()) {
                    $msID = @min(
                        self::getSubObjectIDs(
                            'MulticastSessionAssociation',
                            array(
                                'taskID' => $this->Task->get('id')
                            ),
                            'msID'
                        )
                    );
                    $MulticastSession = self::getClass(
                        'MulticastSession',
                        $msID
                    );
                    if (!$MulticastSession->isValid()) {
                        throw new Exception(_('Invalid Multicast Session'));
                    }
                    if ($MulticastSession->get('clients') < 0) {
                        $clients = 1;
                    } else {
                        $clients = $MulticastSession->get('clients') + 1;
                    }
                    $MulticastSession
                        ->set('clients', $clients)
                        ->set('stateID', self::getProgressState());
                    if (!$MulticastSession->save()) {
                        throw new Exception(_('Failed to update Session'));
                    }
                    if ($this->Host->isValid()) {
                        $this->Host
                            ->set(
                                'imageID',
                                $MulticastSession->get('image')
                            );
                    }
                } elseif ($this->Task->isForced()) {
                    self::$HookManager->processEvent(
                        'TASK_GROUP',
                        array(
                            'StorageGroup' => &$this->StorageGroup,
                            'Host' => &$this->Host
                        )
                    );
                    $this->StorageNode = null;
                    self::$HookManager->processEvent(
                        'TASK_NODE',
                        array(
                            'StorageNode' => &$this->StorageNode,
                            'Host' => &$this->Host
                        )
                    );
                    $method = 'getOptimalStorageNode';
                    if ($this->Task->isCapture()
                        || $this->Task->isMulticast()
                    ) {
                        $method = 'getMasterStorageNode';
                    }
                    if (!$this->StorageNode || !$this->StorageNode->isValid()) {
                        $this->StorageNode = $this->Image
                            ->getStorageGroup()
                            ->{$method}();
                    }
                } else {
                    $this->StorageNode = self::nodeFail(
                        self::getClass(
                            'StorageNode',
                            $this->Task->get('storagenodeID')
                        ),
                        $this->Host->get('id')
                    );
                    $nodeTest = $this->StorageNode instanceof StorageNode &&
                        $this->StorageNode->isValid();

                    if (!$nodeTest) {
                        $msg = sprintf(
                            '%s %s. %s %s.',
                            _('The node trying to be used is currently'),
                            _('unavailable'),
                            _('On reboot we will try to find a new node'),
                            _('automatically')
                        );
                        throw new Exception($msg);
                    }
                    $totalSlots = $this->StorageNode->get('maxClients');
                    $usedSlots = $this->StorageNode->getUsedSlotCount();
                    $inFront = $this->Task->getInFrontOfHostCount();
                    $groupOpenSlots = $totalSlots - $usedSlots;
                    if ($groupOpenSlots < 1) {
                        $msg = sprintf(
                            '%s, %s %d %s.',
                            _('No open slots'),
                            _('There are'),
                            $inFront,
                            _('before me')
                        );
                        throw new Exception($msg);
                    }
                    if ($groupOpenSlots <= $inFront) {
                        $msg = sprintf(
                            '%s, %s %d %s.',
                            _('There are open slots'),
                            _('but'),
                            $inFront,
                            _('before me on this node')
                        );
                        throw new Exception($msg);
                    }
                }
                $this->Task
                    ->set(
                        'storagenodeID',
                        $this->StorageNode->get('id')
                    );
                if (!$this->imageLog(true)) {
                    throw new Exception(_('Failed to update/create image log'));
                }
            }
            $this->Task
                ->set('stateID', self::getProgressState())
                ->set('checkInTime', self::formatTime('now', 'Y-m-d H:i:s'));
            if (!$this->Task->save()) {
                throw new Exception(_('Failed to update Task'));
            }
            if (!$this->taskLog()) {
                throw new Exception(_('Failed to update/create task log'));
            }
            self::$EventManager->notify(
                'HOST_CHECKIN',
                array(
                    'Host' => &$this->Host
                )
            );
            echo '##@GO';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    /**
     * Handles the email sending.
     *
     * @return void
     */
    private function _email()
    {
        list(
            $emailAction,
            $emailAddress,
            $emailBinary,
            $fromEmail
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_EMAIL_ACTION',
                    'FOG_EMAIL_ADDRESS',
                    'FOG_EMAIL_BINARY',
                    'FOG_FROM_EMAIL'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            false
        );
        if (!$emailAction || !$emailAddress) {
            return;
        }
        if (!$this->Host->get('inventory')->isValid()) {
            return;
        }
        $SnapinJob = $this->Host->get('snapinjob');
        $SnapinTasks = self::getSubObjectIDs(
            'SnapinTask',
            array(
                'stateID' => self::getQueuedStates(),
                'jobID' => $SnapinJob->get('id')
            ),
            'snapinID'
        );
        $SnapinNames = array();
        if ($SnapinJob->isValid()) {
            $SnapinNames = self::getSubObjectIDs(
                'Snapin',
                array(
                    'id' => $SnapinTasks,
                ),
                'name'
            );
        }
        if (!$emailBinary) {
            $emailBinary = '/usr/sbin/sendmail -t -f noreply@fogserver.com -i';
        }
        $reg = '#\$\{server-name\}#';
        $nodeName = 'fogserver';
        if ($this->StorageNode->isValid()) {
            $nodeName = $this->StorageNode->get('name');
        }
        $emailBinary = preg_replace(
            $reg,
            $nodeName,
            $emailBinary
        );
        if (!$fromEmail) {
            $fromEmail = 'noreply@fogserver.com';
        }
        $fromEmail = preg_replace(
            $reg,
            $nodeName,
            $fromEmail
        );
        $headers = sprintf(
            "From: %s\r\nX-Mailer: PHP/%s",
            $fromEmail,
            phpversion()
        );
        $engineer = ucwords(
            $this->Task->get('createdBy')
        );
        $primaryUser = ucwords(
            $this->Host->get('inventory')->get('primaryUser')
        );
        $Inventory = $this->Host->get('inventory');
        $mac = $this->Host->get('mac')->__toString();
        $ImageName = $this->Task->getImage()->get('name');
        $Snapins = implode(',', (array)$SnapinNames);
        $email = array(
            sprintf("%s:-\n", _('Machine Details')) => '',
            sprintf("\n%s: ", _('Host Name')) => $this->Host->get('name'),
            sprintf("\n%s: ", _('Computer Model')) => $Inventory->get('sysproduct'),
            sprintf("\n%s: ", _('Serial Number')) => $Inventory->get('sysserial'),
            sprintf("\n%s: ", _('MAC Address')) => $mac,
            "\n" => '',
            sprintf("\n%s: ", _('Image Used')) => $ImageName,
            sprintf("\n%s: ", _('Snapin Used')) => $Snapins,
            "\n" => '',
            sprintf("\n%s: ", _('Imaged By')) => $engineer,
            sprintf("\n%s: ", _('Imaged For')) => $primaryUser
        );
        self::$HookManager->processEvent(
            'EMAIL_ITEMS',
            array(
                'email' => &$email,
                'Host' => &$this->Host
            )
        );
        ob_start();
        foreach ((array)$email as $key => &$val) {
            printf('%s%s', $key, $val);
            unset($key, $val);
        }
        $emailMe = ob_get_clean();
        $stat = sprintf(
            '%s - %s',
            $this->Host->get('name'),
            _('Image Task Completed')
        );
        if ($Inventory->get('other1')) {
            mail(
                $emailAddress,
                sprintf(
                    'ISSUE=%s PROJ=1',
                    $Inventory->get('other1')
                ),
                $emailMe,
                $headers
            );
            $emailMe .= sprintf(
                "\n%s (%s): %s",
                _('Imaged For'),
                _('Call'),
                $Inventory->get('other1')
            );
            $Inventory->set('other1', '')->save();
        }
        mail(
            $emailAddress,
            $stat,
            $emailMe,
            $headers
        );
    }
    /**
     * Function moves the images from dev into root when upload
     * tasking is finished.
     *
     * @throws Exception
     * @return void
     */
    private function _moveUpload()
    {
        if (!$this->Task->isCapture()) {
            return;
        }
        if (!(isset($_REQUEST['mac'])
            && is_string($_REQUEST['mac']))
        ) {
            return;
        }
        $macftp = strtolower(
            str_replace(
                array(
                    ':',
                    '-',
                    '.'
                ),
                '',
                basename($_REQUEST['mac'])
            )
        );
        $src = sprintf(
            '%s/dev/%s',
            $this->StorageNode->get('ftppath'),
            $macftp
        );
        $dest = sprintf(
            '%s/%s',
            $this->StorageNode->get('ftppath'),
            $this->Image->get('path')
        );
        self::$FOGFTP
            ->set('host', $this->StorageNode->get('ip'))
            ->set('username', $this->StorageNode->get('user'))
            ->set('password', $this->StorageNode->get('pass'))
            ->connect()
            ->delete($dest)
            ->rename($src, $dest)
            ->chmod(0777, $dest)
            ->close();
        if ($this->Image->get('format') == 1) {
            $this->Image
                ->set('format', 0)
                ->set('srvsize', self::getFilesize($dest));
        }
        $this->Image
            ->set(
                'deployed',
                self::niceDate()->format('Y-m-d H:i:s')
            )->save();
    }
    /**
     * Handles task checkout
     *
     * @throws Exception
     * @return void
     */
    public function checkout()
    {
        if ($this->Task->isSnapinTasking()) {
            die('##');
        }
        try {
            if ($this->Task->isMulticast()) {
                $MCTask = self::getClass('MulticastSessionAssociation')
                    ->set(
                        'taskID',
                        $this->Task->get('id')
                    )->load('taskID');
                $MulticastSession = $MCTask->getMulticastSession();
                if ($MulticastSession->get('clients') < 0) {
                    $clients = 1;
                } else {
                    $clients = $MulticastSession->get('clients') - 1;
                }
                $MulticastSession
                    ->set('clients', $clients)
                    ->save();
            }
            $this->Host
                ->set('pub_key', '')
                ->set('sec_tok', '');
            if ($this->Task->isDeploy()) {
                $this->Host
                    ->set('deployed', self::niceDate()->format('Y-m-d H:i:s'));
                $this->_email();
            } elseif ($this->Task->isCapture()) {
                $this->_moveUpload();
            }
            $this->Task
                ->set('pct', 100)
                ->set('percent', 100)
                ->set('stateID', self::getCompleteState());
            if (!$this->Host->save()) {
                throw new Exception(_('Failed to update Host'));
            }
            if (!$this->Task->save()) {
                throw new Exception(_('Failed to update Task'));
            }
            self::$HookManager
                ->processEvent(
                    'HOST_TASKING_COMPLETE',
                    array(
                        'Host' => &$this->Host,
                        'Task' => &$this->Task
                    )
                );
            if (!$this->taskLog()) {
                throw new Exception(_('Failed to update task log'));
            }
            if ($this->imagingTask) {
                if (!$this->imageLog(false)) {
                    throw new Exception(_('Failed to update imaging log'));
                }
            }
            self::$EventManager->notify(
                'HOST_IMAGE_COMPLETE',
                array(
                    'HostName' => $this->Host->get('name')
                )
            );
            echo '##';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
