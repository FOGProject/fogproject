<?php
class HostMobile extends FOGPage {
	public function __construct($name = '') {
		$this->name = 'Host Management';
		$this->node = 'hosts';
		// Call parent constructor
		parent::__construct($this->name);
		$this->menu = array();
		$this->subMenu = array();
		$this->notes = array();
		// Header Data
		$this->headerData = array(
			$this->foglang['ID'],
			$this->foglang['Name'],
			$this->foglang['MAC'],
			$this->foglang['Image'],
		);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${host_id}',
			'${host_name}',
			'${host_mac}',
			'<a href="index.php?node=${node}&sub=deploy&id=${host_id}"><i class="fa fa-arrow-down fa-2x"></i></a>',
		);
	}
	public function index() {
		$this->search();
	}
	public function deploy() {
		try {
			$Host = new Host($_REQUEST['id']);
			// Title
			$this->title = $this->foglang['QuickImageMenu'];
			unset($this->headerData);
			$this->attributes = array(
				array(),
			);
			$this->templates = array(
				'${task_started}',
			);
			if (!$Host->getImageMemberFromHostID($_REQUEST['id']))
				throw new Exception($this->foglang['ErrorImageAssoc']);
			if (!$Host->createImagePackage('1', "Mobile: ".$ImageMembers->getHost()->get('name'), false, false, true, false, $_SESSION['FOG_USERNAME']))
				throw new Exception($this->foglang['FailedTask']);
			$this->data[] = array(
				$this->foglang['TaskStarted'],
			);
		} catch (Exception $e) {
			$this->data[] = array(
				$e->getMessage(),
			);
		}
		$this->render();
		$this->FOGCore->redirect('?node=tasks');
	}
	public function search_post() {
		foreach($this->getClass('HostManager')->search() AS $Host) {
			if ($Host && $Host->isValid()) {
				$this->data[] = array(
					'host_id' => $Host->get('id'),
					'host_name' => $Host->get('name'),
					'host_mac' => $Host->get('mac'),
					'node' => $this->node
				);
			}
		}
		// Ouput
		$this->render();
	}
}
