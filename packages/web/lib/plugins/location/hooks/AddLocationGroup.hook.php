<?php
class AddLocationGroup extends Hook
{
	var $name = 'AddLocationGroup';
	var $description = 'Add Location to Groups';
	var $author = 'Rowlett';
	var $active = true;
	var $node = 'location';
	public function GroupFields($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			if ($_REQUEST['node'] == 'group') {
				foreach($arguments['Group']->get('hosts') AS $Host) {
					if ($Host && $Host->isValid()) {
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
	public function GroupAddLocation($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled']) && $_REQUEST['node'] == 'group') {
			foreach($arguments['Group']->get('hosts') AS $Host) {
				if ($Host && $Host->isValid() && $_REQUEST['tab'] == 'group-general') {
					$this->getClass('LocationAssociationManager')->destroy(array('hostID' => $Host->get('id')));
					$Location = $this->getClass('Location',$_REQUEST[location]);
					if ($Location->isValid()) {
						$this->getClass('LocationAssociation')
							->set('locationID', $Location->get(id))
							->set('hostID', $Host->get(id))
							->save();
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
