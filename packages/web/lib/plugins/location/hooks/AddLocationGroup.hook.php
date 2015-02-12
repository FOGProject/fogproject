<?php
class AddLocationGroup extends Hook
{
	var $name = 'AddLocationGroup';
	var $description = 'Add Location to Groups';
	var $author = 'Rowlett';
	var $active = true;
    var $node = 'location';	
	public function GroupFields($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'group')
			{
				foreach($arguments['Group']->get('hosts') AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
						$LA ? $locationID[] = $LA->get('locationID') : null;
					}
				}
				$locationIDMult = (is_array($locationID) ? array_unique($locationID) : $locationID);
				if (count($locationIDMult) == 1)
					$locationMatchID = $LA && $LA->isValid() ? $LA->get('locationID') : null;
				$arguments['fields'] = $this->array_insert_after(_('Group Product Key'),$arguments['fields'],_('Group Location'),$this->getClass('LocationManager')->buildSelectBox($locationMatchID));
			}
		}
	}
	public function GroupAddLocation($arguments)
	{
		if ($_SESSION[$this->node])
		{
			if ($_REQUEST['node'] == 'group')
			{
				foreach($arguments['Group']->get('hosts') AS $Host)
				{
					if ($Host && $Host->isValid())
					{
						if ($_REQUEST['tab'] == 'group-general' && !$_REQUEST['location'])
							$this->getClass('LocationAssociationManager')->destroy(array('hostID' => $Host->get('id')));
						$Location = new Location($_REQUEST['location']);
						if ($Location && $Location->isValid())
						{
							$LA = current((array)$this->getClass('LocationAssociationManager')->find(array('hostID' => $Host->get('id'))));
							if (!$LA || !$LA->isValid())
							{
								$LA = new LocationAssociation(array(
									'locationID' => $_REQUEST['location'],
									'hostID' => $Host->get('id'),
								));
							}
							else
								$LA->set('locationID',$_REQUEST['location']);
							$LA->save();
						}
						else
						{
							if ($LA && $LA->isValid())
								$LA->destroy();
						}
					}
				}
			}
		}
	}
}
$AddLocationGroup = new AddLocationGroup();
// Register hooks
$HookManager->register('GROUP_FIELDS', array($AddLocationGroup, 'GroupFields'));
$HookManager->register('GROUP_EDIT_SUCCESS', array($AddLocationGroup, 'GroupAddLocation'));
