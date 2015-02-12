<?php
class AddLocationHost extends Hook
{
	var $name = 'AddLocationHost';
	var $description = 'Add Location to Hosts';
	var $author = 'Rowlett';
	var $active = true;
    var $node = 'location';	
	public function HostTableHeader($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'host' && $_REQUEST['sub'] != 'pending')
				$arguments['headerData'][4] = _('Location/Deployed');
		}
	}

	public function HostData($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'host' && $_REQUEST['sub'] != 'pending')
			{
				$arguments['templates'][4] = '${location}<br/><small>${deployed}</small>';
				foreach($arguments['data'] AS $index => $vals)
				{
					$Host = new Host($arguments['data'][$index]['host_id']);
					if ($Host && $Host->isValid())
					{
						$LA = current((array)$this->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
						$Location = ($LA ? new Location($LA->get('locationID')) : '');
						$arguments['data'][$index]['location'] = ($Location && $Location->isValid() ? $Location->get('name') : '');
					}
				}
			}
		}
	}
	public function HostFields($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'host')
				$arguments['fields'] = $this->array_insert_after(_('Host Image'),$arguments['fields'],_('Host Location'),'${host_locs}');
		}
	}
	public function HostDataFields($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'host')
			{
				foreach($arguments['data'] AS $index => $vals)
				{
					if ($_REQUEST['sub'] == 'add')
						$arguments['data'][$index] = $this->array_insert_after('host_image',$arguments['data'][$index],'host_locs',$this->getClass('LocationManager')->buildSelectBox($_REQUEST['location']));
					if ($_REQUEST['sub'] == 'edit')
					{
						$LA = current((array)$this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['data'][$index]['host_id'])));
						$Location = $LA && $LA->isValid() ? new Location($LA->get('locationID')) : '';
						$arguments['data'][$index] = $this->array_insert_after('host_image',$arguments['data'][$index],'host_locs',$this->getClass('LocationManager')->buildSelectBox($Location && $Location->isValid() ? $Location->get('id') : $_REQUEST['location']));
					}
				}
			}
		}
	}
	public function HostAddLocation($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'host')
			{
				if ($_REQUEST['tab'] == 'host-general' && !$_REQUEST['location'])
					$this->getClass('LocationAssociationManager')->destroy(array('hostID' => $arguments['Host']->get('id')));
				$Location = new Location($_REQUEST['location']);
				if ($Location && $Location->isValid())
				{
					$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id'))));
					if (!$LA || !$LA->isValid())
					{
						$LA = new LocationAssociation(array(
							'locationID' => $_REQUEST['location'],
							'hostID' => $arguments['Host']->get('id'),
						));
					}
					else
						$LA->set('locationID',$_REQUEST['location']);
					$LA->save();
				}
			}
		}
	}
	public function HostImport($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($arguments['data'][5])
			{
				$LA = new LocationAssociation(array(
					'locationID' => $arguments['data'][5],
					'hostID' => $arguments['Host']->get('id'),
				));
				$LA->save();
			}
		}
	}
	public function HostExport($arguments)
	{
		if ($_SESSION[$this->node])
		{
			$LA = current((array)$this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id'))));
			if ($LA && $LA->isValid())
				$Location = new Location($LA->get('locationID'));
			if ($Location && $Location->isValid())
				$arguments['report']->addCSVCell($Location->get('id'));
		}
	}
	public function HostDestroy($arguments)
	{
		if ($_SESSION[$this->node])
			$this->getClass('LocationAssociationManager')->destroy(array('hostID' => $arguments['Host']->get('id')));
	}
	public function HostEmailHook($arguments)
	{
		if ($_SESSION[$this->node])
		{
			$LA = current((array)$this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id'))));
			if ($LA && $LA->isValid())
				$Location = new Location($LA->get('locationID'));
			$arguments['email'] = $this->array_insert_after("\nSnapin Used: ",$arguments['email'],"\nImaged From (Location): ",($Location && $Location->isValid() ? $Location->get('name') : ''));
		}
	}
}
$AddLocationHost = new AddLocationHost();
// Register hooks
$HookManager->register('HOST_HEADER_DATA', array($AddLocationHost, 'HostTableHeader'));
$HookManager->register('HOST_DATA', array($AddLocationHost, 'HostData'));
$HookManager->register('HOST_FIELDS', array($AddLocationHost, 'HostFields'));
$HookManager->register('HOST_ADD_GEN', array($AddLocationHost, 'HostDataFields'));
$HookManager->register('HOST_ADD_SUCCESS', array($AddLocationHost, 'HostAddLocation'));
$HookManager->register('HOST_EDIT_GEN', array($AddLocationHost, 'HostDataFields'));
$HookManager->register('HOST_EDIT_SUCCESS', array($AddLocationHost, 'HostAddLocation'));
$HookManager->register('HOST_IMPORT', array($AddLocationHost, 'HostImport'));
$HookManager->register('HOST_EXPORT_REPORT', array($AddLocationHost, 'HostExport'));
$HookManager->register('DESTROY_HOST', array($AddLocationHost, 'HostDestroy'));
$HookManager->register('EMAIL_ITEMS', array($AddLocationHost, 'HostEmailHook'));
