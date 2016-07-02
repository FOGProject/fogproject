<?php
class AddLocationGroup extends Hook {
    public $name = 'AddLocationGroup';
    public $description = 'Add menu items to the management page';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'location';
    public function GroupFields($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'group') return;
        $locationID = self::getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Group']->get('hosts')),'locationID');
        $locID = array_shift($locationID);
        $this->array_insert_after(_('Group Product Key'),$arguments['fields'],_('Group Location'),self::getClass('LocationManager')->buildSelectBox($locID));
    }
    public function GroupAddLocation($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'group') return;
        if (str_replace('_','-',$_REQUEST['tab']) != 'group-general') return;
        self::getClass('LocationAssociationManager')->destroy(array('hostID'=>$arguments['Group']->get('hosts')));
        $insert_fields = array('locationID','hostID');
        $insert_values = array();
        array_walk($arguments['Group']->get('hosts'),function(&$hostID,$index) use (&$insert_values) {
            $insert_values[] = array($_REQUEST['location'],$hostID);
        });
        if (count($insert_values) > 0) self::getClass('LocationAssociationManager')->insert_batch($insert_fields,$insert_values);
    }
}
$AddLocationGroup = new AddLocationGroup();
$HookManager->register('GROUP_FIELDS',array($AddLocationGroup,'GroupFields'));
$HookManager->register('GROUP_EDIT_SUCCESS',array($AddLocationGroup,'GroupAddLocation'));
