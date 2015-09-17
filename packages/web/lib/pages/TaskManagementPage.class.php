<?php
class TaskManagementPage extends FOGPage {
    public $node = 'task';
    public function __construct($name = '') {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->menu = array(
            search=>$this->foglang[NewSearch],
            active=>$this->foglang[ActiveTasks],
            listhosts=>sprintf($this->foglang[ListAll],$this->foglang[Hosts]),
            listgroups=>sprintf($this->foglang[ListAll],$this->foglang[Groups]),
            'active-multicast'=>$this->foglang[ActiveMCTasks],
            'active-snapins'=>$this->foglang[ActiveSnapins],
            scheduled=>$this->foglang[ScheduledTasks],
        );
        $this->subMenu = array();
        $this->notes = array();
        $this->HookManager->processEvent(SUB_MENULINK_DATA,array(menu=>&$this->menu,submenu=>&$this->subMenu,id=>&$this->id,notes=>&$this->notes));
        // Header row
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Started By:'),
            _('Hostname<br><small>MAC</small>'),
            '',
            _('Start Time'),
            _('Status'),
        );
        // Row templates
        $this->templates = array(
            '',
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '${startedby}',
            '<p><a href="?node=host&sub=edit&id=${host_id}" title="' . _('Edit Host') . '">${host_name}</a></p><small>${host_mac}</small>',
            '${details_taskname}',
            '<small>${time}</small>',
            '<i class="fa fa-${icon_state} fa-1x icon" title="${state}"></i> <i class="fa fa-${icon_type} fa-1x icon" title="${type}"></i>',
        );
        // Row attributes
        $this->attributes = array(
            array(width=>1,'','class'=>'filter-false'),
            array(width=>16,'class'=>'c filter-false'),
            array(width=>65,'class'=>l,id=>'host-${host_id}'),
            array(width=>120,'class'=>l),
            array(width=>70,'class'=>r),
            array(width=>100,'class'=>r),
            array(width=>50,'class'=>'r filter-false'),
        );
    }
    // Pages
    public function index() {
        $this->active();
    }
    public function search_post() {
        // Find data -> Push data
        $Tasks = $this->getClass(TaskManager)->search();
        foreach ($Tasks AS $i => &$Task) {
            $Host = $Task->getHost();
            $this->data[] = array(
                startedby=>$Task->get(createdBy),
                id=>$Task->get(id),
                name=>$Task->get(name),
                'time'=>$this->formatTime($Task->get(createdTime),'Y-m-d H:i:s'),
                state=>$Task->getTaskStateText(),
                forced=>($Task->get(isForced) ? 1 : 0),
                type=>$Task->getTaskTypeText(),
                percentText=>$Task->get(percent),
                'class'=> ++$i % 2 ? 'alt2' : 'alt1',
                width=>600*($Task->get(percent)/100),
                elapsed=>$Task->get(timeElapsed),
                remains=>$Task->get(timeRemaining),
                percent=>$Task->get(pct),
                copied=>$Task->get(dataCopied),
                total=>$Task->get(dataTotal),
                bpm=>$Task->get(bpm),
                details_taskname=>($Task->get(name)?sprintf('<div class="task-name">%s</div>',$Task->get(name)):''),
                details_taskforce=>($Task->get(isForced)?sprintf('<i class="icon-forced" title="%s"></i>',_('Task forced to start')):($Task->get(typeID) < 3 && $Task->get(stateID) < 3?sprintf('<a href="?node=task&sub=force-task&id=%s" class="icon-force"><i title="%s"></i></a>',$Task->get(id),_('Force task to start')):'&nbsp;')),
                host_id=>$Task->get(hostID),
                host_name=>$Host ? $Host->get(name) : '',
                host_mac=>$Host ? $Host->get(mac)->__toString() : '',
                icon_state=>$Task->getTaskState()->getIcon(),
                icon_type=>$Task->getTaskType()->get(icon),
            );
        }
        unset($Task);
        // Hook
        $this->HookManager->processEvent(HOST_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
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
            array('class'=>l),
            array(width=>60,'class'=>c),
            array(width=>60,'class'=>'r filter-false'),
        );
        $Hosts = $this->getClass(HostManager)->find('','','','','','name');
        foreach($Hosts AS $i => &$Host) {
            if ($Host->isValid() && !$Host->get(pending)) {
                $imgUp = '<a href="?node=task&sub=hostdeploy&type=2&id=${id}"><i class="icon hand fa fa-${upicon} fa-1x" title="'._('Upload').'"></i></a>';
                $imgDown = '<a href="?node=task&sub=hostdeploy&type=1&id=${id}"><i class="icon hand fa fa-${downicon} fa-1x" title="'._('Download').'"></i></a>';
                $imgAdvanced = '<a href="?node=task&sub=hostadvanced&id=${id}#host-tasks"><i class="icon hand fa fa-arrows-alt fa-1x" title="'._('Advanced').'"></i></a>';
                $this->data[] = array(
                    uploadLink=>$imgUp,
                    downLink=>$imgDown,
                    advancedLink=>$imgAdvanced,
                    id=>$Host->get(id),
                    host_name=>$Host->get(name),
                    host_mac=>$Host->get(mac)->__toString(),
                    image_name=>$Host->getImage()->get(name),
                    upicon=>$this->getClass(TaskType,2)->get(icon),
                    downicon=>$this->getClass(TaskType,1)->get(icon),
                );
            }
        }
        unset($Host);
        // Hook
        $this->HookManager->processEvent(HOST_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Render
        $this->render();
    }
    public function hostdeploy() {
        $Host = $this->getClass(Host,$_REQUEST[id]);
        $taskTypeID = $_REQUEST[type];
        $TaskType = $this->getClass(TaskType,$_REQUEST[type]);
        $snapin = '-1';
        $enableShutdown = false;
        $enableSnapins = ($_REQUEST[type] == 17 ? false : -1);
        $taskName = 'Quick Deploy';
        try {
            if ($TaskType->isUpload() && $Host->getImage()->isValid() && $Host->getImage()->get('protected')) throw new Exception(sprintf('%s: %s %s: %s %s',_('Hostname'),$Host->get(name),_('Image'),$Host->getImage()->get(name),_('is protected')));
            $Host->createImagePackage($taskTypeID, $taskName, false, false, $enableSnapins, false, $this->FOGUser->get(name));
            $this->FOGCore->setMessage(_('Successfully created tasking!'));
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
            '<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><i class="fa fa-${task_icon} fa-fw fa-2x" /></i><br />${task_name}</a>',
            '${task_desc}',
        );
        echo '<div><h2>'._('Advanced Actions').'</h2>';
        // Find TaskTypes
        $TaskTypes = $this->getClass(TaskTypeManager)->find(array(access=>array('both', 'host'),isAdvanced=>1),'AND','id');
        // Iterate -> Print
        foreach ($TaskTypes AS $i => &$TaskType) {
            $this->data[] = array(
                node => $_REQUEST[node],
                sub => 'hostdeploy',
                id => $_REQUEST[id],
                type=> $TaskType->get(id),
                task_icon => $TaskType->get(icon),
                task_name => $TaskType->get(name),
                task_desc => $TaskType->get(description),
            );
        }
        unset($TaskType);
        // Hook
        $this->HookManager->processEvent(TASK_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        echo '</div>';
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
            '<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><i class="fa fa-${task_icon} fa-fw fa-2x" /></i><br />${task_name}</a>',
            '${task_desc}',
        );
        echo '<div><h2>'._('Advanced Actions').'</h2>';
        // Find TaskTypes
        $TaskTypes = $this->getClass(TaskTypeManager)->find(array(access=>array('both', 'group'),isAdvanced=>1),'AND','id');
        // Iterate -> Print
        foreach ($TaskTypes AS $i => &$TaskType) {
            $this->data[] = array(
                node=>$_REQUEST[node],
                sub=>'groupdeploy',
                id=>$_REQUEST[id],
                type=>$TaskType->get(id),
                task_icon=>$TaskType->get(icon),
                task_name=>$TaskType->get(name),
                task_desc=>$TaskType->get(description),
            );
        }
        unset($TaskTypes);
        // Hook
        $this->HookManager->processEvent(TASK_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
        echo '</div>';
    }
    public function listgroups() {
        $this->title = _('List all Groups');
        $this->headerData = array(
            _('Name'),
            _('Deploy'),
        );
        $this->attributes = array(
            array('class'=>l),
            array(width=>60,'class'=>'r filter-false'),
        );
        $this->templates = array(
            '<a href="?node=group&sub=edit&id=${id}"/>${name}</a>',
            '${deployLink}&nbsp;${multicastLink}&nbsp;${advancedLink}',
        );
        $Groups = $this->getClass(GroupManager)->find();
        foreach ($Groups AS $i => &$Group) {
            $deployLink = '<a href="?node=task&sub=groupdeploy&type=1&id=${id}"><i class="icon hand fa fa-${downicon} fa-1x" title="'._('Download').'"></i></a>';
            $multicastLink = '<a href="?node=task&sub=groupdeploy&type=8&id=${id}"><i class="icon hand fa fa-${multicon} fa-1x" title="'._('Multicast').'"></i></a>';
            $advancedLink = '<a href="?node=task&sub=groupadvanced&id=${id}"><i class="icon hand fa fa-arrows-alt" title="'._('Advanced').'"></i></a>';
            $this->data[] = array(
                deployLink=>$deployLink,
                advancedLink=>$advancedLink,
                multicastLink=>$multicastLink,
                id=>$Group->get(id),
                name=>$Group->get(name),
                downicon=>$this->getClass(TaskType,1)->get(icon),
                multiicon=>$this->getClass(TaskType,8)->get(icon),
            );
        }
        unset($Group);
        // Hook
        $this->HookManager->processEvent(TasksListGroupData,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Render
        $this->render();
    }
    public function groupdeploy() {
        $Group = $this->getClass(Group,$_REQUEST[id]);
        $taskTypeID = $_REQUEST[type];
        $TaskType = $this->getClass(TaskType,$taskTypeID);
        $snapin = -1;
        $enableShutdown = false;
        $enableSnapins = ($_REQUEST[type] == 17 ? false : -1);
        $enableDebug = (in_array($_REQUEST[type],array(3,15,16)) ? true : false);
        $imagingTasks = array(1,2,8,15,16,17,24);
        $taskName = _(($taskTypeID == 8 ? 'Multicast Group Quick Deploy' : 'Group Quick Deploy'));
        try {

            $Hosts = $this->getClass(HostManager)->find(array(id=>$Group->get(hosts)));
            foreach ($Hosts AS $i => &$Host) {
                if (in_array($taskTypeID,$imagingTasks) && !$Host->get(imageID)) throw new Exception(_('You need to assign an image to all of the hosts'));
                if (!$Host->checkIfExist($taskTypeID)) throw new Exception(_('To setup download task, you must first upload an image'));
            }
            unset($Host);
            $Group->createImagePackage($taskTypeID, $taskName, $enableShutdown, $enableDebug, $enableSnapins, true, $this->FOGUser->get(name));
            $this->FOGCore->setMessage('Successfully created Group tasking!');
            $this->FOGCore->redirect('?node=task&sub=active');
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect('?node=task&sub=listgroups');
        }
    }
    // Active Tasks
    public function active() {
        unset($this->data);
        // Set title
        $this->form = '<center><input type="button" id="taskpause"/></center><br/>';
        $this->title = _('Active Tasks');
        // Tasks
        $i = 0;
        $Tasks = $this->getClass(TaskManager)->find(array(stateID=>array(1,2,3)));
        foreach ($Tasks AS $i => &$Task) {
            $Host = $Task->getHost();
            $this->data[] = array(
                startedby=>$Task->get(createdBy),
                id=>$Task->get(id),
                name=>$Task->get(name),
                'time'=>$this->formatTime($Task->get(createdTime),'Y-m-d H:i:s'),
                state=>$Task->getTaskStateText(),
                forced=>$Task->get(isForced),
                type=>$Task->getTaskTypeText(),
                percentText=>$Task->get(percent),
                'class'=>++$i % 2 ? 'alt2' : 'alt1',
                width=> 600 * ($Task->get(percent)/100),
                elapsed=>$Task->get(timeElapsed),
                remains=>$Task->get(timeRemaining),
                percent=>$Task->get(pct),
                copied=>$Task->get(dataCopied),
                total=>$Task->get(dataTotal),
                bpm=>$Task->get(bpm),
                details_taskname=>($Task->get(name)?sprintf('<div class="task-name">%s</div>',$Task->get(name)):''),
                details_taskforce=>($Task->get(isForced) ? sprintf('<i class="icon-forced" title="%s"></i>', _('Task forced to start')) : ($Task->get(typeID) < 3 && $Task->get(stateID) < 3 ? sprintf('<a href="?node=task&sub=force-task&id=%s" class="icon-force"><i title="%s"></i></a>', $Task->get(id),_('Force task to start')) : '&nbsp;')),
                host_id=>$Host->get(id),
                host_name=>$Host->get(name),
                host_mac=>$Host->get(mac)->__toString(),
                icon_state=>$Task->getTaskState()->getIcon(),
                icon_type=>$Task->getTaskType()->get(icon),
            );
        }
        unset($Task);
        // Hook
        $this->HookManager->processEvent(HOST_DATA,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function canceltasks() {
        $Tasks = $this->getClass(TaskManager)->find(array(id=>(array)$_REQUEST[task]));
        foreach ($Tasks AS $i => &$Task) $Task->cancel();
    }
    // Active Tasks - Force Task Start
    public function force_task() {
        // Find
        $Task = $this->getClass(Task,$_REQUEST[id]);
        // Hook
        $this->HookManager->processEvent(TASK_FORCE,array(Task=>&$Task));
        // Force
        unset($result);
        try {
            if ($this->getClass(Task,$_REQUEST[id])->set(isForced,1)->save()) $result[success] = true;
        } catch (Exception $e) {
            $result[error] = $e->getMessage();
        }
        if ($this->isAJAXRequest()) echo json_encode($result);
        else {
            if ($result[error]) $this->fatalError($result[error]);
            else $this->FOGCore->redirect(sprintf('?node=%s',$this->node));
        }
    }
    // Active Tasks - Cancel Task
    public function cancel_task() {
        // Find
        $Task = $this->getClass(Task,$_REQUEST[id]);
        // Hook
        $this->HookManager->processEvent(TASK_CANCEL,array(Task=>&$Task));
        try {
            // Cancel task - will throw Exception on error
            $Task->cancel();
            // Success
            $result[success] = true;
        } catch (Exception $e) {
            // Failure
            $result[error] = $e->getMessage();
        }
        // Output
        if ($this->isAJAXRequest()) echo json_encode($result);
        else {
            if ($result[error]) $this->fatalError($result[error]);
            else $this->FOGCore->redirect(sprintf('?node=%s', $this->node));
        }
    }
    public function remove_multicast_post() {
        $MulticastSessionIDs = $this->getClass(MulticastSessionsManager,$_REQUEST[task])->find(array(id=>$_REQUEST[task]),'','','','','','','id');
        $MulticastAssocIDs = $this->getClass(MulticastSessionsAssociationManager)->find(array(id=>$MulticastSessionIDs),'','','','','','','msID');
        $TaskIDs = $this->getClass(MulticastSessionsAssociationManager)->find(array(id=>$MulticastAssocIDs),'','','','','','','taskID');
        $Tasks = $this->getClass(TaskManager)->find(array(id=>$MulticastAssocIDs));
        foreach ($Tasks AS $i => &$Task) $Task->cancel();
        unset($Task);
        $this->getClass(MulticastSessionsAssociationManager)->destroy(array(id=>$MulticastAssocIDs));
        $this->getClass(MulticastSessionsManager)->destroy(array(id=>$MulticastSessionIDs));
        $this->FOGCore->setMessage(_('Successfully cancelled selected tasks'));
        $this->FOGCore->redirect('?node='.$this->node.'&sub=active');
    }
    public function active_multicast() {
        // Set title
        $this->title = _('Active Multi-cast Tasks');
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Task Name'),
            _('Hosts'),
            _('Start Time'),
            _('State'),
            _('Status'),
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '${name}',
            '${count}',
            '${start_date}',
            '${state}',
            '${percent}',
        );
        // Row attributes
        $this->attributes = array(
            array(width=>16,'class'=>c),
            array('class'=>c),
            array('class'=>c),
            array('class'=>c),
            array('class'=>c),
            array(width=>40,'class'=>c)
        );
        // Multicast data
        $MSAs = $this->getClass(MulticastSessionsManager)->find(array(stateID=>array(1,2,3)));
        foreach($MSAs AS $i => &$MS) {
            $TS = $this->getClass(TaskState,$MS->get(stateID));
            $this->data[] = array(
                id=>$MS->get(id),
                name=>($MS->get(name)?$MS->get(name): _('Multicast Task')),
                'count'=>$this->getClass(MulticastSessionsAssociationManager)->count(array(msID=>$MS->get(id))),
                start_date=>$MS->get(starttime),
                state=>($TS->get(name)?$TS->get(name):null),
                percent=>$MS->get(percent),
            );
        }
        unset($MS);
        // Hook
        $this->HookManager->processEvent(TaskActiveMulticastData,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function active_snapins() {
        // Set title
        $this->title = 'Active Snapins';
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Host Name'),
            _('Snapin'),
            _('Start Time'),
            _('State'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '${host_name}',
            '<form method="post" method="?node=task&sub=active-snapins">${name}',
            '${startDate}',
            '${state}',
        );
        $this->attributes = array(
            array('class'=>'c filter-false',width=>16),
            array('class'=>l,width=>50),
            array('class'=>l,width=>50),
            array('class'=>l,width=>50),
            array('class'=>r,width=>40),
        );
        $STasks = $this->getClass(SnapinTaskManager)->find(array(stateID=>array(-1,0,1)));
        foreach($STasks AS $i => &$SnapinTask) {
            $Host = $this->getClass(SnapinJob,$SnapinTask->get(jobID))->getHost();
            $Snapin = $this->getClass(Snapin,$SnapinTask->get(snapinID));
            if ($Host->get(snapinjob) && $Host->get(snapinjob)->isValid() && in_array($Host->get(snapinjob)->get(stateID),array(-1,0,1,2,3))) {
                $this->data[] = array(
                    id => $SnapinTask->get(id),
                    name => $Snapin->get(name),
                    hostID => $Host->get(id),
                    host_name => $Host->get(name),
                    startDate => $SnapinTask->get(checkin),
                    state => $SnapinTask->get(stateID) == 0 ? 'Queued' : ($SnapinTask->get(stateID) == 1 ? 'In-Progress' : 'N/A'),
                );
            }
        }
        unset($SnapinTask);
        // Hook
        $this->HookManager->processEvent(TaskActiveSnapinsData,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
    public function active_snapins_post() {
        $SnapinTaskIDs = $this->getClass(SnapinTaskManager)->find(array(id=>$_REQUEST[task]),'','','','','','','id');
        $SnapinJobIDs = $this->getClass(SnapinTaskManager)->find(array(id=>$_REQUEST[task]),'','','','','','','jobID');
        // Delete All selected Tasks
        $this->getClass(SnapinTaskManager)->destroy(array(id=>$SnapinTaskIDs));
        // Only remove the job if all of the tasks that were a part of that job tasking are no longer present.
        if (!$this->getClass(SnapinTaskManager)->count(array(jobID=>$SnapinJobIDs))) $this->getClass(SnapinJobManager)->destroy(array(id=>$SnapinJobIDs));
        $this->FOGCore->setMessage(_('Successfully cancelled selected tasks'));
        $this->FOGCore->redirect('?node='.$this->node.'&sub=active');
    }
    public function cancelscheduled() {
        $this->getClass(ScheduledTaskManager)->destroy(array(id=>$_REQUEST[task]));
        $this->FOGCore->setMessage(_('Successfully cancelled selected tasks'));
        $this->FOGCore->redirect('?node='.$this->node.'&sub=active');
    }
    public function scheduled() {
        // Set title
        $this->title = 'Scheduled Tasks';
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Name:'),
            _('Is Group'),
            _('Task Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active/Type'),
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '<a href="?node=${hostgroup}&sub=edit&id=${host_id}" title="Edit ${hostgroupname}">${hostgroupname}</a>',
            '${groupbased}<form method="post" action="?node=task&sub=scheduled">',
            '${details_taskname}',
            '${task_type}',
            '<small>${time}</small>',
            '${active}/${type}',
        );
        // Row attributes
        $this->attributes = array(
            array(width=>16,'class'=>c),
            array(width=>120,'class'=>l),
            array(),
            array(width=>110,'class'=>l),
            array('class'=>c,width=>80),
            array(width=>70,'class'=>c),
            array(width=>100,'class'=>c,style=>'padding-right: 10px'),
        );
        $SchedTasks = $this->getClass(ScheduledTaskManager)->find();
        foreach ($SchedTasks AS $i => &$task) {
            $Host = $task->getHost();
            $taskType = $task->getTaskType();
            if ($task->get(type) == 'C') $taskTime = FOGCron::parse($task->get(minute).' '.$task->get(hour).' '.$task->get(dayOfMonth).' '.$task->get(month).' '.$task->get(dayOfWeek));
            else $taskTime = $task->get(scheduleTime);
            $taskTime = $this->nice_date()->setTimestamp($taskTime);
            $hostGroupName = ($task->isGroupBased() ? $task->getGroup() : $task->getHost());
            $this->data[] = array(
                id=>$task->get(id),
                hostgroup=>$task->isGroupBased() ? 'group' : 'host',
                hostgroupname=>$hostGroupName,
                host_id=>$hostGroupName->get(id),
                groupbased=>$task->isGroupBased() ? _('Yes') : _('No'),
                details_taskname=>$task->get(name),
                'time'=>$this->formatTime($taskTime),
                active=>$task->get(isActive) ? 'Yes' : 'No',
                type=>$task->get(type) == 'C' ? 'Cron' : 'Delayed',
                schedtaskid=>$task->get(id),
                task_type=>$taskType,
            );
        }
        unset($task);
        // Hook
        $this->HookManager->processEvent(TaskScheduledData,array(headerData=>&$this->headerData,data=>&$this->data,templates=>&$this->templates,attributes=>&$this->attributes));
        // Output
        $this->render();
    }
}
