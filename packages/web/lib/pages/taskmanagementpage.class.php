<?php
/**
 * Displays tasks to the user.
 *
 * PHP version 5
 *
 * @category TaskManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays tasks to the user.
 *
 * @category TaskManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskManagementPage extends FOGPage
{
    /**
     * The node this page works with.
     *
     * @var string
     */
    public $node = 'task';
    /**
     * Initializes the task page items.
     *
     * @param string $name The name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->menu = array(
            'search' => self::$foglang['NewSearch'],
            'active' => self::$foglang['ActiveTasks'],
            'listhosts' => sprintf(
                self::$foglang['ListAll'],
                self::$foglang['Hosts']
            ),
            'listgroups' => sprintf(
                self::$foglang['ListAll'],
                self::$foglang['Groups']
            ),
            'activemulticast' => self::$foglang['ActiveMCTasks'],
            'activesnapins' => self::$foglang['ActiveSnapins'],
            'activescheduled' => self::$foglang['ScheduledTasks'],
        );
        self::$HookManager
            ->processEvent(
                'SUB_MENULINK_DATA',
                array(
                    'menu' => &$this->menu,
                    'submenu' => &$this->subMenu,
                    'id' => &$this->id,
                    'notes' => &$this->notes
                )
            );
        $this->headerData = array(
            '<input type="checkbox" class="toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            _('Started By:'),
            sprintf(
                '%s<br/><small>%s</small>',
                _('Hostname'),
                _('MAC')
            ),
            _('Image Name'),
            _('Start Time'),
            _('Working with node'),
            _('Status')
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name='
            . '"task[]" value="${id}" id="tasker-${id}"/><label for="'
            . 'tasker-${id}"></label>',
            '${startedby}',
            sprintf(
                '<p><a href="?node=host&sub=edit&id=${host_id}" title='
                . '"%s">${host_name}</a></p><small>${host_mac}</small>',
                _('Edit Host')
            ),
            sprintf(
                '<p><a href="?node=image&sub=edit&id=${image_id}" title='
                . '"%s">${image_name}</a></p>',
                _('Edit Image')
            ),
            '<small>${time}</small>',
            '<small>${node_name}</small>',
            '${details_taskforce} <i data-state="${state_id}" class='
            . '"state fa fa-${icon_state} fa-1x icon" title="${state}">'
            . '</i> <i class="fa fa-${icon_type} fa-1x icon" title="${type}"></i>',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false',
                'task-id' => '${id}'
            ),
            array(
                'width' => 65,
                'class' => 'l',
                'id' => 'host-${host_id}'
            ),
            array(
                'width' => 120,
                'class' => 'l'
            ),
            array(
                'width' => 70,
                'class' => 'r'
            ),
            array(
                'width' => 100,
                'class' => 'r'
            ),
            array(
                'width' => 70,
                'class' => 'c'
            ),
            array(
                'width' => 50,
                'class' => 'r filter-false'
            ),
        );
        self::$returnData = function (&$Task) {
            if (!$Task->isValid()) {
                return;
            }
            $Host = $Task->getHost();
            if (!$Host->isValid()) {
                return;
            }
            if ($Task->isSnapinTasking()) {
                $SnapinJob = $Host->get('snapinjob');
                if ($SnapinJob->isValid()) {
                    $STCount = self::getClass('SnapinTaskManager')
                        ->count(
                            array(
                                'jobID' => $SnapinJob->get('id'),
                                'stateID' => self::fastmerge(
                                    (array)$this->getQueuedStates(),
                                    (array)$this->getProgressState()
                                )
                            )
                        );
                    if ($STCount < 1) {
                        $Task->cancel();
                        return;
                    }
                }
            }
            if ($Task->get('typeID') < 3) {
                if ($Task->get('isForced')) {
                    $forcetask = sprintf(
                        '<i class="icon-forced" title="%s"></i>',
                        _('Task forced to start')
                    );
                } else {
                    $forcetask = sprintf(
                        '<i class="icon-force icon" title="%s" href="?%s"></i>',
                        _('Force task to start'),
                        'node=task&sub=forceTask&id=${id}'
                    );
                }
            }
            $this->data[] = array(
                'startedby' => $Task->get('createdBy'),
                'details_taskforce' => $forcetask,
                'id' => $Task->get('id'),
                'name' => $Task->get('name'),
                'time' => self::formatTime(
                    $Task->get('createdTime'),
                    'Y-m-d H:i:s'
                ),
                'state' => $Task->getTaskStateText(),
                'forced' => $Task->get('isForced'),
                'type' => $Task->getTaskTypeText(),
                'width' => 600 * intval($Task->get('percent')) / 100,
                'elapsed' => $Task->get('timeElapsed'),
                'remains' => $Task->get('timeRemaining'),
                'percent' => $Task->get('pct'),
                'copied' => $Task->get('dataCopied'),
                'total' => $Task->get('dataTotal'),
                'bpm' => $Task->get('bpm'),
                'details_taskname' => (
                    $Task->get('name') ?
                    sprintf(
                        '<div class="task-name">%s</div>',
                        $Task->get('name')
                    ) :
                    ''
                ),
                'host_id' => $Task->get('hostID'),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac')->__toString(),
                'icon_state' => $Task->getTaskState()->getIcon(),
                'icon_type' => $Task->getIcon(),
                'state_id' => $Task->getTaskState()->get('id'),
                'image_name' => $Task->getImage()->get('name'),
                'image_id' => $Task->getImage()->get('id'),
                'node_name' => $Task->getStorageNode()->get('name')
            );
            unset($Task, $Host);
        };
    }
    /**
     * Default page to show (active always).
     *
     * @return void
     */
    public function index()
    {
        $this->active();
    }
    /**
     * How to search for data like other pages.
     *
     * @return void
     */
    public function searchPost()
    {
        $this->data = array();
        array_shift($this->headerData);
        array_shift($this->templates);
        array_shift($this->attributes);
        parent::searchPost();
    }
    /**
     * How to display our active data.
     *
     * @return void
     */
    public function active()
    {
        $this->title = 'Active Tasks';
        $this->data = array();
        $find = array(
            'stateID' => self::fastmerge(
                (array) self::getQueuedStates(),
                (array) self::getProgressState()
            )
        );
        $tasks = self::getClass('TaskManager')->find($find);
        if (count($tasks) > 0) {
            array_walk(
                $tasks,
                static::$returnData
            );
        }
        self::$HookManager
            ->processEvent(
                'HOST_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
    }
    /**
     * List all hosts.
     *
     * @return void
     */
    public function listhosts()
    {
        $this->title = 'All Hosts';
        $up = new TaskType(2);
        $down = new TaskType(1);
        $up = sprintf(
            '<a href="?node=task&sub=hostdeploy&type=%s&id=${id}">'
            . '<i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',
            $up->get('id'),
            $up->get('icon'),
            $up->get('name')
        );
        $down = sprintf(
            '<a href="?node=task&sub=hostdeploy&type=%s&id=${id}">'
            . '<i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',
            $down->get('id'),
            $down->get('icon'),
            $down->get('name')
        );
        $adv = sprintf(
            '<a href="?node=task&sub=hostadvanced&id=${id}#host-tasks">'
            . '<i class="icon hand fa fa-arrows-alt fa-fw title="%s"></i></a>',
            _('Advanced')
        );
        $this->headerData = array(
            _('Host Name'),
            _('Assigned Image'),
            _('Tasking'),
        );
        $this->templates = array(
            '<a href="?node=host&sub=edit&id=${id}">${name}</a>'
            . '<br/><small>${mac}</small>',
            '<small>${imagename}</small>',
            sprintf(
                '%s %s %s',
                $down,
                $up,
                $adv
            ),
        );
        $this->attributes = array(
            array(
                'width' => 100,
                'class' => 'i'
            ),
            array(
                'width' => 60,
                'class' => 'c'
            ),
            array(
                'width' => 60,
                'class' => 'r filter-false'
            ),
        );
        foreach ((array)self::getClass('HostManager')
            ->find() as &$Host
        ) {
            if ($Host->get('pending')) {
                continue;
            }
            $this->data[] = array(
                'id' => $Host->get('id'),
                'name' => $Host->get('name'),
                'mac' => $Host->get('mac')->__toString(),
                'imagename' => $Host->getImageName(),
            );
            unset($Host);
        }
        self::$HookManager
            ->processEvent(
                'HOST_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
    }
    /**
     * List all groups.
     *
     * @return void
     */
    public function listgroups()
    {
        $this->title = 'All Groups';
        $mc = new TaskType(8);
        $down = new TaskType(1);
        $mc = sprintf(
            '<a href="?node=task&sub=groupdeploy&type=%s&id=${id}">'
            . '<i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',
            $mc->get('id'),
            $mc->get('icon'),
            $mc->get('name')
        );
        $down = sprintf(
            '<a href="?node=task&sub=groupdeploy&type=%s&id=${id}">'
            . '<i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',
            $down->get('id'),
            $down->get('icon'),
            $down->get('name')
        );
        $adv = sprintf(
            '<a href="?node=task&sub=groupadvanced&id=${id}#group-tasks">'
            . '<i class="icon hand fa fa-arrows-alt fa-fw title="%s"></i></a>',
            _('Advanced')
        );
        $this->headerData = array(
            _('Group Name'),
            _('Tasking'),
        );
        $this->templates = array(
            '<a href="?node=group&sub=edit&id=${id}">${name}</a>',
            sprintf(
                '%s %s %s',
                $mc,
                $down,
                $adv
            ),
        );
        $this->attributes = array(
            array(
                'width' => 100,
                'class' => 'i'
            ),
            array(
                'width' => 60,
                'class' => 'r filter-false'
            ),
        );
        foreach ((array)self::getClass('GroupManager')
            ->find() as &$Group
        ) {
            $this->data[] = array(
                'id' => $Group->get('id'),
                'name' => $Group->get('name'),
            );
            unset($Group);
        }
        self::$HookManager
            ->processEvent(
                'TasksListGroupData',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
    }
    /**
     * Gets the tasking of a particular item.
     *
     * @param string $type The type of the tasking to get.
     *
     * @return void
     */
    private function _tasking($type)
    {
        global $id;
        try {
            $types = array(
                'host',
                'group'
            );
            if (!in_array($type, $types)) {
                throw new Exception(_('Invalid object type passed'));
            }
            $var = ucfirst($type);
            $$var = self::getClass($var, $id);
            if (!$$var->isValid()) {
                throw new Exception(_(sprintf('Invalid %s', $var)));
            }
            $TaskType = new TaskType($_REQUEST['type']);
            if (!$TaskType->isValid()) {
                throw new Exception(_('Invalid Task Type'));
            }
            if ($type == 'host') {
                $Image = $$var->getImage();
                if (!$Image->isValid()) {
                    throw new Exception(_('Invalid image assigned to host'));
                }
                if ($TaskType->isCapture()
                    && $Image->get('protected')
                ) {
                    throw new Exception(
                        sprintf(
                            '%s: %s %s',
                            _('Image'),
                            $Image->get('name'),
                            _('is protected')
                        )
                    );
                }
                $taskName = sprintf(
                    '%s %s',
                    _('Quick'),
                    $TaskType->get('name')
                );
            } elseif ($type == 'group') {
                if ($TaskType->isMulticast()
                    && !$$var->doMembersHaveUniformImages()
                ) {
                    throw new Exception(
                        _('Hosts do not have the same image assigned')
                    );
                }
                $taskName = (
                    $TaskType->isMulticast() ?
                    _('Multicast Quick Deploy') :
                    _('Group Quick Deploy')
                );
            }
            $enableSnapins = $TaskType->get('id') == 17 ? false : -1;
            $enableDebug = in_array(
                $TaskType->get('id'),
                array(3, 15, 16)
            );
            $$var->createImagePackage(
                $TaskType->get('id'),
                $taskName,
                false,
                $enableDebug,
                $enableSnapins,
                $type === 'group',
                self::$FOGUser->get('name'),
                false,
                false,
                $TaskType->isInitNeededTasking() || $TaskType->get('id') == 14
            );
            self::setMessage(
                sprintf(
                    '%s %s %s',
                    _('Successfully created'),
                    _($type),
                    _('tasking')
                )
            );
            self::redirect("?node=$this->node");
        } catch (Exception $e) {
            printf(
                '<div class="task-start-failed"><p>%s</p><p>%s</p></div>',
                _('Failed to create task'),
                $e->getMessage()
            );
        }
    }
    /**
     * Host deploy action.
     *
     * @return void
     */
    public function hostdeploy()
    {
        $this->_tasking('host');
    }
    /**
     * Group deploy action.
     *
     * @return void
     */
    public function groupdeploy()
    {
        $this->_tasking('group');
    }
    /**
     * The advanced task display.
     *
     * @param string $type The type of the tasking to get.
     *
     * @throws Exception
     * @return void
     */
    private function _advanced($type)
    {
        $this->title = sprintf(
            '%s Advanced Actions',
            ucfirst($type)
        );
        global $id;
        unset($this->headerData);
        $types = array(
            'host',
            'group'
        );
        if (!in_array($type, $types)) {
            throw new Exception(_('Invalid object type passed'));
        }
        $this->templates = array(
            sprintf(
                '<a href="?node=%s&sub=%sdeploy&id=${id}&type=${type}">'
                . '<i class="fa fa-${icon} fa-fw fa-2x"/></i><br/>${name}</a>',
                $this->node,
                $type
            ),
            '${description}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        foreach ((array)self::getClass('TaskTypeManager')
            ->find(
                array(
                    'access' => array(
                        'both',
                        $type
                    ),
                    'isAdvanced' => 1
                ),
                'AND',
                'id'
            ) as &$TaskType
        ) {
            $this->data[] = array(
                'id' => $id,
                'type' => $TaskType->get('id'),
                'icon' => $TaskType->get('icon'),
                'name' => $TaskType->get('name'),
                'description' => $TaskType->get('description'),
            );
            unset($TaskType);
        }
        self::$HookManager
            ->processEvent(
                'TASK_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
    }
    /**
     * Display host advanced.
     *
     * @return void
     */
    public function hostadvanced()
    {
        $this->_advanced('host');
    }
    /**
     * Display group advanced.
     *
     * @return void
     */
    public function groupadvanced()
    {
        $this->_advanced('group');
    }
    /**
     * For cancelling/forcing tasks.
     *
     * @return void
     */
    public function activePost()
    {
        if (!self::$ajax) {
            $this->_nonajax();
        }
        self::getClass('TaskManager')->cancel($_REQUEST['task']);
        exit;
    }
    /**
     * Forces a task to start.
     *
     * @return void
     */
    public function forceTask()
    {
        global $id;
        try {
            $Task = new Task($id);
            if (!$Task->isValid()) {
                throw new Exception(_('Invalid task'));
            }
            self::$HookManager
                ->processEvent(
                    'TASK_FORCE',
                    array(
                        'Task' => &$Task
                    )
                );
            $Task->set('isForced', 1)->save();
            $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        if (self::$ajax) {
            echo json_encode($result);
            exit;
        }
        if ($result['error']) {
            self::fatalError($result['error']);
        } else {
            self::redirect(
                sprintf(
                    '?node=%s',
                    $this->node
                )
            );
        }
    }
    /**
     * Display active multicast tasks.
     *
     * @return void
     */
    public function activemulticast()
    {
        $this->title = 'Active Multi-cast Tasks';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler1"/>'
            . '<label for="toggler1"></label>',
            _('Task Name'),
            _('Hosts'),
            _('Start Time'),
            _('State'),
            _('Status'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class='
            . '"toggle-action" id="mctask-${id}"/>'
            . '<label for="mctask-${id}"></label>',
            '${name}',
            '${count}',
            '${start_date}',
            '${state}',
            '${percent}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false',
                'task-id' => '${id}'
            ),
            array(
                'class' => 'c'
            ),
            array(
                'class' => 'c'
            ),
            array(
                'class' => 'c'
            ),
            array(
                'class' => 'c'
            ),
            array(
                'width' => 40,
                'class' => 'c'
            )
        );
        $find = array(
            'stateID' => self::fastmerge(
                (array) self::getQueuedStates(),
                (array) self::getProgressState()
            )
        );
        foreach ((array)self::getClass('MulticastSessionManager')
            ->find($find) as &$MulticastSession
        ) {
            $TaskState = $MulticastSession->getTaskState();
            if (!$TaskState->isValid()) {
                continue;
            }
            $this->data[] = array(
                'id' => $MulticastSession->get('id'),
                'name' => (
                    $MulticastSession->get('name') ?
                    $MulticastSession->get('name') :
                    _('MulticastTask')
                ),
                'count' => (
                    self::getClass('MulticastSessionAssociationManager')
                    ->count(array('msID' => $MulticastSession->get('id')))
                ),
                'start_date' => self::formatTime(
                    $MulticastSession->get('starttime'),
                    'Y-m-d H:i:s'
                ),
                'state' => (
                    $TaskState->get('name') ?
                    $TaskState->get('name') :
                    ''
                ),
                'percent' => $MulticastSession->get('percent'),
            );
            unset($TaskState, $MulticastSession);
        }
        self::$HookManager
            ->processEvent(
                'TaskActiveMulticastData',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
    }
    /**
     * Removes multicast sessions.
     *
     * @return void
     */
    public function activemulticastPost()
    {
        if (!self::$ajax) {
            $this->_nonajax();
        }
        $MulticastSessionIDs = (array)$_REQUEST['task'];
        $TaskIDs = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            array(
                'msID' => $MulticastSessionIDs
            ),
            'taskID'
        );
        self::getClass('TaskManager')->cancel($TaskIDs);
        self::getClass('MulticastSessionManager')->cancel($_REQUEST['task']);
        unset($MulticastSessionIDs);
        exit;
    }
    /**
     * Displays active snapin tasks.
     *
     * @return void
     */
    public function activesnapins()
    {
        $this->title = 'Active Snapins';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler2"/><label for="'
            . 'toggler2"></label>',
            _('Host Name'),
            _('Snapin'),
            _('Start Time'),
            _('State'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class='
            . '"toggle-action" id="sntasks-${id}"/><label for="'
            . 'sntasks-${id}"></label>',
            sprintf(
                '<p><a href="?node=host&sub=edit&id=${host_id}" title='
                . '"%s">${host_name}</a></p><small>${host_mac}</small>',
                _('Edit Host')
            ),
            '${name}',
            '${startDate}',
            '${state}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16,
                'task-id'=>'${id}'
            ),
            array(
                'class' => 'l',
                'width' => 50
            ),
            array(
                'class' => 'l',
                'width' => 50
            ),
            array(
                'class' => 'l',
                'width' => 50
            ),
            array(
                'class' => 'r',
                'width' => 40
            )
        );
        $activestate = self::fastmerge(
            (array) self::getQueuedStates(),
            (array) self::getProgressState()
        );
        foreach ((array)self::getClass('SnapinTaskManager')
            ->find(
                array('stateID' => $activestate)
            ) as &$SnapinTask
        ) {
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                continue;
            }
            $SnapinJob = $SnapinTask->getSnapinJob();
            if (!$SnapinJob->isValid()) {
                continue;
            }
            $Host = $SnapinJob->getHost();
            if (!$Host->isValid()) {
                continue;
            }
            if ($Host->get('snapinjob')->get('id') != $SnapinJob->get('id')) {
                continue;
            }
            $state = $SnapinJob->get('stateID');
            $inArr = in_array($state, $activestate);
            if (!$inArr) {
                continue;
            }
            $this->data[] = array(
                'id' => $SnapinTask->get('id'),
                'name' => $Snapin->get('name'),
                'host_id' => $Host->get('id'),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac')->__toString(),
                'startDate' => self::formatTime(
                    $SnapinTask->get('checkin'),
                    'Y-m-d H:i:s'
                ),
                'state' => self::getClass(
                    'TaskState',
                    $SnapinTask->get('stateID')
                )->get('name'),
                );
            unset($SnapinTask, $Snapin, $SnapinJob, $Host);
        }
        self::$HookManager
            ->processEvent(
                'TaskActiveSnapinsData',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
    }
    /**
     * Redirect if item is not ajax called.
     *
     * @return void
     */
    private function _nonajax()
    {
        self::setMessage(
            _('Cannot cancel tasks this way')
        );
        self::redirect($this->formAction);
    }
    /**
     * Cancels and snapin taskings.
     *
     * @return void
     */
    public function activesnapinsPost()
    {
        if (!self::$ajax) {
            $this->_nonajax();
        }
        $SnapinTaskIDs = (array)$_REQUEST['task'];
        if (count($SnapinTaskIDs) > 0) {
            $SnapinJobIDs = self::getSubObjectIDs(
                'SnapinTask',
                array(
                    'id' => $SnapinTaskIDs
                ),
                'jobID'
            );
            self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        }
        if (count($SnapinJobIDs) > 0) {
            $HostIDs = self::getSubObjectIDs(
                'SnapinJob',
                array(
                    'id' => $SnapinJobIDs
                ),
                'hostID'
            );
        }
        if (count($HostIDs) > 0) {
            $SnapTaskIDs = self::getSubObjectIDs(
                'SnapinTask',
                array(
                    'jobID' => $SnapinJobIDs,
                )
            );
            $TaskIDs = array_diff(
                $SnapTaskIDs,
                $SnapinTaskIDs
            );
        }
        if (count($TaskIDs) < 1) {
            $TaskIDs = self::getSubObjectIDs(
                'Task',
                array(
                    'hostID' => $HostIDs,
                    'typeID' => array(
                        12,
                        13
                    )
                )
            );
            self::getClass('TaskManager')->cancel($TaskIDs);
        }
        exit;
    }
    /**
     * Active scheduled tasks (delayed or cron)
     *
     * @return void
     */
    public function activescheduled()
    {
        $this->title = 'Scheduled Tasks';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler3"/><label for="'
            . 'toggler3"></label>',
            _('Host/Group Name'),
            _('Is Group'),
            _('Task Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active'),
            _('Type'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class='
            . '"toggle-action" id="sctasks-${id}"/><label for="'
            . 'sctasks-${id}"></label>',
            '<a href="?node=${hostgroup}&sub=edit&id=${hostgroupid}" title='
            . '"Edit ${nametype}: ${hostgroupname}">${hostgroupname}</a>${extra}',
            '${groupbased}',
            '<span class="icon" title="${hover}">${details_taskname}</span>',
            '${task_type}',
            '<small>${start_time}</small>',
            '${active}',
            '${type}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false',
                'task-id' => '${id}'
            ),
            array(
                'width' => 100,
                'class' => 'l'
            ),
            array(
                'width' => 25,
                'class' => 'l'
            ),
            array(
                'width' => 110,
                'class' => 'l'
            ),
            array(
                'width' => 80,
                'class' => 'c'
            ),
            array(
                'width' => 70,
                'class' => 'c'
            ),
            array(
                'width' => 30,
                'class' => 'c'
            ),
            array(
                'width' => 80,
                'class' => 'c'
            ),
        );
        foreach ((array)self::getClass('ScheduledTaskManager')
            ->find() as &$ScheduledTask
        ) {
            $method = 'getHost';
            if ($ScheduledTask->isGroupBased()) {
                $method = 'getGroup';
            }
            $ObjTest = $ScheduledTask->{$method}();
            if (!$ObjTest->isValid()) {
                continue;
            }
            $TaskType = $ScheduledTask->getTaskType();
            if (!$TaskType->isValid()) {
                continue;
            }
            $sID = $ScheduledTask->get('other2');
            if ($TaskType->isSnapinTasking()) {
                if ($TaskType->get('id') == 12
                    || $ScheduledTask->get('other2') == -1
                ) {
                    $hover = _('All snapins');
                } elseif ($TaskType->get('id') == 13) {
                    $snapin = new Snapin($sID);
                    if (!$snapin->isValid()) {
                        $hover = _('Invalid snapin');
                    } else {
                        $hover = _('Snapin to be installed')
                            . ': '
                            . $snapin->get('name');
                    }
                }
            }
            $this->data[] = array(
                'id' => $ScheduledTask->get('id'),
                'start_time' => $ScheduledTask->getTime(),
                'groupbased' => (
                    $ScheduledTask->isGroupBased() ?
                    _('Yes') :
                    _('No')
                ),
                'active' => (
                    $ScheduledTask->isActive() ?
                    _('Yes') :
                    _('No')
                ),
                'type' => $ScheduledTask->getScheduledType(),
                'hostgroup' => (
                    $ScheduledTask->isGroupBased() ?
                    _('group') :
                    _('host')
                ),
                'hostgroupname' => $ObjTest->get('name'),
                'hostgroupid' => $ObjTest->get('id'),
                'details_taskname' => $ScheduledTask->get('name'),
                'task_type' => $TaskType->get('name'),
                'extra' => (
                    $ScheduledTask->isGroupBased() ?
                    '' :
                    sprintf(
                        '<br/><small>%s</small>',
                        $ObjTest->get('mac')->__toString()
                    )
                ),
                'nametype' => get_class($ObjTest),
                'hover' => $hover
            );
            unset($ScheduledTask, $ObjTest, $TaskType);
        }
        self::$HookManager
            ->processEvent(
                'TaskScheduledData',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
    }
    /**
     * Canceled tasks for us.
     *
     * @return void
     */
    public function activescheduledPost()
    {
        if (!self::$ajax) {
            $this->_nonajax();
        }
        self::getClass('ScheduledTaskManager')
            ->destroy(
                array('id' => $_REQUEST['task'])
            );
        exit;
    }
}
