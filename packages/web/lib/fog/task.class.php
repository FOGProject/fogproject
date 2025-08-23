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
            'stateID' => self::getQueuedStates(),
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
                $TaskCheckinTime = self::niceDate($Task->checkInTime);
                $isExpired = $this->isCheckinTimeExpired(false, $TaskCheckinTime); //pass in checkin time because we only have the data of the task to check, not the instance of it
                $TaskCheckinTime = self::niceDate($Task->checkInTime); //re-get in case it was updated by expire check, which happens for some reason
                if (!self::validDate($TaskCheckinTime) //if checkin time is invalid don't count task as in front
                    || $isExpired //if checkin time is expired don't count task as in front
                    || $MyCheckinTime < $TaskCheckinTime //if my checkin time is before theirs don't count them as in front
                    || ($MyCheckinTime === $TaskCheckinTime && $MyTaskID <= $Task->id) //if there's a checkin time tie, go off of task ID
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
    public function getTimeTillCheckinExpired($TestCheckinTime = null) {
        $timeout = self::getSetting('FOG_CHECKIN_TIMEOUT');
        if ($timeout < 180) { //enforce minimum timeout, display errors in log if timeout gets reset
            FOGCORE::var_dump_log('Your FOG_CHECKIN_TIMEOUT setting should be greater than 180. A value of 180 was used instead of:');
            FOGCORE::var_dump_log($timeout);
            self::setSetting('FOG_CHECKIN_TIMEOUT', 180);
            $timeout = 180; //minimum timeout of 3 minutes
        }
        if (is_null($TestCheckinTime)) { //get the task's checkin time if one wasn't passed in
            $TestCheckinTime = self::niceDate($this->get('checkInTime'));
        }
            // $TestCheckinTime = self::niceDate($TestCheckinTime);
        // } else {
        // }
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
    public function isCheckinTimeExpired($almost = false, $TaskCheckinTime = null) {
        if (isset($TaskCheckinTime) && self::validDate($TaskCheckinTime)) {
            // FOGCORE::var_dump_log('Using passed in checkin time:');
            $timeTillExpire = $this->getTimeTillCheckinExpired($TaskCheckinTime);
        } else {
            $timeTillExpire = $this->getTimeTillCheckinExpired();
        }
        // FOGCORE::var_dump_log('time till expire is:');
        // FOGCORE::var_dump_log($timeTillExpire);
        if ($almost) {
            $almostExpired = 60; 
            return ($timeTillExpire <= $almostExpired);  //is almost expired, update checkin time
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
     * checks if table is locked. Function built by AI
     *
     * @return bool
     */
    public function isTasksTableLocked() {
        $tableName = "tasks";
        $stmt = self::$DB->query("
            SELECT 
                r.trx_id waiting_trx_id,
                r.trx_mysql_thread_id waiting_thread,
                r.trx_query waiting_query,
                b.trx_id blocking_trx_id,
                b.trx_mysql_thread_id blocking_thread,
                b.trx_query blocking_query
            FROM information_schema.innodb_lock_waits w
            INNER JOIN information_schema.innodb_trx b ON w.blocking_trx_id = b.trx_id
            INNER JOIN information_schema.innodb_trx r ON w.requesting_trx_id = r.trx_id;
        ");

        $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($locks as $lock) {
            // Naive check: does the blocking or waiting query reference the table?
            if (stripos($lock['waiting_query'], $tableName)!== false ||
                stripos($lock['blocking_query'], $tableName)!== false) {
                return true;
            }
        }

        return false;
    }
    /**
     * waits for the task table to be unlocked 
     *
     * @return bool
     */
    public function waitForTasksTableUnlock() {
        while ($this->isTasksTableLocked()) {
            usleep(50000); //wait 50ms before checking again
        }
        return true;
    }
    /**
     * locks the task table 
     *
     * @return bool
     */
    public function lockTasksTable() {
        self::$DB->query("LOCK TABLES tasks WRITE");
        return true;
    }
    /**
     * updates the task checkin time 
     *
     * @return null
     */
    public function updateTaskCheckinTime($firstCheckin = false) {
        // $this->waitForTasksTableUnlock();
        // $this->lockTasksTable();
        // $curTime = self::niceDate();
        if (!$firstCheckin) {
            if ($this->isCheckinTimeExpired()) { //if the checkin time is expired, just set to now
                $newTime = self::niceDate();
            } else { //if just updating, keep the queue position by adding timeout to current checkin time as the current time may cause line jumps
                $MyCheckinTime = self::niceDate($this->get('checkInTime'));
                $timeout = self::getSetting('FOG_CHECKIN_TIMEOUT');
                $addSeconds = $timeout;// + ($inFront * 8); //8 seconds because there's a random wait of 1/2-2 seconds at check in and fos does the checkin every 5 seconds and 60 seconds for when the timeout is updated before expired
                // $MyCheckinTime->add(new DateInterval("PT{$addSeconds}S"))->format('Y-m-d H:i:s');
                $MyCheckinTime = $MyCheckinTime->add(new DateInterval("PT{$addSeconds}S"))->format('Y-m-d H:i:s');
                $newTime = self::niceDate($MyCheckinTime); //re-nice the date to make sure it's valid
            }
        } else { //first time checkin, just set to now
            $newTime = self::niceDate();
        }
        $this->set(
            'stateID',
            self::getCheckedInState()
        )->set(
            'checkInTime',
            $newTime->format('Y-m-d H:i:s')
        );
        
        if (!$this->save()) {
            // $this->unlockTasksTable();
            throw new Exception(_('Failed to update task'));
        } /* else {
            $this->unlockTasksTable();
        } */
        // return $curTime;
    }
    /**
     * unlocks the task table 
     *
     * @return bool
     */
    public function unlockTasksTable() {
        self::$DB->query("UNLOCK TABLES");
        return true;
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
