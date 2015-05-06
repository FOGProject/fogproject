<?php
class PushbulletManagementPage extends FOGPage {
	/** @function __construct() constructor
	  * @param $name the name to send
	  * @return void
	  */
	public function __construct($name = '') {
		$this->name = 'Pushbullet Management';
		$this->node = 'pushbullet';
		// Call parent constructor
		parent::__construct($this->name);
		$this->menu = array(
			'list' => sprintf($this->foglang['ListAll'],_('Pushbullet Accounts')),
			'add' => _('Link Pushbullet Account'),
		);
		if ($_REQUEST['id']) {
			$this->obj = $this->getClass('Pushbullet',$_REQUEST[id]);
			unset($this->subMenu);
		}
		// Header row
		$this->headerData = array(
			'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
			_('Name'),
			_('Email'),
			_('Delete'),
		);
		// Row templates
		$this->templates = array(
			'<input type="checkbox" name="pushbullet[]" value="${id}" class="toggle-action" checked/>',
			'${name}',
			'${email}',
			sprintf('<a href="?node=%s&sub=delete&id=${id}" title="%s"><i class="fa fa-minus-circle fa-1x icon hand"></i></a>',$this->node,_('Delete')),
		);
		$this->attributes = array(
			array('class' => 'c','width' => 16),
			array('class' => 'l'),
			array('class' => 'l'),
			array('class' => 'r'),
		);
	}
	/** @function index() Default List page.
	  * @return void
	  */
	public function index() {
		// Set title
		$this->title = _('Accounts');
		// Find data
		$users = $this->getClass('PushbulletManager')->find();
		// Row data
		foreach ((array)$this->getClass('PushbulletManager')->find() AS $Token) {
			$this->data[] = array(
				'name'    => $Token->get('name'),
				'email'   => $Token->get('email'),
				'id'      => $Token->get('id'),
			);
		}
		// Hook
		$this->HookManager->event[] = 'PUSHBULLET_DATA';
		$this->HookManager->processEvent('PUSHBULLET_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	/** @function add() Page to add a new account
	  * @return void
	  */
	public function add() {
		$this->title = 'Link New Account';
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
			_('Access Token') => '<input class="smaller" type="text" name="apiToken" />',
			'<input type="hidden" name="add" value="1" />' => '<input class="smaller" type="submit" value="'.('Add').'" />',
		);
		print '<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input) {
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		// Hook
		$this->HookManager->event[] = 'PUSHBULLET_ADD';
		$this->HookManager->processEvent('PUSHBULLET_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	/** @function add_post() To actually perform the action of adding
	  * @ return void
	  */
	public function add_post() {
		try {
			$token = trim($_REQUEST['apiToken']);
			if ($this->getClass('PushbulletManager')->exists(trim($_REQUEST['apiToken']))) throw new Exception('Account already linked');
			if (!$token) throw new Exception('Please enter an access token');
			$userInfo = $this->getClass('PushbulletHandler',$token)->getUserInformation();
			$Bullet = new Pushbullet(array(
				'token' => $token,
				'name'  => $userInfo->name,
				'email' => $userInfo->email,
			));
			if ($Bullet->save()) {
				$this->getClass('PushbulletHandler',$token)->pushNote('', 'FOG', 'Account linked');
				$this->FOGCore->setMessage('Account Added!');
				$this->FOGCore->redirect('?node=pushbullet&sub=list');
			}
		} catch (Exception $e) {
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
