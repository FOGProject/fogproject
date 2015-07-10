<?php
class AddLocationGroup extends Hook {
    public function __construct() {
        parent::__construct();
        $this->name = 'AddLocationGroup';
        $this->description = 'Add menu items to the management page';
        $this->author = 'Rowlett';
        $this->active = true;
        $this->node = 'location';
    }
    public function GroupFields($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'group') {
            $locationID = array_unique($this->getClass(LocationAssociationManager)->find(array('hostID' => $arguments[Group]->get(hosts)),'','','','','','','locationID'));
            $locID = array_shift($locationID);
            $arguments[fields] = $this->array_insert_after(_('Group Product Key'),$arguments[fields],_('Group Location'),$this->getClass(LocationManager)->buildSelectBox($locID));
        }
    }
    public function GroupAddLocation($arguments) {
        if (in_array($this->node,(array)$_SESSION[PluginsInstalled]) && $_REQUEST[node] == 'group' && $_REQUEST[tab] == 'group-general') {
            // Remove Assocs
            $this->getClass(LocationAssociationManager)->destroy(array('hostID' => $arguments[Group]->get(hosts)));
            if ($_REQUEST[location]) {
                foreach($arguments[Group]->get(hosts) AS $i => &$Host) {
                    $this->getClass(LocationAssociation)
                        ->set(locationID,$_REQUEST[location])
                        ->set(hostID,$Host)
                        ->save();
                }
                unset($Host);
            }
        }
    }
}
$AddLocationGroup = new AddLocationGroup();
// Register hooks
$HookManager->register('GROUP_FIELDS', array($AddLocationGroup, 'GroupFields'));
$HookManager->register('GROUP_EDIT_SUCCESS', array($AddLocationGroup, 'GroupAddLocation'));
