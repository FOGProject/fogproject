<?php
/** Class Name: TaskManagementPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This allows the user to view active, snapin, and
	multicast tasks.  You can also cancel tasks, or force
	them to operate through this page.

	Useful for:
	Managing tasks.
*/
class TaskManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Task Management';
	var $node = 'tasks';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	public function __construct($name = '')
	{
		parent::__construct($name);
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
			array('width' => 65, 'class' => 'l', 'id' => 'host-${id}'),
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
	public function index()
	{
		// Set title
		$this->title = _('All Tasks');
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('TaskManager')->find(array('stateID' => array(1,2,3))) AS $Task)
		{
			$Host = current($this->FOGCore->getClass('HostManager')->find(array('id' => $Task->get('hostID'))));
			$this->data[] = array(
				'columnkill' => '${details_taskforce} <a href="?node=tasks&sub=cancel-task&id=${id}"><span class="icon icon-kill" title="' . _('Cancel Task') . '"></span></a>',
				'startedby' => $Task->get('createdBy'),
				'id'	=> $Task->get('id'),
				'name'	=> $Task->get('name'),
				'time'	=> date('Y-m-d H:i:s',strtotime($Task->get('createdTime'))),
				'state'	=> $Task->getTaskStateText(),
				'forced'	=> ($Task->get('isForced') ? '1' : '0'),
				'type'	=> $Task->getTaskTypeText(),
				'percentText' => $Task->get('percent'),
				'class' => ++$i % 2 ? 'alt2' : 'alt1',
				'width' => 600 * ($Task->get('percent')/100),
				'elapsed' => $Task->get('timeElapsed'),
				'remains' => $Task->get('timeRemaining'),
				'percent' => $Task->get('percent'),
				'copied' => $Task->get('dataCopied'),
				'total' => $Task->get('dataTotal'),
				'bpm' => $Task->get('bpm'),
				'details_taskname'	=> ($Task->get('name')	? sprintf('<div class="task-name">%s</div>', $Task->get('name')) : ''),
				'details_taskforce'	=> ($Task->get('isForced') ? sprintf('<span class="icon icon-forced" title="%s"></span>', _('Task forced to start')) : ($Task->get('typeID') < 3 && $Task->get('stateID') < 3 ? sprintf('<a href="?node=tasks&sub=force-task&id=%s"><span class="icon icon-force" title="%s"></span></a>', $Task->get('id'),_('Force task to start')) : '&nbsp;')),
				'host_id'	=> $Task->get('hostID'),
				'host_name'	=> $Host ? $Host->get('name') : '',
				'host_mac'	=> $Host ? $Host->get('mac')->__toString() : '',
				'icon_state'	=> strtolower(str_replace(' ', '', $Task->getTaskStateText())),
				'icon_type'	=> strtolower(preg_replace(array('#[[:space:]]+#', '#[^\w-]#', '#\d+#', '#-{2,}#'), array('-', '', '', '-'), $Task->getTaskTypeText())),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search()
	{
		if ($_REQUEST['sub'] != 'search')
			$this->active();
		else
		{
			// Set title
			$this->title = _('Search');
			// Set search form
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
			// Find data -> Push data
			foreach ((array)$this->FOGCore->getClass('TaskManager')->find(array('stateID' => array(1,2,3))) AS $Task)
			{
				$Host = current($this->FOGCore->getClass('HostManager')->find(array('id' => $Task->get('hostID'))));
				$this->data[] = array(
					'columnkill' => $Task->get('stateID') == 1 || $Task->get('stateID') == 2 || $Task->get('stateID') == '3' ? '${details_taskforce} <a href="?node=tasks&sub=cancel-task&id=${id}"><span class="icon icon-kill" title="' . _('Cancel Task') . '"></span></a>' : '',
					'startedby' => $Task->get('createdBy'),
					'id'	=> $Task->get('id'),
					'name'	=> $Task->get('name'),
					'time'	=> date('Y-m-d H:i:s',strtotime($Task->get('createdTime'))),
					'state'	=> $Task->getTaskStateText(),
					'forced'	=> ($Task->get('isForced') ? '1' : '0'),
					'type'	=> $Task->getTaskTypeText(),
					'percentText' => $Task->get('percent'),
					'class' => ++$i % 2 ? 'alt2' : 'alt1',
					'width' => 600 * ($Task->get('percent')/100),
					'elapsed' => $Task->get('timeElapsed'),
					'remains' => $Task->get('timeRemaining'),
					'percent' => $Task->get('percent'),
					'copied' => $Task->get('dataCopied'),
					'total' => $Task->get('dataTotal'),
					'bpm' => $Task->get('bpm'),
					'details_taskname'	=> ($Task->get('name')	? sprintf('<div class="task-name">%s</div>', $Task->get('name')) : ''),
					'details_taskforce'	=> ($Task->get('isForced') ? sprintf('<span class="icon icon-forced" title="%s"></span>', _('Task forced to start')) : ($Task->get('typeID') < 3 && $Task->get('stateID') < 3 ? sprintf('<a href="?node=tasks&sub=force-task&id=%s"><span class="icon icon-force" title="%s"></span></a>', $Task->get('id'),_('Force task to start')) : '&nbsp;')),
					'host_id'	=> $Task->get('hostID'),
					'host_name'	=> $Host ? $Host->get('name') : '',
					'host_mac'	=> $Host ? $Host->get('mac')->__toString() : '',
					'icon_state'	=> strtolower(str_replace(' ', '', $Task->getTaskStateText())),
					'icon_type'	=> strtolower(preg_replace(array('#[[:space:]]+#', '#[^\w-]#', '#\d+#', '#-{2,}#'), array('-', '', '', '-'), $Task->getTaskTypeText())),
				);
			}
			// Hook
			$this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			// Output
			$this->render();
		}
	}
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('TaskManager')->search($keyword) AS $Task)
		{
			$Host = current($this->FOGCore->getClass('HostManager')->find(array('id' => $Task->get('hostID'))));
			$this->data[] = array(
				'columnkill' => $Task->get('stateID') == 1 || $Task->get('stateID') == 2 || $Task->get('stateID') == '3' ? '${details_taskforce} <a href="?node=tasks&sub=cancel-task&id=${id}"><span class="icon icon-kill" title="' . _('Cancel Task') . '"></span></a>' : '',
				'startedby' => $Task->get('createdBy'),
				'id'	=> $Task->get('id'),
				'name'	=> $Task->get('name'),
				'time'	=> date('Y-m-d H:i:s',strtotime($Task->get('createdTime'))),
				'state'	=> $Task->getTaskStateText(),
				'forced'	=> ($Task->get('isForced') ? '1' : '0'),
				'type'	=> $Task->getTaskTypeText(),
				'percentText' => $Task->get('percent'),
				'class' => ++$i % 2 ? 'alt2' : 'alt1',
				'width' => 600 * ($Task->get('percent')/100),
				'elapsed' => $Task->get('timeElapsed'),
				'remains' => $Task->get('timeRemaining'),
				'percent' => $Task->get('percent'),
				'copied' => $Task->get('dataCopied'),
				'total' => $Task->get('dataTotal'),
				'bpm' => $Task->get('bpm'),
				'details_taskname'	=> ($Task->get('name')	? sprintf('<div class="task-name">%s</div>', $Task->get('name')) : ''),
				'details_taskforce'	=> ($Task->get('isForced') ? sprintf('<span class="icon icon-forced" title="%s"></span>', _('Task forced to start')) : ($Task->get('typeID') < 3 && $Task->get('stateID') < 3 ? sprintf('<a href="?node=tasks&sub=force-task&id=%s"><span class="icon icon-force" title="%s"></span></a>', $Task->get('id'),_('Force task to start')) : '&nbsp;')),
				'host_id'	=> $Task->get('hostID'),
				'host_name'	=> $Host ? $Host->get('name') : '',
				'host_mac'	=> $Host ? $Host->get('mac')->__toString() : '',
				'icon_state'	=> strtolower(str_replace(' ', '', $Task->getTaskStateText())),
				'icon_type'	=> strtolower(preg_replace(array('#[[:space:]]+#', '#[^\w-]#', '#\d+#', '#-{2,}#'), array('-', '', '', '-'), $Task->getTaskTypeText())),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	// List all Hosts
	public function listhosts()
	{
		// Set title
		$this->title = _('All Hosts');
		// Header Row
		$this->headerData = array(
			_('Host Name'),
			_('MAC'),
			_('Download'),
			_('Upload'),
			_('Advanced'),
		);
		// Row templates
		$this->templates = array(
			'${host_name}',
			'${host_mac}',
			'${deployLink}',
			'${uploadLink}',
			'${advancedLink}',
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('width' => 170),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
		);
		$Hosts = $this->FOGCore->getClass('HostManager')->find();
		foreach ((array)$Hosts AS $Host)
		{
			$imgUp = '<a href="?node=tasks&sub=hostdeploy&type=2&id='.$Host->get('id').'"><span class="icon icon-upload" title="Upload"></span></a>';
			$imgDown = '<a href="?node=tasks&sub=hostdeploy&type=1&id='.$Host->get('id').'"><span class="icon icon-download" title="Download"></span></a>';
			$imgAdvanced = '<a href="?node=tasks&sub=hostadvanced&id='.$Host->get('id').'#host-tasks"><span class="icon icon-advanced" title="Advanced Deployment"></span></a>';
			$this->data[] = array(
				'id'			=>	$Host->get('id'),
				'host_name'		=>	$Host->get('name'),
				'host_mac'			=>	$Host->get('mac'),
				'uploadLink'	=>	$imgUp,
				'deployLink'	=>	$imgDown,
				'advancedLink'	=>	$imgAdvanced,
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Render
		$this->render();
	}
	public function hostdeploy()
	{
		$Host = new Host($this->REQUEST['id']);
		$taskTypeID = $this->REQUEST['type'];
		$snapin = '-1';
		$enableShutdown = false;
		$enableSnapins = ($_REQUEST['type'] == 17 ? false : -1);
		$taskName = 'Quick Deploy';
		try
		{
			$Host->createImagePackage($taskTypeID, $taskName, false, false, $enableSnapins);
			$this->FOGCore->setMessage('Successfully created tasking!');
			$this->FOGCore->redirect('?node=tasks&sub=active');
		}
		catch (Exception $e)
		{
			printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create deploy task'), $e->getMessage());
		}
	}
	public function hostadvanced()
	{
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes =  array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><img src="./images/${task_icon}" /><br />${task_name}</a>',
			'${task_desc}',
		);
		print "\n\t\t\t<div>";
		print "\n\t\t\t<h2>"._('Advanced Actions').'</h2>';
		// Find TaskTypes
		$TaskTypes = $this->FOGCore->getClass('TaskTypeManager')->find(array('access' => array('both', 'host'), 'isAdvanced' => '1'), 'AND', 'id');
		// Iterate -> Print
		foreach ((array)$TaskTypes AS $TaskType)
		{
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
	public function groupadvanced()
	{
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes =  array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'<a href="?node=${node}&sub=${sub}&id=${id}&type=${type}"><img src="./images/${task_icon}" /><br />${task_name}</a>',
			'${task_desc}',
		);
		print "\n\t\t\t<div>";
		print "\n\t\t\t<h2>"._('Advanced Actions').'</h2>';
		// Find TaskTypes
		$TaskTypes = $this->FOGCore->getClass('TaskTypeManager')->find(array('access' => array('both', 'group'), 'isAdvanced' => '1'), 'AND', 'id');
		// Iterate -> Print
		foreach ((array)$TaskTypes AS $TaskType)
		{
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
	public function listgroups()
	{
		$this->title = _('List all Groups');
		$this->headerData = array(
			_('Name'),
			_('Members'),
			_('Deploy'),
			_('Multicast'),
			_('Advanced'),
		);
		$this->attributes = array(
			array(),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
			array('width' => 55, 'class' => 'c'),
		);
		$this->templates = array(
			'${name}',
			'${memberCount}',
			'${deployLink}',
			'${multicastLink}',
			'${advancedLink}',
		);
		$Groups = $this->FOGCore->getClass('GroupManager')->find();
		foreach ((array)$Groups AS $Group)
		{
			$deployLink = '<a href="?node=tasks&sub=groupdeploy&type=1&id='.$Group->get('id').'"><span class="icon icon-download" title="Download"></span></a>';
			$multicastLink = '<a href="?node=tasks&sub=groupdeploy&type=8&id='.$Group->get('id').'"><span class="icon icon-multicast" title="Upload Multicast"></span></a>';
			$advancedLink = '<a href="?node=tasks&sub=groupadvanced&id='.$Group->get('id').'"><span class="icon icon-advanced" title="Advanced Deployment"></span></a>';
			$this->data[] = array(
				'id'			=>	$Group->get('id'),
				'name'			=>	$Group->get('name'),
				'memberCount'	=>	count($Group->get('hosts')),
				'deployLink'	=>	$deployLink,
				'advancedLink'	=>	$advancedLink,
				'multicastLink'	=>	$multicastLink,
			);
		}
		// Hook
		$this->HookManager->processEvent('TasksListGroupData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Render
		$this->render();
	}
	public function groupdeploy()
	{
		$Group = new Group($this->REQUEST['id']);
		$taskTypeID = $this->REQUEST['type'];
		$snapin = '-1';
		$enableShutdown = false;
		$enableSnapins = ($_REQUEST['type'] == 1 ? true : false);
		$taskName = ($taskTypeID == 8 ? 'Multicast Group Quick Deploy' : 'Group Quick Deploy');
		foreach ((array)$Group->get('hosts') AS $Host)
			$Host->createImagePackage($taskTypeID, $taskName, $enableShutdown, false, $enableSnapins, true);
		$this->FOGCore->setMessage('Successfully created Group tasking!');
		$this->FOGCore->redirect('?node=tasks&sub=active');
	}
	// Active Tasks
	public function active()
	{
		// Set title
		$this->title = _('Active Tasks');
		// Tasks
		$i = 0;
		foreach ((array)$this->FOGCore->getClass('TaskManager')->find(array('stateID' => array(1,2,3))) AS $Task)
		{
			$this->data[] = array(
				'columnkill' => '${details_taskforce} <a href="?node=tasks&sub=cancel-task&id=${id}"><span class="icon icon-kill" title="' . _('Cancel Task') . '"></span></a>',
				'startedby' => $Task->get('createdBy'),
				'id'	=> $Task->get('id'),
				'name'	=> $Task->get('name'),
				'time'	=> date('Y-m-d H:i:s',strtotime($Task->get('createdTime'))),
				'state'	=> $Task->getTaskStateText(),
				'forced'	=> ($Task->get('isForced') ? '1' : '0'),
				'type'	=> $Task->getTaskTypeText(),
				'percentText' => $Task->get('percent'),
				'class' => ++$i % 2 ? 'alt2' : 'alt1',
				'width' => 600 * ($Task->get('percent')/100),
				'elapsed' => $Task->get('timeElapsed'),
				'remains' => $Task->get('timeRemaining'),
				'percent' => $Task->get('percent'),
				'copied' => $Task->get('dataCopied'),
				'total' => $Task->get('dataTotal'),
				'bpm' => $Task->get('bpm'),
				'details_taskname'	=> ($Task->get('name')	? sprintf('<div class="task-name">%s</div>', $Task->get('name')) : ''),
				'details_taskforce'	=> ($Task->get('isForced') ? sprintf('<span class="icon icon-forced" title="%s"></span>', _('Task forced to start')) : ($Task->get('typeID') < 3 && $Task->get('stateID') < 3 ? sprintf('<a href="?node=tasks&sub=force-task&id=%s"><span class="icon icon-force" title="%s"></span></a>', $Task->get('id'),_('Force task to start')) : '&nbsp;')),
				'host_id'	=> $Task->get('hostID'),
				'host_name'	=> $Task->getHost()->get('name'),
				'host_mac'	=> $Task->getHost()->get('mac')->__toString(),
				'icon_state'	=> strtolower(str_replace(' ', '', $Task->getTaskStateText())),
				'icon_type'	=> strtolower(preg_replace(array('#[[:space:]]+#', '#[^\w-]#', '#\d+#', '#-{2,}#'), array('-', '', '', '-'), $Task->getTaskTypeText())),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	// Active Tasks - Force Task Start
	public function force_task()
	{
		// Find
		$Task = new Task($this->REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('TASK_FORCE', array('Task' => &$Task));
		// Force
		try
		{
			$result['success'] = $Task->set('isForced', '1')->save();
		}
		catch (Exception $e)
		{
			$result['error'] = $e->getMessage();
		}
		// Output
		if ($this->FOGCore->isAJAXRequest())
			print json_encode($result);
		else
		{
			if ($result['error'])
				$this->fatalError($result['error']);
			else
				$this->FOGCore->redirect(sprintf('?node=%s', $this->node));
		}
	}
	// Active Tasks - Cancel Task
	public function cancel_task()
	{
		// Find
		$Task = new Task($this->REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('TASK_CANCEL', array('Task' => &$Task));
		// Force
		try
		{
			// Cencel task - will throw Exception on error
			$Task->cancel();
			// Success
			$result['success'] = true;
			if ($Task->get('typeID') == 12 || $Task->get('typeID') == 13)
			{
				$Host = new Host($Task->get('hostID'));
				$SnapinJob = $Host->getActiveSnapinJob();
				$SnapinTasks = $this->FOGCore->getClass('SnapinTaskManager')->find(array('jobID' => $SnapinJob->get('id')));
				print $SnapinJob->get('id');
				foreach ((array)$SnapinTasks AS $SnapinTask)
					$SnapinTask->destroy();
				$SnapinJob->destroy();
			}
			if ($Task->get('typeID') == 8)
			{
				$MSA = current($this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
				$MS = new MulticastSessions($MSA->get('msID'));
				$MS->set('clients', $MS->get('clients')-1)->save();
				if ($MS->get('clients') <= 0)
					$MS->set('completetime',date('Y-m-d H:i:s'))->set('stateID', 5)->save();
			}
			$Task->cancel();
		}
		catch (Exception $e)
		{
			// Failure
			$result['error'] = $e->getMessage();
		}
		
		// Output
		if ($this->FOGCore->isAJAXRequest())
			print json_encode($result);
		else
		{
			if ($result['error'])
				$this->fatalError($result['error']);
			else
				$this->FOGCore->redirect(sprintf('?node=%s', $this->node));
		}
	}
	public function remove_multicast_task()
	{
		try
		{
			// Remove task from associations and multicast sessions.
			$MSAs = $this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $_REQUEST['id']));
			$MulticastTask = new MulticastSessions($_REQUEST['id']);
			foreach((array)$MSAs AS $MSA)
			{
				$Task = new Task($MSA->get('taskID'));
				$MSA->destroy();
				$Task->cancel();
			}
			$MulticastTask->destroy();
		}
		catch (Exception $e){}
	}
	public function active_multicast()
	{
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
			'<a href="?node=tasks&sub=remove-multicast-task&id=${id}"><span class="icon icon-kill" title="Kill Task"></span></a>',
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
		foreach ((array)$this->FOGCore->getClass('MulticastSessionsManager')->find(array('stateID' => array(1,2,3))) AS $MS)
		{
			$TS = new TaskState($MS->get('stateID'));
			$this->data[] = array(
				'id' => $MS->get('id'),
				'name' => ($MS->get('name') ? $MS->get('name') : 'Multicast Task'),
				'count' => count($this->FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('msID' => $MS->get('id')))),
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
	public function active_snapins()
	{
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
			'<form method="post" method="?node=tasks&sub=active-snapins">${name}',
			'${startDate}',
			'${state}',
			'<input type="checkbox" id="${id}" class="delid" name="rmid" value="%id%" onclick="this.form.submit()" title="Kill Task" /><label for="${id}">'._('Delete').'</label></form>',
		);
		$this->attributes = array(
			array(),
			array('class' => 'c'),
			array('class' => 'c'),
			array('class' => 'c'),
			array('width' => 40, 'class' => 'c'),
		);
		$SnapinTasks = $this->FOGCore->getClass('SnapinTaskManager')->find(array('stateID' => array(-1,0,1)));
		foreach ((array)$SnapinTasks AS $SnapinTask)
		{
			$SnapinJobs = $this->FOGCore->getClass('SnapinJobManager')->find(array('id' => $SnapinTask->get('jobID')));
			foreach ((array)$SnapinJobs AS $SnapinJob)
			{
				$this->data[] = array(
					'id'		=> $SnapinTask->get('id'),
					'name'		=> $this->FOGCore->getClass('Snapin',$SnapinTask->get('snapinID'))->get('name'),
					'hostID'	=> $this->FOGCore->getClass('Host',$SnapinJob->get('hostID'))->get('id'),
					'host_name'	=> $this->FOGCore->getClass('Host',$SnapinJob->get('hostID'))->get('name'),
					'startDate' => $SnapinTask->get('checkin'),
					'state'		=> ($SnapinTask->get('stateID') == 0 ? 'Queued' : ($SnapinTask->get('stateID') == 1 ? 'In-Progress' : 'N/A')),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('TaskActiveSnapinsData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function active_snapins_post()
	{
		if(isset($_POST['rmid']))
		{
			// Get the snapin task.
			$SnapinTask = new SnapinTask($_POST['rmid']);
			// Get the job associated with the task.
			$SnapinJob = new SnapinJob($SnapinTask->get('jobID'));
			// Get the referenced host.
			$Host = new Host($SnapinJob->get('hostID'));
			// Get the active task.
			$Task = current($this->FOGCore->getClass('TaskManager')->find(array('hostID' => $Host->get('id'),'stateID' => array(1,2,3))));
			// Check the Jobs to Snapin tasks to verify if this is the only one.
			$SnapinJobManager = $this->FOGCore->getClass('SnapinTaskManager')->find(array('jobID' => $SnapinJob->get('id')));
			// This task is the last task, destroy the job and the task
			if (count($SnapinJobManager) <= 1)
			{
				$SnapinJob->destroy();
				if ($Task)
					$Task->cancel();
			}
			// Destroy the individual task.
			$SnapinTask->destroy();
			// Redirect to the current page.
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function scheduled()
	{
		// Set title
		$this->title = 'Scheduled Tasks';
		// Header row
		$this->headerData = array(
			_('Name:'),
			_('Is Group'),
			_('Task Name'),
			_('Start Time'),
			_('Active/Type'),
			_('Kill'),
		);
		// Row templates
		$this->templates = array(
			'<a href="?node=${hostgroup}&sub=edit&id=${id}" title="Edit ${hostgroupname}">${hostgroupname}</a>',
			'${groupbased}<form method="post" action="?node=tasks&sub=scheduled">',
			'${details_taskname}',
			'<small>${time}</small>',
			'${active}/${type}',
			'<input type="checkbox" name="rmid" id="r${schedtaskid}" class="delid" value="${schedtaskid}" onclick="this.form.submit()" /><label for="r${schedtaskid}">'._('Delete').'</label></form>',
		);
		// Row attributes
		$this->attributes = array(
			array('width' => 120, 'class' => 'l'),
			array(),
			array('width' => 110, 'class' => 'l'),
			array('width' => 70, 'class' => 'c'),
			array('width' => 100, 'class' => 'c', 'style' => 'padding-right: 10px'),
			array('class' => 'c'),
		);
		foreach ((array)$this->FOGCore->getClass('ScheduledTaskManager')->find() AS $task)
		{
			$taskType = $task->getTaskType();
			$taskTime = ($task->get('type') == 'C' ? $task->get('minute').' '.$task->get('hour').' '.$task->get('dayOfMonth').' '.$task->get('month').' '.$task->get('dayOfWeek') : $task->get('scheduleTime'));
			$hostGroupName = ($task->isGroupBased() ? $task->getGroup() : $task->getHost());
			$this->data[] = array(
				'columnkill' => '${details_taskforce} <a href="?node=tasks&sub=cancel-task&id=${id}"><span class="icon icon-kill" title="' . _('Cancel Task') . '"></span></a>',
				'hostgroup' => $task->isGroupBased() ? 'group' : 'host',
				'hostgroupname' => $hostGroupName,
				'id' => $hostGroupName->get('id'),
				'groupbased' => $task->isGroupBased() ? _('Yes') : _('No'),
				'details_taskname' => $task->get('name'),
				'time' => $task->get('type') != 'C' ? $this->FOGCore->formatTime($taskTime) : $taskTime,
				'active' => $task->get('isActive') ? 'Yes' : 'No',
				'type' => $task->get('type') == 'C' ? 'Cron' : 'Delayed',
				'schedtaskid' => $task->get('id'),
			);
		}
		// Hook
		$this->HookManager->processEvent('TaskScheduledData', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function scheduled_post()
	{
		if(isset($_POST['rmid']))
		{
			$this->HookManager->processEvent('TaskScheduledRemove');
			$Task = new ScheduledTask($_REQUEST['rmid']);
			if($Task->destroy())
				$this->HookManager->processEvent('TaskScheduledRemoveSuccess');
			else
				$this->HookManager->processEvent('TaskScheduledRemoveFail');
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
