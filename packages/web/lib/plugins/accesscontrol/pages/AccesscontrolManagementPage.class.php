<?php
/**	Class Name: AccessControlManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/plugins/accesscontrol/pages

	Description: This is an extension of the FOGPage Class
    This class controls access to users based on group or individual
	access limitations determined by the sysadmins/admins of FOG.
	It's only enabled if the plugin is installed.
 
    Useful for:
	Restricting user roles and priveleges
**/
class AccesscontrolManagementPage extends FOGPage
{
	// Base variables
	var $name = 'Access Management';
	var $node = 'accesscontrol';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// __construct
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header row
		// TODO: NEEDS TO BE WORKED ON
		$this->headerData = array(
			'Location Name',
			'Storage Group',
			'Storage Node',
			'TFTP Server',
		);
		// Row templates
		$this->templates = array(
			'<a href="?node=location&sub=edit&id=${id}" title="Edit">${name}</a>',
			'${storageGroup}',
			'${storageNode}',
			'${tftp}',
		);
		$this->attributes = array(
			array('class' => 'l'),
			array('class' => 'l'),
			array('class' => 'c'),
			array('class' => 'r'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('Search');
		// Find data
		$Locations = $this->FOGCore->getClass('LocationManager')->find();
		// Row data
		foreach ((array)$Locations AS $Location)
		{
			$StorageGroup = new StorageGroup($Location->get('storageGroupID'));
			$this->data[] = array(
				'id'	=> $Location->get('id'),
				'name'  => $Location->get('name'),
				'storageNode' => ($Location->get('storageNodeID') ? $this->FOGCore->getClass('StorageNode',$Location->get('storageNodeID'))->get('name') : 'Not Set'),
				'storageGroup' => $StorageGroup->get('name'),
				'tftp' => $Location->get('tftp') ? _('Yes') : _('No'),
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED'))
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('LOCATION_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}

	public function search()
	{
		// Set title
		$this->title = 'Search';
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node=location&sub=search';
		// Hook
		$this->HookManager->processEvent('LOCATION_SEARCH');
		// Output
		$this->render();
	}

	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// To assist with finding by storage group or location.
		$where = array(
		    'id'		=> $keyword,
			'name'		=> $keyword,
			'description' => $keyword,
			'storageGroupID' => $keyword,
		);
		// Find data -> Push data
		foreach ((array)$this->FOGCore->getClass('LocationManager')->find($where,'OR') AS $Location)
		{
			$this->data[] = array(
				'id'		=> $Location->get('id'),
				'name'		=> $Location->get('name'),
				'storageGroup'	=> $this->FOGCore->getClass('StorageGroup',$Location->get('storageGroupID'))->get ('name'),
				'storageNode' => $Location->get('storageNodeID') ? $this->FOGCore->getClass('StorageNode',$Location->get('storageNodeID'))->get('name') : 'Not Set',
				'tftp' => $Location->get('tftp') ? 'Yes' : 'No',
			);
		}
		// Hook
		$this->HookManager->processEvent('LOCATION_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add()
	{
		$this->title = 'New Location';
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
		$fields = array(
			_('Location Name') => '<input class="smaller" type="text" name="name" />',
			_('Storage Group') => $this->FOGCore->getClass('StorageGroupManager')->buildSelectBox(),
			_('Storage Node') => $this->FOGCore->getClass('StorageNodeManager')->buildSelectBox(),
			_('TFTP From Node') => '<input type="checkbox" name="tftp" value="on" />',
			'<input type="hidden" name="add" value="1" />' => '<input class="smaller" type="submit" value="'.('Add').'" />',
		);
		print '<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		// Hook
		$this->HookManager->processEvent('LOCATION_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function add_post()
	{
		try
		{
			$name = trim($_REQUEST['name']);
			if ($this->FOGCore->getClass('LocationManager')->exists(trim($_REQUEST['name'])))
				throw new Exception('Location already Exists, please try again.');
			if (!$name)
				throw new Exception('Please enter a name for this location.');
			if (empty($_REQUEST['storagegroup']))
				throw new Exception('Please select the storage group this location relates to.');
			$Location = new Location(array(
				'name' => trim($_REQUEST['name']),
				'storageGroupID' => $_REQUEST['storagegroup'],
				'storageNodeID' => $_REQUEST['storagenode'],
				'tftp' => $_REQUEST['tftp'],
			));
			if ($_REQUEST['storagenode'] && $Location->get('storageGroupID') != $this->FOGCore->getClass('StorageNode',$_REQUEST['storagenode'])->get('storageGroupID'))
				$Location->set('storageGroupID', $this->FOGCore->getClass('StorageNode',$_REQUEST['storagenode'])->get('storageGroupID'));
			if ($Location->save())
			{
				$this->FOGCore->setMessage('Location Added, editing!');
				$this->FOGCore->redirect('?node=location&sub=edit&id='.$Location->get('id'));
			}
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit()
	{
		// Find
		$Location = new Location($_REQUEST['id']);
		// Get the Storage Node ID if it's set
		// Title
		$this->title = sprintf('%s: %s', 'Edit', $Location->get('name'));
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
		$fields = array(
			_('Location Name') => '<input class="smaller" type="text" name="name" value="${location_name}" />',
			_('Storage Group') => '${storage_groups}',
			_('Storage Node') => '${storage_nodes}',
			$Location->get('storageNodeID') ? _('TFTP From Node') : '' => $Location->get('storageNodeID') ? '<input type="checkbox" name="tftp" value="on" ${checked} />' : '',
			'<input type="hidden" name="update" value="1" />' => '<input type="submit" class="smaller" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$Location->get('id').'">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'location_name' => $Location->get('name'),
				'storage_groups' => $this->FOGCore->getClass('StorageGroupManager')->buildSelectBox($Location->get('storageGroupID')),
				'storage_nodes' => $this->FOGCore->getClass('StorageNodeManager')->buildSelectBox($Location->get('storageNodeID')),
				'checked' => $Location->get('tftp') ? 'checked="checked"' : '',
			);
		}
		// Hook
		$this->HookManager->processEvent('LOCATION_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function edit_post()
	{
		$Location = new Location($_REQUEST['id']);
		$LocationMan = new LocationManager();
		$this->HookManager->processEvent('LOCATION_EDIT_POST', array('Location'=> &$Location));
		try
		{
			if ($_REQUEST['name'] != $Location->get('name') && $LocationMan->exists($_REQUEST['name']))
				throw new Exception('A location with that name already exists.');
			if ($_REQUEST['update'])
			{
				if ($_REQUEST['storagegroup'])
				{
					$Location->set('name', $_REQUEST['name'])
							 ->set('storageGroupID', $_REQUEST['storagegroup']);
				}
				$Location->set('storageNodeID', $_REQUEST['storagenode'])
						 ->set('tftp', $_REQUEST['tftp']);
				if ($Location->save())
				{
					$this->FOGCore->setMessage('Location Updated');
					$this->FOGCore->redirect('?node=location&sub=edit&id='.$Location->get('id'));
				}
			}
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete()
	{
		// Find
		$Location = new Location($_REQUEST['id']);
		//Title
		$this->title = sprintf('%s: %s', _('Remove'), $Location->get('name'));
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
		$fields = array(
			_('Please confirm you want to delete').' <b>'.$Location->get('name').'</b>' => '<input type="submit" value="${title}" />',
		);
		foreach((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'title' => $this->title,
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" class="c">';
		// Hook
		$this->HookManager->processEvent('LOCATION_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function delete_post()
	{
		// Find
		$Location = new Location($_REQUEST['id']);
		// Hook
		$this->HookManager->processEvent('LOCATION_DELETE_POST', array('Location' => &$Location));
		// POST
		try
		{
			// Remove Location Associations
			$this->FOGCore->getClass('LocationAssociationManager')->destroy(array('locationID' => $Location->get('id')));
			// Remove Location
			if (!$Location->destroy())
				throw new Exception(_('Failed to destroy Location'));
			// Hook
			$this->HookManager->processEvent('LOCATION_DELETE_SUCCESS', array('Location' => &$Location));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('Location deleted'), $Location->get('id'), $Location->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('Location deleted'), $Location->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('LOCATION_DELETE_FAIL', array('Location' => &$Location));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('Location'), _('deleted'), $Location->get('id'), $Location->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
