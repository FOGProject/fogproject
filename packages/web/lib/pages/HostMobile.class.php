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
			_('ID'),
			_('Name'),
			_('MAC'),
			_('Image'),
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
		$this->title = _('Quick Image Menu');
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
					_('Task Started'),
				);
			}
			else
			{
				$this->data[] = array(
					_('Task Failed'),
				);
			}
		}
		else
		{
			$this->data[] = array(
				_('Error: Is an image associated with the computer?'),
			);
		}
		$this->render();
		$this->FOGCore->redirect('?node=taskss');
	}

	public function search()
	{
		// Set title
		$this->title = _('Host Search');
		// Set search form
		$this->searchFormURL = sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node);
		// Hook
		$this->HookManager->processEvent('HOST_MOBILE_SEARCH');
		// Output
		$this->render();
	}

	public function search_post()
	{
		$keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $_REQUEST['host-search']) . '%');
		foreach((array)$this->FOGCore->getClass('HostManager')->search($keyword) AS $Host)
		{
			$this->data[] = array(
				'host_id' => $Host->get('id'),
				'host_name' => $Host->get('name'),
				'host_mac' => $Host->get('mac'),
				'node' => $this->node
			);
		}
		// Hook
		$this->HookManager->processEvent('HOST_MOBILE_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Ouput
		$this->render();
	}
}
