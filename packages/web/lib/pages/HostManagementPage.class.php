<?php
/**	Class Name: HostManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/pages
    Description: This is an extension of the FOGPage Class
    This class controls the host management page for FOG.
    It allows creating and editing of hosts.

    Manages host settings such as:
    Image Association, Active Directory, Snapin Add and removal,
    Printer association, and Service configurations.
**/
class HostManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Host Management';
	var $node = 'host';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	/** __construct($name = '')
		Host default construction for listing the hosts.
	*/
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		// Header row
		$this->headerData = array(
			'',
			'<input type="checkbox" name="toggle-checkbox" class="toggle-checkbox" checked="checked" />',
			($_SESSION['FOGPingActive'] ? '' : null),
			_('Host Name'),
			($LocPluginInst ? _('Location/Deployed') : _('Deployed')),
			_('Task'),
			_('Edit/Remove'),
			_('Image'),
		);
		// Row templates
		$this->templates = array(
			'<span class="icon icon-help hand" title="${host_desc}"></span>',
			'<input type="checkbox" name="host[]" value="${host_id}" class="toggle-host" checked="checked" />',
			($_SESSION['FOGPingActive'] ? '<span class="icon ping"></span>' : ''),
			'<a href="?node=host&sub=edit&id=${host_id}" title="Edit: ${host_name} Was last deployed: ${deployed}">${host_name}</a><br /><small>${host_mac}</small>',
			($LocPluginInst ? '${location}<br/><small>${deployed}</small>' : '<small>${deployed}</small>'),
			'<a href="?node=host&sub=deploy&sub=deploy&type=1&id=${host_id}"><span class="icon icon-download" title="Download"></span></a> <a href="?node=host&sub=deploy&sub=deploy&type=2&id=${host_id}"><span class="icon icon-upload" title="Upload"></span></a> <a href="?node=host&sub=deploy&type=8&id=${host_id}"><span class="icon icon-deploy" title="Deploy"></span></a>',
			'<a href="?node=host&sub=edit&id=${host_id}"><span class="icon icon-edit" title="Edit"></span></a> <a href="?node=host&sub=delete&id=${host_id}"><span class="icon icon-delete" title="Delete"></span></a>',
			'${image_name}',
		);
		// Row attributes
		$this->attributes = array(
			array('width' => 22, 'id' => 'host-${host_name}'),
			array('class' => 'c','width' => 16),
			($_SESSION['FOGPingActive'] ? array('width' => 20) : ''),
			array(),
			array('width' => 50, 'class' => 'c'),
			array('width' => 90, 'class' => 'r'),
			array('width' => 80, 'class' => 'c'),
			array('width' => 50, 'class' => 'r'),
			array('width' => 20, 'class' => 'r'),
		);
	}
	/** index()
		This display's the first page.
	*/
	public function index()
	{
		// Set title
		$this->title = _('All Hosts');
		// Get location if enabled:
		$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('HostManager')->find() AS $Host)
		{
			$LA = ($LocPluginInst ? current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id')))) : '');
			$Location = ($LA ? new Location($LA->get('locationID')) : '');
			$this->data[] = array(
				'host_id'	=> $Host->get('id'),
				'deployed' => checkdate($this->FOGCore->formatTime($Host->get('deployed'),'m'),$this->FOGCore->formatTime($Host->get('deployed'),'d'),$this->FOGCore->formatTime($Host->get('deployed'),'Y')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
				'host_name'	=> $Host->get('name'),
				'host_mac'	=> $Host->get('mac')->__toString(),
				'host_desc'  => $Host->get('description'),
				'image_name' => $Host->getImage()->get('name'),
				'location' => ($Location && $Location->isValid() ? $Location->get('name') : ''),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->HookManager->processEvent('HOST_HEADER_DATA',array('headerData' => &$this->headerData, 'title' => &$this->title));
		// Output
		$this->render();
	}
	/** search()
		Prints the search page.
	*/
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);

		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->HookManager->processEvent('HOST_HEADER_DATA',array('headerData' => &$this->headerData));
		// Output
		$this->render();
	}
	/** search_post()
		Provides the data from the search.
	*/
	public function search_post()
	{
		$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// Find data -> Push data
		foreach((array)$this->FOGCore->getClass('HostManager')->search($keyword) AS $Host)
		{
			$LA = ($LocPluginInst ? current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id')))) : '');
			$Location = ($LA ? new Location($LA->get('locationID')) : '');
			$this->data[] = array(
				'host_id'	=> $Host->get('id'),
				'deployed' => checkdate($this->FOGCore->formatTime($Host->get('deployed'),'m'),$this->FOGCore->formatTime($Host->get('deployed'),'d'),$this->FOGCore->formatTime($Host->get('deployed'),'Y')) ? $this->FOGCore->formatTime($Host->get('deployed')) : 'No Data',
				'host_name'	=> $Host->get('name'),
				'host_mac'	=> $Host->get('mac')->__toString(),
				'host_desc'  => $Host->get('description'),
				'image_name' => $Host->getImage()->get('name'),
				'location' => ($Location && $Location->isValid() ? $Location->get('name') : ''),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_DATA', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->HookManager->processEvent('HOST_HEADER_DATA',array('headerData' => &$this->headerData));
		// Output
		$this->render();
	}
	/** add()
		Add's a new host.
	*/
	public function add()
	{
		$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		// Set title
		$this->title = _('New Host');
		// Header template
		$this->headerData = '';
		// Row templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array(),
		);
		$fields = array(
			_('Host Name') => '<input type="text" name="host" value="${host_name}" maxlength="15" class="hostname-input" />*',
			_('Primary MAC') => '<input type="text" id="mac" name="mac" value="${host_mac}" />* <span id="priMaker></span><span class="icon icon-add add-mac hand" title="'._('Add Mac').'"></span><span class="mac-manufactor"></span>',
			_('Host Description') => '<textarea name="description" rows="5" cols="40">${host_desc}</textarea>',
			_('Host Image') => '${host_image}',
			($LocPluginInst ? _('Host Location') : '') => ($LocPluginInst ? '${host_locs}' : ''),
			_('Host Kernel') => '<input type="text" name="kern" value="${host_kern}" />',
			_('Host Kernel Arguments') => '<input type="text" name="args" value="${host_args}" />',
			_('Host Primary Disk') => '<input type="text" name="dev" value="${host_devs}" />',
		);
		$fieldsad = array(
			_('Join Domain after image task') => '<input id="adEnabled" type="checkbox" name="domain"${ad_dom}value="on" />',
			_('Domain Name') => '<input id="adDomain" class="smaller" type="text" name="domainname" value="${ad_name}" />',
			_('Domain OU') => '<input id="adOU" class="smaller" type="text" name="ou" value="${ad_ou}" />',
			_('Domain Username') => '<input id="adUsername" class="smaller" type="text" name="domainuser" value="${ad_user}" />',
			_('Domain Password').'<br/>'._('Must be encrypted') => '<input id="adPassword" class="smaller" type="password" name="domainpassword" value="${ad_pass}" />',
			'<input type="hidden" name="add" value="1" />' => '<input type="submit" value="'._('Add').'" />'
		);
		print "\n\t\t\t<h2>"._('Add new host definition').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'host_name' => $_REQUEST['host'],
				'host_mac' => $_REQUEST['mac'],
				'host_desc' => $_REQUEST['description'],
				'host_image' => $this->FOGCore->getClass('ImageManager')->buildSelectBox($_REQUEST['image'],'','id'),
				'host_kern' => $_REQUEST['kern'],
				'host_args' => $_REQUEST['args'],
				'host_devs' => $_REQUEST['dev'],
				'host_locs' => ($LocPluginInst ? $this->FOGCore->getClass('LocationManager')->buildSelectBox($_REQUEST['location']) : ''),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_ADD_GEN', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// unset for use later.
		unset ($this->data);
		print "\n\t\t\t<h2>"._('Active Directory').'</h2>';
		foreach ((array)$fieldsad AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'ad_dom' => ($_REQUEST['domain'] == 'on' ? 'checked="checked"' : ''),
				'ad_name' => $_REQUEST['domainname'],
				'ad_ou' => $_REQUEST['ou'],
				'ad_user' => $_REQUEST['domainuser'],
				'ad_pass' => $_REQUEST['domainpassword'],
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_ADD_AD', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "\n\t\t\t</form>";
	}
	/** add_post()
		Actually add's the host.
	*/
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('HOST_ADD_POST');
		// POST ?
		try
		{
			// Error checking
			if (empty($_POST['host']))
				throw new Exception(_('Hostname is required'));
			if (empty($_POST['mac']))
				throw new Exception(_('MAC Address is required'));
			// Check if host exists with MAC Address.
			$Host = $this->FOGCore->getClass('HostManager')->getHostByMacAddresses($_POST['mac']);
			if ($Host && $Host->isValid())
				throw new Exception(_('A host with this MAC already exists with Hostname: ').$Host->get('name'));
			if ($this->FOGCore->getClass('HostManager')->exists($_POST['host']))
				throw new Exception(_('Hostname already exists'));
			$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
			// Define new Image object with data provided
			$Host = new Host(array(
				'name'		=> $_POST['host'],
				'description'	=> $_POST['description'],
				'mac'		=> new MACAddress($_POST['mac']),
				'imageID'	=> $_POST['image'],
				'kernel'	=> $_POST['kern'],
				'kernelArgs'	=> $_POST['args'],
				'kernelDevice'	=> $_POST['dev'],
				'useAD'		=> ($_POST["domain"] == "on" ? '1' : '0'),
				'ADDomain'	=> $_POST['domainname'],
				'ADOU'		=> $_POST['ou'],
				'ADUser'	=> $_POST['domainuser'],
				'ADPass'	=> $_POST['domainpassword']
			));
			if ($LocPluginInst && $LocPluginInst->isValid())
			{
				$LA = new LocationAssociation(array(
					'locationID' => $_REQUEST['location'],
					'hostID' => $Host->get('id'),
				));
			}
			else
				$LA = '';
			// Save to database
			if ($Host->save())
			{
				if($LA)
					$LA->save();
				// Hook
				$this->HookManager->processEvent('HOST_ADD_SUCCESS', array('Host' => &$Host));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Host added'), $Host->get('id'), $Host->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('Host added'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->REQUEST['node'], $this->id, $Host->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('HOST_ADD_FAIL', array('Host' => &$Host));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', 'Host', $_POST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** edit()
		Edit host form information.
	*/
	public function edit()
	{
		// Find
		$Host = new Host($this->REQUEST['id']);
		// Inventory find for host.
		$Inventory = current($Host->get('inventory'));
		// Get the associated Groups.
		// Location Find for host.
		$LocPluginInst = current($this->FOGCore->getClass('PluginManager')->find(array('name' => 'location','installed' => 1)));
		$LA = ($LocPluginInst ? current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id')))) : '');
		$Location = ($LA ? new Location($LA->get('locationID')) : '');
		// Title - set title for page title in window
		$this->title = sprintf('%s: %s', 'Edit', $Host->get('name'));
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		if ($_REQUEST['confirmMac'])
		{
			$MAC = new PendingMAC($_REQUEST['confirmMac']);
			$Host->addPendtoAdd($MAC);
			$this->FOGCore->setMessage('MAC: '.$MAC->get('pending').' Approved!');
			$this->FOGCore->redirect('?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'&id='.$_REQUEST['id']);
		}
		if ($_REQUEST['approveAll'] == 1)
		{
			foreach((array)$Host->get('pendingMACs') AS $MAC)
				$Host->addPendtoAdd($MAC);
			$this->FOGCore->setMessage('All Pending MACs approved.');
			$this->FOGCore->redirect('?node='.$_REQUEST['node'].'&sub='.$_REQUEST['sub'].'&id='.$_REQUEST['id']);
		}
		foreach((array)$Host->get('additionalMACs') AS $MAC)
		{
			if ($MAC && $MAC->isValid())
				$addMACs .= '<div><input class="additionalMAC" type="text" name="additionalMACs[]" value="'.$MAC->get('mac').'" /><input type="checkbox" onclick="this.form.submit()" class="delvid" id="rm'.$MAC->get('id').'" name="additionalMACsRM[]" value="'.$MAC->get('id').'" title="'._('Remove MAC').'"/><label for="rm'.$MAC->get('id').'" class="icon icon-remove remove-mac hand"></label><span class="mac-manufactor"></span></div>';
		}
		foreach ((array)$Host->get('pendingMACs') AS $MAC)
		{
			if ($MAC && $MAC->isValid())
				$pending .= '<div><input class="pending-mac" type="text" name="pendingMACs[]" value="'.$MAC->get('pending').'" /><a href="${link}&confirmMac='.$MAC->get('id').'"><span class="icon icon-tick"></span></a><span class="mac-manufactor"></span></div>';
		}
		if ($pending != null && $pending != '')
			$pending .= '<div>'._('Approve All MACs?').'<a href="${link}&approveAll=1"><span class="icon icon-tick"></span></a></div>';
		$genFields = array(
			_('Host Name') => '<input type="text" name="host" value="${host_name}" maxlength="15" class="hostname-input" />*',
			_('Primary MAC') => '<input type="text" name="mac" id="mac" value="${host_mac}" />*<span id="priMaker"></span><span class="icon icon-add add-mac hand" title="'._('Add MAC').'"></span><span class="mac-manufactor"></span>',
			'<span id="additionalMACsRow">'._('Additional MACs').'</span>' => '<span id="additionalMACsCell">'.$addMACs.'</span>',
			($Host->get('pendingMACs') ? _('Pending MACs') : null) => ($Host->get('pendingMACs') ? $pending : null),
			_('Host Description') => '<textarea name="description" rows="5" cols="40">${host_desc}</textarea>',
			_('Host Image') => '${host_image}',
			($LocPluginInst ? _('Host Location') : '') => ($LocPluginInst ? '${host_locs}' : ''),
			_('Host Kernel') => '<input type="text" name="kern" value="${host_kern}" />',
			_('Host Kernel Arguments') => '<input type="text" name="args" value="${host_args}" />',
			_('Host Primary Disk') => '<input type="text" name="dev" value="${host_devs}" />',
			'&nbsp' => '<input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<div id="tab-container">';
		print "\n\t\t\t<!-- General -->";
		print "\n\t\t\t".'<div id="host-general">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-general">';
		print "\n\t\t\t<h2>"._('Edit host definition').'</h2>';
		foreach((array)$genFields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'host_name' => $Host->get('name'),
				'host_mac' => $Host->get('mac'),
				'link' => $this->formAction,
				'host_desc' => $Host->get('description'),
				'host_image' => $this->FOGCore->getClass('ImageManager')->buildSelectBox($Host->get('imageID')),
				'host_locs' => ($LocPluginInst ? $this->FOGCore->getClass('LocationManager')->buildSelectBox($LA && $LA->isValid() ? $LA->get('locationID') : '') : ''),
				'host_kern' => $Host->get('kernel'),
				'host_args' => $Host->get('kernelArgs'),
				'host_devs' => $Host->get('kernelDevice'),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_GEN', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		print '</form>';
		print "\n\t\t\t</div>";
		foreach((array)$Host->get('groups') AS $Group)
			$GroupIDs[] = $Group && $Group->isValid() ? $Group->get('id') : '';
		$GroupStuff = $this->FOGCore->getClass('GroupManager')->buildSelectBox('','group[]" multiple="multiple','',$GroupIDs);
		print "\n\t\t\t<!-- Group Relationships -->";
		print "\n\t\t\t".'<div id="host-grouprel" class="organic-tabs-hidden">';
		print "\n\t\t\t<h2>"._('Group Relationships').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-grouprel">';
		if ($GroupStuff)
		{
			print "\n\t\t\t<p>"._('The selection box below will add your host to a group automatically.').'</p>';
			print "\n\t\t\t<p><center>$GroupStuff";
			print "\n\t\t\t".'<input type="submit" value="'._('Add to Group(s)').'" /></center></p>';
		}
		unset($this->data);
		$this->headerData = array(
			_('Group Name'),
			_('Total Members'),
			_('Remove Membership?'),
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'<a href="?node=group&sub=edit&id=${group_id}" title="'._('Edit Group').':${group_name}">${group_name}</a>',
			'${group_count}',
			'<input type="checkbox" class="delid" onclick="this.form.submit()" name="groupdel" id="groupdelmem${group_id}" value="${group_id}" /><label for="groupdelmem${group_id}">'._('Delete').'</label>',
		);
		// Find Group Relationships
		foreach((array)$Host->get('groups') AS $Group)
		{
			if ($Group && $Group->isValid())
			{
				$this->data[] = array(
					'group_id' => $Group->get('id'),
					'group_name' => $Group->get('name'),
					'group_count' => $Group->getHostCount(),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_GROUP', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		unset($this->data,$this->headerData);
		print '</form>';
		print "\n\t\t\t</div>";
		$this->attributes = array(
			array('class' => 'l'),
			array('style' => 'padding-left: 20px'),
		);
		$this->templates = array(
			'<a href="?node=${node}&sub=${sub}&id=${host_id}${task_type}"><img src="images/${task_icon}" /><br />${task_name}</a>',
			'${task_desc}',
		);
		print "\n\t\t\t<!-- Basic Tasks -->";
		print "\n\t\t\t".'<div id="host-tasks" class="organic-tabs-hidden">';
		print "\n\t\t\t<h2>"._('Host Tasks').'</h2>';
		// Find TaskTypes
		$TaskTypes = $this->FOGCore->getClass('TaskTypeManager')->find(array('access' => array('both', 'host'), 'isAdvanced' => '0'), 'AND', 'id');
		// Iterate -> Print
		foreach ((array)$TaskTypes AS $TaskType)
		{
			$this->data[] = array(
				'node' => $this->node,
				'sub' => 'deploy',
				'host_id' => $Host->get('id'),
				'task_type' => '&type='.$TaskType->get('id'),
				'task_icon' => $TaskType->get('icon'),
				'task_name' => $TaskType->get('name'),
				'task_desc' => $TaskType->get('description'),
			);
		}
		$this->data[] = array(
			'node' => $this->node,
			'sub' => 'edit',
			'host_id' => $Host->get('id'),
			'task_type' => '#host-tasks" class="advanced-tasks-link',
			'task_icon' => 'host-advanced.png',
			'task_name' => _('Advanced'),
			'task_desc' => _('View advanced tasks for this host.'),
		);
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_TASKS', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset ($this->data);
		print '<div id="advanced-tasks" class="hidden">';
		print "\n\t\t\t<h2>"._('Advanced Actions').'</h2>';
		// Find TaskTypes
		$TaskTypes = $this->FOGCore->getClass('TaskTypeManager')->find(array('access' => array('both', 'host'), 'isAdvanced' => '1'), 'AND', 'id');
		// Iterate -> Print
		foreach ((array)$TaskTypes AS $TaskType)
		{
			$this->data[] = array(
				'node' => $this->node,
				'sub' => 'deploy',
				'host_id' => $Host->get('id'),
				'task_type' => '&type='.$TaskType->get('id'),
				'task_icon' => $TaskType->get('icon'),
				'task_name' => $TaskType->get('name'),
				'task_desc' => $TaskType->get('description'),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_ADV', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		unset($this->data);
		print '</div>';
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Active Directory -->";
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Join Domain after image task') => '<input id="adEnabled" type="checkbox" name="domain"${domainon} />',
			_('Domain name') => '<input id="adDomain" class="smaller" type="text" name="domainname" value="${host_dom}" />',
			_('Organizational Unit').'<br /><span class="lightColor">('._('Blank for default').')</span>' => '<input id="adOU" class="smaller" type="text" name="ou" value="${host_ou}" />',
			_('Domain Username') => '<input id="adUsername" class="smaller" type="text" name="domainuser" value="${host_aduser}" />',
			_('Domain Password').'<br />('._('Must be encrypted').')' => '<input id="adPassword" class="smaller" type="password" name="domainpassword" value="${host_adpass}" />',
			'<input type="hidden" name="updatead" value="1" />' => '<input type="submit" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<div id="host-active-directory" class="organic-tabs-hidden">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-active-directory">';
		print "\n\t\t\t<h2>"._('Active Directory').'</h2>';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'domainon' => ($Host->get('useAD') == '1' ? 'checked="checked"' : ''),
				'host_dom' => $Host->get('ADDomain'),
				'host_ou' => $Host->get('ADOU'),
				'host_aduser' => $Host->get('ADUser'),
				'host_adpass' => $Host->get('ADPass'),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_AD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		unset($this->data);
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Printers -->";
		$this->headerData = array(
			_('Default'),
			_('Printer Alias'),
			_('Printer Type'),
			_('Remove'),
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'<input class="default" type="checkbox" name="default" id="printer${printer_id}" value="${printer_id}"${is_default} /><label for="printer${printer_id}"></label>',
			'<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
			'${printer_type}',
			'<input onclick="this.form.submit()" class="delid" type="checkbox" name="printerRemove[]" value="${printer_id}" id="rempr${printer_id}" /><label for="rempr${printer_id}">'._('Delete').'</label>',
		);
		print "\n\t\t\t".'<div id="host-printers" class="organic-tabs-hidden">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-printers">';
		print "\n\t\t\t<h2>"._('Host Printer Configuration').'</h2>';
		print "\n\t\t\t<p>"._('Select Management Level for this Host').'</p>';
		print "\n\t\t\t<p>";
		print "\n\t\t\t".'<input type="radio" name="level" value="0"'.($Host->get('printerLevel') == 0 ? 'checked="checked"' : '').' />'._('No Printer Management').'<br/>';
		print "\n\t\t\t".'<input type="radio" name="level" value="1"'.($Host->get('printerLevel') == 1 ? 'checked="checked"' : '').' />'._('Add Only').'<br/>';
		print "\n\t\t\t".'<input type="radio" name="level" value="2"'.($Host->get('printerLevel') == 2 ? 'checked="checked"' : '').' />'._('Add and Remove').'<br/>';
		print "\n\t\t\t</p>";
		foreach ((array)$Host->get('printers') AS $Printer)
		{
			if ($Printer && $Printer->isValid())
			{
				$this->data[] = array(
					'printer_id' => $Printer->get('id'),
					'is_default' => ($Host->getDefault($Printer->get('id')) ? 'checked="checked"' : ''),
					'printer_name' => addslashes($Printer->get('name')),
					'printer_type' => $Printer->get('config'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_PRINTER', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Reset for next tab
		unset($this->data, $this->headerData);
		print "<h2>"._('Add new printer').'</h2>';
		print $this->FOGCore->getClass('PrinterManager')->buildSelectBox();
		print "\n\t\t\t".'<input type="submit" value="Update" />';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		foreach((array)$Host->get('snapins') AS $Snapin)
			$SnapinIDs[] = $Snapin && $Snapin->isValid() ? $Snapin->get('id') : '';
		$SnapinStuff = $this->FOGCore->getClass('SnapinManager')->buildSelectBox('','snapin[]" multiple="multiple','',$SnapinIDs);
		print "\n\t\t\t<!-- Snapins -->";
		print "\n\t\t\t".'<div id="host-snapins" class="organic-tabs-hidden">';
		print "\n\t\t\t<h2>"._('Snapins').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-snapins">';
		if ($SnapinStuff)
		{
			print "\n\t\t\t<p>"._('The selection box below will add the selected snapins to your host automatically.').'</p>';
			print "\n\t\t\t<p><center>$SnapinStuff";
			print "\n\t\t\t".'<input type="submit" value="'._('Add Snapin(s)').'" /></center></p>';
		}
		$this->headerData = array(
			_('Snapin Name'),
			_('Remove'),
		);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'<a href="?node=snapin&sub=edit&id=${snap_id}">${snap_name}</a>',
			'<input type="checkbox" name="snapinRemove[]" value="${snap_id}" class="delid" onclick="this.form.submit()" id="snap${snap_id}" /><label for="snap${snap_id}">Delete</label>',
		);
		foreach ((array)$Host->get('snapins') AS $Snapin)
		{
			if ($Snapin && $Snapin->isValid())
			{
				$this->data[] = array(
					'snap_id' => $Snapin->get('id'),
					'snap_name' => $Snapin->get('name'),
				);
			}
		}
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_SNAPIN', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		// Reset for next tab
		unset($this->data, $this->headerData);
		print "\n\t\t\t<!-- Service Configuration -->";
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
		print "\n\t\t\t".'<div id="host-service" class="organic-tabs-hidden">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-service">';
		print "\n\t\t\t<h2>"._('Service Configuration').'</h2>';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('General').'</legend>';
		foreach ((array)$this->FOGCore->getClass('ModuleManager')->find() AS $Module)
		{
			foreach((array)$Host->get('modules') AS $ModHost)
			{
				if ($ModHost->get('id') == $Module->get('id'))
					$ModOns[] = $ModHost->get('id');
			}
			$this->data[] = array(
				'input' => '<input type="checkbox" class="checkboxes" name="${mod_shname}" value="${mod_id}" ${checked} />',
				'span' => '<span class="icon icon-help hand" title="${mod_desc}"></span>',
				'checked' => ($ModOns ? 'checked="checked"' : ''),
                'mod_name' => $Module->get('name'),
                'mod_shname' => $Module->get('shortName'),
                'mod_id' => $Module->get('id'),
                'mod_desc' => str_replace('"','\"',$Module->get('description')),
            );
			unset($ModOns);
        }
		$this->data[] = array(
			'mod_name' => '&nbsp',
			'input' => '<input type="hidden" name="updatestatus" value="1" />',
			'span' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_SERVICE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Reset for next tab
		unset($this->data);
		print "\n\t\t\t</fieldset>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-service">';
		print "\n\t\t\t<fieldset>";
		print "\n\t\t\t<legend>"._('Host Screen Resolution').'</legend>';
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
		$Services = $this->FOGCore->getClass('ServiceManager')->find(array('name' => array('FOG_SERVICE_DISPLAYMANAGER_X','FOG_SERVICE_DISPLAYMANAGER_Y','FOG_SERVICE_DISPLAYMANAGER_R')), 'OR', 'id');
		foreach((array)$Services AS $Service)
		{
			$this->data[] = array(
				'input' => '<input type="text" name="${type}" value="${disp}" />',
				'span' => '<span class="icon icon-help hand" title="${desc}"></span>',
				'field' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? _('Screen Width (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAY_MANAGER_Y' ? _('Screen Height (in pixels)') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? _('Screen Refresh Rate (in Hz)') : ''))),
				'type' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? 'x' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? 'y' : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? 'r' : ''))),
				'disp' => ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_X' ? $Host->getDispVals('width') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_Y' ? $Host->getDispVals('height') : ($Service->get('name') == 'FOG_SERVICE_DISPLAYMANAGER_R' ? $Host->getDispVals('refresh') : ''))),
				'desc' => $Service->get('description'),
			);
		}
		$this->data[] = array(
			'field' => '&nbsp;',
			'input' => '<input type="hidden" name="updatedisplay" value="1" />',
			'span' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_DISPSERV', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Reset for next tab
		unset($this->data);
		print "</fieldset>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-service">';
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
			'desc' => '<span class="icon icon-help" title="${serv_desc}"></span>',
			'value' => $Host->getAlo() ? $Host->getAlo() : $Service->get('value'),
			'serv_desc' => $Service->get('description'),
		);
		$this->data[] = array(
			'field' => '<input type="hidden" name="updatealo" value="1" />',
			'input' => '',
			'desc' => '<input type="submit" value="'._('Update').'" />',
		);
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_ALO', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Reset for next tab
		unset($this->data,$fields);
		print "</fieldset>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Inventory -->";
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Primary User') => '<input type="text" value="${inv_user}" name="pu" />',
			_('Other Tag #1') => '<input type="text" value="${inv_oth1}" name="other1" />',
			_('Other Tag #2') => '<input type="text" value="${inv_oth2}" name="other2" />',
			_('System Manufacturer') => '${inv_sysman}',
			_('System Product') => '${inv_sysprod}',
			_('System Version') => '${inv_sysver}',
			_('System Serial Number') => '${inv_sysser}',
			_('System Type') => '${inv_systype}',
			_('BIOS Vendor') => '${bios_ven}',
			_('BIOS Version') => '${bios_ver}',
			_('BIOS Date') => '${bios_date}',
			_('Motherboard Manufacturer') => '${mb_man}',
			_('Motherboard Product Name') => '${mb_name}',
			_('Motherboard Version') => '${mb_ver}',
			_('Motherboard Serial Number') => '${mb_ser}',
			_('Motherboard Asset Tag') => '${mb_asset}',
			_('CPU Manufacturer') => '${cpu_man}',
			_('CPU Version') => '${cpu_ver}',
			_('CPU Normal Speed') => '${cpu_nspeed}',
			_('CPU Max Speed') => '${cpu_mspeed}',
			_('Memory') => '${inv_mem}',
			_('Hard Disk Model') => '${hd_model}',
			_('Hard Disk Firmware') => '${hd_firm}',
			_('Hard Disk Serial Number') => '${hd_ser}',
			_('Chassis Manufacturer') => '${case_man}',
			_('Chassis Version') => '${case_ver}',
			_('Chassis Serial') => '${case_ser}',
			_('Chassis Asset') => '${case_asset}',
			'<input type="hidden" name="update" value="1" />' => '<input type="submit" value="'._('Update').'" />',
		); 
		print "\n\t\t\t".'<div id="host-hardware-inventory" class="organic-tabs-hidden">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-hardware-inventory">';
		print "\n\t\t\t<h2>"._('Host Hardware Inventory').'</h2>';
		if ($Inventory && $Inventory->isValid())
		{
			foreach(array('cpuman','cpuversion') AS $x)
				$Inventory->set($x,implode(' ',array_unique(explode(' ',$Inventory->get($x)))));
			foreach((array)$fields AS $field => $input)
			{
				$this->data[] = array(
					'field' => $field,
					'input' => $input,
					'inv_user' => $Inventory->get('primaryUser'),
					'inv_oth1' => $Inventory->get('other1'),
					'inv_oth2' => $Inventory->get('other2'),
					'inv_sysman' => $Inventory->get('sysman'),
					'inv_sysprod' => $Inventory->get('sysproduct'),
					'inv_sysver' => $Inventory->get('sysversion'),
					'inv_sysser' => $Inventory->get('sysserial'),
					'inv_systype' => $Inventory->get('systype'),
					'bios_ven' => $Inventory->get('biosvendor'),
					'bios_ver' => $Inventory->get('biosversion'),
					'bios_date' => $Inventory->get('biosdate'),
					'mb_man' => $Inventory->get('mbman'),
					'mb_name' => $Inventory->get('mbproductname'),
					'mb_ver' => $Inventory->get('mbversion'),
					'mb_ser' => $Inventory->get('mbserial'),
					'mb_asset' => $Inventory->get('mbasset'),
					'cpu_man' => $Inventory->get('cpuman'),
					'cpu_ver' => $Inventory->get('cpuversion'),
					'cpu_nspeed' => $Inventory->get('cpucurrent'),
					'cpu_mspeed' => $Inventory->get('cpumax'),
					'inv_mem' => $Inventory->getMem(),
					'hd_model' => $Inventory->get('hdmodel'),
					'hd_firm' => $Inventory->get('hdfirmware'),
					'hd_ser' => $Inventory->get('hdserial'),
					'case_man' => $Inventory->get('caseman'),
					'case_ver' => $Inventory->get('caseversion'),
					'case_ser' => $Inventory->get('caseserial'),
					'case_asset' => $Inventory->get('caseasset'),
				);
			}
		}
		else
			unset($this->data);
		// Hook
		$this->HookManager->processEvent('HOST_INVENTORY', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Reset for next tab
		unset($this->data,$fields);
		print "</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Virus -->";
		$this->headerData = array(
			_('Virus Name'),
			_('File'),
			_('Mode'),
			_('Date'),
			_('Clear'),
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'<a href="http://www.google.com/search?q=${virus_name}" target="_blank">${virus_name}</a>',
			'${virus_file}',
			'${virus_mode}',
			'${virus_date}',
			'<input type="checkbox" id="vir_del${virus_id}" class="delvid" name="delvid" onclick="this.form.submit()" value="${virus_id}" /><label for="${virus_id}" title="Delete ${virus_name}"><img src="images/deleteSmall.png" class="link" /></label>',
		);
		print "\n\t\t\t".'<div id="host-virus-history" class="organic-tabs-hidden">';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=host-virus-history">';
		print "\n\t\t\t".'<h2>'._('Virus History').'</h2>';
		print "\n\t\t\t".'<h2><a href="#"><input type="checkbox" class="delvid" id="all" name="delvid" value="all" onclick="this.form.submit()" /><label for="all">('._('clear all history').')</label></a></h2>';
		$Viruses = $this->FOGCore->getClass('VirusManager')->find(array('hostMAC' => $Host->get('mac')));
		foreach((array)$Viruses AS $Virus)
		{
			$this->data[] = array(
				'virus_name' => $Virus->get('name'),
				'virus_file' => $Virus->get('file'),
				'virus_mode' => ($Virus->get('mode') == 'q' ? _('Quarantine') : ($Virus->get('mode') == 's' ? _('Report') : 'N/A')),
				'virus_date' => $Virus->get('date'),
				'virus_id' => $Virus->get('id'),
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_VIRUS', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// Reset for next tab
		unset($this->data,$this->headerData);
		print "</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<!-- Login History -->";
		print "\n\t\t\t".'<div id="host-login-history" class="organic-tabs-hidden">';
		print "\n\t\t\t<h2>"._('Host Login History').'</h2>';
		print "\n\t\t\t".'<form id="dte" method="post" action="'.$this->formAction.'&tab=host-login-history">';
		$this->headerData = array(
			_('Action'),
			_('Username'),
			_('Time'),
			_('Description')
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'${action}',
			'${user_name}',
			'${user_time}',
			'${user_desc}',
		);
		$UserLogins = $this->FOGCore->getClass('UserTrackingManager')->find(array('hostID' => $Host->get('id')));
		foreach((array)$UserLogins AS $UserLogin)
			$DatesOld[] = date('Y-m-d',strtotime($UserLogin->get('datetime')));
		if (is_array($DatesOld))
			$Dates = array_unique($DatesOld);
		if ($Dates)
		{
			rsort($Dates);
			print "\n\t\t\t<p>"._('View History for').'</p>';
			foreach((array)$Dates AS $Date)
			{
				if ($_GET['dte'] == '')
					$_GET['dte'] = $Date;
				$optionDate .= '<option value="'.$Date.'" '.($Date == $_GET['dte'] ? 'selected="selected"' : '').'>'.$Date.'</option>';
			}
			print "\n\t\t\t".'<select name="dte" size="1" onchange="document.getElementById(\'dte\').submit()">'.$optionDate.'</select>';
			print "\n\t\t\t".'<a href="#" onclick="document.getElementByID(\'dte\').submit()"><img src="images/go.png" class="noBorder" /></a></p>';
			$UserLogins = $this->FOGCore->getClass('UserTrackingManager')->find(array('hostID' => $Host->get('id'),'date' => $_GET['dte']),'AND','datetime');
			$_SESSION['fog_logins'] = array();
			$cnt = 0;
			foreach ((array)$UserLogins AS $UserLogin)
			{
				$this->data[] = array(
					'action' => ($UserLogin->get('action') == 1 ? _('Login') : ($UserLogin->get('action') == 0 ? _('Logout') : ($UserLogin->get('action') == 99 ? _('Service Start') : _('Service Stop')))),
					'user_name' => $UserLogin->get('username'),
					'user_time' => date('H:i:s',strtotime($UserLogin->get('datetime'))),
					'user_desc' => $UserLogin->get('description'),
				);
				$loginTime = new DateTime($UserLogin->get('datetime'));
				$arAllUsers[] = ($UserLogin->get('username') != 'Array' ? $UserLogin->get('username') : '');
				if ($UserLogin->get('action') == 1 || $UserLogin->get('action') == 99)
				{
					$tmpUserLogin = new UserLoginEntry($UserLogin->get('username'));
					$tmpUserLogin->setLogInTime($UserLogin->get('datetime'));
					$tmpUserLogin->setClean(true);
				}
				else if ($UserLogin->get('action') == 0)
				{
					if ($tmpUserLogin instanceof UserLoginEntry)
					{
						$tmpUserLogin->setLogOutTime($UserLogin->get('datetime'));
						$_SESSION['fog_logins'][] = serialize($tmpUserLogin);
						$tmpUserLogin = '';
					}
				}
			}
			// Hook
			$this->HookManager->processEvent('HOST_USER_LOGIN', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			// Output
			$this->render();
		}
		else
			print "\n\t\t\t<p>"._('No user history data found!').'</p>';
		// Reset for next tab
		unset($this->data,$this->headerData);
		if(count($_SESSION["fog_logins"])>0)
			print '<p><img src="phpimages/hostloginhistory.phpgraph.php" /></p>';
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t</div>";
	}
	/** edit_post()
		Actually saves the data.
	*/
	public function edit_post()
	{
		// Find
		$Host = new Host($this->REQUEST['id']);
		$HostManager = $this->FOGCore->getClass('HostManager');
		$Inventory = current($this->FOGCore->getClass('InventoryManager')->find(array('hostID' => $Host->get('id'))));
		// Hook
		$this->HookManager->processEvent('HOST_EDIT_POST', array('Host' => &$Host));
		// POST
		try
		{
			// Tabs
			switch ($this->REQUEST['tab'])
			{
				case 'host-general';
					// Error checking
					if (empty($_POST['mac']))
						throw new Exception('MAC Address is required');
					if ($Host->get('name') != $_POST['host'] && $HostManager->exists($_POST['host']))
						throw new Exception('Hostname Exists already');
					// Variables
					$mac = new MACAddress($_POST['mac']);
					// Task variable.
					$Task = current($this->FOGCore->getClass('TaskManager')->find(array('hostID' => $Host->get('id'),'stateID' => array(1,2,3))));
					// Error checking
					if (!$mac->isValid())
						throw new Exception(_('MAC Address is not valid'));
					if (!$_POST['image'] && $Task && $Task->isValid())
						throw new Exception('Cannot unset image.<br />Host is currently in a tasking.');
					// Define new Image object with data provided
					$Host	->set('name',		$_POST['host'])
							->set('description',	$_POST['description'])
							->set('mac',		$mac)
							->set('imageID',	$_POST['image'])
							->set('kernel',		$_POST['kern'])
							->set('kernelArgs',	$_POST['args'])
							->set('kernelDevice',	$_POST['dev']);
					// Add Additional MAC Addresses
					foreach((array)$_POST['additionalMACs'] AS $MAC)
					{
						$PriMAC = ($Host->get('mac') == $MAC ? true : false);
						$AddMAC = current($this->FOGCore->getClass('MACAddressAssociationManager')->find(array('hostID' => $Host->get('id'),'mac' => $MAC)));
						if (!$PriMAC && (!$AddMAC || !$AddMAC->isValid()))
							$Host->addAddMAC($MAC);
					}
					if(isset($_POST['additionalMACsRM']))
					{
						foreach((array)$_POST['additionalMACsRM'] AS $MAC)
						{
							$DelMAC = new MACAddressAssociation($MAC);
							$Host->removeAddMAC($DelMAC);
						}
					}
					// Only one association per host.
					$LA = current($this->FOGCore->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
					if ((!$LA || !$LA->isValid()) && $_REQUEST['location'])
					{
						$Location = new Location($_REQUEST['location']);
						$LA = new LocationAssociation(array(
							'locationID' => $Location->get('id'),
							'hostID' => $Host->get('id'),
						));
					}
					else if ($LA && $LA->isValid() && $_REQUEST['location']) 
					{
						if ($LA->get('locationID') != $_REQUEST['location'])
							$LA->set('locationID', $_REQUEST['location']);
					}
				break;
				case 'host-grouprel';
					if($_POST['group'])
						$Host->addGroup($_POST['group']);
					if(isset($_POST['groupdel']))
						$Host->removeGroup($_POST['groupdel']);
				break;
				case 'host-active-directory';
					$Host	->set('useAD',		($_POST["domain"] == "on" ? '1' : '0'))
							->set('ADDomain',	$_POST['domainname'])
							->set('ADOU',		$_POST['ou'])
							->set('ADUser',		$_POST['domainuser'])
							->set('ADPass',		$_POST['domainpassword']);
				break;
				case 'host-printers';
					$PrinterManager = $this->FOGCore->getClass('PrinterAssociationManager');
					// Set printer level for Host
					if (isset($_POST['level']))
						$Host->set('printerLevel',$_POST['level']);
					// Add
					if (!empty($_POST['printer']))
					{
						if($PrinterManager->exists($_POST['printer'],$Host->get('id')))
							throw new Exception('Printer is already here!');
						else
							$Host->addPrinter($_POST['printer']);
					}
					// Set Default
					if (!empty($this->REQUEST['default']))
						$Host->updateDefault($this->REQUEST['default']);
					if (empty($this->REQUEST['default']))
						$Host->updateDefault('');
					// Remove
					if (!empty($_POST['printerRemove']))
						$Host->removePrinter($this->REQUEST['printerRemove']);
				break;
				case 'host-snapins';
					$SnapinManager = $this->FOGCore->getClass('SnapinAssociationManager');
					// Add
					if (!empty($this->REQUEST['snapin']))
					{
						if($SnapinManager->exists($_POST['snapin'], $Host->get('id')))
							throw new Exception('Snapin already associated.');
						else
							$Host->addSnapin($this->REQUEST['snapin']);
					}
					// Remove
					if (!empty($this->REQUEST['snapinRemove']))
						$Host->removeSnapin($this->REQUEST['snapinRemove']);
				break;
				case 'host-service';
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
					if ($_POST['updatestatus'] == '1')
					{
						foreach((array)$ServiceSetting AS $id => $onoff)
							$onoff ? $Host->addModule($id) : $Host->removeModule($id);
					}
					if ($_POST['updatedisplay'] == '1')
						$Host->setDisp($x,$y,$r);
					if ($_POST['updatealo'] == '1')
						$Host->setAlo($tme);
					$Host->save();
				break;
				case 'host-hardware-inventory';
					$pu = trim($_POST['pu']);
					$other1 = trim($_POST['other1']);
					$other2 = trim($_POST['other2']);
					if ($_POST["update"] == "1")
					{
						$Inventory->set('primaryUser', trim($_POST['pu']))
								  ->set('other1', trim($_POST['other1']))
								  ->set('other2', trim($_POST['other2']))
								  ->save();
					}
				break;
				case 'host-login-history';
					$this->FOGCore->redirect("?node=host&sub=edit&id=".$Host->get('id')."&dte=".$_POST['dte']."#".$this->REQUEST['tab']);
				break;
				case 'host-virus-history';
					if(isset($_POST["delvid"]))
					{
						$Virus = new Virus($_POST['delvid']);
						$Virus->destroy();
					}
					if (isset($_POST['delvid']) && $_POST['delvid'] == 'all')
					{
						$Host->clearAVRecordsForHost();
						$this->FOGCore->redirect('?node=host&sub=edit&id='.$Host->get('id').'#'.$this->REQUEST['tab']);
					}
				break;
			}
			// Save to database
			if ($Host->save())
			{
				if ($LA)
					$LA->save();
				// Hook
				$this->HookManager->processEvent('HOST_EDIT_SUCCESS', array('host' => &$Host));
				// Log History event
				$this->FOGCore->logHistory('Host updated: ID: '.$Host->get('id').', Name: '.$Host->get('name').', Tab: '.$this->REQUEST['tab']);
				// Set session message
				$this->FOGCore->setMessage('Host updated!');
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s#%s', $this->REQUEST['node'], $this->id, $Host->get('id'), $this->REQUEST['tab']));
			}
			else
				throw new Exception('Host update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('HOST_EDIT_FAIL', array('Host' => &$Host));
			// Log History event
			$this->FOGCore->logHistory('Host update failed: Name: '.$_POST['name'].', Tab: '.$this->REQUEST['tab'].', Error: '.$e->getMessage());
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect('?node=host&sub=edit&id='.$Host->get('id').'#'.$this->REQUEST['tab']);
		}
	}
	/** delete()
		Prints the delete host form.
	*/
	public function delete()
	{	
		// Find
		$Host = new Host($this->REQUEST['id']);
		// Title
		$this->title = 'Remove: '.$Host->get('name');
		// Hook
		$this->HookManager->processEvent('HOST_ADD', array('Host' => &$Host));
		print "\n\t\t\t".'<p class="c">'._('Please confirm you want to delete').' <b>'.$Host->get('name').'</b></p>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
		print "\n\t\t\t".'<input type="submit" value="'.$this->title.'" />';
		print "\n\t\t\t</form>";
	}
	/** delete_post()
		Actually deletes the host.
	*/
	public function delete_post()
	{
		// Find
		$Host = new Host($this->REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('HOST_ADD_POST', array('Host' => &$Host));
		// POST
		try
		{
			// Error checking
			if (!$Host->destroy())
				throw new Exception('Failed to destroy Host');
			// Hook
			$this->HookManager->processEvent('HOST_DELETE_SUCCESS', array('Host' => &$Host));
			// Log History event
			$this->FOGCore->logHistory('Host deleted: ID: '.$Host->get('id').', Name: '.$Host->get('name'));
			// Set session message
			$this->FOGCore->setMessage('Host deleted: '.$Host->get('name'));
			// Redirect
			$this->FOGCore->redirect('?node=host');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('HOST_DELETE_FAIL', array('Host' => &$Host));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('Host'), _('deleted'), $Host->get('id'), $Host->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
	/** import()
		Import host form.
	*/
	public function import()
	{
		// Title
		$this->title = 'Import Host List';
		// Header Data
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
		print "\n\t\t\t".'<form enctype="multipart/form-data" method="post" action="'.$this->formAction.'">';
		$fields = array(
			_('CSV File') => '<input class="smaller" type="file" name="file" />',
			'&nbsp;' => '<input class="smaller" type="submit" value="'._('Upload CSV').'" />',
		);
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_IMPORT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "</form>";
		print "\n\t\t\t<p>"._('This page allows you to upload a CSV file of hosts into FOG to ease migration.  Right click').'<a href="./other/hostimport.csv">'._('here').'</a>'._(' and select ').'<strong>'._('Save target as...').'</strong>'._(' or ').'<strong>'.('Save link as...').'</strong>'._(' to download a template file.  The only fields that are required are hostname and MAC address.  Do ').'<strong>'._('NOT').'</strong>'._(' include a header row, and make sure you resave the file as a CSV file and not XLS!').'</p>';
	}
	/** import_post()
		Actually imports the post.
	*/
	public function import_post()
	{
		try
		{
			// Error checking
			if ($_FILES["file"]["error"] > 0)
				throw new Exception(sprintf('Error: '.(is_array($_FILES["file"]["error"]) ? implode(', ', $_FILES["file"]["error"]) : $_FILES["file"]["error"])));
			if (!file_exists($_FILES["file"]["tmp_name"]))
				throw new Exception('Could not find tmp filename');
			$numSuccess = $numFailed = $numAlreadyExist = 0;
			$handle = fopen($_FILES["file"]["tmp_name"], "r");
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) 
			{
				// Ignore header data if left in CSV
				if (preg_match('#ie#', $data[0]))
					continue;
				$totalRows++;
				if ( count( $data ) < 6 && count( $data ) >= 2 )
				{
					try
					{
						// Error checking
						if ($this->FOGCore->getClass('HostManager')->doesHostExistWithMac(new MACAddress($data[0])))
							throw new Exception('A Host with this MAC Address already exists');
						if($this->FOGCore->getClass('HostManager')->exists($data[1]))
							throw new Exception('A host with this name already exists');
						$Host = new Host(array(
							'name'		=> $data[1],
							'description'	=> $data[3] . ' Uploaded by batch import on',
							'ip'		=> $data[2],
							'imageID'	=> $data[4],
							'createdTime'	=> time(),
							'createdBy'	=> $this->FOGUser->get('name'),
							'mac'		=> $data[0]
						));
						
						if ($Host->save())
							$numSuccess++;
						else
							$numFailed++;
					}
					catch (Exception $e )
					{
						$numFailed++;
						$uploadErrors .= sprintf('%s #%s: %s<br />', _('Row'), $totalRows, $e->getMessage());
					}					
				}
				else
				{
					$numFailed++;
					$uploadErrors .= sprintf('%s #%s: %s<br />', _('Row'), $totalRows, _('Invalid number of cells'));
				}
			}
			fclose($handle);
		}
		catch (Exception $e)
		{
			$error = $e->getMessage();
		}
		// Title
		$this->title = _('Import Host Results');
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('Total Rows') => $totalRows,
			_('Successful Hosts') => $numSuccess,
			_('Failed Hosts') => $numFailed,
			_('Errors') => $uploadErrors,
		);

		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		} 
		// Hook
		$this->HookManager->processEvent('HOST_IMPORT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** export()
		Exports the hosts from the database.
	*/
	public function export()
	{
		$this->title = 'Export Hosts';
		// Header Data
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
		// Fields
		$fields = array(
			_('Click the button to download the hosts table backup.') => '<input type="submit" value="'._('Export').'" />',
		);
		$report = new ReportMaker();
		$Hosts = $this->FOGCore->getClass('HostManager')->find();
		foreach((array)$Hosts AS $Host)
		{
			$report->addCSVCell($Host->get('mac'));
			$report->addCSVCell($Host->get('name'));
			$report->addCSVCell($Host->get('ip'));
			$report->addCSVCell($Host->get('description'));
			$report->addCSVCell($Host->get('imageID'));
			$report->endCSVLine();
		}
		$_SESSION['foglastreport']=serialize($report);
		print "\n\t\t\t".'<form method="post" action="export.php?type=host">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_EXPORT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print "</form>";
	}
	/** deploy()
		Deploys a task to the host form.
	*/
	public function deploy()
	{
		// Find
		$Host = new Host($this->REQUEST['id']);
		$TaskType = new TaskType(($this->REQUEST['type'] ? $this->REQUEST['type'] : '1'));
		// Title
		$this->title = _('Create '.$TaskType->get('name').' task for Host '.$Host->get('name'));
		// Deploy
		print "\n\t\t\t".'<p class="c"><b>'._('Are you sure you wish to deploy these machines?').'</b></p>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" id="deploy-container">';
		print "\n\t\t\t".'<div class="confirm-message">';
		if ($TaskType->get('id') == 13)
		{
			print "\n\t\t\t<p>"._('Please select the snapin you want to deploy').'</p>';
			$SAs = $this->FOGCore->getClass('SnapinAssociationManager')->find(array('hostID' => $Host->get('id')));
			if ($SAs)
			{
				foreach((array)$SAs AS $SA)
				{
					$Snapin = new Snapin($SA->get('snapinID'));
					$optionSnapinSel .= '<option value="'.$Snapin->get('id').'">'.$Snapin->get('name').' - ('.$Snapin->get('id').')</option>';
				}
				print "\n\t\t\t".'<select name="snapin">'.$optionSnapinSel.'</select>';
			}
			else
				print "\n\t\t\t<p>"._('No snapins Associated.').'</p>';
		}
		print "\n\t\t\t".'<div class="advanced-settings">';
		print "\n\t\t\t<h2>"._('Advanced Settings').'</h2>';
		print "\n\t\t\t".'<p><input type="checkbox" name="shutdown" id="shutdown" value="1" autocomplete="off"><label for="shutdown">'._('Schedule').' <u>'._('Shutdown').'</u>'._(' after task completion').'</label></p>';
		if (!$TaskType->isDebug())
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
		print "\n\t\t\t</div>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t<h2>"._('Hosts in Task').'</h2>';
		unset($this->headerData);
		$this->attributes = array(
			array(),
			array(),
			array(),
		);
		$this->templates = array(
			'${host_name}',
			'${host_mac}',
			'${image_name}',
		);
		$this->data[] = array(
			'host_name' => $Host->get('name'),
			'host_mac' => $Host->get('mac'),
			'image_name' => $Host->getImage()->get('name'),
		);
		$this->render();
		print '<p class="c"><input type="submit" value="'.$this->title.'" /></p>';
		print "\n\t\t\t</form>";
	}
	/** deploy_post()
		Actually deploy's the tasking.
	*/
	public function deploy_post()
	{
		// Find
		$Host = new Host($this->REQUEST['id']);	
		// Title
		$this->title = _("Deploy Image to Host");
		// Variables
		$taskTypeID = $this->REQUEST['type'];
		$TaskType = new TaskType($taskTypeID);
		$snapin = ($_REQUEST['snapin'] ? $_REQUEST['snapin'] : -1);
		$enableShutdown = ($this->REQUEST['shutdown'] == '1' ? true : false);
		$enableSnapins = ($taskTypeID != '17' ? $snapin : '');
		$enableDebug = ($this->REQUEST['debug'] == 'true' ? true : false);
		$scheduledDeployTime = strtotime($this->REQUEST['scheduleSingleTime']);
		$taskName = ($taskTypeID == '12' ? 'All Snapins Deploy' : ($taskTypeID == '13' ? 'Single Snapin Deploy' : ''));
		// Deploy
		try
		{
			if ($this->REQUEST['scheduleType'] == 'single')
			{
				// Scheduled Deployment
				// NOTE: Function will throw an exception if it fails
				$Host->createSingleRunScheduledPackage($taskTypeID, $taskName, $scheduledDeployTime, $enableShutdown, $enableSnapins, $this->FOGUser->get('name'));
				// Success
				printf('%s',sprintf('<div class="task-start-ok"><p>%s task created for <u>%s</u> with image <u>%s</u></p><p>%s%s</p></div>',$TaskType->get('name'),$Host->get('name'),$Host->getImage()->get('name'),_('Scheduled to start at: '),$_REQUEST['scheduleSingleTime']));
			}
			else if ($this->REQUEST['scheduleType'] == 'cron')
			{
				// Cron Deployment
				// NOTE: Function will throw an exception if it fails
				$Host->createCronScheduledPackage($taskTypeID, $taskName, $this->REQUEST['scheduleCronMin'], $this->REQUEST['scheduleCronHour'], $this->REQUEST['scheduleCronDOM'], $this->REQUEST['scheduleCronMonth'], $this->REQUEST['scheduleCronDOW'], $enableShutdown, $enableSnapins,$this->FOGCore->get('name'));
				// Success
				printf('%s',sprintf('<div class="task-start-ok"><p>%s task created for <u>%s</u> with image <u>%s</u></p><p>%s%s</p></div>',$TaskType->get('name'),$Host->get('name'),$Host->getImage()->get('name'),_('Cron Schedule:'),implode(' ', array($_REQUEST['scheduleCronMin'],$_REQUEST['scheduleCronHour'],$_REQUEST['scheduleCronDOM'],$_REQUEST['scheduleCronMonth'],$_REQUEST['scheduleCronDOW']))));
			}
			else
			{
				// Instant Deployment
				// NOTE: Function will throw an exception if it fails
				$Host->createImagePackage($taskTypeID, $taskName, $enableShutdown, $enableDebug, $enableSnapins);
				// Success
				printf('%s',sprintf('<div class="task-start-ok"><p>%s task created for <u>%s</u> with image <u>%s</u></p></div>',$TaskType->get('name'),$Host->get('name'),$Host->getImage()->get('name')));
			}
		}
		catch (Exception $e)
		{
			printf('%s',sprintf('<div class="task-start-failed"><p>%s task failed to create for<u>%s></u> with image<u>%s</u></p><p>%s</p></div>',$TaskType->get('name'),$Host->get('name'),$Host->getImage()->get('name'),$e->getMessage()));
		}
	}
	// Overrides
	/** render()
		Overrides the FOGCore render method.
		Prints the group box data below the host list/search information.
	*/
	public function render()
	{
		// Render
		parent::render();
		
		// Add action-box
		if ((in_array($_REQUEST['sub'], array('list', 'search')) || ($this->FOGCore->getSetting('FOG_VIEW_DEFAULT_SCREEN') == 'list' && !$_REQUEST['sub'] || in_array($_REQUEST['sub'],array('list','search')))) && !$this->FOGCore->isAJAXRequest() && !$this->FOGCore->isPOSTRequest())
		{	
			print '<form method="post" action="'.sprintf('%s?node=%s&sub=save_group', $_SERVER['PHP_SELF'], $this->node).'" id="action-box">';
			print "\n\t\t\t".'<input type="hidden" name="hostIDArray" id="hostIDArray" value="" autocomplete="off" />';
			print "\n\t\t\t".'<p><label for="group_new">'._('Create new group').'</label><input type="text" name="group_new" id="group_new" autocomplete="off" /></p>';
			print "\n\t\t\t".'<p class="c">'._('OR').'</p>';
			print "\n\t\t\t".'<p><label for="group">'._('Add to group').'</label>'.$this->FOGCore->getClass('GroupManager')->buildSelectBox().'</p>';
			print "\n\t\t\t".'<p class="c"><input type="submit" value="'._("Process Group Changes").'" /></p>';
			print "\n\t\t\t</form>";
		}
	}
	/** save_group()
		Saves the data to a host.
	*/
	public function save_group()
	{
		try
		{
			// Error checking
			if (empty($this->REQUEST['hostIDArray']))
				throw new Exception( _('No Hosts were selected') );
			if (empty($this->REQUEST['group_new']) && empty($this->REQUEST['group']))
				throw new Exception( _('No Group selected and no new Group name entered') );
			// Determine which method to use
			// New group
			if (!empty($this->REQUEST['group_new']))
			{
				if (!$Group = current($this->FOGCore->getClass('GroupManager')->find(array('name' => $this->REQUEST['group_new']))))
				{
					$Group = new Group(array('name' => $this->REQUEST['group_new']));
					if (!$Group->save())
						throw new Exception( _('Failed to create new Group') );
				}
			}
			else
			// Existing group
			{
				if (!$Group = current($this->FOGCore->getClass('GroupManager')->find(array('id' => $this->REQUEST['group']))))
					throw new Exception( _('Invalid Group ID') );
			}
			// Valid
			if (!$Group->isValid())
				throw new Exception( _('Group is Invalid') );
			// Main
			foreach ((array)explode(',', $this->REQUEST['hostIDArray']) AS $hostID)
			{
				//$Group->add('hosts', $hostID);
				$GroupAssociation = new GroupAssociation(array('hostID' => $hostID, 'groupID' => $Group->get('id')));
				$GroupAssociation->save();
			}
			// Success
			print '<div class="task-start-ok"><p>'._('Successfully associated Hosts with the Group ').$Group->get('name').'</p></div>';
		}
		catch (Exception $e)
		{
			printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>', _('Failed to Associate Hosts with Group'), $e->getMessage());
		}
	}
}
// Register page with FOGPageManager
$FOGPageManager->register(new HostManagementPage());
