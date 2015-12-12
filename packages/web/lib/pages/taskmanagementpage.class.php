<?php
class TaskManagementPage extends FOGPage {
    public $node = 'task';
    public function __construct($name = '') {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->menu = array(
            'search'=>$this->foglang['NewSearch'],
            'active'=>$this->foglang['ActiveTasks'],
            'listhosts'=>sprintf($this->foglang['ListAll'],$this->foglang['Hosts']),
            'listgroups'=>sprintf($this->foglang['ListAll'],$this->foglang['Groups']),
            'active-multicast'=>$this->foglang['ActiveMCTasks'],
            'active-snapins'=>$this->foglang['ActiveSnapins'],
            'active-scheduled'=>$this->foglang['ScheduledTasks'],
        );
        $this->subMenu = array();
        $this->notes = array();
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Started By:'),
            _('Hostname<br><small>MAC</small>'),
            '',
            _('Start Time'),
            _('Status'),
        );
        $this->templates = array(
            '',
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '${startedby}',
            '<p><a href="?node=host&sub=edit&id=${host_id}" title="' . _('Edit Host') . '">${host_name}</a></p><small>${host_mac}</small>',
            '${details_taskname}',
            '<small>${time}</small>',
            '${details_taskforce} <i class="fa fa-${icon_state} fa-1x icon" title="${state}"></i> <i class="fa fa-${icon_type} fa-1x icon" title="${type}"></i>',
        );
        $this->attributes = array(
            array('width'=>1,'','class'=>'l filter-false','task-id'=>'${id}'),
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>65,'class'=>'l','id'=>'host-${host_id}'),
            array('width'=>120,'class'=>'l'),
            array('width'=>70,'class'=>'r'),
            array('width'=>100,'class'=>'r'),
            array('width'=>50,'class'=>'r filter-false'),
        );
    }
    public function index() {
        $this->active();
    }
    public function search_post() {
        foreach ((array)$this->getClass('TaskManager')->search('',true) AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) continue;
            $this->data[] = array(
                'startedby'=>$Task->get('createdBy'),
                'details_taskforce' => ($Task->get('isForced') ? sprintf('<i class="icon-forced" title="%s"></i>',_('Task forced to start')) : ($Task->get('typeID') < 3 && in_array($Task->get('stateID'),$this->getQueuedStates()) ? sprintf('<i title="%s" class="icon-force icon" href="?node=task&sub=force-task&id=${id}"></i>',_('Force task to start')) : '&nbsp;')),
                'id'=>$Task->get('id'),
                'name'=>$Task->get('name'),
                'time'=>$this->formatTime($Task->get('createdTime')),
                'state'=>$Task->getTaskStateText(),
                'forced'=>($Task->get('isForced') ? 1 : 0),
                'type'=>$Task->getTaskTypeText(),
                'percentText'=>$Task->get('percent'),
                'width'=>600*($Task->get('percent')/100),
                'elapsed'=>$Task->get('timeElapsed'),
                'remains'=>$Task->get('timeRemaining'),
                'percent'=>$Task->get('pct'),
                'copied'=>$Task->get('dataCopied'),
                'total'=>$Task->get('dataTotal'),
                'bpm'=>$Task->get('bpm'),
                'details_taskname'=>($Task->get('name')?sprintf('<div class="task-name">%s</div>',$Task->get('name')):''),
                'host_id'=>$Task->get('hostID'),
                'host_name'=>$Host->get('name'),
                'host_mac'=>$Host->get('mac')->__toString(),
                'icon_state'=>$Task->getTaskState()->getIcon(),
                'icon_type'=>$Task->getTaskType()->get('icon'),
            );
            unset($Task,$Host);
        }
        $this->HookManager->processEvent('HOST_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        array_shift($this->headerData);
        array_shift($this->headerData);
        array_shift($this->templates);
        array_shift($this->templates);
        array_shift($this->attributes);
        array_shift($this->attributes);
        $this->render();
    }
    public function listhosts() {
        $this->title = _('All Hosts');
        $this->headerData = array(
            _('Host Name'),
            _('Image Name'),
            _('Deploy'),
        );
        $up = $this->getClass('TaskType',2);
        $down = $this->getClass('TaskType',1);
        $this->templates = array(
            '<a href="?node=host&sub=edit&id=${id}"/>${host_name}</a><br/><small>${host_mac}</small>',
            '<small>${image_name}</small>',
            sprintf('<a href="?node=task&sub=hostdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s" title="%s"></i></a><a href="?node=task&sub=hostdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s" title="%s"></i></a><a href="?node=task&sub=hostadvanced&id=${id}#host-tasks"><i class="icon hand fa fa-%s" title="%s"></i></a>',
            $up->get('id'),
            $up->get('icon'),
            $up->get('name'),
            $down->get('id'),
            $down->get('icon'),
            $down->get('name'),
            'arrows-alt',
            _('Advanced')
        ),
    );
        unset($up,$down);
        $this->attributes = array(
            array('class'=>'l'),
            array('width'=>60,'class'=>'c'),
            array('width'=>60,'class'=>'r filter-false'),
        );
        foreach((array)$this->getClass('HostManager')->find() AS $i => &$Host) {
            if (!$Host->isValid() || $Host->get('pending')) continue;
            $hostname = $Host->get('name');
            $MAC = $Host->get('mac');
            $this->data[] = array(
                'id'=>$Host->get('id'),
                'host_name'=>$Host->get('name'),
                'host_mac'=>$MAC,
                'image_name'=>$Host->getImageName(),
            );
            unset($Host);
        }
        $this->HookManager->processEvent('HOST_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function hostdeploy() {
        $Host = $this->getClass('Host',$_REQUEST['id']);
        $taskTypeID = $_REQUEST['type'];
        $TaskType = $this->getClass('TaskType',$_REQUEST['type']);
        $snapin = '-1';
        $enableShutdown = false;
        $enableSnapins = ($_REQUEST['type'] == 17 ? false : -1);
        $taskName = 'Quick Deploy';
        try {
            if ($TaskType->isUpload() && $Host->getImage()->isValid() && $Host->getImage()->get('protected')) throw new Exception(sprintf('%s: %s %s: %s %s',_('Hostname'),$Host->get('name'),_('Image'),$Host->getImageName(),_('is protected')));
            $Host->createImagePackage($taskTypeID, $taskName, false, false, $enableSnapins, false, $_SESSION['FOG_USERNAME']);
            $this->setMessage(_('Successfully created tasking!'));
            $this->redirect('?node=task&sub=active');
        } catch (Exception $e) {
            printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create deploy task'), $e->getMessage());
        }
    }
    public function hostadvanced() {
        unset($this->headerData);
        $this->attributes =  array(
            array(),
            array(),
        );
        $this->templates = array(
            '<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><i class="fa fa-${task_icon} fa-fw fa-2x" /></i><br />${task_name}</a>',
            '${task_desc}',
        );
        foreach ((array)$this->getClass('TaskTypeManager')->find(array('access'=>array('both','host'))) AS $i => &$TaskType) {
            if (!$TaskType->isValid()) continue;
            $this->data[] = array(
                'node' => $_REQUEST['node'],
                'sub' => 'hostdeploy',
                'id' => $_REQUEST['id'],
                'type' => $TaskType->get('id'),
                'task_icon' => $TaskType->get('icon'),
                'task_name' => $TaskType->get('name'),
                'task_desc' => $TaskType->get('description'),
            );
            unset($TaskType);
        }
        $this->HookManager->processEvent('TASK_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<div><h2>%s</h2>',_('Advanced Actions'));
        $this->render();
        echo '</div>';
    }
    public function groupadvanced() {
        unset($this->headerData);
        $this->attributes =  array(
            array(),
            array(),
        );
        $this->templates = array(
            '<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><i class="fa fa-${task_icon} fa-fw fa-2x" /></i><br />${task_name}</a>',
            '${task_desc}',
        );
        $TaskTypes = $this->getClass('TaskTypeManager')->find(array('access'=>array('both', 'group'),'isAdvanced'=>1),'AND','id');
        foreach ((array)$this->getClass('TaskTypeManager')->find(array('access'=>array('both','group'),'isAdvanced'=>1),'AND','id') AS $i => &$TaskType) {
            if (!$TaskType->isValid()) continue;
            $this->data[] = array(
                'node'=>$_REQUEST['node'],
                'sub'=>'groupdeploy',
                'id'=>$_REQUEST['id'],
                'type'=>$TaskType->get('id'),
                'task_icon'=>$TaskType->get('icon'),
                'task_name'=>$TaskType->get('name'),
                'task_desc'=>$TaskType->get('description'),
            );
            unset($TaskType);
        }
        $this->HookManager->processEvent('TASK_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<div><h2>%s</h2>',_('Advanced Actions'));
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
            array('class'=>'l'),
            array('width'=>60,'class'=>'r filter-false'),
        );
        $down = $this->getClass('TaskType',1);
        $mc = $this->getClass('TaskType',8);
        $this->templates = array(
            '<a href="?node=group&sub=edit&id=${id}"/>${name}</a>',
            sprintf('<a href="?node=task&sub=groupdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s" title="%s"></i></a><a href="?node=task&sub=groupdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s" title="%s"></i></a><a href="?node=task&sub=groupadvanced&id=${id}#host-tasks"><i class="icon hand fa fa-%s" title="%s"></i></a>',
            $down->get('id'),
            $down->get('icon'),
            $down->get('name'),
            $mc->get('id'),
            $mc->get('icon'),
            $mc->get('name'),
            'arrows-alt',
            _('Advanced')
        ),
    );
        foreach ((array)$this->getClass('GroupManager')->find() AS $i => &$Group) {
            if (!$Group->isValid()) continue;
            $this->data[] = array(
                'id'=>$Group->get('id'),
                'name'=>$Group->get('name'),
            );
            unset($Group);
        }
        unset($Groups);
        $this->HookManager->processEvent('TasksListGroupData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function groupdeploy() {
        $Group = $this->getClass('Group',$_REQUEST['id']);
        $taskTypeID = $_REQUEST['type'];
        $TaskType = $this->getClass('TaskType',$taskTypeID);
        $snapin = -1;
        $enableShutdown = false;
        $enableSnapins = ($_REQUEST['type'] == 17 ? false : -1);
        $enableDebug = (in_array($_REQUEST['type'],array(3,15,16)) ? true : false);
        $imagingTasks = array(1,2,8,15,16,17,24);
        $taskName = _(($taskTypeID == 8 ? 'Multicast Group Quick Deploy' : 'Group Quick Deploy'));
        try {
            foreach ((array)$this->getClass('HostManager')->find(array('id'=>$Group->get('hosts'))) AS $i => &$Host) {
                if (!$Host->isValid()) continue;
                if (in_array($taskTypeID,$imagingTasks) && !$Host->get('imageID')) throw new Exception(_('You need to assign an image to all of the hosts'));
                if (!$Host->checkIfExist($taskTypeID)) throw new Exception(_('To setup download task, you must first upload an image'));
                unset($Host);
            }
            $Group->createImagePackage($taskTypeID, $taskName, $enableShutdown, $enableDebug, $enableSnapins, true, $_SESSION['FOG_USERNAME']);
            $this->setMessage('Successfully created Group tasking!');
            $this->redirect('?node=task&sub=active');
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect('?node=task&sub=listgroups');
        }
    }
    public function active() {
        unset($this->data);
        $this->title = _('Active Tasks');
        $i = 0;
        foreach ((array)$this->getClass('TaskManager')->find(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) continue;
            $hostname = $Host->get('name');
            $MAC = $Host->get('mac')->__toString();
            $this->data[] = array(
                'startedby'=>$Task->get('createdBy'),
                'details_taskforce' => ($Task->get('isForced') ? sprintf('<i class="icon-forced" title="%s"></i>',_('Task forced to start')) : ($Task->get('typeID') < 3 && in_array($Task->get('stateID'),$this->getQueuedStates()) ? sprintf('<i title="%s" class="icon-force icon" href="?node=task&sub=force-task&id=${id}"></i>',_('Force task to start')) : '&nbsp;')),
                'id'=>$Task->get('id'),
                'name'=>$Task->get('name'),
                'time'=>$this->formatTime($Task->get('createdTime')),
                'state'=>$Task->getTaskStateText(),
                'forced'=>$Task->get('isForced'),
                'type'=>$Task->getTaskTypeText(),
                'percentText'=>$Task->get('percent'),
                'width'=> 600 * ($Task->get('percent')/100),
                'elapsed'=>$Task->get('timeElapsed'),
                'remains'=>$Task->get('timeRemaining'),
                'percent'=>$Task->get('pct'),
                'copied'=>$Task->get('dataCopied'),
                'total'=>$Task->get('dataTotal'),
                'bpm'=>$Task->get('bpm'),
                'details_taskname'=>($Task->get('name')?sprintf('<div class="task-name">%s</div>',$Task->get('name')):''),
                'host_id'=>$Host->get('id'),
                'host_name'=>$hostname,
                'host_mac'=>$MAC,
                'icon_state'=>$Task->getTaskState()->getIcon(),
                'icon_type'=>$Task->getTaskType()->get('icon'),
            );
            unset($Task);
        }
        $this->HookManager->processEvent('HOST_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function canceltasks() {
        foreach ((array)$this->getClass('TaskManager')->find(array('id'=>(array)$_REQUEST['task'])) AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Task->cancel();
            unset($Task);
        }
    }
    public function force_task() {
        $Task = $this->getClass('Task',$_REQUEST['id']);
        $this->HookManager->processEvent('TASK_FORCE',array('Task'=>&$Task));
        unset($result);
        try {
            if ($Task->set('isForced',1)->save()) $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        if ($this->ajax) echo json_encode($result);
        else {
            if ($result['error']) $this->fatalError($result['error']);
            else $this->redirect(sprintf('?node=%s',$this->node));
        }
    }
    public function cancel_task() {
        $Task = $this->getClass('Task',$_REQUEST['id']);
        $this->HookManager->processEvent('TASK_CANCEL',array('Task'=>&$Task));
        try {
            $Task->cancel();
            $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        if ($this->isAJAXRequest()) echo json_encode($result);
        else {
            if ($result['error']) $this->fatalError($result['error']);
            else $this->redirect(sprintf('?node=%s', $this->node));
        }
    }
    public function active_multicast_post() {
        $MulticastSessionIDs = (array)$_REQUEST['task'];
        $TaskIDs = $this->getSubObjectIDs('MulticastSessionsAssociation',array('msID'=>$MulticastSessionIDs),'taskID');
        foreach ((array)$this->getClass('TaskManager')->find(array('id'=>$TaskIDs)) AS $i => &$Task) {
            if (!$Task->isValid()) continue;
            $Task->cancel();
            unset($Task);
        }
        unset($Tasks);
        $this->getClass('MulticastSessionsAssociationManager')->destroy(array('taskID'=>$TaskIDs));
        unset($TaskIDs);
        $this->getClass('MulticastSessionsManager')->destroy(array('id'=>$MulticastSessionIDs));
        unset($MulticastSessionIDs);
    }
    public function active_multicast_ajax() {
        $this->active_multicast();
    }
    public function active_multicast() {
        $this->title = _('Active Multi-cast Tasks');
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Task Name'),
            _('Hosts'),
            _('Start Time'),
            _('State'),
            _('Status'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '${name}',
            '${count}',
            '${start_date}',
            '${state}',
            '${percent}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false','task-id'=>'${id}'),
            array('class'=>'c'),
            array('class'=>'c'),
            array('class'=>'c'),
            array('class'=>'c'),
            array('width'=>40,'class'=>'c')
        );
        foreach((array)$this->getClass('MulticastSessionsManager')->find(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) AS $i => &$MS) {
            if (!$MS->isValid()) continue;
            $TS = $this->getClass('TaskState',$MS->get('stateID'));
            $this->data[] = array(
                'id'=>$MS->get('id'),
                'name'=>($MS->get('name')?$MS->get('name'): _('Multicast Task')),
                'count'=>$this->getClass('MulticastSessionsAssociationManager')->count(array('msID'=>$MS->get('id'))),
                'start_date'=>$this->formatTime($MS->get('starttime')),
                'state'=>($TS->get('name')?$TS->get('name'):null),
                'percent'=>$MS->get('percent'),
            );
            unset($MS,$TS);
        }
        $this->HookManager->processEvent('TaskActiveMulticastData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function active_snapins_ajax() {
        $this->active_snapins();
    }
    public function active_snapins() {
        $this->title = 'Active Snapins';
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
            array('class'=>'l filter-false','width'=>16,'task-id'=>'${id}'),
            array('class'=>'l','width'=>50),
            array('class'=>'l','width'=>50),
            array('class'=>'l','width'=>50),
            array('class'=>'r','width'=>40),
        );
        foreach ((array)$this->getClass('SnapinTaskManager')->find(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) AS $i => &$SnapinTask) {
            if (!$SnapinTask->isValid()) continue;
            $Host = $this->getClass('SnapinJob',$SnapinTask->get('jobID'))->getHost();
            if (!$Host->isValid() || !$Host->get('snapinjob')->isValid() || !in_array($Host->get('snapinjob')->get('stateID'),array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) continue;
            $Snapin = $this->getClass('Snapin',$SnapinTask->get('snapinID'));
            if (!$Snapin->isValid()) continue;
            $this->data[] = array(
                'id' => $SnapinTask->get('id'),
                'name' => $Snapin->get('name'),
                'hostID' => $Host->get('id'),
                'host_name' => $Host->get('name'),
                'startDate' => $this->formatTime($SnapinTask->get('checkin')),
                'state' => $this->getClass('TaskState',$SnapinTask->get('stateID'))->get('name'),
            );
            unset($Host,$SnapinTask,$Snapin);
        }
        $this->HookManager->processEvent('TaskActiveSnapinsData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function active_snapins_post() {
        $SnapinTaskIDs = $this->getSubObjectIDs('SnapinTask',array('id'=>$_REQUEST['task']));
        $SnapinJobIDs = $this->getSubObjectIDs('SnapinTask',array('id'=>$_REQUEST['task']),'jobID');
        $this->getClass('SnapinTaskManager')->destroy(array('id'=>$SnapinTaskIDs));
        if (!$this->getClass('SnapinTaskManager')->count(array('jobID'=>$SnapinJobIDs))) $this->getClass('SnapinJobManager')->destroy(array('id'=>$SnapinJobIDs));
        $this->setMessage(_('Successfully cancelled selected tasks'));
        $this->redirect(sprintf('?node=%s&sub=active',$this->node));
    }
    public function active_scheduled_post() {
        $this->getClass('ScheduledTaskManager')->destroy(array('id'=>$_REQUEST['task']));
        $this->setMessage(_('Successfully cancelled selected tasks'));
        $this->redirect($this->formAction);
    }
    public function active_scheduled_ajax() {
        $this->active_scheduled();
    }
    public function active_scheduled() {
        $this->title = 'Scheduled Tasks';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Name:'),
            _('Is Group'),
            _('Task Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active/Type'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '<a href="?node=${hostgroup}&sub=edit&id=${host_id}" title="Edit ${hostgroupname}">${hostgroupname}</a>',
            '${groupbased}<form method="post" action="?node=task&sub=active-scheduled">',
            '${details_taskname}',
            '${task_type}',
            '<small>${time}</small>',
            '${active}/${type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false','task-id'=>'${id}'),
            array('width'=>120,'class'=>'l'),
            array(),
            array('width'=>110,'class'=>'l'),
            array('class'=>'c','width'=>80),
            array('width'=>70,'class'=>'c'),
            array('width'=>100,'class'=>'c','style'=>'padding-right: 10px'),
        );
        foreach ((array)$this->getClass('ScheduledTaskManager')->find() AS $i => &$ScheduledTask) {
            if (!$ScheduledTask->isValid()) continue;
            $Host = $ScheduledTask->getHost();
            if (!$Host->isValid()) {
                unset($ScheduledTask,$Host);
                continue;
            }
            $taskType = $ScheduledTask->getTaskType();
            if ($ScheduledTask->get('type') == 'C') $taskTime = FOGCron::parse($this->FOGCore,sprintf('%s %s %s %s %s',$ScheduledTask->get('minute'),$ScheduledTask->get('hour'),$ScheduledTask->get('dayOfMonth'),$ScheduledTask->get('month'),$ScheduledTask->get('dayOfWeek')));
            else $taskTime = $ScheduledTask->get('scheduleTime');
            $taskTime = $this->nice_date()->setTimestamp($taskTime);
            $hostGroupName = ($ScheduledTask->isGroupBased() ? $ScheduledTask->getGroup() : $ScheduledTask->getHost());
            $this->data[] = array(
                'id'=>$ScheduledTask->get('id'),
                'hostgroup'=>$ScheduledTask->isGroupBased() ? 'group' : 'host',
                'hostgroupname'=>$hostGroupName,
                'host_id'=>$hostGroupName->get('id'),
                'groupbased'=>$ScheduledTask->isGroupBased() ? _('Yes') : _('No'),
                'details_taskname'=>$ScheduledTask->get('name'),
                'time'=>$this->formatTime($taskTime),
                'active'=>$ScheduledTask->get('isActive') ? 'Yes' : 'No',
                'type'=>$ScheduledTask->get('type') == 'C' ? 'Cron' : 'Delayed',
                'schedtaskid'=>$ScheduledTask->get('id'),
                'task_type'=>$taskType,
            );
            unset($ScheduledTask,$Host,$taskType,$taskTime,$hostGroupName);
        }
        unset($ScheduledTasks);
        $this->HookManager->processEvent('TaskScheduledData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
}
