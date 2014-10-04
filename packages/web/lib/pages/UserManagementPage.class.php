<?php
/** Class Name: UserManagementPage
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/page
	Description: This is an extension of the FOGPage Class
	Used to manage users.  Reset passwords.
	Eventually for RBAC setup's as well.

	Useful for:
	Adding, Deleting users.  Resetting passwords for users.
*/
class UserManagementPage extends FOGPage
{
	// Base variables
	var $name = 'User Management';
	var $node = 'users';
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
			_('Username'),
			_('Edit')
		);
		// Row templates
		$this->templates = array(
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit User')),
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><span class="icon icon-edit"></span></a>', $this->node, $this->id, _('Edit User'))
		);
		// Row attributes
		$this->attributes = array(
			array(),
			array('class' => 'c', 'width' => '55'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('All Users');
		// Find data
		$Users = $this->getClass('UserManager')->find();
		// Row data
		foreach ((array)$Users AS $User)
		{
			$this->data[] = array(
				'id'	=> $User->get('id'),
				'name'	=> $User->get('name')
			);
		}
		if($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && count($this->data) > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('USER_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search()
	{
		// Set title
		$this->title = _('Search');
		// Set search form
		//$this->searchFormURL = 'ajax/users.search.php';
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('USER_SEARCH');
		// Output
		$this->render();
	}
	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		$Users = new UserManager();
		// Find data -> Push data
		foreach ($Users->search($keyword,'User') AS $User)
		{
			$this->data[] = array(
				'id'	=> $User->get('id'),
				'name'	=> $User->get('name')
			);
		}
		// Hook
		$this->HookManager->processEvent('USER_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add()
	{
		// Set title
		$this->title = _('New User');
		unset ($this->headerData);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$this->attributes = array(
			array(),
			array(),
		);
		$fields = array(
			_('User Name') => '<input type="text" name="name" value="'.$_REQUEST['name'].'" autocomplete="off" />',
			_('User Password') => '<input type="password" name="password" value="" autocomplete="off" />',
			_('User Password (confirm)') => '<input type="password" name="password_confirm" value="" autocomplete="off" />',
			_('Mobile/Quick Image Access Only?').'&nbsp;'.'<span class="icon icon-help hand" title="'._('Warning - if you tick this box, this user will not be able to log into this FOG Management Console in the future.').'"></span>' => '<input type="checkbox" name="isGuest" autocomplete="off" />',
			'&nbsp;' => '<input type="submit" value="'._('Create User').'" />',
		);
		print "\n\t\t\t<h2>"._('Add new user account').'</h2>';
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		print "\n\t\t\t".'<input type="hidden" name="add" value="1" />';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		$this->HookManager->processEvent('USER_ADD', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		print "\n\t\t\t</form>";
	}
	public function add_post()
	{
		// Hook
		$this->HookManager->processEvent('USER_ADD_POST');
		// POST
		try
		{
			// UserManager
			$UserManager = $this->getClass('UserManager');
			// Error checking
			if ($UserManager->exists($_REQUEST['name']))
				throw new Exception(_('Username already exists'));
			if (!$UserManager->isPasswordValid($_REQUEST['password'], $_REQUEST['password_confirm']))
				throw new Exception(_('Password is invalid'));
			// Create new Object
			$User = new User(array(
				'name'		=> $_REQUEST['name'],
				'type'		=> (isset($_REQUEST['isGuest']) ? true : '0'),
				'password'	=> $_REQUEST['password'],
				'createdBy'	=> $_SESSION['FOG_USERNAME']
			));
			// Save
			if ($User->save())
			{
				// Hook
				$this->HookManager->processEvent('USER_ADD_SUCCESS', array('User' => &$User));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('User created'), $User->get('id'), $User->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('User created'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $User->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('USER_ADD_FAIL', array('User' => &$User));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s add failed: Name: %s, Error: %s', _('User'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit()
	{
		// Find
		$User = new User($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Edit'), $User->get('name'));
		$fields = array(
			_('User Name') => '<input type="text" name="name" value="'.$User->get('name').'" />',
			_('New Password') => '<input type="password" name="password" value="" />',
			_('New Password (confirm)') => '<input type="password" name="password_confirm" value="" />',
			_('Mobile/Quick Image Access Only?').'&nbsp;'.'<span class="icon icon-help hand" title="'._('Warning - if you tick this box, this user     will not be able to log into this FOG Management Console in the future.').'"></span>' => '<input type="checkbox" name="isGuest" '.($User->get('type') == 1 ? 'checked="checked"' : '').' />',
			'&nbsp;' => '<input type="submit" value="'._('Update').'" />',
		);
		unset ($this->headerData);
		$this->templates = array(
			'${field}',
			'${formData}',
		);
		$this->attributes = array(
			array(),
			array(),
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		print "\n\t\t\t".'<input type="hidden" name="update" value="'.$User->get('id').'" />';
		foreach ((array)$fields AS $field => $formData)
		{
			$this->data[] = array(
				'field' => $field,
				'formData' => $formData,
			);
		}
		$this->HookManager->processEvent('USER_EDIT', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		$this->render();
		print "\n\t\t\t</form>";
	}
	public function edit_post()
	{
		// Find
		$User = new User($this->request['id']);
		// Hook
		$this->HookManager->processEvent('USER_EDIT_POST', array('User' => &$User));
		// POST
		try
		{
			// UserManager
			$UserManager = $this->getClass('UserManager');
			// Error checking
			if ($UserManager->exists($_REQUEST['name'], $User->get('id')))
				throw new Exception(_('Username already exists'));
			if ($_REQUEST['password'] && $_REQUEST['password_confirm'])
			{
				if (!$UserManager->isPasswordValid($_REQUEST['password'], $_REQUEST['password_confirm']))
					throw new Exception(_('Password is invalid'));
			}
			// Update User Object
			$User->set('name', $_REQUEST['name'])
				 ->set('type', ($_REQUEST['isGuest'] == 'on' ? '1' : '0'));
			// Set new password if password was passed
			if ($_REQUEST['password'] && $_REQUEST['password_confirm'])
				$User->set('password',	$_REQUEST['password']);
			// Save
			if ($User->save())
			{
				// Hook
				$this->HookManager->processEvent('USER_UPDATE_SUCCESS', array('User' => &$User));
				// Log History event
				$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('User updated'), $User->get('id'), $User->get('name')));
				// Set session message
				$this->FOGCore->setMessage(_('User updated'));
				// Redirect to new entry
				$this->FOGCore->redirect(sprintf('?node=%s&sub=edit&%s=%s', $this->request['node'], $this->id, $User->get('id')));
			}
			else
				throw new Exception('Database update failed');
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('USER_UPDATE_FAIL', array('User' => &$User));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s update failed: Name: %s, Error: %s', _('User'), $_REQUEST['name'], $e->getMessage()));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect to new entry
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function delete()
	{
		// Find
		$User = new User($this->request['id']);
		// Title
		$this->title = sprintf('%s: %s', _('Remove'), $User->get('name'));
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
			_('Please confirm you want to delete').' <b>'.$User->get('name').'</b>' => '<input type="submit" value="${title}" />',
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
		$this->HookManager->processEvent('USER_DELETE', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function delete_post()
	{
		// Find
		$User = new User($this->request['id']);
		// Hook
		$this->HookManager->processEvent('USER_DELETE_POST', array('User' => &$User));
		// POST
		try
		{
			// Error checking
			if (!$User->destroy())
				throw new Exception(_('Failed to destroy User'));
			// Hook
			$this->HookManager->processEvent('USER_DELETE_SUCCESS', array('User' => &$User));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('User deleted'), $User->get('id'), $User->get('name')));
			// Set session message
			$this->FOGCore->setMessage(sprintf('%s: %s', _('User deleted'), $User->get('name')));
			// Redirect
			$this->FOGCore->redirect(sprintf('?node=%s', $this->request['node']));
		}
		catch (Exception $e)
		{
			// Hook
			$this->HookManager->processEvent('USER_DELETE_FAIL', array('User' => &$User));
			// Log History event
			$this->FOGCore->logHistory(sprintf('%s %s: ID: %s, Name: %s', _('User'), _('deleted'), $User->get('id'), $User->get('name')));
			// Set session message
			$this->FOGCore->setMessage($e->getMessage());
			// Redirect
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
