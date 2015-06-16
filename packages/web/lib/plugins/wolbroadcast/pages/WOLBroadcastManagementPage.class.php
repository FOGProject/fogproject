<?php
class WOLBroadcastManagementPage extends FOGPage {
	public $node = 'wolbroadcast';
	// __construct
	public function __construct($name = '') {
		$this->name = 'WOL Broadcast Management';
		// Call parent constructor
		parent::__construct($this->name);
		if ($_REQUEST['id']) {
			$this->obj = $this->getClass('Wolbroadcast',$_REQUEST[id]);
			$this->subMenu = array(
					$this->linkformat => $this->foglang[General],
					$this->delformat => $this->foglang[Delete],
					);
			$this->notes = array(
					_('Broadcast Name') => $this->obj->get('name'),
					_('IP Address') => $this->obj->get('broadcast'),
					);
		}
		// Header row
		$this->headerData = array(
				'<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
				'Broadcast Name',
				'Broadcast IP',
				);
		// Row templates
		$this->templates = array(
				'<input type="checkbox" name="wolbroadcast[]" value="${id}" class="toggle-action" checked/>',
				'<a href="?node=wolbroadcast&sub=edit&id=${id}" title="Edit">${name}</a>',
				'${wol_ip}',
				);
		$this->attributes = array(
				array('class' => 'c', 'width' => '16'),
				array('class' => 'l'),
				array('class' => 'r'),
				);
	}
	// Pages
	public function index() {
		// Set title
		$this->title = _('All Broadcasts');
		if ($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('WolbroadcastManager')->count() > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
			$this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
		// Find data
		$Broadcasts = $this->getClass('WolbroadcastManager')->find();
		// Row data
		foreach ((array)$Broadcasts AS $Broadcast) {
			if ($Broadcast && $Broadcast->isValid()) {
				$this->data[] = array(
						'id'	=> $Broadcast->get('id'),
						'name'  => $Broadcast->get('name'),
						'wol_ip' => $Broadcast->get('broadcast'),
						);
			}
		}
		// Hook
		$this->HookManager->processEvent('BROADCAST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function search() {
		// Set title
		$this->title = 'Search';
		// Set search form
		$this->searchFormURL = $_SERVER['PHP_SELF'].'?node=wolbroadcast&sub=search';
		// Hook
		$this->HookManager->processEvent('BROADCAST_SEARCH');
		// Output
		$this->render();
	}
	public function search_post() {
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
		// To assist with finding wol broadcasts.
		$where = array(
				'id'		=> $keyword,
				'name'		=> $keyword,
				'broadcast' => $keyword,
			      );
		// Find data -> Push data
		foreach ((array)$this->getClass('WolbroadcastManager')->find($where,'OR') AS $Broadcast) {
			if ($Broadcast && $Broadcast->isValid()) {
				$this->data[] = array(
						'id'		=> $Broadcast->get('id'),
						'name'		=> $Broadcast->get('name'),
						'wol_ip' => $Broadcast->get('broadcast'),
						);
			}
		}
		// Hook
		$this->HookManager->processEvent('BROADCAST_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
	}
	public function add() {
		$this->title = 'New Broadcast Address';
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
				_('Broadcast Name') => '<input class="smaller" type="text" name="name" />',
				_('Broadcast IP') => '<input class="smaller" type="text" name="broadcast" />',
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
		$this->HookManager->processEvent('BROADCAST_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function add_post() {
		try {
			$name = trim($_REQUEST['name']);
			$ip = trim($_REQUEST['broadcast']);
			if ($this->getClass('WolbroadcastManager')->exists(trim($_REQUEST['name'])))
				throw new Exception('Broacast name already Exists, please try again.');
			if (!$name)
				throw new Exception('Please enter a name for this address.');
			if (empty($ip))
				throw new Exception('Please enter the broadcast address.');
			if (strlen($ip) > 15 || !filter_var($ip,FILTER_VALIDATE_IP))
				throw new Exception('Please enter a valid ip');
			$WOLBroadcast = new Wolbroadcast(array(
						'name' => $name,
						'broadcast' => $ip,
						));
			if ($WOLBroadcast->save()) {
				$this->FOGCore->setMessage('Broadcast Added, editing!');
				$this->FOGCore->redirect('?node=wolbroadcast&sub=edit&id='.$WOLBroadcast->get('id'));
			}
		} catch (Exception $e) {
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
	public function edit() {
		// Find
		$WOLBroadcast = $this->obj;
		// Title
		$this->title = sprintf('%s: %s', 'Edit', $WOLBroadcast->get('name'));
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
				_('Broadcast Name') => '<input class="smaller" type="text" name="name" value="${broadcast_name}" />',
				_('Broadcast Address') => '<input class="smaller" type="text" name="broadcast" value="${broadcast_ip}" />',
				'<input type="hidden" name="update" value="1" />' => '<input type="submit" class="smaller" value="'._('Update').'" />',
			       );
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$WOLBroadcast->get('id').'">';
		foreach ((array)$fields AS $field => $input) {
			$this->data[] = array(
					'field' => $field,
					'input' => $input,
					'broadcast_name' => $WOLBroadcast->get('name'),
					'broadcast_ip' => $WOLBroadcast->get('broadcast'),
					);
		}
		// Hook
		$this->HookManager->processEvent('BROADCAST_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	public function edit_post() {
		$WOLBroadcast = $this->obj;
		$WOLBroadcastMan = new WolbroadcastManager();
		$this->HookManager->processEvent('BROADCAST_EDIT_POST', array('Broadcast'=> &$WOLBroadcast));
		try {
			$name = trim($_REQUEST['name']);
			$ip = trim($_REQUEST['broadcast']);
			if (!$name)
				throw new Exception('You need to have a name for the broadcast address.');
			if (!$ip || !filter_var($ip,FILTER_VALIDATE_IP))
				throw new Exception('Please enter a valid IP address');
			if ($_REQUEST['name'] != $WOLBroadcast->get('name') && $WOLBroadcastMan->exists($_REQUEST['name']))
				throw new Exception('A broadcast with that name already exists.');
			if ($_REQUEST['update']) {
				if ($ip != $WOLBroadcast->get('broadcast'))
					$WOLBroadcast->set('broadcast', $ip);
				if ($name != $WOLBroadcast->get('name'))
					$WOLBroadcast->set('name',$name);
				if ($WOLBroadcast->save()) {
					$this->FOGCore->setMessage('Broadcast Updated');
					$this->FOGCore->redirect('?node=wolbroadcast&sub=edit&id='.$WOLBroadcast->get('id'));
				}
			}
		} catch (Exception $e) {
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
