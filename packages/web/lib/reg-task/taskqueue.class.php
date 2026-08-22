<?php
/**
 * The queue handling system for FOG's checkin/checkout processes.
 *
 * PHP version 7.4+
 *
 * @category TaskQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
     * Idempotent completion ack.
     *
     * A client may post its completion (Post_Stage2/Post_Stage3) after the
     * server has already moved the task to Complete. This happens most often
     * with multicast: MulticastManager completes the session the moment
     * udp-sender exits, while the clients are still flushing disk and running
     * post-image fixups (e.g. NTFS hostname change). By the time a client
     * checks out there is no longer an active task, so the normal flow throws
     * "No Active Task found" and the client loops on that error even though
     * imaging actually succeeded.
     *
     * If the host's most recent matching-type task is already in the Complete
     * state and was checked in recently, reply with '##' (the success token
     * the client waits for) and stop. Genuinely cancelled tasks move to the
     * Cancelled state - not Complete - so real cancellations still fall
     * through and error exactly as before.
     *
     * Must be static: it is called before TaskQueue is constructed, because
     * the constructor (TaskingElement) echoes the "No Active Task" error and
     * exits before checkout() can ever run.
     *
     * @return void
     */
    public static function ackIfAlreadyComplete()
    {
        $type = trim((string)filter_input(INPUT_POST, 'type'));
        if (!$type) {
            $type = trim((string)filter_input(INPUT_GET, 'type'));
        }
        if ($type !== 'up' && $type !== 'down') {
            return;
        }
        try {
            self::getHostItem(false);
        } catch (\Exception $e) {
            return;
        }
        if (!self::$Host->isValid()) {
            return;
        }
        // Only pre-acknowledge when the host has NO live in-progress task.
        // That is exactly the multicast slow-client window this guard exists
        // for (the server already completed the task). If an active task is
        // present (e.g. a same-day re-image of the host), bail and let the
        // real checkout() run, or it would be skipped and the image never
        // moved out of dev/.
        if (self::$Host->get('task')->isValid()) {
            return;
        }
        $typeIDs = ($type === 'up')
            ? TaskType::CAPTURETASKS
            : TaskType::DEPLOYTASKS;
        $taskIDs = Route::getIds(
            'task',
            [
                'hostID' => self::$Host->get('id'),
                'typeID' => $typeIDs,
                'stateID' => self::getCompleteState(),
            ]
        );
        // Latest completed task wins; ids increase over time.
        $taskID = (int)@max($taskIDs ?: [0]);
        if ($taskID < 1) {
            return;
        }
        $Task = self::getClass('Task', $taskID);
        if (!$Task->isValid()) {
            return;
        }
        $checkin = trim((string)$Task->get('checkInTime'));
        if ($checkin === '' || $checkin === '0000-00-00 00:00:00') {
            return;
        }
        try {
            $elapsed = self::niceDate()->getTimestamp()
                - self::niceDate($checkin)->getTimestamp();
        } catch (\Exception $e) {
            return;
        }
        // Bound to one day so a stale prior task of the same type can't be
        // mistaken for the one that just completed.
        if ($elapsed < 0 || $elapsed > 86400) {
            return;
        }
        echo '##';
        exit;
    }
    /**
     * Handles task checkin
     *
     * @throws Exception
     * @return void
     */
    public function checkIn()
    {
        try {
            $this->Task->taskCheckIn();
            if ($this->imagingTask) {
                if ($this->Task->isCapture()) {
                    $this->Task->getImage()->set('size', '')->save();
                }
                $method = ($this->Task->isCapture() || $this->Task->isMulticast())
                    ? 'getMasterStorageNode'
                    : 'getOptimalStorageNode';
                if ($this->Task->isMulticast()) {
                    $msID = self::minId(Route::getIds('multicastsessionassociation', ['taskID' => $this->Task->get('id')], 'msID'));
                    $MulticastSession = self::getClass(
                        'MulticastSession',
                        $msID
                    );
                    if (!$MulticastSession->isValid()) {
                        throw new \Exception(_('Invalid Multicast Session'));
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
                        throw new \Exception(_('Failed to update Session'));
                    }
                    if (!self::$Host->isValid()) {
                        throw new \Exception('##@GO');
                    }
                    self::$Host->set(
                        'imageID',
                        $MulticastSession->get('image')
                    );
                } elseif ($this->Task->isForced()) {
                    self::$HookManager->processEvent(
                        'TASK_GROUP',
                        [
                            'StorageGroup' => &$this->StorageGroup,
                            'Host' => &self::$Host
                        ]
                    );
                    $this->StorageNode = null;
                    self::$HookManager->processEvent(
                        'TASK_NODE',
                        [
                            'StorageNode' => &$this->StorageNode,
                            'Host' => &self::$Host
                        ]
                    );
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
                        self::$Host->get('id')
                    );
                    $nodeOk = $this->StorageNode instanceof StorageNode &&
                        $this->StorageNode->isValid();

                    if (!$nodeOk) {
                        $msg = sprintf(
                            '%s %s. %s %s.',
                            _('The node trying to be used is currently'),
                            _('unavailable'),
                            _('On reboot we will try to find a new node'),
                            _('automatically')
                        );
                        throw new \Exception($msg);
                    }
                    $totalSlots = $this->StorageNode->get('maxClients');
                    $usedSlots = $this->StorageNode->getUsedSlotCount();
                    $inFront = $this->Task->getInFrontOfHostCount();
                    $groupOpenSlots = $totalSlots - $usedSlots;

                    $MyLineTime = self::niceDate($this->Task->get('scheduledStartTime'));
                    // Fallback to now if placeholder hasn't been established yet.
                    if (!self::validDate($MyLineTime)) {
                        $MyLineTime = self::niceDate();
                    }
                    $msgFormat = '%s, %s %d %s. %s %s.';
                    if ($groupOpenSlots < 1) {
                        $msg = sprintf(
                            $msgFormat,
                            _('No open slots'),
                            _('There are'),
                            $inFront,
                            _('before me on this node'),
                            _('Got in line at'),
                            $MyLineTime->format('Y-m-d H:i:s')
                        );
                        throw new \Exception($msg);
                    }
                    
                    if ($groupOpenSlots <= $inFront) {
                        $msg = sprintf(
                            $msgFormat,
                            _('There are open slots'),
                            _('but there are'),
                            $inFront,
                            _('before me on this node'),
                            _('Got in line at'),
                            $MyLineTime->format('Y-m-d H:i:s')
                        );
                        throw new \Exception($msg);
                    }
                }
                $this->Task
                    ->set('storagenodeID', $this->StorageNode->get('id'));
            }
            // Here rather than at the top of the method: a host waiting in
            // the queue calls checkIn() on every poll and throws above, so a
            // header written on entry would record one row per poll for the
            // whole wait. This is the point the task actually starts.
            Audit::record([
                'type' => 'task.start',
                'authSource' => Audit::SOURCE_ANONYMOUS,
                'subjectType' => 'task',
                'subjectID' => (int)$this->Task->get('id'),
                'subjectLabel' => (string)self::$Host->get('name'),
                'renderable' => 1
            ]);
            $this->Task
                ->set('stateID', self::getProgressState())
                ->set('checkInTime', self::niceDate()->format('Y-m-d H:i:s'));
            if (!$this->Task->save()) {
                Audit::markOutcome(Audit::FAILED);
                throw new \Exception(_('Failed to update Task'));
            }
            if (!$this->taskLog()) {
                throw new \Exception(_('Failed to update/create task log'));
            }
            self::$EventManager->notify(
                'HOST_CHECKIN',
                ['Host' => &self::$Host]
            );
            echo '##@GO';
        } catch (\Exception $e) {
            echo \Initiator::e($e->getMessage());
        }
    }
    /**
     * Handles the email sending.
     *
     * @return void
     */
    private function _email()
    {
        $keys = [
            'FOG_EMAIL_ACTION',
            'FOG_EMAIL_ADDRESS',
            'FOG_EMAIL_BINARY',
            'FOG_FROM_EMAIL'
        ];
        list(
            $emailAction,
            $emailAddress,
            $emailBinary,
            $fromEmail
        ) = self::getSetting($keys);
        if (!$emailAction || !$emailAddress) {
            return;
        }
        if (!self::$Host->get('inventory')->isValid()) {
            return;
        }
        $SnapinJob = self::$Host->get('snapinjob');
        $find = [
            'stateID' => self::getQueuedStates(),
            'jobID' => $SnapinJob->get('id')
        ];
        $SnapinTasks = Route::getIds(
            'snapintask',
            $find,
            'snapinID'
        );
        $SnapinNames = [];
        if ($SnapinJob->isValid()) {
            $find = ['id' => $SnapinTasks];
            $SnapinNames = Route::getIds(
                'snapin',
                $find,
                'name'
            );
        }
        if (!$emailBinary) {
            $emailBinary = '/usr/sbin/sendmail -t -f noreply@fogserver.com -i';
        }
        $reg = '#\$\{server\-name\}#';
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
            self::$Host->get('inventory')->get('primaryUser')
        );
        $replaceUser = '#\$\{user-name\}#';
        $emailAddress = preg_replace(
            $replaceUser,
            lcfirst($engineer),
            $emailAddress
        );
        $emailAddress = preg_replace(
            $reg,
            $nodeName,
            $emailAddress
        );
        $Inventory = self::$Host->get('inventory');
        $mac = self::$Host->get('mac')->__toString();
        $ImageName = $this->Task->getImage()->get('name');
        $ImageStartTime = self::niceDate($this->Task->get('checkInTime'))->format('Y-m-d H:i:s');
        $ImageEndTime = self::niceDate()->format('Y-m-d H:i:s');
        $duration = self::diff($ImageStartTime, $ImageEndTime);
        $Snapins = implode(',', (array)$SnapinNames);
        $email = [
            sprintf("%s:-\n", _('Machine Details')) => '',
            sprintf("\n%s: ", _('Host Name')) => self::$Host->get('name'),
            sprintf("\n%s: ", _('Computer Model')) => $Inventory->get('sysproduct'),
            sprintf("\n%s: ", _('Serial Number')) => $Inventory->get('sysserial'),
            sprintf("\n%s: ", _('MAC Address')) => $mac,
            "\n" => '',
            sprintf("\n%s: ", _('Image Used')) => $ImageName,
            sprintf("\n%s: ", _('Snapin Used')) => $Snapins,
            "\n" => '',
            sprintf("\n%s: ", _('Imaged By')) => $engineer,
            sprintf("\n%s: ", _('Imaged For')) => $primaryUser,
            sprintf("\n%s: ", _('Imaging Started')) => $ImageStartTime,
            sprintf("\n%s: ", _('Imaging Completed')) => $ImageEndTime,
            sprintf("\n%s: ", _('Imaging Duration')) => $duration
        ];
        self::$HookManager->processEvent(
            'EMAIL_ITEMS',
            [
                'email' => &$email,
                'Host' => &self::$Host
            ]
        );
        ob_start();
        foreach ((array)$email as $key => &$val) {
            printf('%s%s', $key, $val);
            unset($key, $val);
        }
        $emailMe = ob_get_clean();
        $stat = sprintf(
            '%s - %s',
            self::$Host->get('name'),
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
            //$Inventory->set('other1', '')->save();
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
                [
                    ':',
                    '-',
                    '.'
                ],
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
        self::$FOGSSH->username = $this->StorageNode->get('user');
        self::$FOGSSH->password = $this->StorageNode->get('pass');
        self::$FOGSSH->host = $this->StorageNode->get('ip');
        if (!self::$FOGSSH->connect()) {
            throw new \Exception(_('Unable to connect to ssh during move upload'));
        }
        // Move any existing image aside instead of deleting it up front. If the
        // rename below fails we can put it back, rather than having destroyed
        // the previous image for a capture that never landed.
        $backup = sprintf('%s.movetmp', $dest);
        $moved = false;
        if (self::$FOGSSH->exists($dest)) {
            self::$FOGSSH->delete($backup);
            if (!self::$FOGSSH->sftp_rename($dest, $backup)) {
                self::$FOGSSH->disconnect();
                throw new \Exception(
                    sprintf(
                        '%s: %s',
                        _('Unable to move the existing image aside'),
                        $dest
                    )
                );
            }
            $moved = true;
        }
        if (!self::$FOGSSH->sftp_rename($src, $dest)) {
            if ($moved) {
                self::$FOGSSH->sftp_rename($backup, $dest);
            }
            self::$FOGSSH->disconnect();
            throw new \Exception(
                sprintf(
                    '%s: %s -> %s. %s',
                    _('Unable to move the captured image into place'),
                    $src,
                    $dest,
                    _('Check the capture folder is owned by and writable to the storage node user')
                )
            );
        }
        if ($moved) {
            self::$FOGSSH->delete($backup);
        }
        self::$FOGSSH->sftp_chmod($dest, 0775);
        self::$FOGSSH->disconnect();
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
        self::randWait();
        if ($this->Task->isSnapinTasking()) {
            die('##');
        }
        try {
            Audit::record([
                'type' => 'task.complete',
                'authSource' => Audit::SOURCE_ANONYMOUS,
                'subjectType' => 'task',
                'subjectID' => (int)$this->Task->get('id'),
                'subjectLabel' => (string)self::$Host->get('name'),
                'renderable' => 1
            ]);
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
            self::$Host
                ->set('pub_key', '')
                ->set('sec_tok', '')
                // Clearing sec_tok without the grace token would leave a
                // secret behind that nothing can use but that still sits in
                // the database.
                ->set('prev_sec_tok', '')
                // On completetion reset token and lock
                ->set('token', self::createSecToken())
                ->set('tokenlock', false);
            $updateFields = [
                'pub_key' => self::$Host->get('pub_key'),
                'sec_tok' => self::$Host->get('sec_tok'),
                'prev_sec_tok' => self::$Host->get('prev_sec_tok'),
                'token' => self::$Host->get('token'),
                'tokenlock' => self::$Host->get('tokenlock')
            ];
            if ($this->Task->isDeploy()) {
                $deployedAt = self::niceDate()->format('Y-m-d H:i:s');
                self::$Host->set('deployed', $deployedAt);
                $updateFields['deployed'] = self::$Host->get('deployed');
                // images.imageLastDeploy had a field mapping on the Image
                // model and no writer anywhere -- measured at 3 of 29 images
                // carrying a value on a live install. It matters more now
                // that imagingLog is gone (ADR 0022 decision 3), because it
                // is the column a reader reaches for to answer "when did
                // this image last go out". Written beside the host's own
                // last-deploy stamp, from the same moment.
                if ($this->imagingTask
                    && $this->Image
                    && $this->Image->isValid()
                ) {
                    $this->Image
                        ->set('deployed', $deployedAt)
                        ->save();
                }
                $this->_email();
            } elseif ($this->Task->isCapture()) {
                $this->_moveUpload();
            }
            $this->Task
                ->set('pct', 100)
                ->set('percent', 100)
                ->set('stateID', self::getCompleteState());
            if (!self::$Host->isValid()) {
                throw new \Exception('##');
            }
            $updatedHost = self::getClass('HostManager')->update(
                ['id' => self::$Host->get('id')],
                '',
                $updateFields
            );
            if (!$updatedHost) {
                throw new \Exception(_('Failed to update host'));
            }
            if (!$this->Task->save()) {
                throw new \Exception(_('Failed to update Task'));
            }
            self::$HookManager->processEvent(
                'HOST_TASKING_COMPLETE',
                [
                    'Host' => &self::$Host,
                    'Task' => &$this->Task
                ]
            );
            if (!$this->taskLog()) {
                throw new \Exception(_('Failed to update task log'));
            }
            $this->_notifyImagingOutcome();
            echo '##';
        } catch (\Exception $e) {
            // Imaging ran but FOG could not finish recording it -- the host
            // update, the task save, the task log or the imaging log failed.
            // The task is left short of Complete and FOS is told, but until
            // now nobody watching notifications was. See #1202.
            Audit::markOutcome(Audit::FAILED);
            $this->_notifyImagingOutcome($e->getMessage());
            echo $e->getMessage();
        }
    }
    /**
     * Notifies listeners that an imaging task finished, or did not.
     *
     * Three things were wrong here before #1202.
     *
     * `HOST_IMAGEUP_COMPLETE` was never fired by anything, on any server,
     * ever, despite all three bundled notification plugins registering a
     * listener for it. Captures announced themselves as `HOST_IMAGE_COMPLETE`
     * -- the deploy name -- so "an image finished uploading" and "a machine
     * finished being imaged" were indistinguishable to anything listening.
     * The two names exist precisely to tell those apart.
     *
     * The notification also fired for tasks that are not imaging at all. This
     * method is reached from Post_Wipe.php as well as Post_Stage2/3.php, so
     * wiping a disk sent "This host has finished imaging."
     *
     * And the payload was the host's name and nothing else, which is why every
     * bundled listener can only say "this host has finished imaging" without
     * naming the image. The existing `HostName` key is kept exactly as it was
     * -- it is the only key any current listener reads, in core, in the
     * bundled plugins and in whatever third-party plugins exist -- and the
     * rest is added alongside it.
     *
     * @param string $reason Empty on success; the failure text otherwise.
     *
     * @return void
     */
    private function _notifyImagingOutcome($reason = '')
    {
        if (!$this->imagingTask) {
            return;
        }
        $data = [
            'HostName' => self::$Host->get('name'),
            'Host' => self::$Host,
            'Task' => $this->Task,
            'Image' => $this->Image,
            'ImageName' => (
                $this->Image && $this->Image->isValid() ?
                $this->Image->get('name') :
                ''
            ),
            'TaskType' => $this->Task->getTaskTypeText()
        ];
        if ('' !== (string) $reason) {
            $data['Reason'] = (string) $reason;
            self::$EventManager->notify('HOST_IMAGE_FAIL', $data);
            return;
        }
        self::$EventManager->notify(
            $this->Task->isCapture() ?
            'HOST_IMAGEUP_COMPLETE' :
            'HOST_IMAGE_COMPLETE',
            $data
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskQueue', 'TaskQueue');
