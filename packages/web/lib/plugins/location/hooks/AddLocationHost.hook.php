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
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'host') return;
        if ($_REQUEST['sub'] == 'pending') return;
        $arguments['headerData'][4] = _('Location/Deployed');
    }
    public function HostData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'host') return;
        if ($_REQUEST['sub'] == 'pending') return;
        $arguments['templates'][4] = '${location}<br/><small>${deployed}</small>';
        foreach((array)$arguments['data'] AS $index => &$vals) {
            $locationID = $this->getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['data'][$index]['host_id']),'locationID');
            $locID = array_shift($locationID);
            $arguments['data'][$index]['location'] = $this->getClass('Location',$locID)->get('name');
            unset($vals);
        }
    }
    public function HostFields($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'host') return;
        $locationID = $this->getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')),'locationID');
        $locID = array_shift($locationID);
        $arguments['fields'] = $this->array_insert_after(_('Host Product Key'),$arguments['fields'],_('Host Location'),$this->getClass('LocationManager')->buildSelectBox($locID));
    }
    public function HostAddLocation($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if ($_REQUEST['node'] != 'host') return;
        if (!in_array($_REQUEST['sub'],array('add','add_post'))) return;
        if (str_replace('_','-',$_REQUEST['tab']) != 'host-general') return;
        if (!$_REQUEST['location']) return;
        $Location = $this->getClass('Location',$_REQUEST['location']);
        if (!$Location->isValid()) return;
        $Location->addHost($arguments['Host']->get('id'))->save(false);
    }
    public function HostImport($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $Location = $this->getClass('Location',$arguments['data'][5]);
        if (!$Location->isValid()) return;
        $Location->addHost($arguments['Host']->get('id'))->save(false);
    }
    public function HostExport($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $locationID = $this->subObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')),'locationID');
        $locID = array_shift($locationID);
        $arguments['report']->addCSVCell($locID > 0 ? $locID : null);
    }
    public function HostDestroy($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $this->getClass('LocationAssociationManager')->destroy(array('hostID'=>$arguments['Host']->get('id')));
    }
    public function HostEmailHook($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $locationID = $this->subObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')),'locationID');
        $locID = array_shift($locationID);
        if (!$this->getClass('Location',$locID)->isValid()) return;
        $arguments['email'] = $this->array_insert_after("\nSnapin Used: ",$arguments['email'],"\nImaged From (Location): ",$this->getClass('Location',$locID)->get('name'));
    }
    public function HostRegister($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $locationID = trim(base64_decode($_REQUEST['location']));
        $Location = $this->getClass('Location',$locationID);
        if (!$Location->isValid()) return;
        $Location->addHost($arguments['Host']->get('id'))->save(false);
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
