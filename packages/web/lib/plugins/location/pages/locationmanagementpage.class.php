<?php
/**
 * Location management page.
 *
 * PHP version 5
 *
 * @category LocationManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location management page.
 *
 * @category LocationManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationManagementPage extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'location';
    /**
     * Initializes the Location management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Location Management');
        self::$foglang['ExportLocation'] = _('Export Locations');
        self::$foglang['ImportLocation'] = _('Import Locations');
        parent::__construct($this->name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#location-gen" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                "$this->delformat" => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Location'] => $this->obj->get('name'),
                sprintf(
                    '%s %s',
                    self::$foglang['Storage'],
                    self::$foglang['Group']
                ) => $this->obj->getStorageGroup()->get('name')
            );
            if ($this->obj->getStorageNode()->isValid()) {
                $this->notes[
                    sprintf(
                        '%s %s',
                        self::$foglang['Storage'],
                        self::$foglang['Node']
                    )
                ] = $this->obj->getStorageNode()->get('name');
            }
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Location Name'),
            _('Storage Group'),
            _('Storage Node'),
            _('Kernels/Inits from location'),
        );
        $this->templates = array(
            '<input type="checkbox" name="location[]" value='
            . '"${id}" class="toggle-action" checked/>',
            '<a href="?node=location&sub=edit&id=${id}" data-toggle="tooltip" '
            . 'data-placement="right" title="'
            . _('Edit')
            . ' '
            . '${name}">${name}</a>',
            '${storageGroup}',
            '${storageNode}',
            '${tftp}',
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Location the object to use
         *
         * @return void
         */
        self::$returnData = function (&$Location) {
            $sn = empty($Location->storagenode->name) ? '*' : $Location->storagenode->name;
            $this->data[] = array(
                'id' => $Location->id,
                'name' => $Location->name,
                'storageGroup' => $Location->storagegroup->name,
                'storageNode' => $sn,
                'tftp' => $Location->tftp ? _('Yes') : _('No'),
            );
            unset($Location);
        };
    }
    /**
     * Show form for creating a new location entry.
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('New Location');
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $storagegroup = filter_input(
            INPUT_POST,
            'storagegroup'
        );
        $storagenode = filter_input(
            INPUT_POST,
            'storagenode'
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $sgbuild = self::getClass('StorageGroupManager')->buildSelectBox(
            $storagegroup
        );
        $snbuild = self::getClass('StorageNodeManager')->buildSelectBox(
            $storagenode
        );
        $tftp = isset($_POST['tftp']) ? ' checked' : '';
        $fields = array(
            '<label for="name">'
            . _('Location Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control locationname-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $sgbuild,
            '<label for="storagenode">'
            . _('Storage Node')
            . '</label>' => $snbuild,
            '<label for="isen">'
            . _('Use inits and kernels from this node')
            . '</label>' => '<input type="checkbox" name="tftp" '
            . 'class="tftpenabled" id="isen"'
            . $tftp
            . '/>',
            '<label for="add">'
            . _('Create New Location')
            . '</label>' => '<button type="submit" name="add" id="add" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'LOCATION_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->indexDivDisplay();
        echo '</form>';
        echo '</div>';
        unset(
            $fields,
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Actually create the location.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('LOCATION_ADD_POST');
        $name = filter_input(INPUT_POST, 'name');
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $tftp = isset($_POST['tftp']);
        try {
            if (self::getClass('LocationManager')->exists($name)) {
                throw new Exception(
                    _('Location already Exists, please try again.')
                );
            }
            if (!$name) {
                throw new Exception(
                    _('Please enter a name for this location.')
                );
            }
            if (!$storagegroup) {
                throw new Exception(
                    _('Please select the storage group this location relates to.')
                );
            }
            $sgID = $storagegroup;
            $sn = new StorageNode($storagenode);
            if ($sn->isValid()) {
                $sgID = $sn->getStorageGroup()->get('id');
            }
            $Location = self::getClass('Location')
                ->set('name', $name)
                ->set('storagegroupID', $sgID)
                ->set('storagenodeID', $storagenode)
                ->set('tftp', (int)$tftp);
            if (!$Location->save()) {
                throw new Exception(
                    _('Add location failed!')
                );
            }
            $hook = 'LOCATION_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Location added!'),
                    'title' => _('Location Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'LOCATION_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Location Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Location' => &$Location)
            );
        unset($Location);
        echo $msg;
        exit;
    }
    /**
     * Present the location to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('Location General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        ) ?: $this->obj->get('name');
        $storagegroup = filter_input(
            INPUT_POST,
            'storagegroup'
        ) ?: $this->obj->get('storagegroupID');
        $storagenode = filter_input(
            INPUT_POST,
            'storagenode'
        ) ?: $this->obj->get('storagenodeID');
        $sgbuild = self::getClass('StorageGroupManager')->buildSelectBox(
            $storagegroup
        );
        $snbuild = self::getClass('StorageNodeManager')->buildSelectBox(
            $storagenode
        );
        $tftp = isset($_POST['tftp']) ? ' checked' : '';
        if (!$tftp) {
            $tftp = $this->obj->get('tftp') ? ' checked' : '';
        }
        $fields = array(
            '<label for="name">'
            . _('Location Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control locationname-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name" required/>'
            . '</div>',
            '<label for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $sgbuild,
            '<label for="storagenode">'
            . _('Storage Node')
            . '</label>' => $snbuild,
            '<label for="isen">'
            . _('Use inits and kernels from this node')
            . '</label>' => '<input type="checkbox" name="tftp" '
            . 'class="tftpenabled" id="isen"'
            . $tftp
            . '/>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'LOCATION_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9 tab-content">';
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="location-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=location-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Actually update the location.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'LOCATION_EDIT_POST',
                array(
                    'Location' => &$this->obj
                )
            );
        $name = filter_input(INPUT_POST, 'name');
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $tftp = isset($_POST['tftp']);
        try {
            if ($name != $this->obj->get('name')
                && $this->obj->getManager()->exists($name)
            ) {
                throw new Exception(
                    _('A location with that name already exists.')
                );
            }
            if (isset($_POST['update'])) {
                if (!$storagegroup) {
                    throw new Exception(
                        _('A group is required for a location')
                    );
                }
                $sn = new StorageNode($storagenode);
                $sgID = $storagegroup;
                if ($sn->isValid()) {
                    $sgID = $sn->getStorageGroup()->get('id');
                }
                $this->obj
                    ->set('name', $name)
                    ->set('storagegroupID', $sgID)
                    ->set('storagenodeID', $storagenode)
                    ->set('tftp', (int)$tftp);
                if (!$this->obj->save()) {
                    throw new Exception(
                        _('Location update failed!')
                    );
                }
                $hook = 'LOCATION_UPDATE_SUCCESS';
                $msg = json_encode(
                    array(
                        'msg' => _('Location updated!'),
                        'title' => _('Location Update Success')
                    )
                );
            }
        } catch (Exception $e) {
            $hook = 'LOCATION_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Location Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Location' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
