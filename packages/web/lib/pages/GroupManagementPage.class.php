<<<<<<< HEAD
<?php
/**	Class Name: GroupManagementPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
    Description: This is an extention of the FOGPage Class
    This class controls the group management page for FOG.
	It, now, allows group creation within as opposed to the
	old method it used.

	Manages group settings such as:
	Image Association, Active Directory, Snapin Add and removal,
	Printer association, and Service configurations.

	Useful for:
	Making setting changes quickly on multiple systems at a time.
**/
class GroupManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Group Management';
	var $node = 'group';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	/** __construct($name = '')
		Builds the default header and template information
		for the Group page.
		This builds the default display for index and search.
	*/
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header row
		$this->headerData = array(
			_('Name'),
			//_('Description'),
			_('Members'),
			'',
			'',
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=group&sub=edit&%s=${id}" title="Edit">${name}</a>', $this->id),
			//'${description}',
			'${count}',
			sprintf('<a href="?node=group&sub=deploy&type=1&%s=${id}"><span class="icon icon-download" title="Download"></span></a> <a href="?node=group&sub=deploy&type=8&%s=${id}"><span class="icon icon-multicast" title="Multi-cast"></span></a> <a href="?node=group&sub=edit&%s=${id}#group-tasks"><span class="icon icon-deploy" title="Deploy"></span></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
			sprintf('<a href="?node=group&sub=edit&%s=${id}"><span class="icon icon-edit" title="Edit"></span></a> <a href="?node=group&sub=delete&%s=${id}"><span class="icon icon-delete" title="Delete"></span></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
		);
		// Row attributes
		$this->attributes = array(
			array(),
			//array('width' => 150),
			array('width' => 40, 'class' => 'c'),
			array('width' => 90, 'class' => 'c'),
			array('width' => 50, 'class' => 'c')
		);
	}
	// Pages
	/** index()
		This is the first page displayed.  However, if search is used
		as the default view, this isn't displayed.  But it still serves
		as a means to display data, if there was a problem with the search
		function.
	*/
	public function index()
	{
		// Set title
		$this->title = _('All Groups');
		// Find data
		$Groups = $this->FOGCore->getClass('GroupManager')->find();
		// Row data
		foreach ((array)$Groups AS $Group)
		{
			$this->data[] = array(
				'id'		=> $Group->get('id'),
				'name'		=> $Group->get('name'),
				'description'	=> $Group->get('description'),
				'count'		=> $Group->getHostCount(),
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('GROUP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** search()
		This function displays the search form used by the system.
		If default view is search, this is displayed.  You can search
		for the groups using this.
	*/
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('GROUP_SEARCH');
		// Output
		$this->render();
	}
	/** search_post()
		This function is how the data gets processed and displayed based on what was
		searched for.
	*/
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// Find data -> Push data
		foreach((array)$this->FOGCore->getClass('GroupManager')->search($keyword) AS $Group)
		{
			$this->data[] = array(
				'id'		=> $Group->get('id'),
				'name'		=> $Group->get('name'),
				'description'	=> $Group->get('description'),
				'count'		=> $Group->getHostCount(), 
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** add()
		This function is what creates the new group.
		You can do this from two places.  You can do it from the
		Host List, but now you can also do it from the Group page
		as well.  In years past, you could only create a group using
		the host list page.
	*/
	public function add()
	{
		// Set title
		$this->title = _('New Group');
		// Headerdata
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${formField}',
		);
		$fields = array(
			_('Group Name') => '<input type="text" name="name" />',
			_('Group Description') => '<textarea name="description" rows="8" cols="40"></textarea>',
			_('Group Kernel') => '<input type="text" name="kern" />',
			_('Group Kernel Arguments') => '<input type="text" name="args" />',
			_('Group Primary Disk') => '<input type="text" name="dev" />',
			'' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach ((array)$fields AS $field => $formField)
		{
			$this->data[] = array(
				'field' => $field,
				'formField' => $formField,
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	/** add_post()
		This is the function that actually creates the group.
	*/
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('GROUP_ADD_POST');
		// POST
		try
		{
			// Error checking
			if (empty($_POST['name']))
				throw new Exception('Group Name is required');
			if ($this->FOGCore->getClass('GroupManager')->exists($_POST['name']))
				throw new Exception('Group Name already exists');
			// Define new Image object with data provided
			$Group = new Group(array(
				'name'		=> $_POST['name'],
				'description'	=> $_POST['description'],
				'kernel'	=> $_POST['kern'],
				'kernelArgs'	=> $_POST['args'],
				'kernelDevice'	=> $_POST['dev']
			));
			// Save to database
			if ($Group->save())
			{
				// Hook
				$this->HookManager->processEvent('GROUP_ADD_SUCCESS', array('Group' => &$Group));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Group added'), $Group->get('id'), $Group->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Group added'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Group->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('GROUP_ADD_FAIL', array('Group' => &$Group));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Group'), $_POST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** edit()
		This is how you edit a group.  You can also
		add hosts from this page now.  You used to only
		be able to add hosts to the groups from the host list page.
		This should make some things easier.  You can also use it
		to setup tasks for the group, snapins, printers, active directory,
		images, etc...
	*/
	public function edit()
	{
		// Find
		$Group = new Group($_REQUEST['id']);	
		// If location is installed.
		$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		// If all hosts have the same image setup up the selection.
		foreach ((array)$Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$imageID[] = $Host && $Host->isValid() ? $Host->getImage()->get('id') : '';
				$groupKey[] = $Host && $Host->isValid() ? base64_decode($Host->get('productKey')) : '';
			}
		}
		$imageIDMult = (is_array($imageID) ? array_unique($imageID) : $imageID);
		$groupKeyMult = (is_array($groupKey) ? array_unique($groupKey) : $groupKey);
		$groupKeyMult = array_filter($groupKeyMult);
		if (count($imageIDMult) == 1)
			$imageMatchID = $Host && $Host->isValid() ? $Host->getImage()->get('id') : '';
		// For the location plugin.  If all have the same location, setup the selection to let people know.
		if ($LocPluginInst)
		{
			// To set the location similar to the rest of the groups.
			foreach ((array)$Group->get('hosts') AS $Host)
			{
				if ($Host && $Host->isValid())
				{
					$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
					$LA ? $locationID[] = $LA->get('locationID') : null;
				}
			}
			$locationIDMult = (is_array($locationID) ? array_unique($locationID) : $locationID);
			if (count($locationIDMult) == 1)
				$locationMatchID = $LA && $LA->isValid() ? $LA->get('locationID') : null;
		}
		// Title - set title for page title in window
		$this->title = sprintf('%s: %s', _('Edit'), $Group->get('name'));
		// Headerdata
		unset ($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Group Name') => '<input type="text" name="name" value="${group_name}" />',
			_('Group Description') => '<textarea name="description" rows="8" cols="40">${group_desc}</textarea>',
			_('Group Product Key') => '<input id="productKey" type="text" name="key" value="${group_key}" />',
			($LocPluginInst ? _('Group Location') : null) => ($LocPluginInst ? $this->FOGCore->getClass('LocationManager')->buildSelectBox($locationMatchID) : null),
			_('Group Kernel') => '<input type="text" name="kern" value="${group_kern}" />',
			_('Group Kernel Arguments') => '<input type="text" name="args" value="${group_args}" />',
			_('Group Primary Disk') => '<input type="text" name="dev" value="${group_devs}" />',
			'<input type="hidden" name="updategroup" value="1" />' => '<input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-general">';
		print "\n\t\t\t".'<input type="hidden" name="'.$this->id.'" value="'.$_REQUEST['id'].'" />';
		print "\n\t\t\t".'<div id="tab-container">';
		print "\n\t\t\t<!-- General -->";
		print "\n\t\t\t".'<div id="group-general">';
		print "\n\t\t\t".'<h2>'._('Modify Group').': '.$Group->get('name').'</h2>';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'group_name' => $Group->get('name'),
				'group_desc' => $Group->get('description'),
				'group_kern' => $Group->get('kernel'),
				'group_args' => $Group->get('kernelArgs'),
				'group_devs' => $Group->get('kernelDev'),
				'group_key' => count($groupKeyMult) == 1 ? $groupKeyMult[0] : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_DATA_GEN', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset ($this->data);
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Basic Tasks -->";
		$this->attributes = array(
			array('class' => 'l'),
			array('style' => 'padding-left: 20px'),
		);
		$this->templates = array(
			'<a href="?node=${node}&sub=${sub}&id=${group_id}${task_type}"><img src="images/${task_icon}" /><br/>${task_name}</a>',
			'${task_desc}',
		);
		print "\n\t\t\t".'<div id="group-tasks">';
		print "\n\t\t\t<h2>"._('Group Tasks').'</h2>';
		// Find TaskTypes
		$TaskTypes = $this->FOGCore->getClass('TaskTypeManager')->find(array('access' => array('both', 'group'), 'isAdvanced' => '0'), 'AND', 'id');
		// Iterate -> Print
		foreach ((array)$TaskTypes AS $TaskType)
		{
			$this->data[] = array(
				'node' => $this->node,
				'sub' => 'deploy',
				'group_id' => $Group->get('id'),
				'task_type' => '&type='.$TaskType->get('id'),
				'task_icon' => $TaskType->get('icon'),
				'task_name' => $TaskType->get('name'),
				'task_desc' => $TaskType->get('description'),
			);
		}
		$this->data[] = array(
			'node' => $this->node,
			'sub' => 'edit',
			'group_id' => $Group->get('id'),
			'task_type' => '#group-tasks" class="advanced-tasks-link',
			'task_icon' => 'host-advanced.png',
			'task_name' => _('Advanced'),
			'task_desc' => _('View advanced tasks for this group.'),
		);
		// Hook
		$this->HookManager->processEvent('GROUP_DATA_TASKS', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset ($this->data);
		print "\n\t\t\t".'<div id="advanced-tasks" class="hidden">';
		print "\n\t\t\t".'<h2>'._('Advanced Actions').'</h2>';
		// Find TaskTypes
		$TaskTypes = $this->FOGCore->getClass('TaskTypeManager')->find(array('access' => array('both', 'group'), 'isAdvanced' => '1'), 'AND', 'id');
		// Iterate -> Print
		foreach ((array)$TaskTypes AS $TaskType)
		{
			$this->data[] = array(
				'node' => $this->node,
				'sub' => 'deploy',
				'group_id' => $Group->get('id'),
				'task_type' => '&type='.$TaskType->get('id'),
				'task_icon' => $TaskType->get('icon'),
				'task_name' => $TaskType->get('name'),
				'task_desc' => $TaskType->get('description'),
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_DATA_ADV', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		$GAs = $this->FOGCore->getClass('GroupAssociationManager')->find(array('groupID' => $Group->get('id')));
		$AllGAs = $this->FOGCore->getClass('GroupAssociationManager')->find();
		foreach((array)$AllGAs AS $GAAll)
		{
			$MyCurGroup = new Group($GAAll->get('groupID'));
			if ($MyCurGroup->isValid())
				$AllHostIDs[] = $GAAll->get('hostID');
		}
		$AllHostIDs = array_unique((array)$AllHostIDs);
		foreach((array)$GAs AS $GA)
			$HostIDs[] = $GA->get('hostID');
		$HostStuff = $this->FOGCore->getClass('HostManager')->buildSelectBox('','host[]" multiple="','name',$HostIDs);
		// Unset for later use.
		unset ($this->data);
		print '</div>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t<!-- Membership -->";
		print "\n\t\t\t".'<div id="group-membership">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-membership">';
		// These methods below will get all Hosts without a Group Association at all.
		$AllHostsInGeneral = $this->FOGCore->getClass('HostManager')->find();
		foreach($AllHostsInGeneral AS $HostGroupStuff)
		{
			if (!in_array($HostGroupStuff->get('id'),(array)$AllHostIDs))
				$GroupOption[] = '<option value="'.$HostGroupStuff->get('id').'">'.$HostGroupStuff->get('name').' - ('.$HostGroupStuff->get('id').')</option>';
		}
		if ($HostStuff)
		{
			print "\n\t\t\t".'<h2>'._('Modify Membership for').' '.$Group->get('name').'</h2>';
			print "\n\t\t\t".'<p><center>'._('Add hosts to group').' '.$Group->get('name').':</center></p>';
			print "\n\t\t\t".'<p><center>'.$HostStuff.'</center></p>';
		}
		if ($GroupOption)
		{
			print "\n\t\t\t"._('Check here to see hosts not within a group').'&nbsp;&nbsp;<input type="checkbox" name="hostNoShow" id="hostNoShow" />';
			print "\n\t\t\t".'<center><div id="hostNoGroup">';
			print "\n\t\t\t".'<p><center>'._('Hosts below do not belong to a group').'</center></p>';
			print "\n\t\t\t".'<select name="host[]" multiple="" autocomplete="off">'."\n\t\t\t".'<option value=""> - '._('Please select an option').' - </option>'.implode("\n\t\t\t\t",(array)$GroupOption)."\n\t\t\t</select>";
			print "\n\t\t\t".'</div></center>';
		}
		if ($HostStuff || $GroupOption)
		{
			print "\n\t\t\t".'<center><input type="submit" value="'._('Add Host(s) to Group').'" /></center>';
			print "\n\t\t\t</form>";
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-membership">';
		}
		$this->headerData = array(
            _('Hostname'),
            ('Deployed'),
            _('Remove'),
            _('Image'),
		);
		$this->attributes = array(
            array(),
            array(),
            array(),
            array(),
		);
		$this->templates = array(
			'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
			'<small>${deployed}</small>',
			'<input type="checkbox" name="member" value="${host_id}" class="delid" onclick="this.form.submit()" id="memberdel${host_id}" /><label for="memberdel${host_id}">Delete</label>',
			'<small>${image_name}</small>',
		);
		foreach ((array)$Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
                    'host_id'   => $Host->get('id'),
                    'deployed' => checkdate($this->FOGCore->formatTime($Host->get('deployed'),'m'),$this->FOGCore->formatTime($Host->get('deployed'),'d'),$this->FOGCore->formatTime($Host->get('deployed'),'Y')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
                    'host_name' => $Host->get('name'),
                    'host_mac'  => $Host->get('mac')->__toString(),
                    'image_name' => $this->FOGCore->getClass('ImageManager')->buildSelectBox($Host->getImage()->get('id'),$Host->get('name').'_'.$Host->get('id')),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('GROUP_MEMBERSHIP', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '<input type="hidden" name="updatehosts" value="1" /><center><input type="submit" value="'._('Update Hosts').'" /></center>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Image Association -->";
		print "\n\t\t\t".'<div id="group-image">';
		print "\n\t\t\t<h2>"._('Image Association for').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-image">';
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$this->data[] = array(
			'field' => $this->FOGCore->getClass('ImageManager')->buildSelectBox($imageMatchID).'</select>',
			'input' => '<input type="submit" value="'._('Update Images').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_IMAGE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print "</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Add Snap-ins -->";
		print "\n\t\t\t".'<div id="group-snap-add">';
		print "\n\t\t\t<h2>"._('Add Snapin to all hosts in').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-snap-add">';
		$this->data[] = array(
			'field' => $this->FOGCore->getClass('SnapinManager')->buildSelectBox().'</select>',
			'input' => '<input type="hidden" name="gsnapinadd" value="1" /><input type="submit" value="'._('Add Snapin').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_SNAP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</form>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Remove Snap-ins -->";
		print "\n\t\t\t".'<div id="group-snap-del">';
		print "\n\t\t\t<h2>"._('Remove Snapin to all hosts in').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-snap-del">';
		$this->data[] = array(
			'field' => $this->FOGCore->getClass('SnapinManager')->buildSelectBox().'</select>',
			'input' => '<input type="hidden" name="gsnapindel" value="1" /><input type="submit" value="'._('Remove Snapin').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_SNAP_DEL', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</form>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Service Settings -->";
		$this->attributes = array(
			array('width' => 270),
			array('class' => 'c'),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${mod_name}',
			'${input}',
			'${span}',
		);
		$this->data[] = array(
			'mod_name' => 'Select/Deselect All',
			'input' => '<input type="checkbox" class="checkboxes" id="checkAll" name="checkAll" value="checkAll" />',
			'span' => ''
		);
		print "\n\t\t\t".'<div id="group-service">';
		print "\n\t\t\t".'<h2>'._('Service Configuration').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('General')."</legend>";
        foreach ((array)$this->FOGCore->getClass('ModuleManager')->find() AS $Module)
        {
			$i = 0;
			foreach((array)$Group->get('hosts') AS $Host)
			{
				if ($Host && $Host->isValid())
				{
					foreach((array)$Host->get('modules') AS $ModHost)
					{
						if ($ModHost && $ModHost->isValid())
						{
							if ($ModHost->get('id') == $Module->get('id'))
								$ModOns[] = $ModHost->get('id');
						}
					}
					$i = count($ModOns);
				}
			}
			$this->data[] = array(
				'input' => '<input type="checkbox" class="checkboxes" name="${mod_shname}" value="${mod_id}" ${checked} />',
				'span' => '<span class="icon icon-help hand" title="${mod_desc}"></span>',
				'checked' => ($i == $Group->getHostCount() ? 'checked="checked"' : ''),
				'mod_name' => $Module->get('name'),
				'mod_shname' => $Module->get('shortName'),
				'mod_id' => $Module->get('id'),
				'mod_desc' => str_replace('"','\"',$Module->get('description')),
			);
			unset($ModOns);
		}
		$this->data[] = array(
			'mod_name' => '<input type="hidden" name="updatestatus" value="1" />',
			'input' => '',
			'span' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_MODULES', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print "</fieldset>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('Group Screen Resolution').'</legend>';
		$this->attributes = array(
				array('class' => 'l','style' => 'padding-right: 25px'),
				array('class' => 'c'),
				array('class' => 'r'),
		);
		$this->templates = array(
			'${field}',
			'${input}',
			'${span}',
		);
		$Services = $this->FOGCore->getClass('ServiceManager')->find(array('name' => array('FOG_SERVICE_DISPLAYMANAGER_X','FOG_SERVICE_DISPLAYMANAGER_Y','FOG_SERVICE_DISPLAYMANAGER_R')),'OR','id');
		foreach((array)$Services AS $Service)
		{
			$this->data[] = array(
				'input' => '<input type="text" name="${type}" value="${disp}" />',
				'span' => '<span class="icon icon-help hand" title="${desc}"></span>',
				'field' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? _('Screen Width (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? _('Screen Height (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? _('Screen Refresh Rate (in Hz)') : null))),
				'type' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? 'x' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? 'y' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? 'r' : null))),
				'disp' => $Service->get('value'),
				'desc' => $Service->get('description'),
			);
		}
		$this->data[] = array(
			'input' => '<input type="hidden" name="updatedisplay" value="1" />',
			'span' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_DISPLAY', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</fieldset>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('Auto Log Out Settings').'</legend>';
		$this->attributes = array(
			array('width' => 270),
			array('class' => 'c'),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${field}',
			'${input}',
			'${desc}',
		);
		$Service = current($this->FOGCore->getClass('ServiceManager')->find(array('name' => 'FOG_SERVICE_AUTOLOGOFF_MIN')));
		$this->data[] = array(
			'field' => _('Auto Log Out Time (in minutes)'),
			'input' => '<input type="text" name="tme" value="${value}" />',
			'desc' => '<span class="icon icon-help hand" title="${serv_desc}"></span>',
			'value' => $Service->get('value'),
			'serv_desc' => $Service->get('description'),
		);
		$this->data[] = array(
			'field' => '<input type="hidden" name="updatealo" value="1" />',
			'input' => null,
			'desc' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_ALO', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</fieldset>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Active Directory -->";
		print "\n\t\t\t".'<div id="group-active-directory">';
		print "\n\t\t\t<h2>"._('Modify AD information for').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-active-directory">';
		$OUs = explode('|',$this->FOGCore->getSetting('FOG_AD_DEFAULT_OU'));
		foreach ((array)$OUs AS $OU)
			$OUOptions[] = $OU;
		$OUOptions = array_filter($OUOptions);
		if (count($OUOptions) > 1)
		{
			$OUs = array_unique((array)$OUOptions);
			$optionOU[] = '<option value=""> - '._('Please select an option').' - </option>';
			foreach ($OUs AS $OU)
			{
				$opt = preg_match('#;#i',$OU) ? preg_replace('#;#i','',$OU) : $OU;
				$optionOU[] = '<option value="'.$opt.'"'.(preg_match('#;#i',$OU) ? ' selected="selected"' : '').'>'.$opt.'</option>';
            }    
			$OUOptions = '<select id="adOU" class="smaller" name="ou">'.implode($optionOU).'</select>';
		}
		else
			$OUOptions = '<input id="adOU" class="smaller" type="text" name="ou" autocomplete="off" />';
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Join Domain after image task') => '<input id="adEnabled" type="checkbox" name="domain" value="on"'.($_REQUEST['domain'] == 'on' ? ' selected="selected"' : '').' />',
			_('Domain name') => '<input id="adDomain" type="text" name="domainname" autocomplete="off" />',
			_('Organizational Unit') => $OUOptions,
			_('Domain Username') => '<input id="adUsername" type="text" name="domainuser" autocomplete="off" />',
			_('Domain Password') => '<input id="adPassword" type="password" name="domainpass" autocomplete="off" /><span class="lightColor">('._('Must be encrypted').')</span>',
			'<input type="hidden" name="updatead" value="1" />' => '<input type="submit" value="'._('Update').'" />',
		);
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_AD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</form>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Printers -->";
		print "\n\t\t\t".'<div id="group-printers">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-printers">';
		print "\n\t\t\t<h2>"._('Select Management Level for all Hosts in this group').'</h2>';
		print "\n\t\t\t".'<p class="l">';
		print "\n\t\t\t\t".'<input type="radio" name="level" value="0" />'._('No Printer Management').'<br/>';
		print "\n\t\t\t\t".'<input type="radio" name="level" value="1" />'._('Add Only').'<br/>';
		print "\n\t\t\t\t".'<input type="radio" name="level" value="2" />'._('Add and Remove').'<br/>';
		print "\n\t\t\t</p>";
		print "\n\t\t\t".'<div class="hostgroup">';
		print "\n\t\t\t<h2>"._('Add new printer to all hosts in this group.').'</h2>';
		print $this->FOGCore->getClass('PrinterManager')->buildSelectBox(null,"prntadd");
		//print "\n\t\t\t</select>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<div class="hostgroup">';
		print "\n\t\t\t<h2>"._('Remove printer from all hosts in this group.').'</h2>';
		print $this->FOGCore->getClass('PrinterManager')->buildSelectBox(null,"prntdel");
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<input type="hidden" name="update" value="1" /><input type="submit" value="'._('Update').'" />';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t</div>";
	}
	/** edit_post()
		This updates the information from the edit function.
	*/
	public function edit_post()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('GROUP_EDIT_POST', array('Group' => &$Group));
		// Group Edit 
		try
		{
			switch($this->REQUEST['tab'])
			{
				// Group Main Edit
				case 'group-general';
					// Error checking
					if (empty($_POST['name']))
						throw new Exception('Group Name is required');
					else
					{
						// Define new Image object with data provided
						$Group	->set('name',		$_POST['name'])
								->set('description',	$_POST['description'])
								->set('kernel',		$_POST['kern'])
								->set('kernelArgs',	$_POST['args'])
								->set('kernelDevice',	$_POST['dev']);
								foreach((array)$Group->get('hosts') AS $Host)
								{
									if ($Host && $Host->isValid())
									{
										$Host->set('kernel',		$_POST['kern'])
											 ->set('kernelArgs',	$_POST['args'])
											 ->set('kernelDevice',	$_POST['dev'])
											 ->set('productKey', $_POST['key'])
											 ->save();
									}
								}
						// Sets the location for the group.
						$Location = new Location($_REQUEST['location']);
						foreach ((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
							{
								// Remove all associations
								$this->FOGCore->getClass('LocationAssociationManager')->destroy(array('hostID' => $Host->get('id')));
								// Create new association
								$LA = new LocationAssociation(array(
									'locationID' => $Location->get('id'),
									'hostID' => $Host->get('id'),
								));
								$LA->save();
							}
						}
					}
				break;
				// Group membership
				case 'group-membership';
					if ($_POST['updatehosts'])
					{
						foreach((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
								$Host->set('imageID',$_POST[$Host->get('name').'_'.$Host->get('id')])->save();
						}
					}
					if($_POST['host'])
						$Group->addHost($_POST['host']);
					if(isset($_POST['member']))
						$Group->removeHost($_POST['member']);
				break;
				// Image Association
				case 'group-image';
					// Error Checking
					if (empty($_POST['image']))
						throw new Exception('Select an Image');
					else
					{
						foreach ((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
							{
								$Task = current($this->FOGCore->getClass('TaskManager')->find(array('hostID' => $Host->get('id'),'stateID' => array(1,2,3))));
								if ($Task && $Task->isValid() && !$_REQUEST['image'])
									throw new Exception('Cannot unset image.<br />Host is currently in a tasking.');
								else
									$Host->set('imageID', $this->REQUEST['image']);
								$Host->save();
							}
						}
					}
				break;
				// Snapin Add
				case 'group-snap-add';
					// Error Checking
					if (empty($_POST['snapin']))
						throw new Exception('Select a Snapin');
					else
					{
						foreach ((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
								$Host->addSnapin($_REQUEST['snapin'])->save();
						}
					}
				break;
				// Snapin Del
				case 'group-snap-del';
					// Error Checking
					if (empty($_POST['snapin']))
						throw new Exception('Select a Snapin');
					else
					{
						foreach ((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
								$Host->removeSnapin($_REQUEST['snapin'])->save();
						}
					}
				break;
				// Active Directory
				case 'group-active-directory';
					foreach ((array)$Group->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid())
						{
							$Host->set('useAD', ($this->REQUEST['domain'] == "on" ? '1' : '0'))
								 ->set('ADDomain', $this->REQUEST['domainname'])
								 ->set('ADOU', $this->REQUEST['ou'])
								 ->set('ADUser', $this->REQUEST['domainuser'])
								 ->set('ADPass', $this->REQUEST['domainpass']);
							$Host->save();
						}
					}
				break;
				// Printer Add/Rem
				case 'group-printers';
					// Error Checking
					foreach ((array)$Group->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid())
						{
							$Host->set('printerLevel', $_REQUEST['level']);
							if (!empty($_POST['prntadd']))
								$Host->addPrinter($_REQUEST['prntadd']);
							if (!empty($_POST['prntdel']))
								$Host->removePrinter($_REQUEST['prntdel']);
							$Host->save();
						}
					}
				break;
				// Update Services
				case 'group-service';
                    // The values below are the checking of the service enabled/disabled.
                    // If they're enabled when you click update, they'll send the call
                    // with the Module's ID to insert into the db.  If they're disabled
                    // they'll delete from the database.
                    $ServiceModules = $this->FOGCore->getClass('ModuleManager')->find('','','id');
                    foreach((array)$ServiceModules AS $ServiceModule)
						$ServiceSetting[$ServiceModule->get('id')] = $_POST[$ServiceModule->get('shortName')];
                    // The values below set the display Width, Height, and Refresh.  If they're not set by you, they'll
                    // be set to the default values within the system.
                    $x =(is_numeric($_POST['x']) ? $_POST['x'] : $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_X'));
                    $y =(is_numeric($_POST['y']) ? $_POST['y'] : $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y'));
                    $r =(is_numeric($_POST['r']) ? $_POST['r'] : $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_R'));
                    $tme = (is_numeric($_POST['tme']) ? $_POST['tme'] : $this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN'));
					foreach((array)$Group->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid())
						{
							if($_POST['updatestatus'] == '1')
							{
								foreach((array)$ServiceSetting AS $id => $onoff)
									$onoff ? $Host->addModule($id) : $Host->removeModule($id);
							}
							if ($_POST['updatedisplay'] == '1')
								$Host->setDisp($x,$y,$r);
							if ($_POST['updatealo'] == '1')
								$Host->setAlo($tme);
							$Host->save();
						}
					}
                break;
			}
            // Save to database
			if ($Group->save())
			{
				// Hook
				$this->HookManager->processEvent('GROUP_EDIT_SUCCESS', array('host' => &$Group));
				// Log History event
				$this->FOGCore->logHistory(sprintf('Group updated: ID: %s, Name: %s', $Group->get('id'), $Group->get('name')));
				// Set session message
				$this->FOGCore->setMessage('Group information updated!');
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Group->get('id'), $this->REQUEST['tab']));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('GROUP_EDIT_FAIL', array('Group' => &$Group));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Group'), $_POST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Group->get('id'), $this->request['tab']));
		}
	}
	/** delete()
		This is used to delete groups. This displays to verify you really want to delete it.
	*/
	public function delete()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Remove'), $Group->get('name'));
		// Headerdata
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Please confirm you want to delete').' <b>'.$Group->get('name').'</b>' => '&nbsp;',
			_('Delete all hosts within the group as well?') => '<input type="checkbox" name="massDelHosts" value="1" />',
			'&nbsp;' => '<input type="submit" value="${title}" />',
		);
		foreach($fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'title' => $this->title,
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
		// Hook
		$this->HookManager->processEvent('GROUP_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	/** delete_post()
		This is actually what deletes the group if you click submit.
	*/
	public function delete_post()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('GROUP_DELETE_POST', array('Group' => &$Group));
		// POST
		try
		{
			if ($_REQUEST['delHostConfirm'] == '1')
			{
				foreach((array)$Group->get('hosts') AS $Host)
				{
					if ($Host->isValid())
						$Host->destroy();
				}
			}
			// Remove hosts first.
			if (isset($_REQUEST['massDelHosts']))
			{
				unset($this->data);
				// Header Data
				$this->headerData = array(
					_('Host Name'),
					_('Last Deployed'),
				);
				// Attributes
				$this->attributes = array(
					array(),
					array(),
				);
				// Templates
				$this->templates = array(
					'${host_name}<br /><small>${host_mac}</small>',
					'<small>${host_deployed}</small>',
				);
				foreach((array)$Group->get('hosts') AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						$this->data[] = array(
							'host_name' => $Host->get('name'),
							'host_mac' => $Host->get('mac'),
							'host_deployed' => $Host->get('deployed'),
						);
					}
				}
				print "\n\t\t\t".'<p>'._('Please confirm you want to delete the following hosts from the FOG Database.').'</p>';
				print "\n\t\t\t".'<form method="post" action="?node=group&sub=delete&id='.$Group->get('id').'" class="c">';
				// Hook
				$this->HookManager->processEvent('GROUP_DELETE_HOST_FORM', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
				// output
				$this->render();
				print '<input type="hidden" name="delHostConfirm" value="1" /><input type="submit" value="Delete all hosts?" />';
				print "\n\t\t\t".'</form>';
			}
			else
			{
				// Remove Group associations
				$this->FOGCore->getClass('GroupAssociationManager')->destroy(array('groupID' => $Group->get('id')));
				// Remove Group
				if (!$Group->destroy())
					throw new Exception(_('Failed to destroy Group'));
				// Hook
				$this->HookManager->processEvent('GROUP_DELETE_SUCCESS', array('Group' => &$Group));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Group deleted'), $Group->get('id'), $Group->get('name')));
				// Set session message
				$this->FOGCore->setMessage(sprintf('%s: %s', _('Group deleted'), $Group->get('name')));
				// Redirect
				$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
			}
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('GROUP_DELETE_FAIL', array('Group' => &$Group));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** deploy()
		If you're setting up tasks, this is how it happens.
	*/
	public function deploy()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		$TaskType = new TaskType(($this->REQUEST['type'] ? $this->REQUEST['type'] : '1'));
		// Title
		$this->title = sprintf("%s '%s' task %s '%s'", _('Create'), $TaskType->get('name'), _('for Group'), $Group->get('name'));
		// Deploy
		print "\n\t\t\t".'<p class="c"><b>'._('Are you sure you wish to deploy these machines?').'</b></p>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" id="deploy-container">';
		print "\n\t\t\t".'<div class="confirm-message">';
		if ($TaskType->get('id') == 13)
		{
			print "\n\t\t\t<p>"._('Please select the snapin you want to deploy').'</p>';
			print $this->FOGCore->getClass('SnapinManager')->buildSelectBox();
		}
		print "\n\t\t\t".'<div class="advanced-settings">';
		print "\n\t\t\t<h2>"._('Advanced Settings').'</h2>';
		print "\n\t\t\t".'<p><input type="checkbox" name="shutdown" id="shutdown" value="1" autocomplete="off"> <label for="shutdown">'._('Schedule <u>Shutdown</u> after task completion').'</label></p>';
		if (!$TaskType->isDebug() && $TaskType->get('id') != 11)
		{
			print "\n\t\t\t".'<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked="checked" /><label for="scheduleInstant">'._('Schedule ').' <u>'._('Instant Deployment').'</u></label></p>';
			print "\n\t\t\t".'<p><input type="radio" name="scheduleType" id="scheduleSingle" value="single" autocomplete="off" /><label for="scheduleSingle">'._('Schedule ').' <u>'._('Delayed Deployment').'</u></label></p>';
			print "\n\t\t\t".'<p class="hidden" id="singleOptions"><input type="text" name="scheduleSingleTime" id="scheduleSingleTime" autocomplete="off" /></p>';
			print "\n\t\t\t".'<p><input type="radio" name="scheduleType" id="scheduleCron" value="cron" autocomplete="off"> <label for="scheduleCron">'._('Schedule ').' <u>'._('Cron-style Deployment').'</u></label></p>';
			print "\n\t\t\t".'<p class="hidden" id="cronOptions">';
			print "\n\t\t\t".'<input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off" />';
			print "\n\t\t\t".'<input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off" />';
			print "\n\t\t\t".'<input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off" />';
			print "\n\t\t\t".'<input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off" />';
			print "\n\t\t\t".'<input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" />';
			print "\n\t\t\t</p>";
		}
		if ($TaskType->get('id') == 11)
		{
			print "\n\t\t\t<p>"._('Which account would you like to reset the password for?').'</p>';
			print "\n\t\t\t".'<input type="text" name="account" value="Administrator" />';
		}
		print "\n\t\t\t</div>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<h2>"._('Hosts in Task').'</h2>';
		unset($this->headerData,$this->data);
		$this->attributes = array(
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'<a href="${link}" title="'._('Edit').'">${host_name}</a>',
			'${host_mac}${host_ip}',
			'${image_name}',
		);
		foreach ((array)$Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'link' => $_SERVER['PHP_SELF'].'?node=host&sub=edit&id=${host_id}',
					'host_id' => $Host->get('id'),
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac'),
					'host_ip' => ($Host->get('ip') ? '('.$Host->get('ip').')' : ''),
					'image_name' => $Host->getImage()->get('name'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('GROUP_DEPLOY', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '<center><p><input type="submit" value="'.$this->title.'" /></p></center>';
		print "\n\t\t\t</form>";
	}
	/** deploy_post()
		Actually deploy's the tasking.  Based on cron, delayed, or instant deployment.
	*/
	public function deploy_post()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		$TaskType = new TaskType(($_REQUEST['type'] ? $_REQUEST['type'] : '1'));
		// Title
		$this->title = sprintf('%s: %s', _('Deploy Task'), $TaskType->get('name'));
		// Variables
		$taskTypeID = $this->REQUEST['type'];
		$snapin = ($_REQUEST['snapin'] ? $_REQUEST['snapin'] : -1);
		$enableShutdown = ($this->REQUEST['shutdown'] == 1 ? true : false);
		$enableSnapins = ($taskTypeID != '17' ? $snapin : null);
		$enableDebug = ($this->REQUEST['debug'] == 'true' ? true : false);
		$scheduledDeployTime = strtotime($this->REQUEST['scheduleSingleTime']);
		$taskName = $Group->get('name');
		$imagingTasks = array(1,2,8,15,16,17);
		// Deploy
		try
		{
			// Error checking
			if ($TaskType->isMulticast() && !$Group->doMembersHaveUniformImages())
				throw new Exception(_('Hosts do not have Uniformed Image assignments'));
			foreach((array)$Group->get('hosts') AS $Host)
			{
				if ($Host && $Host->isValid())
				{
					if (in_array($taskTypeID,$imagingTasks) && !$Host->get('imageID'))
						throw new Exception(_('You need to assign an image to the host'));
					if (!$Host->checkIfExist($taskTypeID))
						throw new Exception(_('To setup download task, you must first upload an image'));
				}
			}
			if ($taskTypeID == '11' && !trim($_REQUEST['account']))
				throw new Exception(_('To setup password reset request, you must specify a user'));
			try
			{
				// NOTE: These functions will throw an exception if they fail
				if ($this->REQUEST['scheduleType'] == 'single')
				{
					if ($scheduledDeployTime < time())
						throw new Exception(sprintf(_('Scheduled date is in the past. Date: %s'), date('Y/d/m H:i', $scheduledDeployTime)));
					$ScheduledTask = new ScheduledTask(array(
						'taskType' => $taskTypeID,
						'name' => $taskName,
						'hostID' => $Group->get('id'),
						'shutdown' => $enableShutdown,
						'other2' => $enableSnapins,
						'isGroupTask' => 1,
						'type' => 'S',
						'scheduleTime' => $scheduledDeployTime,
						'other3' => $_SESSION['FOG_USERNAME'],
					));
					if ($ScheduledTask->save())
					{
						foreach((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
								$success[] = sprintf('<li>%s &ndash; %s</li>', $Host->get('name'), $Host->getImage()->get('name'));
						}
					}
				}
				else if ($this->REQUEST['scheduleType'] == 'cron')
				{
					$ScheduledTask = new ScheduledTask(array(
						'taskType' => $taskTypeID,
						'name' => $taskName,
						'hostID' => $Group->get('id'),
						'minute' => $_REQUEST['scheduleCronMin'],
						'hour' => $_REQUEST['scheduleCronHour'],
						'dayOfMonth' => $_REQUEST['scheduleCronDOM'],
						'month' => $_REQUEST['scheduleCronMonth'],
						'dayOfWeek' => $_REQUEST['scheduleCronDOW'],
						'shutdown' => $enableShutdown,
						'other3' => $_SESSION['FOG_USERNAME'],
						'other2' => $enableSnapins,
						'isGroupTask' => 1,
						'type' => 'C',
					));
					if ($ScheduledTask->save())
					{
						foreach((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
								$success[] = sprintf('<li>%s &ndash; %s</li>', $Host->get('name'), $Host->getImage()->get('name'));
						}
					}
				}
				else
				{
					foreach ((array)$Group->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid())
						{
							if($Host->createImagePackage($taskTypeID, $taskName, $enableShutdown, $enableDebug, $enableSnapins, true, $_SESSION['FOG_USERNAME'], trim($_REQUEST['account'])))
								$success[] = sprintf('<li>%s &ndash; %s</li>', $Host->get('name'), $Host->getImage()->get('name'));
						}
					}
				}
			}
			catch (Exception $e)
			{
				$error[] = sprintf('%s: %s', $Host->get('name'), $e->getMessage());
			}	
			// Failure
			if (count($error))
				throw new Exception('<ul><li>' . implode('</li><li>', $error) . '</li></ul>');
		}
		catch (Exception $e)
		{
			// Failure
			printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>', _('Failed to create deployment tasks for the following Hosts'), $e->getMessage());
		}
				
		// Success
		if (count($success))
		{
			printf('<div class="task-start-ok"><p>%s</p><p>%s%s%s</p></div>',
				sprintf(_('Successfully created tasks for deployment to the following Hosts'), $Host->getImage()->get('name')),
				($this->REQUEST['scheduleType'] == 'cron'	? _('Cron Schedule:') . ' ' . implode(' ', array($this->REQUEST['scheduleCronMin'], $this->REQUEST['scheduleCronHour'], $this->REQUEST['scheduleCronDOM'], $this->REQUEST['scheduleCronMonth'], $this->REQUEST['scheduleCronDOW'])) : ''),
				($this->REQUEST['scheduleType'] == 'single'	? _('Scheduled to start at:') . ' ' . $this->REQUEST['scheduleSingleTime'] : ''),
				(count($success) ? '<ul>' . implode('', $success) . '</ul>' : '')
			);
		}
	}
}
=======
<?php
/**	Class Name: GroupManagementPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
    Description: This is an extention of the FOGPage Class
    This class controls the group management page for FOG.
	It, now, allows group creation within as opposed to the
	old method it used.

	Manages group settings such as:
	Image Association, Active Directory, Snapin Add and removal,
	Printer association, and Service configurations.

	Useful for:
	Making setting changes quickly on multiple systems at a time.
**/
class GroupManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Group Management';
	var $node = 'group';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	/** __construct($name = '')
		Builds the default header and template information
		for the Group page.
		This builds the default display for index and search.
	*/
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header row
		$this->headerData = array(
			_('Name'),
			//_('Description'),
			_('Members'),
			'',
			'',
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=group&sub=edit&%s=${id}" title="Edit">${name}</a>', $this->id),
			//'${description}',
			'${count}',
			sprintf('<a href="?node=group&sub=deploy&type=1&%s=${id}"><span class="icon icon-download" title="Download"></span></a> <a href="?node=group&sub=deploy&type=8&%s=${id}"><span class="icon icon-multicast" title="Multi-cast"></span></a> <a href="?node=group&sub=edit&%s=${id}#group-tasks"><span class="icon icon-deploy" title="Deploy"></span></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
			sprintf('<a href="?node=group&sub=edit&%s=${id}"><span class="icon icon-edit" title="Edit"></span></a> <a href="?node=group&sub=delete&%s=${id}"><span class="icon icon-delete" title="Delete"></span></a>', $this->id, $this->id, $this->id, $this->id, $this->id, $this->id),
		);
		// Row attributes
		$this->attributes = array(
			array(),
			//array('width' => 150),
			array('width' => 40, 'class' => 'c'),
			array('width' => 90, 'class' => 'c'),
			array('width' => 50, 'class' => 'c')
		);
	}
	// Pages
	/** index()
		This is the first page displayed.  However, if search is used
		as the default view, this isn't displayed.  But it still serves
		as a means to display data, if there was a problem with the search
		function.
	*/
	public function index()
	{
		// Set title
		$this->title = _('All Groups');
		// Find data
		$Groups = $this->getClass('GroupManager')->find();
		// Row data
		foreach ((array)$Groups AS $Group)
		{
			$this->data[] = array(
				'id'		=> $Group->get('id'),
				'name'		=> $Group->get('name'),
				'description'	=> $Group->get('description'),
				'count'		=> $Group->getHostCount(),
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('GROUP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** search()
		This function displays the search form used by the system.
		If default view is search, this is displayed.  You can search
		for the groups using this.
	*/
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('GROUP_SEARCH');
		// Output
		$this->render();
	}
	/** search_post()
		This function is how the data gets processed and displayed based on what was
		searched for.
	*/
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		$Groups = new GroupManager();
		// Find data -> Push data
		foreach($Groups->search($keyword,'Group') AS $Group)
		{
			$this->data[] = array(
				'id'		=> $Group->get('id'),
				'name'		=> $Group->get('name'),
				'description'	=> $Group->get('description'),
				'count'		=> $Group->getHostCount(), 
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** add()
		This function is what creates the new group.
		You can do this from two places.  You can do it from the
		Host List, but now you can also do it from the Group page
		as well.  In years past, you could only create a group using
		the host list page.
	*/
	public function add()
	{
		// Set title
		$this->title = _('New Group');
		// Headerdata
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${formField}',
		);
		$fields = array(
			_('Group Name') => '<input type="text" name="name" />',
			_('Group Description') => '<textarea name="description" rows="8" cols="40"></textarea>',
			_('Group Kernel') => '<input type="text" name="kern" />',
			_('Group Kernel Arguments') => '<input type="text" name="args" />',
			_('Group Primary Disk') => '<input type="text" name="dev" />',
			'' => '<input type="submit" value="'._('Add').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach ((array)$fields AS $field => $formField)
		{
			$this->data[] = array(
				'field' => $field,
				'formField' => $formField,
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	/** add_post()
		This is the function that actually creates the group.
	*/
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('GROUP_ADD_POST');
		// POST
		try
		{
			// Error checking
			if (empty($_REQUEST['name']))
				throw new Exception('Group Name is required');
			if ($this->getClass('GroupManager')->exists($_REQUEST['name']))
				throw new Exception('Group Name already exists');
			// Define new Image object with data provided
			$Group = new Group(array(
				'name'		=> $_REQUEST['name'],
				'description'	=> $_REQUEST['description'],
				'kernel'	=> $_REQUEST['kern'],
				'kernelArgs'	=> $_REQUEST['args'],
				'kernelDevice'	=> $_REQUEST['dev']
			));
			// Save to database
			if ($Group->save())
			{
				// Hook
				$this->HookManager->processEvent('GROUP_ADD_SUCCESS', array('Group' => &$Group));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Group added'), $Group->get('id'), $Group->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Group added'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $Group->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('GROUP_ADD_FAIL', array('Group' => &$Group));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('Group'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** edit()
		This is how you edit a group.  You can also
		add hosts from this page now.  You used to only
		be able to add hosts to the groups from the host list page.
		This should make some things easier.  You can also use it
		to setup tasks for the group, snapins, printers, active directory,
		images, etc...
	*/
	public function edit()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		// If location is installed.
		$LocPluginInst = current($this->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		// If all hosts have the same image setup up the selection.
		foreach ((array)$Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$imageID[] = $Host && $Host->isValid() ? $Host->getImage()->get('id') : '';
				$groupKey[] = $Host && $Host->isValid() ? base64_decode($Host->get('productKey')) : '';
			}
		}
		$imageIDMult = (is_array($imageID) ? array_unique($imageID) : $imageID);
		$groupKeyMult = (is_array($groupKey) ? array_unique($groupKey) : $groupKey);
		$groupKeyMult = array_filter($groupKeyMult);
		if (count($imageIDMult) == 1)
			$imageMatchID = $Host && $Host->isValid() ? $Host->getImage()->get('id') : '';
		// For the location plugin.  If all have the same location, setup the selection to let people know.
		if ($LocPluginInst)
		{
			// To set the location similar to the rest of the groups.
			foreach ((array)$Group->get('hosts') AS $Host)
			{
				if ($Host && $Host->isValid())
				{
					$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
					$LA ? $locationID[] = $LA->get('locationID') : null;
				}
			}
			$locationIDMult = (is_array($locationID) ? array_unique($locationID) : $locationID);
			if (count($locationIDMult) == 1)
				$locationMatchID = $LA && $LA->isValid() ? $LA->get('locationID') : null;
		}
		// Title - set title for page title in window
		$this->title = sprintf('%s: %s', _('Edit'), $Group->get('name'));
		// Headerdata
		unset ($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Group Name') => '<input type="text" name="name" value="${group_name}" />',
			_('Group Description') => '<textarea name="description" rows="8" cols="40">${group_desc}</textarea>',
			_('Group Product Key') => '<input id="productKey" type="text" name="key" value="${group_key}" />',
			($LocPluginInst ? _('Group Location') : null) => ($LocPluginInst ? $this->getClass('LocationManager')->buildSelectBox($locationMatchID) : null),
			_('Group Kernel') => '<input type="text" name="kern" value="${group_kern}" />',
			_('Group Kernel Arguments') => '<input type="text" name="args" value="${group_args}" />',
			_('Group Primary Disk') => '<input type="text" name="dev" value="${group_devs}" />',
			'<input type="hidden" name="updategroup" value="1" />' => '<input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-general">';
		print "\n\t\t\t".'<input type="hidden" name="'.$this->id.'" value="'.$_REQUEST['id'].'" />';
		print "\n\t\t\t".'<div id="tab-container">';
		print "\n\t\t\t<!-- General -->";
		print "\n\t\t\t".'<div id="group-general">';
		print "\n\t\t\t".'<h2>'._('Modify Group').': '.$Group->get('name').'</h2>';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'group_name' => $Group->get('name'),
				'group_desc' => $Group->get('description'),
				'group_kern' => $Group->get('kernel'),
				'group_args' => $Group->get('kernelArgs'),
				'group_devs' => $Group->get('kernelDev'),
				'group_key' => count($groupKeyMult) == 1 ? $groupKeyMult[0] : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('GROUP_DATA_GEN', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset ($this->data);
		print "</div>";
		print "\n\t\t\t</form>";
		$this->basictasksOptions();
		print "\n\t\t\t<!-- Membership -->";
		// Hopeful implementation of all groups to add to group system in similar means to how host page does from list/search functions.
		print "\n\t\t\t".'<div id="group-membership">';
		// Create the Header data:
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboxgroup1" class="toggle-checkbox1" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			_('Image'),
		);
		// Create the template data:
		$this->templates = array(
			'<span class="icon icon-help hand" title="${host_desc}"></span>',
			'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host${check_num}" />',
			($_SESSION['FOGPingActive'] ? '<span class="icon ping"></span>' : ''),
			'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
			'${image_name}',
		);
		// Create the attributes to build the table info:
		$this->attributes = array(
			array('width' => 22, 'id' => 'host-${host_name}'),
			array('class' => 'c', 'width' => 16),
			($_SESSION['FOGPingActive'] ? array('width' => 20) : ''),
			array(),
			array(),
		);
		// Get the Hosts in this group
		foreach($Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
				$HostsInMe[] = $Host->get('id');
		}
		// Get All Host ID's that are associated to a group
		foreach($this->getClass('GroupAssociationManager')->find() AS $GroupAssoc)
		{
			if ($GroupAssoc && $GroupAssoc->isValid())
				$HostInAnyGroupIDs[] = $GroupAssoc->get('hostID');
		}
		// Make the values unique as a host can be a part of many groups.
		$HostInAnyGroupIDs = array_unique((array)$HostInAnyGroupIDs);
		// Set the values
		foreach($this->getClass('HostManager')->find() AS $Host)
		{
			if ($Host && $Host->isValid() && !$Host->get('pending'))
			{
				if (!in_array($Host->get('id'),$HostInAnyGroupIDs))
					$HostNotInAnyGroup[] = $Host;
				if (!in_array($Host->get('id'),$HostsInMe))
					$HostNotInGroup[] = $Host;
			}
		}
		// All hosts not in this group.
		foreach((array)$HostNotInGroup AS $Host)
		{
			if ($Host && $Host->isValid() && !$Host->get('pending'))
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'image_name' => $Host->getImage()->get('name'),
					'check_num' => '1'
				);
			}
		}
		$GroupDataExists = false;
		if (count($this->data) > 0)
		{
			$GroupDataExists = true;
			$this->HookManager->processEvent('GROUP_HOST_NOT_IN_ME',array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			print "\n\t\t\t<center>".'<label for="hostMeShow">'._('Check here to see hosts not in this group').'&nbsp;&nbsp;<input type="checkbox" name="hostMeShow" id="hostMeShow" /></label>';
			print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-membership">';
			print "\n\t\t\t".'<div id="hostNotInMe">';
			print "\n\t\t\t".'<h2>'._('Modify Membership for').' '.$Group->get('name').'</h2>';
			print "\n\t\t\t".'<p>'._('Add hosts to group').' '.$Group->get('name').'</p>';
			$this->render();
			print "</div>";
		}
		// Reset the data for the next value
		unset($this->data);
		// Create the Header data
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkboxgroup2" class="toggle-checkbox2" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			_('Image'),
		);
		// All hosts not in any group.
		foreach((array)$HostNotInAnyGroup AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac')->__toString(),
					'host_desc' => $Host->get('description'),
					'image_name' => $Host->getImage()->get('name'),
					'check_num' => '2'
				);
			}
		}
		if (count($this->data) > 0)
		{
			$GroupDataExists = true;
			$this->HookManager->processEvent('GROUP_HOST_NOT_IN_ANY',array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			print "\n\t\t\t".'<label for="hostNoShow">'._('Check here to see hosts not within a group').'&nbsp;&nbsp;<input type="checkbox" name="hostNoShow" id="hostNoShow" /></label>';
			print "\n\t\t\t".'<div id="hostNoGroup">';
			print "\n\t\t\t".'<p>'._('Hosts below do not belong to a group').'</p>';
			print "\n\t\t\t".'<p>'._('Add hosts to group').' '.$Group->get('name').'</p>';
			$this->render();
			print "\n\t\t\t</div>";
		}
		if ($GroupDataExists)
		{
			print '</br><input type="submit" value="'._('Add Host(s) to Group').'" />';
			print "\n\t\t\t</form></center>";
		}
		unset($this->data);
		$this->headerData = array(
            _('Hostname'),
            _('Deployed'),
            _('Remove'),
            _('Image'),
		);
		$this->attributes = array(
            array(),
            array(),
            array(),
            array(),
		);
		$this->templates = array(
			'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
			'<small>${deployed}</small>',
			'<input type="checkbox" name="member" value="${host_id}" class="delid" onclick="this.form.submit()" id="memberdel${host_id}" /><label for="memberdel${host_id}">Delete</label>',
			'<small>${image_name}</small>',
		);
		foreach ((array)$Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
                    'host_id'   => $Host->get('id'),
                    'deployed' => $this->validDate($Host->get('deployed')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
                    'host_name' => $Host->get('name'),
                    'host_mac'  => $Host->get('mac')->__toString(),
                    'image_name' => $this->getClass('ImageManager')->buildSelectBox($Host->getImage()->get('id'),$Host->get('name').'_'.$Host->get('id')),
				);
			}
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-membership">';
		// Hook
		$this->HookManager->processEvent('GROUP_MEMBERSHIP', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '<input type="hidden" name="updatehosts" value="1" /><center><input type="submit" value="'._('Update Hosts').'" /></center>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Image Association -->";
		print "\n\t\t\t".'<div id="group-image">';
		print "\n\t\t\t<h2>"._('Image Association for').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-image">';
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$this->data[] = array(
			'field' => $this->getClass('ImageManager')->buildSelectBox($imageMatchID).'</select>',
			'input' => '<input type="submit" value="'._('Update Images').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_IMAGE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print "</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Add Snap-ins -->";
		print "\n\t\t\t".'<div id="group-snap-add">';
		print "\n\t\t\t<h2>"._('Add Snapin to all hosts in').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-snap-add">';
		$this->headerData = array(
			'<input type="checkbox" name="toggle-checkboxsnapin" class="toggle-checkboxsnapin" />',
			_('Snapin Name'),
			_('Created'),
		);
		$this->templates = array(
			'<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapin" />',
			'<a href="?node=snapin&sub=edit&id=${snapin_id}" title="'._('Edit').'">${snapin_name}</a>',
			'${snapin_created}',
		);
		$this->attributes = array(
			array('width' => 16, 'class' => 'c'),
			array('width' => 90, 'class' => 'l'),
			array('width' => 20, 'class' => 'r'),
		);
		// Get all snapins.
		foreach($this->getClass('SnapinManager')->find() AS $Snapin)
		{
			if ($Snapin && $Snapin->isValid())
			{
				$this->data[] = array(
					'snapin_id' => $Snapin->get('id'),
					'snapin_name' => $Snapin->get('name'),
					'snapin_created' => $this->formatTime($Snapin->get('createdTime')),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('GROUP_SNAP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "\n\t\t\t".'<center><input type="submit" value="'._('Add Snapin(s)').'" /></center>';
		unset($this->data);
		print '</form>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Remove Snap-ins -->";
		print "\n\t\t\t".'<div id="group-snap-del">';
		print "\n\t\t\t<h2>"._('Remove Snapin to all hosts in').': '.$Group->get('name').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-snap-del">';
		$this->headerData = array(
			'<input type="checkbox" name="toggle-checkboxsnapinrm" class="toggle-checkboxsnapinrm" />',
			_('Snapin Name'),
			_('Created'),
		);
		$this->templates = array(
			'<input type="checkbox" name="snapin[]" value="${snapin_id}" class="toggle-snapinrm" />',
			'<a href="?node=snapin&sub=edit&id=${snapin_id}" title="'._('Edit').'">${snapin_name}</a>',
			'${snapin_created}',
		);
		$this->attributes = array(
			array('width' => 16, 'class' => 'c'),
			array('width' => 90, 'class' => 'l'),
			array('width' => 20, 'class' => 'r'),
		);
		// Get all snapins.
		foreach($this->getClass('SnapinManager')->find() AS $Snapin)
		{
			if ($Snapin && $Snapin->isValid())
			{
				$this->data[] = array(
					'snapin_id' => $Snapin->get('id'),
					'snapin_name' => $Snapin->get('name'),
					'snapin_created' => $Snapin->get('createdTime'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('GROUP_SNAP_DEL', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "\n\t\t\t".'<center><input type="submit" value="'._('Remove Snapin(s)').'" /></center>';
		unset($this->headerData,$this->data);
		print '</form>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Service Settings -->";
		$this->attributes = array(
			array('width' => 270),
			array('class' => 'c'),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${mod_name}',
			'${input}',
			'${span}',
		);
		$this->data[] = array(
			'mod_name' => 'Select/Deselect All',
			'input' => '<input type="checkbox" class="checkboxes" id="checkAll" name="checkAll" value="checkAll" />',
			'span' => ''
		);
		print "\n\t\t\t".'<div id="group-service">';
		print "\n\t\t\t".'<h2>'._('Service Configuration').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('General')."</legend>";
        foreach ((array)$this->getClass('ModuleManager')->find() AS $Module)
        {
			$i = 0;
			foreach((array)$Group->get('hosts') AS $Host)
			{
				if ($Host && $Host->isValid())
				{
					foreach((array)$Host->get('modules') AS $ModHost)
					{
						if ($ModHost && $ModHost->isValid())
						{
							if ($ModHost->get('id') == $Module->get('id'))
								$ModOns[] = $ModHost->get('id');
						}
					}
					$i = count($ModOns);
				}
			}
			$this->data[] = array(
				'input' => '<input type="checkbox" '.($Module->get('isDefault') ? 'class="checkboxes"' : '').' name="${mod_shname}" value="${mod_id}" ${checked} '.(!$Module->get('isDefault') ? 'disabled="disabled"' : '').' />',
				'span' => '<span class="icon icon-help hand" title="${mod_desc}"></span>',
				'checked' => ($i == $Group->getHostCount() ? 'checked="checked"' : ''),
				'mod_name' => $Module->get('name'),
				'mod_shname' => $Module->get('shortName'),
				'mod_id' => $Module->get('id'),
				'mod_desc' => str_replace('"','\"',$Module->get('description')),
			);
			unset($ModOns);
		}
		$this->data[] = array(
			'mod_name' => '<input type="hidden" name="updatestatus" value="1" />',
			'input' => '',
			'span' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_MODULES', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print "</fieldset>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('Group Screen Resolution').'</legend>';
		$this->attributes = array(
				array('class' => 'l','style' => 'padding-right: 25px'),
				array('class' => 'c'),
				array('class' => 'r'),
		);
		$this->templates = array(
			'${field}',
			'${input}',
			'${span}',
		);
		$Services = $this->getClass('ServiceManager')->find(array('name' => array('FOG_SERVICE_DISPLAYMANAGER_X','FOG_SERVICE_DISPLAYMANAGER_Y','FOG_SERVICE_DISPLAYMANAGER_R')),'OR','id');
		foreach((array)$Services AS $Service)
		{
			$this->data[] = array(
				'input' => '<input type="text" name="${type}" value="${disp}" />',
				'span' => '<span class="icon icon-help hand" title="${desc}"></span>',
				'field' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? _('Screen Width (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? _('Screen Height (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? _('Screen Refresh Rate (in Hz)') : null))),
				'type' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? 'x' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? 'y' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? 'r' : null))),
				'disp' => $Service->get('value'),
				'desc' => $Service->get('description'),
			);
		}
		$this->data[] = array(
			'field' => '',
			'input' => '<input type="hidden" name="updatedisplay" value="1" />',
			'span' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_DISPLAY', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</fieldset>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('Auto Log Out Settings').'</legend>';
		$this->attributes = array(
			array('width' => 270),
			array('class' => 'c'),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${field}',
			'${input}',
			'${desc}',
		);
		$Service = current($this->getClass('ServiceManager')->find(array('name' => 'FOG_SERVICE_AUTOLOGOFF_MIN')));
		$this->data[] = array(
			'field' => _('Auto Log Out Time (in minutes)'),
			'input' => '<input type="text" name="tme" value="${value}" />',
			'desc' => '<span class="icon icon-help hand" title="${serv_desc}"></span>',
			'value' => $Service->get('value'),
			'serv_desc' => $Service->get('description'),
		);
		$this->data[] = array(
			'field' => '<input type="hidden" name="updatealo" value="1" />',
			'input' => null,
			'desc' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('GROUP_ALO', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print '</fieldset>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		$this->adFieldsToDisplay();
		print "\n\t\t\t<!-- Printers -->";
		print "\n\t\t\t".'<div id="group-printers">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=group-printers">';
		print "\n\t\t\t<h2>"._('Select Management Level for all Hosts in this group').'</h2>';
		print "\n\t\t\t".'<p class="l">';
		print "\n\t\t\t\t".'<input type="radio" name="level" value="0" />'._('No Printer Management').'<br/>';
		print "\n\t\t\t\t".'<input type="radio" name="level" value="1" />'._('Add Only').'<br/>';
		print "\n\t\t\t\t".'<input type="radio" name="level" value="2" />'._('Add and Remove').'<br/>';
		print "\n\t\t\t</p>";
		print "\n\t\t\t".'<div class="hostgroup">';
		// Create Header for printers
		$this->headerData = array(
			//prntadd
			'<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprint" />',
			_('Default'),
			_('Printer Name'),
			_('Configuration'),
		);
		// Create Template for Printers:
		$this->templates = array(
			'<input type="checkbox" name="prntadd[]" value="${printer_id}" class="toggle-print" />',
			'<input class="default" type="radio" name="default" id="printer${printer_id}" value="${printer_id}" /><label for="printer${printer_id}"></label><input type="hidden" name="printerid[]" />',
			'<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
			'${printer_type}',
		);
		$this->attributes = array(
			array('width' => 16, 'class' => 'c'),
			array('width' => 20),
			array('width' => 50, 'class' => 'l'),
			array('width' => 50, 'class' => 'r'),
		);
		foreach($this->getClass('PrinterManager')->find() AS $Printer)
		{
			if ($Printer && $Printer->isValid())
			{
				$this->data[] = array(
					'printer_id' => $Printer->get('id'),
					'printer_name' => addslashes($Printer->get('name')),
					'printer_type' => $Printer->get('config'),
				);
			}
		}
		if (count($this->data) > 0)
		{
			print "\n\t\t\t<h2>"._('Add new printer(s) to all hosts in this group.').'</h2>';
			$this->HookManager->processEvent('GROUP_ADD_PRINTER',array('data' => &$this->data,'templates' => &$this->templates,'headerData' => &$this->headerData,'attributes' => &$this->attributes));
			$this->render();
			unset($this->data);
		}
		else
			print "\n\t\t\t<h2>"._('There are no printers to assign.').'</h2>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<div class="hostgroup">';
		$this->headerData = array(
			'<input type="checkbox" name="toggle-checkboxprint" class="toggle-checkboxprintrm" />',
			_('Printer Name'),
			_('Configuration'),
		);
		// Create Template for Printers:
		$this->templates = array(
			'<input type="checkbox" name="prntdel[]" value="${printer_id}" class="toggle-printrm" />',
			'${printer_name}',
			'${printer_type}',
		);
		$this->attributes = array(
			array('width' => 16, 'class' => 'c'),
			array('width' => 50, 'class' => 'l'),
			array('width' => 50, 'class' => 'r'),
		);
		foreach($this->getClass('PrinterManager')->find() AS $Printer)
		{
			if ($Printer && $Printer->isValid())
			{
				$this->data[] = array(
					'printer_id' => $Printer->get('id'),
					'printer_name' => addslashes($Printer->get('name')),
					'printer_type' => $Printer->get('config'),
				);
			}
		}
		if (count($this->data) > 0)
		{

			print "\n\t\t\t<h2>"._('Remove printer from all hosts in this group.').'</h2>';
			$this->HookManager->processEvent('GROUP_REM_PRINTER',array('data' => &$this->data,'templates' => &$this->templates, 'headerData' => &$this->headerData, 'attributes' => &$this->attributes));
			$this->render();
			unset($this->data);
		}
		else
			print "\n\t\t\t<h2>"._('There are no printers to assign.').'</h2>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t".'<input type="hidden" name="update" value="1" /><input type="submit" value="'._('Update').'" />';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t</div>";
	}
	/** edit_post()
		This updates the information from the edit function.
	*/
	public function edit_post()
	{
		// Find
		$Group = new Group($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('GROUP_EDIT_POST', array('Group' => &$Group));
		// Group Edit 
		try
		{
			switch($_REQUEST['tab'])
			{
				// Group Main Edit
				case 'group-general';
					// Error checking
					if (empty($_REQUEST['name']))
						throw new Exception('Group Name is required');
					else
					{
						// Define new Image object with data provided
						$Group	->set('name',		$_REQUEST['name'])
								->set('description',	$_REQUEST['description'])
								->set('kernel',		$_REQUEST['kern'])
								->set('kernelArgs',	$_REQUEST['args'])
								->set('kernelDevice',	$_REQUEST['dev']);
								foreach((array)$Group->get('hosts') AS $Host)
								{
									if ($Host && $Host->isValid())
									{
										$Host->set('kernel',		$_REQUEST['kern'])
											 ->set('kernelArgs',	$_REQUEST['args'])
											 ->set('kernelDevice',	$_REQUEST['dev'])
											 ->set('productKey', $_REQUEST['key'])
											 ->save();
									}
								}
						// Sets the location for the group.
						$Location = new Location($_REQUEST['location']);
						foreach ((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
							{
								// Remove all associations
								$this->getClass('LocationAssociationManager')->destroy(array('hostID' => $Host->get('id')));
								// Create new association
								$LA = new LocationAssociation(array(
									'locationID' => $Location->get('id'),
									'hostID' => $Host->get('id'),
								));
								$LA->save();
							}
						}
					}
				break;
				// Group membership
				case 'group-membership';
					if ($_REQUEST['host'])
					{
						if (is_array($_REQUEST['host']))
							$_REQUEST['host'] = array_unique($_REQUEST['host']);
						$Group->addHost($_REQUEST['host']);
					}
					if ($_REQUEST['updatehosts'])
					{
						foreach((array)$Group->get('hosts') AS $Host)
						{
							if ($Host && $Host->isValid())
								$Host->set('imageID',$_REQUEST[$Host->get('name').'_'.$Host->get('id')])->save();
						}
					}
					if(isset($_REQUEST['member']))
						$Group->removeHost($_REQUEST['member']);
				break;
				// Image Association
				case 'group-image';
					$Group->addImage($_REQUEST['image']);
				break;
				// Snapin Add
				case 'group-snap-add';
					$Group->addSnapin($_REQUEST['snapin']);
				break;
				// Snapin Del
				case 'group-snap-del';
					$Group->removeSnapin($_REQUEST['snapin']);
				break;
				// Active Directory
				case 'group-active-directory';
					$useAD = ($_REQUEST['domain'] == 'on');
					$domain = $_REQUEST['domainname'];
					$ou = $_REQUEST['ou'];
					$user = $_REQUEST['domainuser'];
					$pass = $_REQUEST['domainpassword'];
					$Group->setAD($useAD,$domain,$ou,$user,$pass);
				break;
				// Printer Add/Rem
				case 'group-printers';
					$Group->addPrinter($_REQUEST['prntadd'],$_REQUEST['prntdel'],$_REQUEST['level']);
					$Group->updateDefault($_REQUEST['printerid'],$_REQUEST['default']);
				break;
				// Update Services
				case 'group-service';
                    // The values below are the checking of the service enabled/disabled.
                    // If they're enabled when you click update, they'll send the call
                    // with the Module's ID to insert into the db.  If they're disabled
                    // they'll delete from the database.
                    $ServiceModules = $this->getClass('ModuleManager')->find('','','id');
                    foreach((array)$ServiceModules AS $ServiceModule)
						$ServiceSetting[$ServiceModule->get('id')] = $_REQUEST[$ServiceModule->get('shortName')];
                    // The values below set the display Width, Height, and Refresh.  If they're not set by you, they'll
                    // be set to the default values within the system.
                    $x =(is_numeric($_REQUEST['x']) ? $_REQUEST['x'] : $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_X'));
                    $y =(is_numeric($_REQUEST['y']) ? $_REQUEST['y'] : $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_Y'));
                    $r =(is_numeric($_REQUEST['r']) ? $_REQUEST['r'] : $this->FOGCore->getSetting('FOG_SERVICE_DISPLAYMANAGER_R'));
                    $tme = (is_numeric($_REQUEST['tme']) ? $_REQUEST['tme'] : $this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN'));
					foreach((array)$Group->get('hosts') AS $Host)
					{
						if ($Host && $Host->isValid())
						{
							if($_REQUEST['updatestatus'] == '1')
							{
								foreach((array)$ServiceSetting AS $id => $onoff)
									$onoff ? $Host->addModule($id)->save('modules') : $Host->removeModule($id)->save('modules');
							}
							if ($_REQUEST['updatedisplay'] == '1')
								$Host->setDisp($x,$y,$r);
							if ($_REQUEST['updatealo'] == '1')
								$Host->setAlo($tme);
							$Host->save();
						}
					}
                break;
			}
            // Save to database
			if ($Group->save())
			{
				// Hook
				$this->HookManager->processEvent('GROUP_EDIT_SUCCESS', array('host' => &$Group));
				// Log History event
				$this->FOGCore->logHistory(sprintf('Group updated: ID: %s, Name: %s', $Group->get('id'), $Group->get('name')));
				// Set session message
				$this->FOGCore->setMessage('Group information updated!');
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Group->get('id'), $this->REQUEST['tab']));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('GROUP_EDIT_FAIL', array('Group' => &$Group));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('Group'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->request['node'], $this->id, $Group->get('id'), $this->request['tab']));
		}
	}
	public function delete_hosts()
	{
		$Group = new Group($_REQUEST['id']);
		$this->title = _('Delete Hosts');
		unset($this->data);
		// Header Data
		$this->headerData = array(
			_('Host Name'),
			_('Last Deployed'),
		);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${host_name}<br/><small>${host_mac}</small>',
			'<small>${host_deployed}</small>',
		);
		foreach((array)$Group->get('hosts') AS $Host)
		{
			if ($Host && $Host->isValid())
			{
				$this->data[] = array(
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac'),
					'host_deployed' => $Host->get('deployed'),
				);
			}
		}
		printf('%s<p>%s</p>',"\n\t\t\t",_('Confirm you really want to delete the following hosts'));
		printf('%s<form method="post" action="?node=group&sub=delete&id=%s" class="c">',"\n\t\t\t",$Group->get('id'));
		// Hook
		$this->HookManager->processEvent('GROUP_DELETE_HOST_FORM',array('headerData' => &$this->headerData,'data' => &$this->data,'templates' => &$this->templates,'attributes' => &$this->attributes));
		// Output
		$this->render();
		printf('<input type="hidden" name="delHostConfirm" value="1" /><input type="submit" value="%s" />',_('Delete listed hosts'));
		printf('</form>');
	}
}
>>>>>>> dev-branch
