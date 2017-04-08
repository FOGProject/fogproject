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
     * Returns the in front of number.
     *
     * @return int
     */
    public function getInFrontOfHostCount()
    {
        $count = 0;
        $curTime = self::niceDate();
        $MyCheckinTime = self::niceDate($this->get('checkInTime'));
        $myLastCheckin = $curTime->getTimestamp() - $MyCheckinTime->getTimestamp();
        if ($myLastCheckin >= self::getSetting('FOG_CHECKIN_TIMEOUT')) {
            $this->set('checkInTime', $curTime->format('Y-m-d H:i:s'))->save();
        }
        $used = explode(',', self::getSetting('FOG_USED_TASKS'));
        $find = array(
            'stateID' => self::fastmerge(
                (array)self::getQueuedStates(),
                (array)self::getProgressState()
            ),
            'typeID' => $used,
            'storagegroupID' => $this->get('storagegroupID'),
            'storagenodeID' => $this->get('storagenodeID')
        );
        $checkTime = self::getSetting('FOG_CHECKIN_TIMEOUT');
        foreach ((array)$this->getManager()
            ->find($find) as &$Task
        ) {
            $TaskCheckinTime = self::niceDate($Task->get('checkInTime'));
            $timeOfLastCheckin = $curTime
                ->getTimestamp() - $TaskCheckinTime
                ->getTimestamp();
            if ($timeOfLastCheckin >= $checkTime) {
                $Task->set(
                    'checkInTime',
                    $curTime->format('Y-m-d H:i:s')
                )->save();
            }
            if ($MyCheckinTime > $TaskCheckinTime) {
                ++$count;
            }
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
        return $this->get('storagegroup');
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
