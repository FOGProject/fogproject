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

namespace FOG;

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
                [
                    'Host' => &self::$Host,
                    'StorageNode' => &$this->StorageNode,
                    'StorageGroup' => &$this->StorageGroup
                ]
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
                if (!count($this->StorageGroup->get($getter) ?: [])) {
                    $getter = 'allnodes';
                }
                $StorageNodes = Route::getList(
                    'storagenode',
                    ['id' => $this->StorageGroup->get($getter)]
                );
                foreach ($StorageNodes as &$StorageNode) {
                    $this->StorageNodes[] = self::getClass(
                        'StorageNode',
                        $StorageNode->id
                    );
                    unset($StorageNode);
                }
                if ($this->Task->isCapture()
                    || $this->Task->isMulticast()
                ) {
                    $this->StorageNode = $this
                        ->StorageGroup
                        ->getMasterStorageNode();
                }
            }
        } catch (\Exception $e) {
            echo \Initiator::e($e->getMessage());
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
            throw new \Exception(
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
     * Returns item listings more dynamically
     *
     * @return void
     * @throws Exception
     */
    public static function getlisting($classname)
    {
        try {
            // asValue(): names() has no wrapper of its own, and its payload
            // is a bare list with no envelope to unwrap. This is here so a
            // failure raises into the caller rather than ending the response.
            $names = Route::asValue(
                function () use ($classname) {
                    Route::names($classname);
                }
            );
            if (count($names ?: []) <= 0) {
                throw new \Exception(
                    sprintf(
                        _('There are no %s on this server'),
                        $classname . 's'
                    )
                );
            }
            foreach ($names as $item) {
                printf(
                    '\tID# %s\t-\t%s\n',
                    $item->id,
                    $item->name
                );
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        exit;
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
            throw new \Exception(
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
        if (!count($StorageGroup->get($getter) ?: [])) {
            $getter = 'allnodes';
        }
        if (!count($StorageGroup->get($getter) ?: [])) {
            throw new \Exception(
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
            $StorageNode = new StorageNode();
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
        // ADR 0022 decision 3: this row is now the whole record of an
        // imaging run, so it carries what imagingLog used to.
        //
        // Host name and task type name were added to the table by schema 341
        // but written only on the FOS report rows -- 341's backfill excluded
        // logType='state' explicitly. That left capture-versus-deploy absent
        // from exactly the rows a per-event count reads, which is what the
        // dashboard chart needs now that imagingLog is gone.
        //
        // All three are denormalized on purpose. The route from this row to
        // any of them is taskID -> tasks -> hosts/taskTypes/images, and tasks
        // are deleted routinely -- Route::deletemass('host') cascades to
        // them. Same reasoning as 341's, extended to the image.
        $imageName = '';
        if ($this->imagingTask
            && $this->Image
            && $this->Image->isValid()
        ) {
            $imageName = $this->Image->get('name');
        }

        return self::getClass('TaskLog', $this->Task)
            ->set('taskID', $this->Task->get('id'))
            ->set('taskStateID', $this->Task->get('stateID'))
            ->set('createdTime', $this->Task->get('createdTime'))
            ->set('createdBy', $this->Task->get('createdBy'))
            ->set('hostID', self::$Host->get('id'))
            ->set('hostName', self::$Host->get('name'))
            ->set('taskTypeName', $this->Task->getTaskTypeText())
            ->set('imageName', $imageName)
            ->save();
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskingElement', 'TaskingElement');
