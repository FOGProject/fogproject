<?php
class TaskManagementPage extends FOGPage {
    public $node = 'task';
    public function __construct($name = '') {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->menu = array(
            'search' => $this->foglang[NewSearch],
            'active' => $this->foglang[ActiveTasks],
            'listhosts' => sprintf($this->foglang[ListAll],$this->foglang[Hosts]),
            'listgroups' => sprintf($this->foglang[ListAll],$this->foglang[Groups]),
            'active-multicast' => $this->foglang[ActiveMCTasks],
            'active-snapins' => $this->foglang[ActiveSnapins],
            'scheduled' => $this->foglang[ScheduledTasks],
        );
        $this->subMenu = array();
        $this->notes = array();
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu' => &$this->menu,'submenu' => &$this->subMenu,'id' => &$this->id,'notes' => &$this->notes));
        // Header row
        $this->headerData = array(
            _('Started By:'),
            _('Hostname<br><small>MAC</small>'),
            '',
            '',
            _('Start Time'),
            _('Status'),
            _('Actions'),
        );
        // Row templates
        $this->templates = array(
            '${startedby}',
            '<p><a href="?node=host&sub=edit&id=${host_id}" title="' . _('Edit Host') . '">${host_name}</a></p><small>${host_mac}</small>',
            '',
            '${details_taskname}',
            '<small>${time}</small>',
            '<span class="icon icon-${icon_state}" title="${state}"></span> <span class="icon icon-${icon_type}" title="${type}"></span>',
            '${columnkill}',
        );
        // Row attributes
        $this->attributes = array(
            array('width' => 65, 'class' => 'l', 'id' => 'host-${host_id}'),
            array('width' => 120, 'class' => 'l'),
            array(),
            array('width' => 110, 'class' => 'l'),
            array('width' => 70, 'class' => 'r'),
            array('width' => 100, 'class' => 'r'),
            array('width' => 50, 'class' => 'r'),
            array(),
        );
    }
    // Pages
    public function index() {$this->active();}
        public function search_post() {
            // Find data -> Push data
            foreach ($this->getClass('TaskManager')->search() AS $Task) {
                if ($Task && $Task->isValid()) {
                    $Host = $Task->getHost();
                    $this->data[] = array(
                        'columnkill' => $Task->get('stateID') == 1 || $Task->get('stateID') == 2 || $Task->get('stateID') == '3' ? '${details_taskforce} <a href="?node=task&sub=cancel-task&id=${id}"><i class="icon fa fa-minus-circle" title="' . _('Cancel Task') . '"></i></a>' : '',
                        'startedby' => $Task->get('createdBy'),
                        'id' => $Task->get('id'),
                        'name' => $Task->get('name'),
                        'time' => $this->formatTime($Task->get('createdTime'),'Y-m-d H:i:s'),
                        'state' => $Task->getTaskStateText(),
                        'forced' => ($Task->get('isForced') ? '1' : '0'),
                        'type' => $Task->getTaskTypeText(),
                        'percentText' => $Task->get('percent'),
                        'class' => ++$i % 2 ? 'alt2' : 'alt1',
                        'width' => 600 * ($Task->get('percent')/100),
                        'elapsed' => $Task->get('timeElapsed'),
                        'remains' => $Task->get('timeRemaining'),
                        'percent' => $Task->get('pct'),
                        'copied' => $Task->get('dataCopied'),
                        'total' => $Task->get('dataTotal'),
                        'bpm' => $Task->get('bpm'),
                        'details_taskname' => ($Task->get('name')	? sprintf('<div class="task-name">%s</div>', $Task->get('name')) : ''),
                        'details_taskforce' => ($Task->get('isForced') ? sprintf('<span class="icon fa fa-play" title="%s"></i>', _('Task forced to start')) : ($Task->get('typeID') < 3 && $Task->get('stateID') < 3 ? sprintf('<a href="?node=task&sub=force-task&id=%s"><i class="icon fa fa-step-forward" title="%s"></i></a>', $Task->get('id'),_('Force task to start')) : '&nbsp;')),
                        'host_id' => $Task->get('hostID'),
                        'host_name' => $Host ? $Host->get('name') : '',
                        'host_mac' => $Host ? $Host->get('mac')->__toString() : '',
                        'icon_state' => strtolower(str_replace(' ', '', $Task->getTaskStateText())),
                        'icon_type' => strtolower(preg_replace(array('#[[:space:]]+#', '#[^\w-]#', '#\d+#', '#-{2,}#'), array('-', '', '', '-'), $Task->getTaskTypeText())),
                    );
                }
            }
            // Hook
            $this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
            // Output
            $this->render();
        }
    // List all Hosts
    public function listhosts() {
        // Set title
        $this->title = _('All Hosts');
        // Header Row
        $this->headerData = array(
            _('Host Name'),
            _('Image Name'),
            _('Deploy'),
        );
        // Row templates
        $this->templates = array(
            '<a href="?node=host&sub=edit&id=${id}"/>${host_name}</a><br /><small>${host_mac}</small>',
            '<small>${image_name}</small>',
            '${downLink}&nbsp;${uploadLink}&nbsp;${advancedLink}',
        );
        // Row attributes
        $this->attributes = array(
            array('width' => 55, 'class' => 'l'),
            array('width' => 60, 'class' => 'c'),
            array('width' => 60, 'class' => 'r'),
        );
        foreach($this->getClass('HostManager')->find('','','','','','name') AS $Host) {
            if ($Host && $Host->isValid() && !$Host->get('pending')) {
                $imgUp = '<a href="?node=task&sub=hostdeploy&type=2&id=${id}"><i class="icon hand fa fa-arrow-up" title="'._('Upload').'"></i></a>';
                $imgDown = '<a href="?node=task&sub=hostdeploy&type=1&id=${id}"><i class="icon hand fa fa-arrow-down" title="'._('Download').'"></i></a>';
                $imgAdvanced = '<a href="?node=task&sub=hostadvanced&id=${id}#host-tasks"><i class="icon hand fa fa-arrows-alt" title="'._('Advanced').'"></i></a>';
                $this->data[] = array(
                    'uploadLink' => $imgUp,
                    'downLink' => $imgDown,
                    'advancedLink' => $imgAdvanced,
                    'id' => $Host->get('id'),
                    'host_name' => $Host->get('name'),
                    'host_mac' => $Host->get('mac')->__toString(),
                    'image_name' => $Host->getImage()->get('name'),
                );
            }
        }
        // Hook
        $this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Render
        $this->render();
    }
    public function hostdeploy() {
        $Host = $this->getClass('Host',$_REQUEST[id]);
        $taskTypeID = $_REQUEST['type'];
        $TaskType = $this->getClass('TaskType',$_REQUEST[type]);
        $snapin = '-1';
        $enableShutdown = false;
        $enableSnapins = ($_REQUEST['type'] == 17 ? false : -1);
        $taskName = 'Quick Deploy';
        try {
            if ($TaskType->isUpload() && $Host->getImage()->isValid() && $Host->getImage()->get('protected')) throw new Exception(sprintf('%s: %s %s: %s %s',_('Hostname'),$Host->get('name'),_('Image'),$Host->getImage()->get('name'),_('is protected')));
            $Host->createImagePackage($taskTypeID, $taskName, false, false, $enableSnapins, false, $this->FOGUser->get('name'));
            $this->FOGCore->setMessage('Successfully created tasking!');
            $this->FOGCore->redirect('?node=task&sub=active');
        } catch (Exception $e) {
            printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create deploy task'), $e->getMessage());
        }
    }
    public function hostadvanced() {
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes =  array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><img src="'.$this->imagelink.'${task_icon}" /><br />${task_name}</a>',
            '${task_desc}',
        );
        print "\n\t\t\t<div>";
        print "\n\t\t\t<h2>"._('Advanced Actions').'</h2>';
        // Find TaskTypes
        $TaskTypes = $this->getClass('TaskTypeManager')->find(array('access' => array('both', 'host'), 'isAdvanced' => '1'), 'AND', 'id');
        // Iterate -> Print
        foreach ((array)$TaskTypes AS $TaskType) {
            $this->data[] = array(
                'node' => $_REQUEST['node'],
                'sub' => 'hostdeploy',
                'id' => $_REQUEST['id'],
                'type'=> $TaskType->get('id'),
                'task_icon' => $TaskType->get('icon'),
                'task_name' => $TaskType->get('name'),
                'task_desc' => $TaskType->get('description'),
            );
        }
        // Hook
        $this->HookManager->processEvent('TASK_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print "</div>";
    }
    public function groupadvanced() {
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes =  array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><img src="'.$this->imagelink.'${task_icon}" /><br />${task_name}</a>',
            '${task_desc}',
        );
        print "\n\t\t\t<div>";
        print "\n\t\t\t<h2>"._('Advanced Actions').'</h2>';
        // Find TaskTypes
        $TaskTypes = $this->getClass('TaskTypeManager')->find(array('access' => array('both', 'group'), 'isAdvanced' => '1'), 'AND', 'id');
        // Iterate -> Print
        foreach ((array)$TaskTypes AS $TaskType) {
            $this->data[] = array(
                'node' => $_REQUEST['node'],
                'sub' => 'groupdeploy',
                'id' => $_REQUEST['id'],
                'type'=> $TaskType->get('id'),
                'task_icon' => $TaskType->get('icon'),
                'task_name' => $TaskType->get('name'),
                'task_desc' => $TaskType->get('description'),
            );
        }
        // Hook
        $this->HookManager->processEvent('TASK_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print "</div>";
    }
    public function listgroups() {
        $this->title = _('List all Groups');
        $this->headerData = array(
            _('Name'),
            _('Deploy'),
        );
        $this->attributes = array(
            array('width' => 55, 'class' => 'l'),
            array('width' => 60,'class' => 'c'),
        );
        $this->templates = array(
            '<a href="?node=group&sub=edit&id=${id}"/>${name}</a>',
            '${deployLink}&nbsp;${multicastLink}&nbsp;${advancedLink}',
        );
        $Groups = $this->getClass('GroupManager')->find();
        foreach ((array)$Groups AS $Group) {
            $deployLink = '<a href="?node=task&sub=groupdeploy&type=1&id=${id}"><i class="icon hand fa fa-arrow-down" title="'._('Download').'"></i></a>';
            $multicastLink = '<a href="?node=task&sub=groupdeploy&type=8&id=${id}"><i class="icon hand fa fa-share-alt" title="'._('Multicast').'"></i></a>';
            $advancedLink = '<a href="?node=task&sub=groupadvanced&id=${id}"><i class="icon hand fa fa-arrows-alt" title="'._('Advanced').'"></i></a>';
            $this->data[] = array(
                'deployLink' => $deployLink,
                'advancedLink' => $advancedLink,
                'multicastLink' => $multicastLink,
                'id' => $Group->get('id'),
                'name' => $Group->get('name'),
            );
        }
        // Hook
        $this->HookManager->processEvent('TasksListGroupData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Render
        $this->render();
    }
    public function groupdeploy() {
        $Group = $this->getClass('Group',$_REQUEST[id]);
        $taskTypeID = $_REQUEST['type'];
        $TaskType = $this->getClass('TaskType',$taskTypeID);
        $snapin = '-1';
        $enableShutdown = false;
        $enableSnapins = ($_REQUEST['type'] == 17 ? false : -1);
        $enableDebug = (in_array($_REQUEST['type'],array(3,15,16)) ? true : false);
        $imagingTasks = array(1,2,8,15,16,17,24);
        $taskName = ($taskTypeID == 8 ? 'Multicast Group Quick Deploy' : 'Group Quick Deploy');
        try {
            foreach ((array)$Group->get('hosts') AS $Host) {
                if ($Host && $Host->isValid() && $Host->get('task') && $Host->get('task')->isValid()) throw new Exception(_('One or more hosts are currently in a task'));
            }
            foreach ((array)$Group->get('hosts') AS $Host) {
                if ($Host && $Host->isValid()) {
                    if (in_array($taskTypeID,$imagingTasks) && !$Host->get('imageID')) throw new Exception(_('You need to assign an image to all of the hosts'));
                    if (!$Host->checkIfExist($taskTypeID)) throw new Exception(_('To setup download task, you must first upload an image'));
                }
            }
            foreach ((array)$Group->get('hosts') AS $Host) {
                if ($Host && $Host->isValid() && !$Host->get('pending')) $Host->createImagePackage($taskTypeID, $taskName, $enableShutdown, $enableDebug, $enableSnapins, true, $this->FOGUser->get('name'));
            }
            $this->FOGCore->setMessage('Successfully created Group tasking!');
            $this->FOGCore->redirect('?node=task&sub=active');
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect('?node=task&sub=listgroups');
        }
    }
    // Active Tasks
    public function active() {
        // Set title
        $this->form = "<center>".'<input type="button" id="taskpause" /></center><br/>';
        $this->title = _('Active Tasks');
        // Tasks
        $i = 0;
        foreach ((array)$this->getClass('TaskManager')->find(array('stateID' => array(1,2,3))) AS $Task) {
            if ($Task && $Task->isValid()) {
                $Host = new Host($Task->get('hostID'));
                if ($Host && $Host->isValid()) {
                    $this->data[] = array(
                        'columnkill' => '${details_taskforce} <a href="?node=task&sub=cancel-task&id=${id}"><i class="fa fa-minus-circle" title="' . _('Cancel Task') . '"></i></a>',
                        'startedby' => $Task->get('createdBy'),
                        'id' => $Task->get('id'),
                        'name' => $Task->get('name'),
                        'time' => $this->formatTime($Task->get('createdTime'),'Y-m-d H:i:s'),
                        'state' => $Task->getTaskStateText(),
                        'forced' => ($Task->get('isForced') ? '1' : '0'),
                        'type' => $Task->getTaskTypeText(),
                        'percentText' => $Task->get('percent'),
                        'class' => ++$i % 2 ? 'alt2' : 'alt1',
                        'width' => 600 * ($Task->get('percent')/100),
                        'elapsed' => $Task->get('timeElapsed'),
                        'remains' => $Task->get('timeRemaining'),
                        'percent' => $Task->get('pct'),
                        'copied' => $Task->get('dataCopied'),
                        'total' => $Task->get('dataTotal'),
                        'bpm' => $Task->get('bpm'),
                        'details_taskname'	=> ($Task->get('name')	? sprintf('<div class="task-name">%s</div>', $Task->get('name')) : ''),
                        'details_taskforce'	=> ($Task->get('isForced') ? sprintf('<i class="fa fa-play" title="%s"></i>', _('Task forced to start')) : ($Task->get('typeID') < 3 && $Task->get('stateID') < 3 ? sprintf('<a href="?node=task&sub=force-task&id=%s"><i class="fa fa-step-forward" title="%s"></i></a>', $Task->get('id'),_('Force task to start')) : '&nbsp;')),
                        'host_id'	=> $Host->get('id'),
                        'host_name'	=> $Host->get('name'),
                        'host_mac' => $Host->get('mac')->__toString(),
                        'icon_state' => strtolower(str_replace(' ', '', $Task->getTaskStateText())),
                        'icon_type'	=> strtolower(preg_replace(array('#[[:space:]]+#', '#[^\w-]#', '#\d+#', '#-{2,}#'), array('-', '', '', '-'), $Task->getTaskTypeText())),
                    );
                }
            }
        }
        // Hook
        $this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    // Active Tasks - Force Task Start
    public function force_task() {
        // Find
        $Task = $this->getClass('Task',$_REQUEST[id]);
        // Hook
        $this->HookManager->processEvent('TASK_FORCE', array('Task' => &$Task));
        // Force
        try {
            $result['success'] = $Task->set('isForced', '1')->save();
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        // Output
        if ($this->isAJAXRequest()) print json_encode($result);
        else {
            if ($result['error']) $this->fatalError($result['error']);
            else $this->FOGCore->redirect(sprintf('?node=%s', $this->node));
        }
    }
    // Active Tasks - Cancel Task
    public function cancel_task() {
        // Find
        $Task = $this->getClass('Task',$_REQUEST[id]);
        // Hook
        $this->HookManager->processEvent('TASK_CANCEL', array('Task' => &$Task));
        try {
            // Cancel task - will throw Exception on error
            $Task->cancel();
            // Success
            $result['success'] = true;
        } catch (Exception $e) {
            // Failure
            $result['error'] = $e->getMessage();
        }
        // Output
        if ($this->isAJAXRequest()) print json_encode($result);
        else {
            if ($result['error']) $this->fatalError($result['error']);
            else $this->FOGCore->redirect(sprintf('?node=%s', $this->node));
        }
    }
    public function remove_multicast_task() {
        $MulticastSession = $this->getClass('MulticastSessions',$_REQUEST[id]);
        $this->HookManager->processEvent('MULTICAST_TASK_CANCEL',array('MulticastSession' => &$MulticastSession));
        foreach($this->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $MulticastSession->get(id))) AS $MSA) {
            if ($MSA->isValid()) {
                $MS = $MSA->getMulticastSession();
                if ($MS->get(id) == $MulticastSession->get(id)) $MSA->getTask()->cancel();
            }
        }
    }
    public function active_multicast() {
        // Set title
        $this->title = _('Active Multi-cast Tasks');
        // Header row
        $this->headerData = array(
            _('Task Name'),
            _('Hosts'),
            _('Start Time'),
            _('State'),
            _('Status'),
            _('Kill')
        );
        // Row templates
        $this->templates = array(
            '${name}',
            '${count}',
            '${start_date}',
            '${state}',
            '${percent}',
            '<a href="?node=task&sub=remove-multicast-task&id=${id}"><i class="icon fa fa-minus-circle" title="Kill Task"></i></a>',
        );
        // Row attributes
        $this->attributes = array(
            array(),
            array('class' => 'c'),
            array('class' => 'c'),
            array('class' => 'c'),
            array('class' => 'c'),
            array('width' => 40, 'class' => 'c')
        );
        // Multicast data
        foreach ((array)$this->getClass('MulticastSessionsManager')->find(array('stateID' => array(1,2,3))) AS $MS) {
            $TS = $this->getClass('TaskState',$MS->get('stateID'));
            $this->data[] = array(
                'id' => $MS->get('id'),
                'name' => ($MS->get('name') ? $MS->get('name') : 'Multicast Task'),
                'count' => $this->getClass('MulticastSessionsAssociationManager')->count(array('msID' => $MS->get('id'))),
                'start_date' => $MS->get('starttime'),
                'state' => ($TS->get('name') ? $TS->get('name') : null),
                'percent'	=> $MS->get('percent'),
            );
        }
        // Hook
        $this->HookManager->processEvent('TaskActiveMulticastData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function active_snapins() {
        // Set title
        $this->title = 'Active Snapins';
        // Header row
        $this->headerData = array(
            _('Host Name'),
            _('Snapin'),
            _('Start Time'),
            _('State'),
            _('Kill'),
        );
        $this->templates = array(
            '${host_name}',
            '<form method="post" method="?node=task&sub=active-snapins">${name}',
            '${startDate}',
            '${state}',
            '<input type="checkbox" id="${id}" class="delid" name="rmid" value="${id}" onclick="this.form.submit()" title="Kill Task" /><label for="${id}" class="icon fa fa-minus-circle" title="'._('Delete').'">&nbsp;</label></form>',
        );
        $this->attributes = array(
            array(),
            array('class' => 'c'),
            array('class' => 'c'),
            array('class' => 'c'),
            array('width' => 40, 'class' => 'c'),
        );
        $SnapinTasks = $this->getClass('SnapinTaskManager')->find(array('stateID' => array(-1,0,1)));
        foreach ((array)$SnapinTasks AS $SnapinTask) {
            if ($SnapinTask && $SnapinTask->isValid()) {
                $SnapinJobs = $this->getClass('SnapinJobManager')->find(array('id' => $SnapinTask->get('jobID')));
                foreach ((array)$SnapinJobs AS $SnapinJob) {
                    if ($SnapinJob && $SnapinJob->isValid()) {
                        $Host = $SnapinJob->getHost();
                        if ($Host && $Host->isValid()) {
                            foreach($Host->get('snapins') AS $Snapin) {
                                if ($Snapin && $Snapin->isValid() && $Snapin->get('id') == $SnapinTask->get('snapinID')) {
                                    $this->data[] = array(
                                        'id' => $SnapinTask->get('id'),
                                        'name' => $Snapin->get('name'),
                                        'hostID' => $Host->get('id'),
                                        'host_name' => $Host->get('name'),
                                        'startDate' => $SnapinTask->get('checkin'),
                                        'state' => ($SnapinTask->get('stateID') == 0 ? 'Queued' : ($SnapinTask->get('stateID') == 1 ? 'In-Progress' : 'N/A')),
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        // Hook
        $this->HookManager->processEvent('TaskActiveSnapinsData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function active_snapins_post() {
        if(isset($_REQUEST['rmid'])) {
            // Get the snapin task.
            $SnapinTask = $this->getClass('SnapinTask',$_REQUEST[rmid]);
            // Get the job associated with the task.
            $SnapinJob = $SnapinTask->getSnapinJob();
            // Get the referenced host.
            $Host = $SnapinJob->getHost();
            // Get the active task.
            $Task = current($this->getClass('TaskManager')->find(array('hostID' => $Host->get('id'),'stateID' => array(1,2,3))));
            // Check the Jobs to Snapin tasks to verify if this is the only one.
            $SnapinJobManager = $this->getClass('SnapinTaskManager')->find(array('jobID' => $SnapinJob->get('id')));
            // This task is the last task, destroy the job and the task
            if (count($SnapinJobManager) <= 1) {
                $SnapinJob->destroy();
                if ($Task) $Task->cancel();
            }
            // Destroy the individual task.
            $SnapinTask->destroy();
            // Redirect to the current page.
            $this->FOGCore->redirect("?node=".$this->node."&sub=active-snapins");
        }
    }
    public function scheduled() {
        // Set title
        $this->title = 'Scheduled Tasks';
        // Header row
        $this->headerData = array(
            _('Name:'),
            _('Is Group'),
            _('Task Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active/Type'),
            _('Kill'),
        );
        // Row templates
        $this->templates = array(
            '<a href="?node=${hostgroup}&sub=edit&id=${id}" title="Edit ${hostgroupname}">${hostgroupname}</a>',
            '${groupbased}<form method="post" action="?node=task&sub=scheduled">',
            '${details_taskname}',
            '${task_type}',
            '<small>${time}</small>',
            '${active}/${type}',
            '<input type="checkbox" name="rmid" id="r${schedtaskid}" class="delid" value="${schedtaskid}" onclick="this.form.submit()" /><label for="r${schedtaskid}" class="icon fa fa-minus-circle" title="'._('Delete').'">&nbsp;</label></form>',
        );
        // Row attributes
        $this->attributes = array(
            array('width' => 120, 'class' => 'l'),
            array(),
            array('width' => 110, 'class' => 'l'),
            array('class' => 'c', 'width' => 80),
            array('width' => 70, 'class' => 'c'),
            array('width' => 100, 'class' => 'c', 'style' => 'padding-right: 10px'),
            array('class' => 'c'),
        );
        foreach ((array)$this->getClass('ScheduledTaskManager')->find() AS $task) {
            if ($task && $task->isValid()) {
                $Host = $task->getHost();
                if ($Host && $Host->isValid()) {
                    $taskType = $task->getTaskType();
                    if ($task->get('type') == 'C') $taskTime = FOGCron::parse($task->get('minute').' '.$task->get('hour').' '.$task->get('dayOfMonth').' '.$task->get('month').' '.$task->get('dayOfWeek'));
                    else $taskTime = $task->get('scheduleTime');
                    $taskTime = $this->nice_date()->setTimestamp($taskTime);
                    $hostGroupName = ($task->isGroupBased() ? $task->getGroup() : $task->getHost());
                    $this->data[] = array(
                        'columnkill' => '${details_taskforce} <a href="?node=task&sub=cancel-task&id=${id}"><i class="icon fa fa-minus-circle" title="' . _('Cancel Task') . '"></i></a>',
                        'hostgroup' => $task->isGroupBased() ? 'group' : 'host',
                        'hostgroupname' => $hostGroupName,
                        'id' => $hostGroupName->get('id'),
                        'groupbased' => $task->isGroupBased() ? _('Yes') : _('No'),
                        'details_taskname' => $task->get('name'),
                        'time' => $this->formatTime($taskTime),
                        'active' => $task->get('isActive') ? 'Yes' : 'No',
                        'type' => $task->get('type') == 'C' ? 'Cron' : 'Delayed',
                        'schedtaskid' => $task->get('id'),
                        'task_type' => $taskType,
                    );
                }
            }
        }
        // Hook
        $this->HookManager->processEvent('TaskScheduledData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function scheduled_post() {
        if(isset($_REQUEST['rmid'])) {
            $this->HookManager->processEvent('TaskScheduledRemove');
            if (!$this->getClass('ScheduledTask',$_REQUEST[rmid])->destroy()) $this->HookManager->processEvent('TaskSchedulerRemoveFail');
            else $this->HookManager->processEvent('TaskSchedulerRemoveSuccess');
            $this->FOGCore->redirect($this->formAction);
        }
    }
}
