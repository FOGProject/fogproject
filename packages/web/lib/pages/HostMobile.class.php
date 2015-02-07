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
			'<a href="index.php?node=${node}&sub=deploy&id=${host_id}"><i class="fa fa-arrow-down fa-2x"></i></a>',
		);
	}
	public function index()
	{
		$this->search();
	}
	public function deploy()
	{
		try
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
			if (!$Host->getImageMemberFromHostID($_REQUEST['id']))
				throw new Exception($this->foglang['ErrorImageAssoc']);
			if (!$Host->createImagePackage('1', "Mobile: ".$ImageMembers->getHost()->get('name'), false, false, true, false, $_SESSION['FOG_USERNAME']))
				throw new Exception($this->foglang['FailedTask']);
			$this->data[] = array(
				$this->foglang['TaskStarted'],
			);
		}
		catch (Exception $e)
		{
			$this->data[] = array(
				$e->getMessage(),
			);
		}
		$this->render();
		$this->FOGCore->redirect('?node=taskss');
	}
	public function search_post()
	{
		foreach($this->getClass('HostManager')->search() AS $Host)
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
