<?php
class AddLocationHost extends Hook
{
    public $name = 'AddLocationHost';
    public $description = 'Add Location to Hosts';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'location';
    public function HostTableHeader($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        if ($_REQUEST['sub'] == 'pending') {
            return;
        }
        $arguments['headerData'][4] = _('Location/Deployed');
    }
    public function HostData($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        if ($_REQUEST['sub'] == 'pending') {
            return;
        }
        $arguments['templates'][4] = '${location}<br/><small>${deployed}</small>';
        foreach ((array)$arguments['data'] as $index => &$vals) {
            $locationID = self::getSubObjectIDs('LocationAssociation', array('hostID'=>$arguments['data'][$index]['id']), 'locationID');
            $locID = array_shift($locationID);
            $arguments['data'][$index]['location'] = self::getClass('Location', $locID)->get('name');
            unset($vals);
        }
    }
    public function HostFields($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        $locationID = self::getSubObjectIDs('LocationAssociation', array('hostID'=>$arguments['Host']->get('id')), 'locationID');
        $locID = array_shift($locationID);
        $this->array_insert_after(_('Host Product Key'), $arguments['fields'], _('Host Location'), self::getClass('LocationManager')->buildSelectBox($locID));
    }
    public function HostAddLocation($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        if (!in_array($_REQUEST['sub'], array('add', 'add_post', 'edit', 'edit_post'))) {
            return;
        }
        if (str_replace('_', '-', $_REQUEST['tab']) != 'host-general') {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(array('hostID'=>$arguments['Host']->get('id')));
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->set('locationID', $_REQUEST['location'])
            ->save();
    }
    public function HostImport($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->set('locationID', $arguments['data'][5])
            ->save();
    }
    public function HostExport($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $locationID = self::getSubObjectIDs('LocationAssociation', array('hostID'=>$arguments['Host']->get('id')), 'locationID');
        $locID = array_shift($locationID);
        $arguments['report']->addCSVCell($locID > 0 ? $locID : null);
    }
    public function HostDestroy($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(array('hostID'=>$arguments['Host']->get('id')));
    }
    public function HostEmailHook($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $locationID = self::getSubObjectIDs('LocationAssociation', array('hostID'=>$arguments['Host']->get('id')), 'locationID');
        $locID = array_shift($locationID);
        if (!self::getClass('Location', $locID)->isValid()) {
            return;
        }
        $this->array_insert_after("\nSnapin Used: ", $arguments['email'], "\nImaged From (Location): ", self::getClass('Location', $locID)->get('name'));
    }
    public function HostRegister($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->set('locationID', $_REQUEST['location'])
            ->save();
        self::$HookManager->processEvent('HOST_REGISTER_LOCATION', array('Host'=>$Host, 'Location'=>&$_REQUEST['location']));
    }
    public function HostInfoExpose($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $arguments['repFields']['location'] = self::getClass('Location', @min(self::getSubObjectIDs('Location', array('hostID'=>$arguments['Host']->get('id')))))->get('name');
    }
}
$AddLocationHost = new AddLocationHost();
$HookManager->register('HOST_HEADER_DATA', array($AddLocationHost, 'HostTableHeader'));
$HookManager->register('HOST_DATA', array($AddLocationHost, 'HostData'));
$HookManager->register('HOST_FIELDS', array($AddLocationHost, 'HostFields'));
$HookManager->register('HOST_ADD_SUCCESS', array($AddLocationHost, 'HostAddLocation'));
$HookManager->register('HOST_EDIT_SUCCESS', array($AddLocationHost, 'HostAddLocation'));
$HookManager->register('HOST_REGISTER', array($AddLocationHost, 'HostRegister'));
$HookManager->register('HOST_IMPORT', array($AddLocationHost, 'HostImport'));
$HookManager->register('HOST_EXPORT_REPORT', array($AddLocationHost, 'HostExport'));
$HookManager->register('DESTROY_HOST', array($AddLocationHost, 'HostDestroy'));
$HookManager->register('EMAIL_ITEMS', array($AddLocationHost, 'HostEmailHook'));
$HookManager->register('HOST_INFO_EXPOSE', array($AddLocationHost, 'HostInfoExpose'));
