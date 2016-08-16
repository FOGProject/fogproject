<?php
class AddLocationGroup extends Hook
{
    public $name = 'AddLocationGroup';
    public $description = 'Add menu items to the management page';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'location';
    public function GroupSideMenu($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'group') {
            return;
        }
        $link = $arguments['linkformat'];
        $this->array_insert_after("$link#group-image", $arguments['submenu'], "$link#group-location", _('Location Association'));
    }
    public function GroupFields($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'group') {
            return;
        }
        $locationID = self::getSubObjectIDs('LocationAssociation', array('hostID'=>$arguments['Group']->get('hosts')), 'locationID');
        $locID = array_shift($locationID);
        echo '<!-- Location --><div id="group-location">';
        printf('<h2>%s: %s</h2>', _('Location Association for'), $arguments['Group']->get('name'));
        printf('<form method="post" action="%s&tab=group-location">', $arguments['formAction']);
        unset($arguments['headerData']);
        $arguments['attributes'] = array(
            array(),
            array(),
        );
        $arguments['templates'] = array(
            '${field}',
            '${input}',
        );
        $arguments['data'][] = array(
            'field' => self::getClass('LocationManager')->buildSelectBox($locID),
            'input' => sprintf('<input type="submit" value="%s"/>', _('Update Locations')),
        );
        $arguments['render']->render();
        echo '</form></div>';
    }
    public function GroupAddLocation($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'group') {
            return;
        }
        if ($_REQUEST['tab'] != 'group-location') {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(array('hostID'=>$arguments['Group']->get('hosts')));
        $insert_fields = array('locationID','hostID');
        $insert_values = array();
        array_walk($arguments['Group']->get('hosts'), function (&$hostID, $index) use (&$insert_values) {
            $insert_values[] = array($_REQUEST['location'], $hostID);
        });
        if (count($insert_values) > 0) {
            self::getClass('LocationAssociationManager')->insert_batch($insert_fields, $insert_values);
        }
    }
}
$AddLocationGroup = new AddLocationGroup();
$HookManager->register('GROUP_GENERAL_EXTRA', array($AddLocationGroup, 'GroupFields'));
$HookManager->register('SUB_MENULINK_DATA', array($AddLocationGroup, 'GroupSideMenu'));
$HookManager->register('GROUP_EDIT_SUCCESS', array($AddLocationGroup, 'GroupAddLocation'));
