<?php
class TaskManagementPage extends FOGPage {
    public $node = 'task';
    public function __construct($name = '') {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->menu = array(
            'search' => $this->foglang['NewSearch'],
            'active' => $this->foglang['ActiveTasks'],
            'listhosts' => sprintf($this->foglang['ListAll'],$this->foglang['Hosts']),
            'listgroups' => sprintf($this->foglang['ListAll'],$this->foglang['Groups']),
            'active-multicast' => $this->foglang['ActiveMCTasks'],
            'active-snapins' => $this->foglang['ActiveSnapins'],
            'active-scheduled' => $this->foglang['ScheduledTasks'],
        );
        $this->HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
        $this->headerData = array(
            '<input type="checkbox" class="toggle-checkboxAction"/>',
            _('Started By:'),
            sprintf('%s<br/><small>%s</small>',_('Hostname'),_('MAC')),
            '',
            _('Start Time'),
            _('Status'),
        );
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="task[]" value="${id}"/>',
            '${startedby}',
            sprintf('<p><a href="?node=host&sub=edit&id=${host_id}" title="%s">${host_name}</a></p><small>${host_mac}</small>',_('Edit Host')),
            '${details_taskname}',
            '<small>${time}</small>',
            '${details_taskforce} <i class="fa fa-${icon_state} fa-1x icon" title="${state}"></i> <i class="fa fa-${icon_type} fa-1x icon" title="${type}"></i>',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false','task-id'=>'${id}'),
            array('width'=>65,'class'=>'l','id'=>'host-${host_id}'),
            array('width'=>120,'class'=>'l'),
            array('width'=>70,'class'=>'r'),
            array('width'=>100,'class'=>'r'),
            array('width'=>50,'class'=>'r filter-false'),
        );
        unset($this->data);
    }
    public function index() {
        $this->active();
    }
    public function search_post() {
        foreach (self::getClass('TaskManager')->search('',true) AS &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) continue;
            if ($Task->get('typeID') < 3) $forcetask = $Task->get('isForced') ? sprintf('<i class="icon-forced" title="%s"></i>',_('Task forced to start')) : sprintf('<i title="%s" class="icon-force icon" href="?node=task&sub=force-task&id=${id}"></i>',_('Force task to start'));
            $this->data[] = array(
                'startedby' => $Task->get('createdBy'),
                'details_taskforce' => $forcetask,
                'id' => $Task->get('id'),
                'name' => $Task->get('name'),
                'time' => $this->formatTime($Task->get('createdTime'),'Y-m-d H:i:s'),
                'state' => $Task->getTaskStateText(),
                'forced' => $Task->get('isForced'),
                'type' => $Task->getTaskTypeText(),
                'width' => 600 * $Task->get('percent') / 100,
                'elapsed' => $Task->get('timeElapsed'),
                'remains' => $Task->get('timeRemaining'),
                'percent' => $Task->get('pct'),
                'copied' => $Task->get('dataCopied'),
                'total' => $Task->get('dataTotal'),
                'bpm' => $Task->get('bpm'),
                'details_taskname' => $Task->get('name') ? sprintf('<div class="task-name">%s</div>',$Task->get('name')) : '',
                'host_id' => $Task->get('hostID'),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac')->__toString(),
                'icon_state' => $Task->getTaskState()->getIcon(),
                'icon_type' => $Task->getIcon(),
            );
            unset($Task,$Host);
        }
        $this->HookManager->processEvent('HOST_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    public function active() {
        $this->title = 'Active Tasks';
        foreach (self::getClass('Taskmanager')->find(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) AS &$Task) {
            if (!$Task->isValid()) continue;
            $Host = $Task->getHost();
            if (!$Host->isValid()) continue;
            $forcetask = '';
            if ($Task->get('typeID') < 3) $forcetask = $Task->get('isForced') ? sprintf('<i class="icon-forced" title="%s"></i>',_('Task forced to start')) : sprintf('<i title="%s" class="icon-force icon" href="?node=task&sub=force-task&id=${id}"></i>',_('Force task to start'));
            $this->data[] = array(
                'startedby' => $Task->get('createdBy'),
                'details_taskforce' => $forcetask,
                'id' => $Task->get('id'),
                'name' => $Task->get('name'),
                'time' => $this->formatTime($Task->get('createdTime'),'Y-m-d H:i:s'),
                'state' => $Task->getTaskStateText(),
                'forced' => $Task->get('isForced'),
                'type' => $Task->getTaskTypeText(),
                'width' => 600 * $Task->get('percent') / 100,
                'elapsed' => $Task->get('timeElapsed'),
                'remains' => $Task->get('timeRemaining'),
                'percent' => $Task->get('pct'),
                'copied' => $Task->get('dataCopied'),
                'total' => $Task->get('dataTotal'),
                'bpm' => $Task->get('bpm'),
                'details_taskname' => $Task->get('name') ? sprintf('<div class="task-name">%s</div>',$Task->get('name')) : '',
                'host_id' => $Task->get('hostID'),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac')->__toString(),
                'icon_state' => $Task->getTaskState()->getIcon(),
                'icon_type' => $Task->getIcon(),
            );
            unset($Task,$Host);
        }
        $this->HookManager->processEvent('HOST_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    public function listhosts() {
        $this->title = 'All Hosts';
        $up = self::getClass('TaskType',2);
        $down = self::getClass('TaskType',1);
        $up = sprintf('<a href="?node=task&sub=hostdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',$up->get('id'),$up->get('icon'),$up->get('name'));
        $down = sprintf('<a href="?node=task&sub=hostdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',$down->get('id'),$down->get('icon'),$down->get('name'));
        $adv = sprintf('<a href="?node=task&sub=hostadvanced&id=${id}#host-tasks"><i class="icon hand fa fa-arrows-alt fa-fw title="%s"></i></a>',_('Advanced'));
        $this->headerData = array(
            _('Host Name'),
            _('Assigned Image'),
            _('Tasking'),
        );
        $this->templates = array(
            '<a href="?node=host&sub=edit&id=${id}"/>${name}</a><br/><small>${mac}</small>',
            '<small>${imagename}</small>',
            sprintf('%s %s %s',$up,$down,$adv),
        );
        $this->attributes = array(
            array('width'=>100,'class'=>'i'),
            array('width'=>60,'class'=>'c'),
            array('width'=>60,'class'=>'r filter-false'),
        );
        foreach (self::getClass('HostManager')->find() AS &$Host) {
            if (!$Host->isValid() || $Host->get('pending')) continue;
            $this->data[] = array(
                'id' => $Host->get('id'),
                'name' => $Host->get('name'),
                'mac' => $Host->get('mac')->__toString(),
                'imagename' => $Host->getImageName(),
            );
            unset($Host);
        }
        $this->HookManager->processEvent('HOST_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    public function listgroups() {
        $this->title = 'All Groups';
        $mc = self::getClass('TaskType',8);
        $down = self::getClass('TaskType',1);
        $mc = sprintf('<a href="?node=task&sub=groupdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',$mc->get('id'),$mc->get('icon'),$mc->get('name'));
        $down = sprintf('<a href="?node=task&sub=groupdeploy&type=%s&id=${id}"><i class="icon hand fa fa-%s fa-fw" title="%s"></i></a>',$down->get('id'),$down->get('icon'),$down->get('name'));
        $adv = sprintf('<a href="?node=task&sub=groupadvanced&id=${id}#group-tasks"><i class="icon hand fa fa-arrows-alt fa-fw title="%s"></i></a>',_('Advanced'));
        $this->headerData = array(
            _('Group Name'),
            _('Tasking'),
        );
        $this->templates = array(
            '<a href="?node=group&sub=edit&id=${id}"/>${name}</a>',
            sprintf('%s %s %s',$mc,$down,$adv),
        );
        $this->attributes = array(
            array('width'=>100,'class'=>'i'),
            array('width'=>60,'class'=>'r filter-false'),
        );
        foreach (self::getClass('GroupManager')->find() AS &$Group) {
            if (!$Group->isValid()) continue;
            $this->data[] = array(
                'id' => $Group->get('id'),
                'name' => $Group->get('name'),
            );
            unset($Group);
        }
        $this->HookManager->processEvent('TasksListGroupData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    private function tasking($type) {
        try {
            $types = array('host','group');
            if (!in_array($type,$types)) throw new Exception(_('Invalid object type passed'));
            $var = ucfirst($type);
            $$var = self::getClass($var,(int)$_REQUEST['id']);
            if (!$$var->isValid()) throw new Exception(_(sprintf('Invalid %s',$var)));
            $TaskType = self::getClass('TaskType',(int)$_REQUEST['type']);
            if (!$TaskType->isValid()) throw new Exception(_('Invalid Task Type'));
            if ($type == 'host') {
                $Image = $$var->getImage();
                if (!$Image->isValid()) throw new Exception(_('Invalid image assigned to host'));
                if ($TaskType->isUpload() && $Image->get('protected')) throw new Exception(sprintf('%s: %s %s',_('Image'),$Image->get('name'),_('is protected')));
                $taskName = _('Quick Deploy');
            } else if ($type == 'group') {
                if ($TaskType->isMulticast() && !$$var->doMembersHaveUniformImages()) throw new Exception(_('Hosts do not have the same image assigned'));
                $taskName = _($TaskType->isMulticast() ? 'Multicast Quick Deploy' : 'Group Quick Deploy');
            }
            $enableSnapins = $TaskType->get('id') == 17 ? false : -1;
            $enableDebug = in_array($TaskType->get('id'),array(3,15,16));
            $$var->createImagePackage($TaskType->get('id'),$taskName,false,$enableDebug,$enableSnapins,false,$_SESSION['FOG_USERNAME']);
            $this->setMessage(_(sprintf('Successfully created %s tasking',$var)));
            $this->redirect("?node=$this->node");
        } catch (Exception $e) {
            printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create task'),$e->getMessage());
        }
    }
    public function hostdeploy() {
        $this->tasking('host');
    }
    public function groupdeploy() {
        $this->tasking('group');
    }
    private function advanced($type) {
        $this->title = sprintf('%s Advanced Actions',ucfirst($type));
        $id = (int)$_REQUEST['id'];
        unset($this->headerData);
        $this->templates = array(
            sprintf('<a href="?node=%s&sub=%sdeploy&id=${id}&type=${type}"><i class="fa fa-${icon} fa-fw fa-2x"/></i><br/>${name}</a>',$this->node,$type),
            '${description}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        foreach (self::getClass('TaskTypeManager')->find(array('access'=>array('both',$type),'isAdvanced'=>1),'AND','id') AS &$TaskType) {
            if (!$TaskType->isValid()) continue;
            $this->data[] = array(
                'id' => $id,
                'type' => $TaskType->get('id'),
                'icon' => $TaskType->get('icon'),
                'name' => $TaskType->get('name'),
                'description' => $TaskType->get('description'),
            );
            unset($TaskType);
        }
        $this->HookManager->processEvent('TASK_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    public function hostadvanced() {
        $this->advanced('host');
    }
    public function groupadvanced() {
        $this->advanced('group');
    }
    public function active_post() {
        if (!$this->ajax) $this->nonajax();
        self::getClass('TaskManager')->cancel($_REQUEST['task']);
        exit;
    }
    public function force_task() {
        try {
            $Task = self::getClass('Task',(int)$_REQUEST['id']);
            if (!$Task->isValid()) throw new Exception(_('Invalid task'));
            $this->HookManager->processEvent('TASK_FORCE',array('Task'=>&$Task));
            $Task->set('isForced',1)->save();
            $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        if ($this->ajax) {
            echo json_encode($result);
            exit;
        }
        $result['error'] ? $this->fatalError($result['error']) : $this->redirect(sprintf('?node=%s',$this->node));
    }
    public function active_multicast() {
        $this->title = 'Active Multi-cast Tasks';
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
        foreach (self::getClass('MulticastSessionsManager')->find(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) AS &$MulticastSession) {
            if (!$MulticastSession->isValid()) continue;
            $TaskState = $MulticastSession->getTaskState();
            if (!$TaskState->isValid()) continue;
            $this->data[] = array(
                'id' => $MulticastSession->get('id'),
                'name' => $MulticastSession->get('name') ? $MulticastSession->get('name') : _('MulticastTask'),
                'count' => self::getClass('MulticastSessionsAssociationManager')->count(array('msID'=>$MulticastSession->get('id'))),
                'start_date' => $this->formatTime($MulticastSession->get('starttime'),'Y-m-d H:i:s'),
                'state' => $TaskState->get('name') ? $TaskState->get('name') : '',
                'percent' => $MulticastSession->get('percent'),
            );
            unset($TaskState,$MulticastSession);
        }
        $this->HookManager->processEvent('TaskActiveMulticastData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function active_multicast_post() {
        if (!$this->ajax) $this->nonajax();
        $MulticastSessionIDs = (array)$_REQUEST['task'];
        $TaskIDs = $this->getSubObjectIDs('MulticastSessionsAssociation',array('msID'=>$MulticastSessionIDs),'taskID');
        self::getClass('TaskManager')->cancel($TaskIDs);
        self::getClass('MulticastSessionsManager')->cancel($_REQUEST['task']);
        unset($MulticastSessionIDs);
        exit;
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
            sprintf('<p><a href="?node=host&sub=edit&id=${host_id}" title="%s">${host_name}</a></p><small>${host_mac}</small>',_('Edit Host')),
            '${name}',
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
        foreach (self::getClass('SnapinTaskManager')->find(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) AS &$SnapinTask) {
            if (!$SnapinTask->isValid()) continue;
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) continue;
            $SnapinJob = $SnapinTask->getSnapinJob();
            if (!$SnapinJob->isValid()) continue;
            $Host = $SnapinJob->getHost();
            if (!$Host->isValid()) continue;
            if ($Host->get('snapinjob')->get('id') != $SnapinJob->get('id')) continue;
            if (!in_array($SnapinJob->get('stateID'),array_merge($this->getQueuedStates(),(array)$this->getProgressState()))) continue;
            $this->data[] = array(
                'id' => $SnapinTask->get('id'),
                'name' => $Snapin->get('name'),
                'host_id' => $Host->get('id'),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac')->__toString(),
                'startDate' => $this->formatTime($SnapinTask->get('checkin'),'Y-m-d H:i:s'),
                'state' => self::getClass('TaskState',$SnapinTask->get('stateID'))->get('name'),
            );
            unset($SnapinTask,$Snapin,$SnapinJob,$Host);
        }
        $this->HookManager->processEvent('TaskActiveSnapinsData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    private function nonajax() {
        $this->setMessage(_('Cannot cancel tasks this way'));
        $this->redirect($this->formAction);
    }
    public function active_snapins_post() {
        if (!$this->ajax) $this->nonajax();
        $SnapinTaskIDs = (array)$_REQUEST['task'];
        $SnapinJobIDs = $this->getSubObjectIDs('SnapinTask',array('id'=>$SnapinTaskIDs),'jobID');
        $SnapinJobIDs = $this->getSubObjectIDs('SnapinTask',array('id'=>$SnapinTaskIDs),'jobID');
        $HostIDs = $this->getSubObjectIDs('SnapinJob',array('id'=>(array)$SnapinJobIDs),'hostID');
        $TaskIDs = $this->getSubObjectIDs('Task',array('hostID'=>$HostIDs));
        self::getClass('TaskManager')->cancel($TaskIDs);
        self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        if (self::getClass('SnapinTaskManager')->count(array('jobID'=>$SnapinJobIDs)) < 1) self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        exit;
    }
    public function active_scheduled() {
        $this->title = 'Scheduled Tasks';
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            _('Host/Group Name'),
            _('Is Group'),
            _('Task Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active'),
            _('Type'),
        );
        $this->templates = array(
            '<input type="checkbox" name="task[]" value="${id}" class="toggle-action"/>',
            '<a href="?node=${hostgroup}&sub=edit&id=${hostgroupid}" title="Edit ${nametype}: ${hostgroupname}">${hostgroupname}</a>${extra}',
            '${groupbased}',
            '${details_taskname}',
            '${task_type}',
            '<small>${start_time}</small>',
            '${active}',
            '${type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false','task-id'=>'${id}'),
            array('width'=>100,'class'=>'l'),
            array('width'=>25,'class'=>'l'),
            array('width'=>110,'class'=>'l'),
            array('width'=>80,'class'=>'c'),
            array('width'=>70,'class'=>'c'),
            array('width'=>30,'class'=>'c'),
            array('width'=>80,'class'=>'c'),
        );
        foreach (self::getClass('ScheduledTaskManager')->find() AS &$ScheduledTask) {
            if (!$ScheduledTask->isValid()) continue;
            $ObjTest = $ScheduledTask->isGroupBased() ? $ScheduledTask->getGroup() : $ScheduledTask->getHost();
            if (!$ObjTest->isValid()) continue;
            $TaskType = $ScheduledTask->getTaskType();
            if (!$TaskType->isValid()) continue;
            $this->data[] = array(
                'id' => $ScheduledTask->get('id'),
                'start_time' => $ScheduledTask->getTime(),
                'groupbased' => $ScheduledTask->isGroupBased() ? _('Yes') : _('No'),
                'active' => $ScheduledTask->isActive() ? _('Yes') : _('No'),
                'type' => $ScheduledTask->getScheduledType(),
                'hostgroup' => $ScheduledTask->isGroupBased() ? 'group' : 'host',
                'hostgroupname' => $ObjTest->get('name'),
                'hostgroupid' => $ObjTest->get('id'),
                'details_taskname' => $ScheduledTask->get('name'),
                'task_type' => $TaskType->get('name'),
                'extra' => $ScheduledTask->isGroupBased() ? '' : sprintf('<br/><small>%s</small>',$ObjTest->get('mac')->__toString()),
                'nametype' => get_class($ObjTest),
            );
            unset($ScheduledTask,$ObjTest,$TaskType);
        }
        $this->HookManager->processEvent('TaskScheduledData',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
    }
    public function active_scheduled_post() {
        if (!$this->ajax) $this->nonajax();
        self::getClass('ScheduledTaskManager')->destroy(array('id'=>(array)$_REQUEST['task']));
        exit;
    }
}
