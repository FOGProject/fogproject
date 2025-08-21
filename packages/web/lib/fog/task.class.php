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
        // $curTime = self::niceDate();
        $MyCheckinTime = self::niceDate($this->get('checkInTime'));
        // $MyCreatedTime = self::niceDate($this->get('createdTime'));

        //get atomic identifier for this task so we don't count ourselves in the queue
        $MyTaskID = $this->get('id');

        // $myLastCheckin = $curTime->getTimestamp() - $MyCheckinTime->getTimestamp();
        // if ($myLastCheckin >= self::getSetting('FOG_CHECKIN_TIMEOUT')) {
        //     $this->set('checkInTime', $curTime->format('Y-m-d H:i:s'))->save();
        // }
        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        $find = [
            'stateID' => self::getQueuedStates(),
            'typeID' => $used,
            'storagegroupID' => $this->get('storagegroupID'),
            'storagenodeID' => $this->get('storagenodeID')
        ];
        // $checkTime = self::getSetting('FOG_CHECKIN_TIMEOUT');
        Route::listem(
            __CLASS__,
            $find
        );
        $Tasks = json_decode(
            Route::getData()
        );
        // FOGCORE::var_dump_log('count,curtime,mycheckintime,mylastcheckin,myID are:');
        // FOGCORE::var_dump_log($count);
        // FOGCORE::var_dump_log($curTime);
        // FOGCORE::var_dump_log($MyCheckinTime);
        // FOGCORE::var_dump_log($myLastCheckin);
        // FOGCORE::var_dump_log($MyTaskID);
        foreach ($Tasks->data as &$Task) {
            // FOGCORE::var_dump_log('cur task is');
            // FOGCORE::var_dump_log($Task);
            
            //don't try a check against self for in front count
            if ($Task->id != $MyTaskID) {
                try {
                    //get the check in time of the task being checked against
                    $TaskCheckinTime = self::niceDate($Task->checkInTime);
                    
                    /* This was for timeout checks, timeout not used now, keeping just in case reference is needed */
                    //check if the task being checked against has a valid check in time
                    // $TimeSinceLastCheckin = $curTime->getTimestamp() - $TaskCheckinTime->getTimestamp();
                    // if ($TimeSinceLastCheckin >= $checkTime) {
                    //     FOGCORE::var_dump_log('This task is timed out:');
                    //     $TaskCheckInTimedOut = $true;
                    // } else {
                    //     $TaskCheckInTimedOut = $false;
                    // }
                    
                    //if no exception from nicedate, then check if curtime gt checktime and increment count
                    //also don't count self and only count if mycheckintime is greater than checkintime of the task being checked against
                    //i.e. if I checked in at 8:30 (mycheckintime) and the task being checked against has a check in time of 8:25 (taskcheckintime), 
                    // then my time is after/greater than the task being checked against, so it is in front of me and I should increment the count
                    if (/* !($TaskCheckInTimedOut) && */ (self::validDate($TaskCheckinTime)) && ($MyCheckinTime >= $TaskCheckinTime)) {
                        if ($MyCheckinTime === $TaskCheckinTime) {
                            //the check in times are exactly the same, that's crazy!
                            //whoever has the lower task id goes first as that task was created first
                            //if my task id is greater than the checked task's id, then it is in front of me
                            if ($MyTaskID > $Task->id) {
                                ++$count;
                            }
                        } else {
                            ++$count;
                        }
                    }
                } catch (Exception $e) {
                    // FOGCORE::var_dump_log("nice date is invalid for checkInTime of task with id:");
                    // FOGCORE::var_dump_log($Task->id);
                    //don't increment count for tasks with a 'No Data' check in time
                }
            }
            // FOGCORE::var_dump_log('count of in front is now:');
            // FOGCORE::var_dump_log($count);
            unset($Task);
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
            $msIDS = json_decode(Route::getData(), true);
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
