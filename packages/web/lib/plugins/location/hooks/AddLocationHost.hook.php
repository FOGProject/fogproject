<?php
class AddLocationHost extends Hook {
	public $name = 'AddLocationHost';
	public $description = 'Add Location to Hosts';
	public $author = 'Rowlett';
	public $active = true;
	public $node = 'location';	
	public function HostTableHeader($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			if ($_REQUEST[node] == 'host' && $_REQUEST[sub] != 'pending')
				$arguments[headerData][4] = _('Location/Deployed');
		}
	}
	public function HostData($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			if ($_REQUEST[node] == 'host' && $_REQUEST[sub] != 'pending') {
				$arguments[templates][4] = '${location}<br/><small>${deployed}</small>';
				foreach($arguments[data] AS $index => $vals) {
					$Host = $this->getClass(Host,$arguments[data][$index][host_id]);
					$LName = '';
					foreach($this->getClass(LocationAssociationManager)->find(array('hostID' => $Host->get(id))) AS $LA) {
						if ($LA->isValid()) {
							$LName = $this->getClass(Location,$LA->get(locationID))->get(name);
							break;
						}
					}
					$arguments[data][$index][location] = $LName;
				}
			}
		}
	}
	public function HostFields($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			if ($_REQUEST[node] == 'host')
				$arguments[fields] = $this->array_insert_after(_('Host Image'),$arguments[fields],_('Host Location'),'${host_locs}');
		}
	}
	public function HostDataFields($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			if ($_REQUEST[node] == 'host') {
				foreach($arguments[data] AS $index => $vals) {
					if ($_REQUEST[sub] == 'add') $arguments[data][$index] = $this->array_insert_after('host_image',$arguments[data][$index],'host_locs',$this->getClass(LocationManager)->buildSelectBox($_REQUEST[location]));
					if ($_REQUEST[sub] == 'edit') {
						$LID = $_REQUEST[location];
						foreach($this->getClass(LocationAssociationManager)->find(array('hostID' => $arguments[data][$index][host_id])) AS $LA) {
							if ($LA->isValid()) {
								$LID = $this->getClass(Location,$LA->get(locationID))->get(id);
								break;
							}
						}
						$arguments[data][$index] = $this->array_insert_after('host_image',$arguments[data][$index],'host_locs',$this->getClass(LocationManager)->buildSelectBox($LID));
					}
				}
			}
		}
	}
	public function HostAddLocation($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			if ($_REQUEST[node] == 'host') {
				if ($_REQUEST['tab'] == 'host-general') $this->getClass('LocationAssociationManager')->destroy(array('hostID' => $arguments[Host]->get(id)));
				$Location = $this->getClass(Location,$_REQUEST[location]);
				if ($Location->isValid()) {
					$this->getClass(LocationAssociation)
						->set(locationID, $Location->get(id))
						->set(hostID, $arguments[Host]->get(id))
						->save();
				}
			}
		}
	}
	public function HostImport($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			$Location = $this->getClass(Location,$arguments[data][5]);
			if ($Location->isValid()) {
				$this->getClass(LocationAssociation)
					->set(locationID, $arguments[data][5])
					->set(hostID, $arguments[Host]->get(id))
					->save();
			}
		}
	}
	public function HostExport($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			foreach($this->getClass(LocationAssociationManager)->find(array('hostID' => $arguments[Host]->get(id))) AS $LA) {
				$Location = $this->getClass(Location,$LA->get(locationID));
				if ($Location->isValid()) break;
			}
			if ($Location->isValid()) $arguments[report]->addCSVCell($Location->get(id));
		}
	}
	public function HostDestroy($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) $this->getClass('LocationAssociationManager')->destroy(array('hostID' => $arguments[Host]->get(id)));
	}
	public function HostEmailHook($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) {
			foreach($this->getClass(LocationAssociationManager)->find(array('hostID' => $arguments[Host]->get(id))) AS $LA) {
				$Location = $this->getClass(Location,$LA->get(locationID));
				if ($Location->isValid()) break;
			}
			$arguments[email] = $this->array_insert_after("\nSnapin Used: ",$arguments[email],"\nImaged From (Location): ",($Location->isValid() ? $Location->get(name) : ''));
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
