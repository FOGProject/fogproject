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
    protected $databaseFields = [
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
        'bypassbitlocker' => 'taskBypassBitlocker'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'id',
        'typeID',
        'hostID'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'image',
        'host',
        'type',
        'state',
        'storagenode',
        'storagegroup'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Image' => [
            'id',
            'imageID',
            'image'
        ],
        'Host' => [
            'id',
            'hostID',
            'host'
        ],
        'TaskType' => [
            'id',
            'typeID',
            'type'
        ],
        'TaskState' => [
            'id',
            'stateID',
            'state'
        ],
        'StorageNode' => [
            'id',
            'storagenodeID',
            'storagenode'
        ],
        'StorageGroup' => [
            'id',
            'storagegroupID',
            'storagegroup'
        ]
    ];
    /**
     * Return the checkin timeout or 180 if its less than 180.
     *
     * @return int
     */
    private static function fogCheckinTimeout()
    {
        $raw = (int) self::getSetting('FOG_CHECKIN_TIMEOUT') ?: 600;
        return max($raw, 180); // enforce minimum of 180 seconds
    }
    /**
     * Returns cutoff timing
     *
     * @return DateTimeImmutable
     */
    private static function cutoff()
    {
        return (clone self::niceDate())
            ->modify('-' . self::fogCheckinTimeout() . ' seconds');
    }
    /**
     * Returns if the checkin time is expired.
     *
     * @param string $checkInTime The checkin time.
     * @return bool
     */
    private static function isExpired(string $checkInTime)
    {
        if (!$checkInTime) {
            return true;
        }
        $ci = self::niceDate($checkInTime);
        if (!self::validDate($ci)) {
            return true;
        }
        return ($ci < self::cutoff());
    }
    /**
     * Returns if we are almost expired and should update.
     *
     * @param string $checkInTime  The checkin time.
     * @param int    $graceSeconds The grace period in seconds.
     *
     * @return bool
     */
    private static function isAlmostExpired(string $checkInTime, int $graceSeconds = 30)
    {
        if (!$checkInTime) {
            return true;
        }
        $ci = self::niceDate($checkInTime);
        if (!self::validDate($ci)) {
            return true;
        }
        $almostCutoff = (clone(self::cutoff()))->modify("+{$graceSeconds} seconds"); // timeout - grace
        return $ci < $almostCutoff; // i.e., within grace window
    }
    /**
     * expires a timed out task if checkin expired
     * clears scheduledStartTime and sets status to queued
     * updates checkin time with when it expired
     * If true passed in, will return true if task was expired false otherwise
     *
     * @return null
     */
    public function expireTaskCheckin(bool $return = false)
    {
        $result = false;
        if (self::isExpired($this->get('checkInTime') ?? '')) { //make sure checked for expired
            $curState = $this->get('stateID');
            if ($curState != self::getQueuedState()) { // If not already reset to queued state
                $curtime = self::niceDate();
                $this->set('stateID', self::getQueuedState())
                    ->set('checkInTime', $curtime->format('Y-m-d H:i:s'))
                    ->set('scheduledStartTime', '0000-00-00 00:00:00');
                if(!$this->save()) {
                    throw new Exception(_('Failed to update task'));
                } else {
                    $result = true;
                }
            }
        }
        if ($return) {
            return $result;
        }
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
        // My scheduled start validity, if not valid, set to now
        if (!self::validDate($myStStart)) {
            $myStStart = self::niceDate();
        }

        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        $find = [
            'stateID' => self::getCheckedInState(),
            'typeID' => $used,
            'storagegroupID' => $this->get('storagegroupID'),
            'storagenodeID' => $this->get('storagenodeID')
        ];
        Route::listem(__CLASS__, $find);
        $Tasks = json_decode(Route::getData());

        foreach ($Tasks->data as $Task) {
            $tid = (int) $Task->id;
            if ($tid === $myTaskID) {
                continue;
            }

            // Liveness: if expired, it's getting re-queued to the back. Don't count
            if (self::isExpired($Task->checkInTime ?? '')) {
                self::getClass('Task', $tid)->expireTaskCheckin();
                continue;
            }

            $stStart = self::niceDate($Task->scheduledStartTime ?? '');
            // Scheduled start validity, if not valid, don't count
            if (!self::validDate($stStart)) {
                continue;
            }

            if ($stStart < $myStStart // Their scheduled start is before mine, they are ahead of me
                || ($stStart == $myStStart && $tid < $myTaskID) // Break ties with taskID if scheduled start times are the same
            ) {
                ++$count;
            }
        }

        return $count;
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
            self::getClass('SnapinTaskManager')->update(
                ['jobID' => $SnapinJob->get('id')],
                '',
                [
                    'complete' => self::niceDate()->format('Y-m-d H:i:s'),
                    'stateID' => self::getCancelledState()
                ]
            );
            $SnapinJob->set(
                'stateID',
                self::getCancelledState()
            )->save();
        }
        if ($this->isMulticast()) {
            $find = ['taskID' => $this->get('id')];
            Route::ids(
                'multicastsessionsassociation',
                $find,
                'msID'
            );
            $msIDs = json_decode(Route::getData(), true);
            self::getClass('MulticastSessionManager')
                ->update(
                    ['id' => $msIDs],
                    '',
                    [
                        'clients' => 0,
                        'completetime' => self::formatTime('now', 'Y-m-d H:i:s'),
                        'stateID' => self::getCancelledState()
                    ]
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
     * Updates the task checkin time, state, and scheduled start time as needed
     *
     * @return void
     * @throws Exception
     */
    public function taskCheckIn()
    {
        $curState = $this->get('stateID');

        // Timings
        $checkInTime = $this->get('checkInTime');
        $curTime = self::niceDate();

        $almost = $this->isAlmostExpired($checkInTime); // expiring in 30 seconds or less
        $expire = $this->isExpired($checkInTime); // checkin time expired

        $store_update = false;
        $checkInState = self::getCheckedInState();

        if ($curState != $checkInState || $expire) {
            $this
                ->set('stateID', $checkInState)
                ->set('checkInTime', $curTime->format('Y-m-d H:i:s'))
                ->set('scheduledStartTime', $curTime->format('Y-m-d H:i:s'));
            $store_update = true;
        } elseif ($almost && in_array($curState, self::getQueuedStates())) {
            $this
                ->set('checkInTime', $curTime->format('Y-m-d H:i:s'));
            $store_update = true;
        }
        if ($store_update && !$this->save()) {
            throw new Exception(_('Failed to update task'));
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
