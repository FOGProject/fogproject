<?php
/**	Class Name: LDAPManagementPage
    FOGPage lives in: {fogwebdir}/lib/fog
    Lives in: {fogwebdir}/lib/plugins/ldap/pages

	Description: This is an extension of the FOGPage Class
    This class controls locations you want FOG to associate
	with.  It's only enabled if the plugin is installed.
 
    Useful for:
    Setting up clients that may move from sight to sight.
**/
class LDAPManagementPage extends FOGPage
{
	// Base variables
	var $name = 'LDAP Management';
	var $node = 'ldap';
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
		$this->headerData = array(
			'LDAP Server Name',
			'LDAP Server Description',
			'LDAP Server',
			'Port',
		);
		// Row templates
		$this->templates = array(
			'<a href="?node=ldap&sub=edit&id=${id}" title="Edit">${name}</a>',
			'${description}',
			'${address}',
			'${port}',
		);
		$this->attributes = array(
			array('class' => 'l'),
			array('class' => 'l'),
			array('class' => 'l'),
			array('class' => 'l'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('Search');
		// Find data
		$LDAPs = $this->getClass('LDAPManager')->find();
		// Row data
		foreach ((array)$LDAPs AS $LDAP)
		{
			$this->data[] = array(
				'id'	=> $LDAP->get('id'),
				'name'  => $LDAP->get('name'),
				'description' => $LDAP->get('description'),
				'address' => $LDAP->get('address'),
				'DN'	=> $LDAP->get('DN'),
				'port'	=> $LDAP->get('port'),

			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED'))
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->event[] = 'LDAP_DATA';
		$this->HookManager->processEvent('LDAP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}

	public function search()
	{
		// Set title
		$this->title = 'Search';
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node=ldap&sub=search';
		// Hook
		$this->HookManager->event[] = 'LDAP_SEARCH';
		$this->HookManager->processEvent('LDAP_SEARCH');
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
			'address' => $keyword,
			'DN'		=> $keyword,
		);
		// Find data -> Push data
		foreach ((array)$this->getClass('LDAPManager')->find($where,'OR') AS $LDAP)
		{
			$this->data[] = array(
				'id'		=> $LDAP->get('id'),
				'name'		=> $LDAP->get('name'),
				'description' => $LDAP->get('description'),
				'address'	=> $LDAP->get('address'),
				'DN'		=> $LDAP->get('DN'),
				'port'	=> $LDAP->get('port'),
			);
		}
		// Hook
		$this->HookManager->event[] = 'LDAP_DATA';
		$this->HookManager->processEvent('LDAP_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add()
	{
		$this->title = 'New LDAP Server';
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
			_('LDAP Server Name') => '<input class="smaller" type="text" name="name" />',
			_('LDAP Server Description') => '<input class="smaller" type="text" name="description" />',
			_('LDAP Server Address') => '<input class="smaller" type="text" name="address" />',
			_('DN') => '<input class="smaller" type="text" name="DN" />',
			_('Server Port') => '<input class="smaller" type="text" name="port" />',
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
		$this->HookManager->event[] = 'LDAP_ADD';
		$this->HookManager->processEvent('LDAP_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function add_post()
	{
		try
		{
			$name = trim($_REQUEST['name']);
			$address = trim($_REQUEST['address']);
			if ($this->getClass('LDAPManager')->exists(trim($_REQUEST['name'])))
				throw new Exception('LDAP server already Exists, please try again.');
			if (!$name)
				throw new Exception('Please enter a name for this LDAP server.');
			if (empty($address))
				throw new Exception('Please enter a LDAP server address');
			$LDAP = new LDAP(array(
				'name' => trim($_REQUEST['name']),
				'description' => $_REQUEST['description'],
				'address' => $_REQUEST['address'],
				'DN' => $_REQUEST['DN'],
				'port'	=> $_REQUEST['port'],
			));
			if ($LDAP->save())
			{
				$this->FOGCore->setMessage('LDAP Server Added, editing!');
				$this->FOGCore->redirect('?node=ldap&sub=edit&id='.$LDAP->get('id'));
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
		$LDAP = new LDAP($_REQUEST['id']);
		// Title
		$this->title = sprintf('%s: %s', 'Edit', $LDAP->get('name'));
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
			_('LDAP Server Name') => '<input class="smaller" type="text" name="name" value="${ldap_name}" />',
			_('LDAP Server Description') => '<input class="smaller" type="text" name="description" value="${ldap_description}" />',
			_('LDAP Server Address') => '<input class="smaller" type="text" name="address" value="${ldap_address}" />',
			_('DN') => '<input class="smaller" type="text" name="DN" value="${ldap_DN}" />',
			_('DN') => '<input class="smaller" type="text" name="port" value="${ldap_port}" />',
			'<input type="hidden" name="update" value="1" />' => '<input type="submit" class="smaller" value="'._('Update').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$LDAP->get('id').'">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'ldap_name' => $LDAP->get('name'),
				'ldap_description' => $LDAP->get('description'),
				'ldap_address' => $LDAP->get('address'),
				'ldap_DN' => $LDAP->get('DN'),
				'ldap_port'	=> $LDAP->get('port'),
			);
		}
		// Hook
		$this->HookManager->event[] = 'LDAP_EDIT';
		$this->HookManager->processEvent('LDAP_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function edit_post()
	{
		$LDAP = new LDAP($_REQUEST['id']);
		$LDAPMan = new LDAPManager();
		$this->HookManager->event[] = 'LDAP_EDIT_POST';
		$this->HookManager->processEvent('LDAP_EDIT_POST', array('LDAP'=> &$LDAP));
		try
		{
			if ($_REQUEST['name'] != $LDAP->get('name') && $LDAPMan->exists($_REQUEST['name']))
				throw new Exception('A LDAP Server with that name already exists.');
			if (empty($_REQUEST['address']))
				throw new Exception('LDAP server address is empty!!');
			if ($_REQUEST['update'])
			{
				if ($_REQUEST['name'] != $LDAP->get('name'))
					$LDAP->set('name', $_REQUEST['name']);
				
				if ($_REQUEST['description'] != $LDAP->get('description'))
					$LDAP->set('description', $_REQUEST['description']);
				
				if ($_REQUEST['address'] != $LDAP->get('address'))
					$LDAP->set('address', $_REQUEST['address']);
				if ($_REQUEST['DN'] != $LDAP->get('DN'))
					$LDAP->set('DN', $_REQUEST['DN']);
				if ($_REQUEST['port'] != $LDAP->get('port'))
					$LDAP->set('port', $_REQUEST['port']);
				if ($LDAP->save())
				{
					$this->FOGCore->setMessage('LDAP Server Updated');
					$this->FOGCore->redirect('?node=ldap&sub=edit&id='.$LDAP->get('id'));
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
		$LDAP = new LDAP($_REQUEST['id']);
		//Title
		$this->title = sprintf('%s: %s', _('Remove'), $LDAP->get('name'));
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
			_('Please confirm you want to delete').' <b>'.$LDAP->get('name').'</b>' => '<input type="submit" value="${title}" />',
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
		$this->HookManager->event[] = 'LDAP_DELETE';
		$this->HookManager->processEvent('LDAP_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function delete_post()
	{
		// Find
		$LDAP = new LDAP($_REQUEST['id']);
		// Hook
		$this->HookManager->event[] = 'LDAP_DELETE_POST';
		$this->HookManager->processEvent('LDAP_DELETE_POST', array('LDAP' => &$LDAP));
		// POST
		try
		{
			// Remove LDAP Server
			if (!$LDAP->destroy())
				throw new Exception(_('Failed to destroy LDAP Server'));
			// Hook
			$this->HookManager->event[] = 'LDAP_DELETE_SUCCESS';
			$this->HookManager->processEvent('LDAP_DELETE_SUCCESS', array('LDAP' => &$LDAP));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('LDAP Server deleted'), $LDAP->get('id'), $LDAP->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('LDAP Server deleted'), $LDAP->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->event[] = 'LDAP_DELETE_FAIL';
			$this->HookManager->processEvent('LDAP_DELETE_FAIL', array('LDAP' => &$LDAP));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('LDAP Server'), _('deleted'), $LDAP->get('id'), $LDAP->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
