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

namespace FOG\TaskHandling;

use FOG\Base\FOGBase;
use FOG\Items\StorageNode;
use FOG\Items\TaskLog;
use FOG\Router\Route;

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
                    $this->StorageNodes[] = new StorageNode($StorageNode->id);
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
            // getNames(): names() answers with its rows under a `data`
            // envelope, and this wants the rows. It raises on failure into
            // the caller rather than ending the response, as asValue() did.
            $names = Route::getNames($classname);
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
        // imaging run, so it carries what imagingLog used to -- host name,
        // task type name and image name, denormalized because tasks are
        // deleted routinely and this row outlives them (schema 341's
        // reasoning, extended to the image).
        //
        // The row is built by TaskLog::recordState() rather than here, because
        // a state transition is not something only a TaskingElement can cause.
        // Cancellation reaches the tasks table through Task::cancel() and
        // TaskManager::cancel(), neither of which has a TaskingElement, and so
        // wrote no row at all -- leaving In-Progress as the last thing the log
        // ever said about a canceled task.
        //
        // recordState() resolves the host from the task instead of from
        // self::$Host. For this caller they are the same host; for the cancel
        // callers there is no request host to resolve.
        return TaskLog::recordState($this->Task);
    }
}
