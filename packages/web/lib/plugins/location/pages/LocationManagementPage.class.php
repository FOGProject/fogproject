<?php
class LocationManagementPage extends FOGPage {
    public $node = 'location';
    public function __construct($name = '') {
        $this->name = 'Location Management';
        // Call parent constructor
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->obj = $this->getClass('Location',$_REQUEST['id']);
            $this->subMenu = array(
                "$this->linkformat" => $this->foglang[General],
                "$this->delformat" => $this->foglang[Delete],
            );
            $this->notes = array(
                $this->foglang[Location] => $this->obj->get('name'),
                $this->foglang[Storage].' '.$this->foglang[Group] => $this->obj->getStorageGroup(),
            );
            if ($this->obj->getStorageNode()->isValid()) $this->notes[$this->foglang[Storage].' '.$this->foglang[Node]] = $this->obj->getStorageNode();
        }
        // Header row
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            'Location Name',
            'Storage Group',
            'Storage Node',
            'TFTP Server',
        );
        // Row templates
        $this->templates = array(
            '<input type="checkbox" name="location[]" value="${id}" class="toggle-action" checked/>',
            '<a href="?node=location&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${storageGroup}',
            '${storageNode}',
            '${tftp}',
        );
        $this->attributes = array(
            array('class' => 'c','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'c'),
            array('class' => 'r'),
        );
    }
    // Pages
    public function index() {
        // Set title
        $this->title = _('Search');
        if ($this->FOGCore->getSetting('FOG_DATA_RETURNED') > 0 && $this->getClass('LocationManager')->count() > $this->FOGCore->getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list')
            $this->FOGCore->redirect(sprintf('%s?node=%s&sub=search', $_SERVER['PHP_SELF'], $this->node));
        // Find data
        $Locations = $this->getClass('LocationManager')->find();
        // Row data
        foreach ((array)$Locations AS $Location) {
            $StorageGroup = new StorageGroup($Location->get('storageGroupID'));
            $this->data[] = array(
                'id'	=> $Location->get('id'),
                'name'  => $Location->get('name'),
                'storageNode' => ($Location->get('storageNodeID') ? $this->getClass('StorageNode',$Location->get('storageNodeID'))->get('name') : 'Not Set'),
                'storageGroup' => $StorageGroup->get('name'),
                'tftp' => $Location->get('tftp') ? _('Yes') : _('No'),
            );
        }
        // Hook
        $this->HookManager->event[] = 'LOCATION_DATA';
        $this->HookManager->processEvent('LOCATION_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function search_post() {
        // Variables
        $keyword = preg_replace('#%+#', '%', '%' . preg_replace('#[[:space:]]#', '%', $this->REQUEST['crit']) . '%');
        // To assist with finding by storage group or location.
        $where = array(
            'id'		=> $keyword,
            'name'		=> $keyword,
            'description' => $keyword,
            'storageGroupID' => $keyword,
        );
        // Find data -> Push data
        foreach ((array)$this->getClass('LocationManager')->find($where,'OR') AS $Location) {
            $this->data[] = array(
                'id'		=> $Location->get('id'),
                'name'		=> $Location->get('name'),
                'storageGroup'	=> $this->getClass('StorageGroup',$Location->get('storageGroupID'))->get ('name'),
                'storageNode' => $Location->get('storageNodeID') ? $this->getClass('StorageNode',$Location->get('storageNodeID'))->get('name') : 'Not Set',
                'tftp' => $Location->get('tftp') ? 'Yes' : 'No',
            );
        }
        // Hook
        $this->HookManager->event[] = 'LOCATION_DATA';
        $this->HookManager->processEvent('LOCATION_DATA', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
    }
    public function add() {
        $this->title = 'New Location';
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Location Name') => '<input class="smaller" type="text" name="name" />',
            _('Storage Group') => $this->getClass('StorageGroupManager')->buildSelectBox(),
            _('Storage Node') => $this->getClass('StorageNodeManager')->buildSelectBox(),
            _('TFTP From Node') => '<input type="checkbox" name="tftp" value="on" />',
            '<input type="hidden" name="add" value="1" />' => '<input class="smaller" type="submit" value="'.('Add').'" />',
        );
        print '<form method="post" action="'.$this->formAction.'">';
        foreach((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        // Hook
        $this->HookManager->event[] = 'LOCATION_ADD';
        $this->HookManager->processEvent('LOCATION_ADD', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
    public function add_post() {
        try {
            $name = trim($_REQUEST['name']);
            if ($this->getClass('LocationManager')->exists(trim($_REQUEST['name'])))
                throw new Exception('Location already Exists, please try again.');
            if (!$name)
                throw new Exception('Please enter a name for this location.');
            if (empty($_REQUEST['storagegroup']))
                throw new Exception('Please select the storage group this location relates to.');
            $Location = new Location(array(
                'name' => trim($_REQUEST['name']),
                'storageGroupID' => $_REQUEST['storagegroup'],
                'storageNodeID' => $_REQUEST['storagenode'],
                'tftp' => $_REQUEST['tftp'],
            ));
            if ($_REQUEST['storagenode'] && $Location->get('storageGroupID') != $this->getClass('StorageNode',$_REQUEST['storagenode'])->get('storageGroupID'))
                $Location->set('storageGroupID', $this->getClass('StorageNode',$_REQUEST['storagenode'])->get('storageGroupID'));
            if ($Location->save()) {
                $this->FOGCore->setMessage('Location Added, editing!');
                $this->FOGCore->redirect('?node=location&sub=edit&id='.$Location->get('id'));
            }
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect($this->formAction);
        }
    }
    public function edit() {
        // Find
        $Location = $this->obj;
        // Get the Storage Node ID if it's set
        // Title
        $this->title = sprintf('%s: %s', 'Edit', $Location->get('name'));
        // Header Data
        unset($this->headerData);
        // Attributes
        $this->attributes = array(
            array(),
            array(),
        );
        // Templates
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Location Name') => '<input class="smaller" type="text" name="name" value="${location_name}" />',
            _('Storage Group') => '${storage_groups}',
            _('Storage Node') => '${storage_nodes}',
            $Location->get('storageNodeID') ? _('TFTP From Node') : '' => $Location->get('storageNodeID') ? '<input type="checkbox" name="tftp" value="on" ${checked} />' : '',
            '<input type="hidden" name="update" value="1" />' => '<input type="submit" class="smaller" value="'._('Update').'" />',
        );
        print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&id='.$Location->get('id').'">';
        foreach ((array)$fields AS $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'location_name' => $Location->get('name'),
                'storage_groups' => $this->getClass('StorageGroupManager')->buildSelectBox($Location->get('storageGroupID')),
                'storage_nodes' => $this->getClass('StorageNodeManager')->buildSelectBox($Location->get('storageNodeID')),
                'checked' => $Location->get('tftp') ? 'checked' : '',
            );
        }
        // Hook
        $this->HookManager->event[] = 'LOCATION_EDIT';
        $this->HookManager->processEvent('LOCATION_EDIT', array('headerData' => &$this->headerData, 'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
        // Output
        $this->render();
        print '</form>';
    }
    public function edit_post() {
        $Location = $this->obj;
        $LocationMan = new LocationManager();
        $this->HookManager->event[] = 'LOCATION_EDIT_POST';
        $this->HookManager->processEvent('LOCATION_EDIT_POST', array('Location'=> &$Location));
        try {
            if ($_REQUEST['name'] != $Location->get('name') && $LocationMan->exists($_REQUEST['name']))
                throw new Exception('A location with that name already exists.');
            if ($_REQUEST['update']) {
                if ($_REQUEST['storagegroup']) {
                    $Location->set('name', $_REQUEST['name'])
                        ->set('storageGroupID', $_REQUEST['storagegroup']);
                }
                $Location->set('storageNodeID', $_REQUEST['storagenode'])
                    ->set('tftp', $_REQUEST['tftp']);
                if ($Location->save()) {
                    $this->FOGCore->setMessage('Location Updated');
                    $this->FOGCore->redirect('?node=location&sub=edit&id='.$Location->get('id'));
                }
            }
        } catch (Exception $e) {
            $this->FOGCore->setMessage($e->getMessage());
            $this->FOGCore->redirect($this->formAction);
        }
    }
}
