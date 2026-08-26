<?php
/**
 * The tasking element base class.
 *
 * PHP version 7.4+
 *
 * @category TaskingElement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The tasking element base class.
 *
 * @category TaskingElement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class TaskingElement extends FOGBase
{
    /**
     * The task object.
     *
     * @var object
     */
    protected $Task;
    /**
     * The image object.
     *
     * @var object
     */
    protected $Image;
    /**
     * The storage group object
     *
     * @var object
     */
    protected $StorageGroup;
    /**
     * The storage node object
     *
     * @var object
     */
    protected $StorageNode;
    /**
     * The storage nodes array
     *
     * @var array
     */
    protected $StorageNodes;
    /**
     * The imaging task holder
     *
     * @var bool
     */
    protected $imagingTask;
    /**
     * Initializes the Tasking stuff
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        try {
            self::getHostItem(false);
            $this->Task = self::$Host
                ->get('task');
            self::checkTasking(
                $this->Task,
                self::$Host->get('name'),
                self::$Host->get('mac')
            );
            $this->imagingTask = $this
                ->Task
                ->isImagingTask();
            $this->StorageGroup = $this->StorageNode = null;
            self::$HookManager->processEvent(
                'HOST_NEW_SETTINGS',
                array(
                    'Host' => &self::$Host,
                    'StorageNode' => &$this->StorageNode,
                    'StorageGroup' => &$this->StorageGroup
                )
            );
            if (!$this->StorageGroup
                || !$this->StorageGroup->isValid()
            ) {
                $this->StorageGroup = $this
                    ->Task
                    ->getStorageGroup();
            }
            if ($this->imagingTask) {
                if (!$this->StorageNode
                    || !$this->StorageNode->isValid()
                ) {
                    if ($this->Task->isCapture()
                        || $this->Task->isMulticast()
                    ) {
                        $this->StorageNode = $this
                            ->StorageGroup
                            ->getMasterStorageNode();
                    } else {
                        $this->StorageNode = $this
                            ->StorageGroup
                            ->getOptimalStorageNode();
                    }
                }
                self::checkStorageGroup(
                    $this->StorageGroup
                );
                self::checkStorageNodes(
                    $this->StorageGroup
                );
                $this->Image = $this
                    ->Task
                    ->getImage();
                $getter = 'enablednodes';
                if (count($this->StorageGroup->get($getter)) < 1) {
                    $getter = 'allnodes';
                }
                $this->StorageNodes = self::getClass('StorageNodeManager')
                    ->find(
                        array('id' => $this->StorageGroup->get($getter))
                    );
                if ($this->Task->isCapture()
                    || $this->Task->isMulticast()
                ) {
                    $this->StorageNode = $this
                        ->StorageGroup
                        ->getMasterStorageNode();
                }
            }
        } catch (Exception $e) {
            echo Initiator::e($e->getMessage());
            exit;
        }
    }
    /**
     * Checks the tasking of the current task.
     *
     * @param object $Task the task to check.
     * @param string $name the host name.
     * @param string $mac  the mac address of the host.
     *
     * @throws Exception
     *
     * @return void
     */
    protected static function checkTasking(
        &$Task,
        $name,
        $mac
    ) {
        if (!$Task->isValid()) {
            throw new Exception(
                sprintf(
                    '%s: %s (%s)',
                    _('No Active Task found for Host'),
                    $name,
                    $mac
                )
            );
        }
    }
    /**
     * Checks the storage group.
     *
     * @param object $StorageGroup the storage group object.
     *
     * @throws Exception
     *
     * @return void
     */
    protected static function checkStorageGroup(&$StorageGroup)
    {
        if (!$StorageGroup->isValid()) {
            throw new Exception(
                _('Invalid Storage Group')
            );
        }
    }
    /**
     * Checks that there are nodes on the storage group.
     *
     * @param object $StorageGroup the storage group object.
     *
     * @throws Exception
     *
     * @return void
     */
    protected static function checkStorageNodes(&$StorageGroup)
    {
        $getter = 'enablednodes';
        if (count($StorageGroup->get($getter)) < 1) {
            $getter = 'allnodes';
        }
        if (count($StorageGroup->get($getter)) < 1) {
            throw new Exception(
                sprintf(
                    '%s, %s?',
                    _('Could not find a Storage Node in this group'),
                    _('is there one enabled')
                )
            );
        }
    }
    /**
     * Checks the node failure status.
     *
     * @param object $StorageNode the storage node object.
     * @param object $Host        the host object.
     *
     * @return object
     */
    protected static function nodeFail(
        $StorageNode,
        $Host
    ) {
        if ($StorageNode->getNodeFailure($Host)) {
            $StorageNode = new StorageNode();
            printf(
                '%s %s (%s) %s.',
                _('Storage Node'),
                $StorageNode->get('name'),
                $StorageNode->get('ip'),
                sprintf(
                    '%s, %s',
                    _('is open'),
                    _('but has recently failed for this host')
                )
            );
        }
        return $StorageNode;
    }
    /**
     * Creates the log record for the task.
     *
     * @return bool|object
     */
    protected function taskLog()
    {
        return self::getClass('TaskLog', $this->Task)
            ->set('taskID', $this->Task->get('id'))
            ->set('taskStateID', $this->Task->get('stateID'))
            ->set('createdTime', $this->Task->get('createdTime'))
            ->set('createdBy', $this->Task->get('createdBy'))
            ->save();
    }
    /**
     * Creates the image log record for the task/host.
     *
     * @param bool $checkin if this is checkin or checkout.
     *
     * @return bool|object
     */
    protected function imageLog($checkin = false)
    {
        if ($checkin === true) {
            self::getClass('ImagingLogManager')
                ->destroy(
                    array(
                        'hostID' => self::$Host->get('id'),
                        // GH-1245: an unfinished log has no finish time.
                        // Reads as `ilFinishTime IS NULL` -- see
                        // FOGManagerController::find().
                        'finish' => null
                    )
                );
            return self::getClass('ImagingLog')
                ->set('hostID', self::$Host->get('id'))
                ->set('start', self::formatTime('now', 'Y-m-d H:i:s'))
                ->set('image', $this->Image->get('name'))
                ->set('type', $_REQUEST['type'])
                ->set('createdBy', $this->Task->get('createdBy'))
                ->save();
        }
        $ilID = self::getSubObjectIDs(
            'ImagingLog',
            array(
                'hostID' => self::$Host->get('id'),
                // GH-1245: as above -- the row this is looking for is the one
                // that has not finished.
                'finish' => null,
                'image' => $this->Image->get('name'),
            )
        );
        // getSubObjectIDs can legitimately return an empty array when no open
        // imaging log exists for this host/image, and PHP 8's max() throws an
        // uncaught ValueError on that (@ cannot suppress an Error). maxId()
        // yields 0, which makes the ImagingLog below a new row as intended.
        $ilID = self::maxId($ilID);
        $ImagingLog = self::getClass('ImagingLog', $ilID)
            ->set('finish', self::formatTime('now', 'Y-m-d H:i:s'));
        // A new row needs the three fields ImagingLog declares required --
        // hostID, start and image -- or save() refuses it and the caller
        // reports "Failed to update imaging log" for a machine that imaged
        // fine. Setting only `finish`, as this did, could therefore only ever
        // fail on the no-open-log path the maxId() note above describes.
        // The start time is the task's own check-in, not now: the imaging
        // began then, and a start equal to the finish would read as a
        // zero-length deployment in the imaging report.
        if (!$ImagingLog->isValid()) {
            $checkInTime = $this->Task->get('checkInTime');
            if (!self::validDate($checkInTime)) {
                $checkInTime = self::formatTime('now', 'Y-m-d H:i:s');
            }
            $ImagingLog
                ->set('hostID', self::$Host->get('id'))
                ->set('start', $checkInTime)
                ->set('image', $this->Image->get('name'))
                // 'up'/'down' are the two values the imaging report maps to
                // Capture and Deploy; check-in reads them off the request,
                // which is not available here.
                ->set('type', $this->Task->isCapture() ? 'up' : 'down')
                ->set('createdBy', $this->Task->get('createdBy'));
        }
        return $ImagingLog->save();
    }
}
