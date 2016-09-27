<?php
class LocationManagementPage extends FOGPage
{
    public $node = 'location';
    public function __construct($name = '')
    {
        $this->name = 'Location Management';
        self::$foglang['ExportLocation'] = _('Export Locations');
        self::$foglang['ImportLocation'] = _('Import Locations');
        parent::__construct($this->name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                "$this->delformat" => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Location'] => $this->obj->get('name'),
                sprintf('%s %s', self::$foglang['Storage'], self::$foglang['Group']) => $this->obj->getStorageGroup(),
            );
            if ($this->obj->getStorageNode()->isValid()) {
                $this->notes[sprintf('%s %s', self::$foglang['Storage'], self::$foglang['Node'])] = $this->obj->getStorageNode();
            }
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction" checked/>',
            _('Location Name'),
            _('Storage Group'),
            _('Storage Node'),
            _('Kernels/Inits from location'),
        );
        $this->templates = array(
            '<input type="checkbox" name="location[]" value="${id}" class="toggle-action" checked/>',
            '<a href="?node=location&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${storageGroup}',
            '${storageNode}',
            '${tftp}',
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width'=>16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'c'),
            array('class' => 'r'),
        );
        self::$returnData = function (&$Location) {
            if (!$Location->isValid()) {
                return;
            }
            $this->data[] = array(
                'id'=>$Location->get('id'),
                'name'=>$Location->get('name'),
                'storageGroup'=>self::getClass('StorageGroup', $Location->get('storagegroupID'))->get('name'),
                'storageNode'=>$Location->get('storageNodeID')?self::getClass('StorageNode', $Location->get('storageNodeID'))->get('name') : _('Not Set'),
                'tftp'=>$Location->get('tftp') ? _('Yes') : _('No'),
            );
            unset($Location);
        };
    }
    public function index()
    {
        $this->title = _('Search');
        if (self::getSetting('FOG_DATA_RETURNED')>0 && self::getClass('LocationManager')->count() > self::getSetting('FOG_DATA_RETURNED') && $_REQUEST['sub'] != 'list') {
            $this->redirect(sprintf('?node=%s&sub=search', $this->node));
        }
        $this->data = array();
        array_map(self::$returnData, (array)self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('LOCATION_DATA', array('headerData'=>&$this->headerData, 'data'=>&$this->data, 'templates'=>&$this->templates, 'attributes'=>&$this->attributes));
        $this->render();
    }
    public function searchPost()
    {
        $this->data = array();
        array_map(self::$returnData, (array)self::getClass($this->childClass)->getManager()->search('', true));
        self::$HookManager->processEvent('LOCATION_DATA', array('headerData'=>&$this->headerData, 'data'=>&$this->data, 'templates'=>&$this->templates, 'attributes'=>&$this->attributes));
        $this->render();
    }
    public function add()
    {
        $this->title = _('New Location');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Location Name') => '<input class="smaller" type="text" name="name" />',
            _('Storage Group') => self::getClass('StorageGroupManager')->buildSelectBox(),
            _('Storage Node') => self::getClass('StorageNodeManager')->buildSelectBox(),
            _('Use inits and kernels from this node') => sprintf(
                '<input type="checkbox" name="tftp" value="on"%s/>',
                isset($_REQUEST['tftp']) ? ' checked' : ''
            ),
            '' => sprintf('<input name="add" class="smaller" type="submit" value="%s"/>', _('Add')),
        );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager->processEvent('LOCATION_ADD', array('headerData'=>&$this->headerData, 'data'=>&$this->data, 'templates'=>&$this->templates, 'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">', $this->formAction);
        $this->render();
        echo '</form>';
    }
    public function addPost()
    {
        try {
            $name = trim($_REQUEST['name']);
            if (self::getClass('LocationManager')->exists(trim($_REQUEST['name']))) {
                throw new Exception(_('Location already Exists, please try again.'));
            }
            if (!$name) {
                throw new Exception(_('Please enter a name for this location.'));
            }
            if (empty($_REQUEST['storagegroup'])) {
                throw new Exception(_('Please select the storage group this location relates to.'));
            }
            $Location = self::getClass('Location')
                ->set('name', $name)
                ->set('storagegroupID', $_REQUEST['storagegroup'])
                ->set('storageNodeID', $_REQUEST['storagenode'])
                ->set('tftp', $_REQUEST['tftp']);
            if ($_REQUEST['storagenode'] && $Location->get('storagegroupID') != self::getClass('StorageNode', $_REQUEST['storagenode'])->get('storagegroupID')) {
                $Location->set('storagegroupID', self::getClass('StorageNode', $_REQUEST['storagenode'])->get('storagegroupID'));
            }
            if (!$Location->save()) {
                throw new Exception(_('Failed to create'));
            }
            $this->setMessage(_('Location Added, editing!'));
            $this->redirect(sprintf('?node=location&sub=edit&id=%s', $Location->get('id')));
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Location Name') => sprintf('<input class="smaller" type="text" name="name" value="%s"/>', $this->obj->get('name')),
            _('Storage Group') => self::getClass('StorageGroupManager')->buildSelectBox($this->obj->get('storagegroupID')),
            _('Storage Node') => self::getClass('StorageNodeManager')->buildSelectBox($this->obj->get('storageNodeID')),
            _('Use inits and kernels from this node') => sprintf('<input type="checkbox" name="tftp" value="on"%s/>', $this->obj->get('tftp') ? ' checked' : ''),
            '&nbsp;' => sprintf('<input type="submit" class="smaller" name="update" value="%s"/>', _('Update')),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent('LOCATION_EDIT', array('headerData'=>&$this->headerData, 'data'=>&$this->data, 'templates'=>&$this->templates, 'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s&id=%d">', $this->formAction, $this->obj->get('id'));
        $this->render();
        echo '</form>';
    }
    public function editPost()
    {
        self::$HookManager->processEvent('LOCATION_EDIT_POST', array('Location'=> &$this->obj));
        try {
            if ($_REQUEST['name'] != $this->obj->get('name') && $this->obj->getManager()->exists($_REQUEST['name'])) {
                throw new Exception(_('A location with that name already exists.'));
            }
            if (isset($_REQUEST['update'])) {
                if (empty($_REQUEST['storagegroup'])) {
                    throw new Exception(_('Please select the storage group this location relates to.'));
                }
                $NodeID = intval($_REQUEST['storagenode']);
                $this->obj
                    ->set('name', $_REQUEST['name'])
                    ->set('storagegroupID', $NodeID ? self::getClass('StorageNode', $NodeID)->get('storagegroupID') : $_REQUEST['storagegroup'])
                    ->set('storageNodeID', $NodeID)
                    ->set('tftp', isset($_REQUEST['tftp']));
                if (!$this->obj->save()) {
                    throw new Exception(_('Failed to update'));
                }
                $this->setMessage(_('Location Updated'));
                $this->redirect(sprintf('?node=location&sub=edit&id=%d', $this->obj->get('id')));
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
}
