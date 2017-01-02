<?php
/**
 * The tasking element base class.
 *
 * PHP version 5
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
     * The host object.
     *
     * @var object
     */
    protected $Host;
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
            $this->Host = self::getHostItem(false);
            $this->Task = $this
                ->Host
                ->get('task');
            self::checkTasking(
                $this->Task,
                $this->Host->get('name'),
                $this->Host->get('mac')
            );
            $this->imagingTask = $this
                ->Task
                ->isImagingTask();
            $this->StorageGroup = $this->StorageNode = null;
            self::$HookManager->processEvent(
                'HOST_NEW_SETTINGS',
                array(
                    'Host' => &$this->Host,
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
            echo $e->getMessage();
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
                        'hostID' => $this->Host->get('id'),
                        'finish' => '0000-00-00 00:00:00'
                    )
                );
            return self::getClass('ImagingLog')
                ->set('hostID', $this->Host->get('id'))
                ->set('start', self::formatTime('', 'Y-m-d H:i:s'))
                ->set('image', $this->Image->get('name'))
                ->set('type', $_REQUEST['type'])
                ->set('createdBy', $this->Task->get('createdBy'))
                ->save();
        }
        $ilID = self::getSubObjectIDs(
            'ImagingLog',
            array(
                'hostID' => $this->Host->get('id'),
                'finish' => '0000-00-00 00:00:00',
                'image' => $this->Image->get('name'),
            )
        );
        $ilID = @max($ilID);
        return self::getClass('ImagingLog', $ilID)
            ->set('finish', self::formatTime('', 'Y-m-d H:i:s'))
            ->save();
    }
}
