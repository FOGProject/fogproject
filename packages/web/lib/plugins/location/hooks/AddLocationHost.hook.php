<?php
class AddLocationHost extends Hook {
    public $name = 'AddLocationHost';
    public $description = 'Add Location to Hosts';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'location';
    public function __construct() {
        parent::__construct();
        $this->name = 'AddLocationHost';
        $this->description = 'Add Location to Hosts';
        $this->author = 'Rowlett';
        $this->active = true;
        $this->node = 'location';
    }
    public function HostTableHeader($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'host' && $_REQUEST[sub] != 'pending') $arguments[headerData][4] = _('Location/Deployed');
    }
    public function HostData($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'host' && $_REQUEST[sub] != 'pending') {
            $arguments[templates][4] = '${location}<br/><small>${deployed}</small>';
            foreach($arguments[data] AS $index => &$vals) {
                $LocAssocs = $this->getClass(LocationAssociationManager)->find(array(hostID=>$arguments[data][$index][host_id]),'','','','','','','locationID');
                $locID = array_shift($LocAssocs);
                $arguments[data][$index][location] = $this->getClass(Location,$locID)->get(name);
                unset($LocAssocs);
            }
            unset($vals);
        }
    }
    public function HostFields($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'host') {
            if ($_REQUEST[sub] == 'edit') $locationID = $this->getClass(LocationAssociationManager)->find(array('hostID' => $arguments[Host]->get(id)),'','','','','','','locationID');
            $locID = array_shift($locationID);
            $arguments[fields] = $this->array_insert_after(_('Host Product Key'),$arguments[fields],_('Host Location'),$this->getClass(LocationManager)->buildSelectBox($locID));
        }
    }
    public function HostAddLocation($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'host' && $_REQUEST[tab] == 'host-general') {
            // Remove Assocs
            $this->getClass(LocationAssociationManager)->destroy(array('hostID' => $arguments[Host]->get(id)));
            if ($_REQUEST[location]) {
                $this->getClass(LocationAssociation)
                    ->set(locationID,$_REQUEST[location])
                    ->set(hostID,$arguments[Host]->get(id))
                    ->save();
            }
        }
    }
    public function HostImport($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled])) {
            $Location = $this->getClass(Location,$arguments[data][5]);
            if ($Location->isValid()) {
                $this->getClass(LocationAssociation)
                    ->set(locationID,$arguments[data][5])
                    ->set(hostID,$arguments[Host]->get(id))
                    ->save();
            }
        }
    }
    public function HostExport($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled])) {
            $LocAssocs = $this->getClass(LocationAssociationManager)->find(array(hostID=>$arguments[Host]->get(id)),'','','','','','','locationID');
            $locID = array_shift($LocAssocs);
            $arguments[report]->addCSVCell($locID > 0 ? $locID : null);
        }
    }
    public function HostDestroy($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled])) $this->getClass(LocationAssociationManager)->destroy(array(hostID=>$arguments[Host]->get(id)));
    }
    public function HostEmailHook($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled])) {
            $LocAssocs = $this->getClass(LocationAssociationManager)->find(array(hostID=>$arguments[Host]->get(id)),'','','','','','','locationID');
            $locID = array_shift($LocAssocs);
            $arguments[email] = $this->array_insert_after("\nSnapin Used: ",$arguments[email],"\nImaged From (Location): ",$this->getClass(Location,$locID)->get(name));
        }
    }
}
$AddLocationHost = new AddLocationHost();
// Register hooks
$HookManager->register('HOST_HEADER_DATA', array($AddLocationHost, 'HostTableHeader'));
$HookManager->register('HOST_DATA', array($AddLocationHost, 'HostData'));
$HookManager->register('HOST_FIELDS', array($AddLocationHost, 'HostFields'));
$HookManager->register('HOST_ADD_SUCCESS', array($AddLocationHost, 'HostAddLocation'));
$HookManager->register('HOST_EDIT_SUCCESS', array($AddLocationHost, 'HostAddLocation'));
$HookManager->register('HOST_IMPORT', array($AddLocationHost, 'HostImport'));
$HookManager->register('HOST_EXPORT_REPORT', array($AddLocationHost, 'HostExport'));
$HookManager->register('DESTROY_HOST', array($AddLocationHost, 'HostDestroy'));
$HookManager->register('EMAIL_ITEMS', array($AddLocationHost, 'HostEmailHook'));
