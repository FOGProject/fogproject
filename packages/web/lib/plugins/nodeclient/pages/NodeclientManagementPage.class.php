<?php
class NodeclientManagementPage extends FOGPage {
	public $node = 'nodeclient';
	public function __construct($name = '') {
		$this->name = 'Node Client Configuration';
		// Call parent constructor
		parent::__construct($name);
		if ($_REQUEST['id']) {
			$this->obj = $this->getClass('NodeJS',$_REQUEST['id']);
			$this->subMenu = array(
					$this->linkformat = $this->foglang[General],
					$this->delformat = $this->foglang[Delete],
					);
		}
		// Header row
		$this->headerData = array(
				'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
				_('Name'),
				_('Port'),
				_('IP/Hostname'),
				);
		// Row templates
		$this->templates = array(
				'<input type="checkbox" name="nodeclient[]" value="${id}" class="toggle-action" checked/>',
				sprintf('<a href="?node=%s&sub=edit&id=${id}" title="%s">${name}</a>',$this->node,_('Edit')),
				'${port}',
				'${ip}',
				);
		$this->attributes = array(
				array('class' => 'l'),
				array(),
				array('class' => 'r'),
				);
	}
	// Pages
	public function index() {
		// Set title
		$this->title = _('All Node Servers');
		if ($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('NodeJSManager')->count() > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
		// Row data
		foreach ((array)$this->getClass('NodeJSManager')->find() AS $NodeConf) {
			if ($NodeConf && $NodeConf->isValid()) {
				$this->data[] = array(
						'id'	=> $NodeConf->get('id'),
						'name'  => $NodeConf->get('name'),
						'ip' => $NodeConf->get('ip'),
						'port' => $NodeConf->get('port'),
						);
			}
		}
		// Hook
		$this->HookManager->processEvent('NODECONF_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search() {
		// Set title
		$this->title = 'Search';
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node='.$this->node.'&sub=search';
		// Hook
		$this->HookManager->processEvent('NODECONF_SEARCH');
		// Output
		$this->render();
	}
	public function search_post() {
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// To assist with finding wol broadcasts.
		$where = array(
				'id'	=> $keyword,
				'name'  => $keyword,
				'ip' => $keyword,
				'port' => $keyword,
			      );
		// Find data -> Push data
		foreach ((array)$this->getClass('NodeJSManager')->find($where,'OR') AS $NodeConf) {
			if ($NodeConf && $NodeConf->isValid()) {
				$this->data[] = array(
						'id'	=> $NodeConf->get('id'),
						'name'  => $NodeConf->get('name'),
						'ip' => $NodeConf->get('ip'),
						'port' => $NodeConf->get('port'),
						);
			}
		}
		// Hook
		$this->HookManager->processEvent('NODECONF_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add() {
		$this->title = 'New Node Configuration';
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
				_('Node Name') => '<input class="smaller" type="text" name="name" value="${name}"/>',
				_('Node IP') => '<input class="smaller" type="text" name="nodeip" value="${ip}"/>',
				_('Node Port') => '<input class="smaller" text="text" name="portnum" value="${port}"/>',
				'<input type="hidden" name="add" value="1" />' => '<input class="smaller" type="submit" value="'.('Add').'" />',
			       );
		print '<form method="post" action="'.$this->formAction.'">';
		foreach((array)$fields AS $field => $input) {
			$this->data[] = array(
					'field' => $field,
					'input' => $input,
					'name'  => $_REQUEST['name'],
					'ip' => $_REQUEST['nodeip'],
					'port' => $_REQUEST['portnum'],
					);
		}
		// Hook
		$this->HookManager->processEvent('NODECONF_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function add_post() {
		try {
			$name = trim($_REQUEST['name']);
			$ip = trim($_REQUEST['nodeip']);
			$port = trim($_REQUEST['portnum']);
			if ($this->getClass('NodeJSManager')->exists(trim($_REQUEST['name'])))
				throw new Exception('Node name already Exists, please try again.');
			if (!$name)
				throw new Exception('Please enter a name for this node server');
			if (empty($ip))
				throw new Exception('Please enter the node FQDN or IP address.');
			if (!$port || !is_numeric($port) || $port < 1 || $port > 65535)
				throw new Exception('Please enter a valid port number between 1 and 65535');
			$NodeConf = new NodeJS(array(
						'name' => $name,
						'ip' => $ip,
						'port' => $port,
						));
			if ($NodeConf->save()) {
				$this->FOGCore->setMessage('Node Added, editing!');
				$this->FOGCore->redirect('?node=nodeclient&sub=edit&id='.$NodeConf->get('id'));
			}
		} catch (Exception $e) {
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit() {
		// Find
		$NodeConf = $this->obj;
		// Title
		$this->title = sprintf('%s: %s', 'Edit', $NodeConf->get('name'));
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
				_('Node Name') => '<input class="smaller" type="text" name="name" value="${name}"/>',
				_('Node IP') => '<input class="smaller" type="text" name="nodeip" value="${ip}"/>',
				_('Node Port') => '<input class="smaller" text="text" name="portnum" value="${port}"/>',
				'<input type="hidden" name="update" value="1" />' => '<input class="smaller" type="submit" value="'._('Update').'" />',
			       );
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$NodeConf->get('id').'">';
		foreach ((array)$fields AS $field => $input) {
			$this->data[] = array(
					'field' => $field,
					'input' => $input,
					'name'  => $NodeConf->get('name'),
					'ip' => $NodeConf->get('ip'),
					'port' => $NodeConf->get('port'),
					);
		}
		// Hook
		$this->HookManager->processEvent('NODECONF_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function edit_post() {
		try {
			$NodeConf = $this->obj;
			$name = trim($_REQUEST['name']);
			$ip = trim($_REQUEST['nodeip']);
			$port = trim($_REQUEST['portnum']);
			if (!$name)
				throw new Exception('Please enter a name for this node server');
			if (empty($ip))
				throw new Exception('Please enter the node FQDN or IP address.');
			if (!is_numeric($port) || $port < 1 || $port > 65535)
				throw new Exception('Please enter a valid port number between 1 and 65535');
			$this->HookManager->processEvent('NODECONF_EDIT_POST', array('NodeConf'=> &$NodeConf));
			if ($_REQUEST['name'] != $NodeConf->get('name') && $NodeConf->exists($_REQUEST['name']))
				throw new Exception('Node name already Exists, please try again.');
			if ($_REQUEST['update']) {
				if ($ip != $NodeConf->get('ip')) $NodeConf->set('ip', $ip);
				if ($name != $NodeConf->get('name')) $NodeConf->set('name',$name);
				if ($port != $NodeConf->get('port')) $NodeConf->set('port',$port);
				if ($NodeConf->save()) {
					$this->FOGCore->setMessage('Node Updated');
					$this->FOGCore->redirect('?node=nodeclient&sub=edit&id='.$NodeConf->get('id'));
				}
			}
		} catch (Exception $e) {
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
