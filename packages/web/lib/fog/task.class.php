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
    //minimum checkin timeout in seconds, enforced if FOG_CHECKIN_TIMEOUT setting is lower
    //prevents excessive db writes when waiting for tasks to timeout/expire
    const FOG_TASK_CHECKIN_MIN_TIMEOUT = 180;
    //if a task's checkin time is within this many seconds of expiring/timing out, update the checkin time
    const FOG_TASK_CHECKIN_ALMOST_EXPIRED = 60;
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
     * Returns the in front of number.
     *
     * @return int
     */
    public function getInFrontOfHostCount()
    {
        $count = 0;
        $MyCheckinTime = self::niceDate($this->get('checkInTime'));
        $curTime = self::niceDate();
        //get atomic identifier for this task so we don't count ourselves in the queue
        $MyTaskID = $this->get('id');

        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        $find = [
            'stateID' => self::getCheckedInState(),
            'typeID' => $used,
            'storagegroupID' => $this->get('storagegroupID'),
            'storagenodeID' => $this->get('storagenodeID')
        ];
        Route::listem(
            __CLASS__,
            $find
        );
        $Tasks = json_decode(
            Route::getData()
        );
        foreach ($Tasks->data as $Task) {
            if ($Task->id == $MyTaskID) {
                continue;
            }
            try {
                if (!self::validDate(self::niceDate($Task->checkInTime)) //if niceDate version of tasks' checkin time is invalid don't count task as in front, also catch the exception
                    || self::getClass('task', $Task->id)->isCheckinTimeExpired(false) //if checkin time is expired don't count task as in front
                    || $MyTaskID < $Task->id //base the order of waiting tasks on createTime via ID, checkin time will change to show a task is active
                ) {
                    continue;
                }
                ++$count;
            } catch (Exception $e) { }
        }

        return $count;
    }
    /**
     * Gets time until checkin expires.
     *
     * @return float
    */
    public function getTimeTillCheckinExpired() {
        $timeout = self::getSetting('FOG_CHECKIN_TIMEOUT');
        $minTimeOut = self::FOG_TASK_CHECKIN_MIN_TIMEOUT;
        if ($timeout < $minTimeOut) { //enforce minimum timeout, display errors in log if timeout gets reset
            FOGCORE::var_dump_log("Your FOG_CHECKIN_TIMEOUT setting should be greater than ${minTimeOut}. A value of ${minTimeOut} has been set instead of: ${timeout}");
            self::setSetting('FOG_CHECKIN_TIMEOUT', $minTimeOut);
            $timeout = self::getSetting('FOG_CHECKIN_TIMEOUT');
        }
        $TestCheckinTime = self::niceDate($this->get('checkInTime'));
        $expireTime = $TestCheckinTime->add(new DateInterval("PT{$timeout}S"));
        $curTime = self::niceDate();
        $timeTillExpire = $expireTime->getTimestamp() - $curTime->getTimestamp();
        return $timeTillExpire;
    }
    /**
     * Checks if checkin time is expired or almost expired if almost switch present.
     *
     * @return bool
     */
    public function isCheckinTimeExpired($almost = false) {
        $timeTillExpire = $this->getTimeTillCheckinExpired();
        if ($almost) {
            return ($timeTillExpire <= self::FOG_TASK_CHECKIN_ALMOST_EXPIRED);  //is almost expired, update checkin time
        } else {
            return $timeTillExpire <= 0;
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
        if ($this->key($key) == 'checkInTime'
            && is_numeric($value)
            && strlen($value) == 10
        ) {
            $value = self::niceDate($value)->format('Y-m-d H:i:s');
        }

        return parent::set($key, $value);
    }
    /**
     * updates the task checkin time 
     *
     * @return null
     */
    public function updateTaskCheckinTime() {
        $curState = $this->get('stateID');
        if ($curState != self::getCheckedInState()) {
            $firstCheckin = true; //if not in checked in state, it's the first checkin
        } else {
            $firstCheckin = false; //already checked in, update the statetime if about to expire
        }
        if (!$firstCheckin) {
            $almost = true;
            if ($this->isCheckinTimeExpired($almost)) { //whether expiring in 60 seconds or already expired, update the checkin time to now, if it's expired and is checking in, it should be updated so its active/not expired, order is only based of taskID now
                $updateCheckin = true;
            } else { //checkin time not expired or expiring, do nothing
                $updateCheckin = false;
            }
        } else { //first time checkin, set the checkin time other times should just set the state and state time
            $updateCheckin = true;
        }
        if ($updateCheckin) {
            $newTime = self::niceDate();
            $this->set(
                'stateID',
                self::getCheckedInState()
            )->set(
                'checkInTime',
                $newTime->format('Y-m-d H:i:s')
            );
            
            if (!$this->save()) {
                throw new Exception(_('Failed to update task'));
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
