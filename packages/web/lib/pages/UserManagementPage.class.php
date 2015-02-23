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
	var $node = 'user';
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
			'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
			_('Username'),
			_('Edit')
		);
		// Row templates
		$this->templates = array(
			'<input type="checkbox" name="user[]" value="${id}" class="toggle-action" checked/>',
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>', $this->node, $this->id, _('Edit User')),
			sprintf('<a href="?node=%s&sub=edit&%s=${id}" title="%s"><i class="icon fa fa-pencil"></i></a>', $this->node, $this->id, _('Edit User'))
		);
		// Row attributes
		$this->attributes = array(
			array('class' => 'c', 'width' => '16'),
			array(),
			array('class' => 'c', 'width' => '55'),
		);
	}
	// Pages
	public function index()
	{
		// Set title
		$this->title = _('All Users');
		if ($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('UserManager')->count() > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
		// Find data
		$Users = $this->getClass('UserManager')->find();
		// Row data
		foreach ((array)$Users AS $User)
		{
			if ($User && $User->isValid())
			{
				$this->data[] = array(
					'id'	=> $User->get('id'),
					'name'	=> $User->get('name')
				);
			}
		}
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
		// Find data -> Push data
		foreach ($this->getClass('UserManager')->search() AS $User)
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
			_('Mobile/Quick Image Access Only?').'&nbsp;'.'<span class="icon icon-help hand" title="'._('Warning - if you tick this box, this user     will not be able to log into this FOG Management Console in the future.').'"></span>' => '<input type="checkbox" name="isGuest" '.($User->get('type') == 1 ? 'checked' : '').' />',
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
		if ((!$_REQUEST['sub'] || in_array($_REQUEST['sub'],array('list','search'))) && !$this->FOGCore->isAJAXRequest() && !$this->FOGCore->isPOSTRequest())
		{
			$this->additional = array(
				"\n\t\t\t".'<div class="c" id="action-boxdel">',
				"\n\t\t\t<p>"._('Delete all selected items').'</p>',
				"\n\t\t\t\t".'<form method="post" action="'.sprintf('?node=%s&sub=deletemulti',$this->node).'">',
				"\n\t\t\t".'<input type="hidden" name="userIDArray" value="" autocomplete="off" />',
				"\n\t\t\t\t\t".'<input type="submit" value="'._('Delete all selected users').'?"/>',
				"\n\t\t\t\t</form>",
				"\n\t\t\t</div>",
			);
		}
		if ($this->additional)
			print implode("\n\t\t\t",(array)$this->additional);
	}
	public function deletemulti()
	{
		$this->title = _('Users to remove');
		unset($this->headerData);
		print "\n\t\t\t".'<div class="confirm-message">';
		print "\n\t\t\t<p>"._('Users to be removed').":</p>";
		$this->attributes = array(
			array(),
		);
		$this->templates = array(
			'<a href="?node=user&sub=edit&id=${user_id}">${user_name}</a>',
		);
		foreach ((array)explode(',',$_REQUEST['userIDArray']) AS $userID)
		{
			$User = new User($userID);
			if ($User && $User->isValid())
			{
				$this->data[] = array(
					'user_id' => $User->get('id'),
					'user_name' => $User->get('name'),
				);
				$_SESSION['delitems']['user'][] = $User->get('id');
				array_push($this->additional,"\n\t\t\t<p>".$User->get('name')."</p>");
			}
		}
		$this->render();
		print "\n\t\t\t\t".'<form method="post" action="?node=user&sub=deleteconf">';
		print "\n\t\t\t\t\t<center>".'<input type="submit" value="'._('Are you sure you wish to remove these users').'?"/></center>';
		print "\n\t\t\t\t</form>";
		print "\n\t\t\t</div>";
	}
	public function deleteconf()
	{
		foreach($_SESSION['delitems']['user'] AS $userid)
		{
			$User = new User($userid);
			if ($User && $User->isValid())
				$User->destroy();
		}
		unset($_SESSION['delitems']);
		$this->FOGCore->setMessage('All selected items have been deleted');
		$this->FOGCore->redirect('?node='.$this->node);
	}
}
