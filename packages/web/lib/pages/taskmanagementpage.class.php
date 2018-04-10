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
                'class' => 'filter-false',
                'task-id' => '${id}'
            ),
            array(
                'width' => 65,
                'id' => 'host-${host_id}'
            ),
            array(
                'width' => 120,
            ),
            array(
                'width' => 70,
            ),
            array(
                'width' => 100,
            ),
            array(
                'width' => 70,
            ),
            array(
                'width' => 50,
                'class' => 'filter-false'
            ),
        );
        /**
         * Lamda function to return data either by list or search.
         *
         * @param object $Image the object to use.
         *
         * @return void
         */
        self::$returnData = function (&$Task) {
            $tmpTask = self::getClass(
                'Task',
                $Task->id
            );
            $SnapinTrue = $tmpTask->isSnapinTasking();
            if ($SnapinTrue) {
                $SnapinJob = self::getClass(
                    'SnapinJob',
                    $Task->host->snapinjob->id
                );
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
                        $tmpTask->cancel();
                        return;
                    }
                }
            }
            if ($Task->state->id < 3) {
                if ($Task->type->id < 3) {
                    if ($Task->isForced) {
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
            }
            $this->data[] = array(
                'startedby' => $Task->createdBy,
                'details_taskforce' => $forcetask,
                'id' => $Task->id,
                'name' => $Task->name,
                'time' => self::formatTime(
                    $Task->createdTime,
                    'Y-m-d H:i:s'
                ),
                'state' => $Task->state->name,
                'forced' => $Task->isForced,
                'type' => $Task->type->name,
                'elapsed' => $Task->timeElapsed,
                'remains' => $Task->timeRemaining,
                'percent' => $Task->pct,
                'copied' => $Task->dataCopied,
                'total' => $Task->dataTotal,
                'bpm' => $Task->bpm,
                'details_taskname' => (
                    $Task->name ?
                    sprintf(
                        '<div class="task-name">%s</div>',
                        $Task->name
                    ) :
                    ''
                ),
                'host_id' => $Task->host->id,
                'host_name' => $Task->host->name,
                'host_mac' => $Task->host->mac,
                'icon_state' => $Task->state->icon,
                'icon_type' => $Task->type->icon,
                'state_id' => $Task->state->id,
                'image_name' => $Task->image->name,
                'image_id' => $Task->image->id,
                'node_name' => $Task->storagenode->name
            );
            unset($tmpTask, $Task);
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
        $this->title = _('Active Tasks');
        $this->data = array();
        $find = array(
            'stateID' => self::fastmerge(
                (array) self::getQueuedStates(),
                (array) self::getProgressState()
            )
        );
        Route::active('task');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->tasks;
        if (count($items) > 0) {
            array_walk(
                $items,
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
        unset($items);
        if (self::$ajax) {
            return $this->render();
        }
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Parse and process/return the active item.
     *
     * @return void
     */
    public function displayActive()
    {
        echo '<div class="tab-content col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * List all hosts.
     *
     * @return void
     */
    public function listhosts()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('All Hosts');
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
            ),
            array(
                'width' => 60,
                'class' => 'filter-false'
            ),
        );
        Route::listem(
            'host',
            'name',
            false,
            array('pending' => array('0', '', null))
        );
        $Hosts = json_decode(
            Route::getData()
        );
        $Hosts = $Hosts->hosts;
        foreach ((array)$Hosts as &$Host) {
            $this->data[] = array(
                'id' => $Host->id,
                'name' => $Host->name,
                'mac' => $Host->primac,
                'imagename' => $Host->image->name,
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
        unset($Hosts);
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * List all groups.
     *
     * @return void
     */
    public function listgroups()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('All Groups');
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
                'class' => 'filter-false'
            ),
        );
        Route::listem('group');
        $Groups = json_decode(
            Route::getData()
        );
        $Groups = $Groups->groups;
        foreach ((array)$Groups as &$Group) {
            $this->data[] = array(
                'id' => $Group->id,
                'name' => $Group->name,
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
        unset($Groups);
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
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
            $typeID = filter_input(INPUT_GET, 'type');
            $TaskType = new TaskType($typeID);
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
            echo '<div class="col-xs-9">';
            echo '<div class="panel panel-success">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Tasked Successfully');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo _('Tasked successfully, click active tasks to view in line.');
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="col-xs-9">';
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Failed to create task');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo $e->getMessage();
            echo '</div>';
            echo '</div>';
            echo '</div>';
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _(
            sprintf(
                '%s Advanced Actions',
                ucfirst($type)
            )
        );
        global $id;
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
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        Route::listem(
            'tasktype',
            'id',
            false,
            array(
                'access' => array('both', $type),
                'isAdvanced' => 1
            )
        );
        $TaskTypes = json_decode(
            Route::getData()
        );
        $TaskTypes = $TaskTypes->tasktypes;
        foreach ((array)$TaskTypes as &$TaskType) {
            $this->data[] = array(
                'id' => $id,
                'type' => $TaskType->id,
                'icon' => $TaskType->icon,
                'name' => $TaskType->name,
                'description' => $TaskType->description
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
        unset($TaskTypes);
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
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
        self::getClass('TaskManager')->cancel($_POST['task']);
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('Active Multi-cast Tasks');
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
                'class' => 'filter-false',
                'task-id' => '${id}'
            ),
            array(
            ),
            array(
            ),
            array(
            ),
            array(
            ),
            array(
                'width' => 40
            )
        );
        Route::active('multicastsession');
        $Sessions = json_decode(
            Route::getData()
        );
        $Sessions = $Sessions->multicastsessions;
        foreach ((array)$Sessions as &$MulticastSession) {
            $TaskState = $MulticastSession->state;
            if (!$TaskState->id) {
                continue;
            }
            $this->data[] = array(
                'id' => $MulticastSession->id,
                'name' => (
                    $MulticastSession->name ?: _('MulticastTask')
                ),
                'count' => (
                    self::getClass('MulticastSessionAssociationManager')
                    ->count(array('msID' => $MulticastSession->id))
                ),
                'start_date' => self::formatTime(
                    $MulticastSession->starttime,
                    'Y-m-d H:i:s'
                ),
                'state' => (
                    $TaskState->name ?: ''
                ),
                'percent' => $MulticastSession->percent,
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
        unset($Sessions);
        if (self::$ajax) {
            return $this->render();
        }
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
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
        $MulticastSessionIDs = (array)$_POST['task'];
        $TaskIDs = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            array(
                'msID' => $MulticastSessionIDs
            ),
            'taskID'
        );
        self::getClass('TaskManager')->cancel($TaskIDs);
        self::getClass('MulticastSessionManager')->cancel($_POST['task']);
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
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
                'class' => 'filter-false',
                'width' => 16,
                'task-id'=>'${id}'
            ),
            array(
                'width' => 50
            ),
            array(
                'width' => 50
            ),
            array(
                'width' => 50
            ),
            array(
                'width' => 40
            )
        );
        Route::active('snapintask');
        $SnapinTasks = json_decode(
            Route::getData()
        );
        $activestate = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $SnapinTasks = $SnapinTasks->snapintasks;
        foreach ((array)$SnapinTasks as &$SnapinTask) {
            $Snapin = $SnapinTask->snapin;
            if (!$Snapin->id) {
                continue;
            }
            $SnapinJob = $SnapinTask->snapinjob;
            if (!$SnapinJob->id) {
                continue;
            }
            Route::indiv('host', $SnapinJob->hostID);
            $Host = json_decode(
                Route::getData()
            );
            if (!$Host->id) {
                continue;
            }
            $state = $SnapinJob->stateID;
            $inArr = in_array($state, $activestate);
            if (!$inArr) {
                continue;
            }
            $this->data[] = array(
                'id' => $SnapinTask->id,
                'name' => $Snapin->name,
                'host_id' => $Host->id,
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'startDate' => self::formatTime(
                    $SnapinTask->checkin,
                    'Y-m-d H:i:s'
                ),
                'state' => $SnapinTask->state->name
            );
            unset(
                $SnapinTask,
                $Snapin,
                $SnapinJob,
                $Host
            );
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
        unset($SnapinTasks);
        if (self::$ajax) {
            return $this->render();
        }
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
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
        $SnapinTaskIDs = (array)$_POST['task'];
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('Scheduled Tasks');
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
                'class' => 'filter-false',
                'task-id' => '${id}'
            ),
            array(
                'width' => 100
            ),
            array(
                'width' => 25
            ),
            array(
                'width' => 110
            ),
            array(
                'width' => 80
            ),
            array(
                'width' => 70
            ),
            array(
                'width' => 30
            ),
            array(
                'width' => 80
            )
        );
        Route::active('scheduledtask');
        $ScheduledTasks = json_decode(
            Route::getData()
        );
        $ScheduledTasks = $ScheduledTasks->scheduledtasks;
        foreach ((array)$ScheduledTasks as &$ScheduledTask) {
            $method = 'host';
            if ($ScheduledTask->isGroupTask) {
                $method = 'group';
            }
            $ObjTest = $ScheduledTask->{$method};
            if (!$ObjTest->id) {
                continue;
            }
            $TaskType = $ScheduledTask->tasktype;
            if (!$TaskType->id) {
                continue;
            }
            $sID = $ScheduledTask->other2;
            if ($TaskType->isSnapinTasking) {
                if ($TaskType->id == 12
                    || $ScheduledTask->other2 == -1
                ) {
                    $hover = _('All snapins');
                } elseif ($TaskType->id == 13) {
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
                'id' => $ScheduledTask->id,
                'start_time' => $ScheduledTask->runtime,
                'groupbased' => (
                    $ScheduledTask->isGroupTask ?
                    _('Yes') :
                    _('No')
                ),
                'active' => (
                    $ScheduledTask->isActive ?
                    _('Yes') :
                    _('No')
                ),
                'type' => $ScheduledTask->type == 'C' ? _('Cron') : _('Delayed'),
                'hostgroup' => (
                    $ScheduledTask->isGroupTask ?
                    _('group') :
                    _('host')
                ),
                'hostgroupname' => $ObjTest->name,
                'hostgroupid' => $ObjTest->id,
                'details_taskname' => $ScheduledTask->name,
                'task_type' => $TaskType->name,
                'extra' => (
                    $ScheduledTask->isGroupTask ?
                    '' :
                    sprintf(
                        '<br/>%s',
                        $ObjTest->primac
                    )
                ),
                'nametype' => $method,
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
        if (self::$ajax) {
            return $this->render();
        }
        $this->displayActive();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
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
                array('id' => $_POST['task'])
            );
        exit;
    }
}
