<?php
/**
 * Task handler class.
 *
 * PHP version 5
 *
 * @category Task
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task handler class.
 *
 * @category Task
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Task extends TaskType
{
    /**
     * The task table name.
     *
     * @var string
     */
    protected $databaseTable = 'tasks';
    /**
     * The task fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'taskID',
        'name' => 'taskName',
        'checkInTime' => 'taskCheckIn',
        'hostID' => 'taskHostID',
        'stateID' => 'taskStateID',
        'createdTime' => 'taskCreateTime',
        'createdBy' => 'taskCreateBy',
        'isForced' => 'taskForce',
        'scheduledStartTime' => 'taskScheduledStartTime',
        'typeID' => 'taskTypeID',
        'pct' => 'taskPCT',
        'bpm' => 'taskBPM',
        'timeElapsed' => 'taskTimeElapsed',
        'timeRemaining' => 'taskTimeRemaining',
        'dataCopied' => 'taskDataCopied',
        'percent' => 'taskPercentText',
        'dataTotal' => 'taskDataTotal',
        'storagegroupID' => 'taskNFSGroupID',
        'storagenodeID' => 'taskNFSMemberID',
        'NFSFailures' => 'taskNFSFailures',
        'NFSLastMemberID' => 'taskLastMemberID',
        'shutdown' => 'taskShutdown',
        'passreset' => 'taskPassreset',
        'isDebug' => 'taskIsDebug',
        'imageID' => 'taskImageID',
        'wol' => 'taskWOL',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'id',
        'typeID',
        'hostID',
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'image',
        'host',
        'type',
        'state',
        'storagenode',
        'storagegroup'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Image' => array(
            'id',
            'imageID',
            'image'
        ),
        'Host' => array(
            'id',
            'hostID',
            'host'
        ),
        'TaskType' => array(
            'id',
            'typeID',
            'type'
        ),
        'TaskState' => array(
            'id',
            'stateID',
            'state'
        ),
        'StorageNode' => array(
            'id',
            'storagenodeID',
            'storagenode'
        ),
        'StorageGroup' => array(
            'id',
            'storagegroupID',
            'storagegroup'
        )
    );
    /**
     * Return the checkin timeout or 180 if its less than 180.
     *
     * @return int
     */
    private static function fogCheckinTimeout()
    {
        $raw = (int) self::getSetting('FOG_CHECKIN_TIMEOUT');
        return max($raw, 180); // enforce minimum of 180 seconds
    }
    /**
     * Returns the in front of number.
     *
     * @return int
     */
    public function getInFrontOfHostCount()
    {
        $count = 0;
        $myTaskID = (int) $this->get('id');
        $myStStart = self::niceDate($this->get('scheduledStartTime'));

        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        $find = array(
            'stateID' => self::getCheckedInState(),
            'typeID' => $used,
            'storagegroupID' => $this->get('storagegroupID'),
            'storagenodeID' => $this->get('storagenodeID')
        );

        foreach ((array)$this->getManager()->find($find) as $Task) {
            $tid = (int) $Task->id;
            if ($tid == $myTaskID) {
                continue;
            }
            try {
                $ci = self::niceDate($Task->checkInTime);
                $stStart = self::niceDate($Task->scheduledStartTime);
                if (
                    !self::validDate($ci) // Task checkin is invalid, don't count
                    || !self::validDate($stStart) // Scheduled start is invalid, don't count
                    || $myStStart < $stStart // My scheduled start is before theirs, they are behind me
                    || ($myStStart == $stStart && $myTaskID < $tid)
                    
                ) {
                    continue;
                }
                ++$count;
            } catch (Exception $e) {
            }
        }

        return $count;
    }
    /**
     * Gets time until checkin expires.
     *
     * @return float
     */
    public function getTimeTillCheckinExpired()
    {
        $timeout = self::fogCheckinTimeout();
        $checkinTime = self::niceDate($this->get('checkInTime'));
        $expireTime = (new DateTimeImmutable('now', $checkinTime->getTimezone()))
            ->modify("-{$timeout} seconds");
        $curTime = self::niceDate();
        return $expireTime->getTimestamp() - $curTime->getTimestamp();
    }
    /**
     * Checks if checkin time is expired or almost expired
     *
     * @param bool $almost Check if we're about to expire and should update.
     *
     * @return bool
     */
    public function isCheckinTimeExpired($almost = false)
    {
        $timeTillExpire = $this->getTimeTillCheckinExpired();
        if ($almost) {
            return ($timeTillExpire <= 30); // is almost expired, update checkin time
        }
        if ($timeTillExpire <= 0) {
            $curTime = self::niceDate();
            $this->set('stateID', self::getQueuedState())
                 ->set('checkInTime', $curTime->format('Y-m-d H:i:s'));
            if (!$this->save()) {
                throw new Exception(_('Failed to update task'));
            }
            return true;
        }
    }
    /**
     * Cancels the task.
     *
     * @return object
     */
    public function cancel()
    {
        $SnapinJob = $this
            ->getHost()
            ->get('snapinjob');
        if ($SnapinJob instanceof SnapinJob
            && $SnapinJob->isValid()
        ) {
            self::getClass('SnapinTaskManager')
                ->update(
                    array(
                        'jobID' => $SnapinJob->get('id')
                    ),
                    '',
                    array(
                        'complete' => self::niceDate()->format('Y-m-d H:i:s'),
                        'stateID' => self::getCancelledState()
                    )
                );
            $SnapinJob->set(
                'stateID',
                self::getCancelledState()
            )->save();
        }
        if ($this->isMulticast()) {
            $msIDs = self::getSubObjectIDs(
                'MulticastSessionAssociation',
                array(
                    'taskID' => $this->get('id')
                ),
                'jobID'
            );
            self::getClass('MulticastSessionManager')
                ->update(
                    array('id' => $msIDs),
                    '',
                    array(
                        'clients' => 0,
                        'completetime' => self::formatTime('now', 'Y-m-d H:i:s'),
                        'stateID' => self::getCancelledState()
                    )
                );
        }
        $this->set('stateID', self::getCancelledState())->save();

        return $this;
    }
    /**
     * Custom Set method.
     *
     * @param string $key   The key to set.
     * @param mixed  $value The value to set.
     *
     * @return object
     */
    public function set($key, $value)
    {
        if (($this->key($key) == 'checkInTime' || $this->key($key) == 'scheduledStartTime')
            && is_numeric($value)
            && strlen($value) == 10
        ) {
            $value = self::niceDate($value)->format('Y-m-d H:i:s');
        }

        return parent::set($key, $value);
    }
    /**
     * Updates the task checkin time, state, and scheduled start time as needed.
     *
     * @return void
     * @throws Exception
     */
    public function taskCheckin()
    {
        $curState = $this->get('stateID');
        $curTime = self::niceDate();
        $almost = $this->isCheckinTimeExpired(true); // expiring in 30 seconds or less
        $expire = $this->isCheckinTimeExpired(false); // checkin time expired
        if (
            $curState != self::getCheckedInState
            || $almost
            || $expire
        ) {
            $this
                ->set('stateID', self::getCheckedInState())
                ->set('checkInTime', $curTime->format('Y-m-d H:i:s'));
            if ($expire) {
                $this
                    ->set(
                        'scheduledStartTime',
                        $curTime->format('Y-m-d H:i:s')
                    );
            }
            if (!$this->save()) {
                throw new Excpetion(_('Failed to update task'));
            }
        }
    }
    /**
     * Returns the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return $this->get('host');
    }
    /**
     * Returns the storage group object.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return new StorageGroup($this->get('storagenode')->get('storagegroupID'));
    }
    /**
     * Returns the storage node object.
     *
     * @return object
     */
    public function getStorageNode()
    {
        return $this->get('storagenode');
    }
    /**
     * Returns the image object.
     *
     * @return object
     */
    public function getImage()
    {
        return $this->get('image');
    }
    /**
     * Returns the task type object.
     *
     * @return object
     */
    public function getTaskType()
    {
        return $this->get('type');
    }
    /**
     * Returns the the type text
     *
     * @return string
     */
    public function getTaskTypeText()
    {
        return $this->getTaskType()->get('name');
    }
    /**
     * Returns the task state object.
     *
     * @return object
     */
    public function getTaskState()
    {
        return $this->get('state');
    }
    /**
     * Returns the state text.
     *
     * @return string
     */
    public function getTaskStateText()
    {
        return $this->getTaskState()->get('name');
    }
    /**
     * Returns if the task is forced or not.
     *
     * @return bool
     */
    public function isForced()
    {
        return (bool) ($this->get('isForced') > 0);
    }
    /**
     * Returns if the task is a debug or not.
     *
     * @return bool
     */
    public function isDebug()
    {
        return (bool) (parent::isDebug()
            || $this->get('isDebug'));
    }
}
