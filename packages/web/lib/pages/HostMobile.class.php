<?php
/** Class Name: HostMobile
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This is the hosts page for the Mobile side of FOG.
	It's just a minimal page that gets displayed.
	The user can search for hosts and setup deploy tasks.

	Useful for:
	Mobile device viewing.
*/
class HostMobile extends FOGPage
{
	var $name = 'Host Management';
	var $node = 'hosts';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
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
			'<a href="index.php?node=${node}&sub=deploy&id=${host_id}"><img class="task" src="./images/send.png" /></a>',
		);
	}

	public function index()
	{
		$this->search();
	}

	public function deploy()
	{
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
		$ImageMembers = $Host->getImageMemberFromHostID($_REQUEST['id']);
		if ($ImageMembers)
		{
			if ($Host->createImagePackage('1', "Mobile: ".$ImageMembers->getHost()->get('name'), false, false, true, false, $_SESSION['FOG_USERNAME']))
			{
				$this->data[] = array(
					$this->foglang['TaskStarted'],
				);
			}
			else
			{
				$this->data[] = array(
					$this->foglang['FailedTask'],
				);
			}
		}
		else
		{
			$this->data[] = array(
				$this->foglang['ErrorImageAssoc'],
			);
		}
		$this->render();
		$this->FOGCore->redirect('?node=taskss');
	}

	public function search()
	{
		// Set title
		$this->title = $this->foglang['HostSearch'];
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Output
		$this->render();
	}

	public function search_post()
	{
		// Variables
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['host-search']) . '%');
		// Get All hosts class for matching keyword
		$Hosts = new HostManager();
		foreach($Hosts->search($keyword) AS $Host)
		{
			if ($Host && $Host->isValid())
			{
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
