<?php
/**
 * Location management page.
 *
 * PHP version 5
 *
 * @category LocationManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location management page.
 *
 * @category LocationManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationManagement extends FOGPage
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
        $this->name = 'Location Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Location Name'),
            _('Storage Group'),
            _('Storage Node'),
            _('Storage Node Protocol'),
            _('Kernels/Inits from location')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Location');

        $location = filter_input(INPUT_POST, 'location');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $storagenodeprotocol = filter_input(INPUT_POST, 'storagenodeprotocol');
        $storagegroupSelector = self::getClass('StorageGroupManager')
            ->buildSelectBox($storagegroup);
        $storagenodeSelector = self::getClass('StorageNodeManager')
            ->buildSelectBox($storagenode);
        $storagenodeProtocolSelector = self::getClass('LocationManager')
            ->buildProtocolSelectBox($storagenodeprotocol);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'location',
                _('Location Name')
            ) => self::makeInput(
                'form-control locationname-input',
                'location',
                _('Location Name'),
                'text',
                'location',
                $location,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Location Description')
            ) => self::makeTextarea(
                'form-control locationdescription-input',
                'description',
                _('Location Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group')
            ) => $storagegroupSelector,
            self::makeLabel(
                $labelClass,
                'storagenode',
                _('Storage Node')
            ) => $storagenodeSelector,
            self::makeLabel(
                $labelClass,
                'storagenodeprotocol',
                _('Storage Node Protocol')
            ) => $storagenodeProtocolSelector,
            self::makeLabel(
                $labelClass,
                'bootfrom',
                _('Boot files from')
            ) => self::makeInput(
                '',
                'bootfrom',
                '',
                'checkbox',
                'bootfrom',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'LOCATION_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Location' => self::getClass('Location')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'location-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="location-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Location');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function addModal()
    {
        $location = filter_input(INPUT_POST, 'location');
        $description = filter_input(INPUT_POST, 'description');
        $storagegroup = filter_input(INPUT_POST, 'storagegroup');
        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $storagenodeprotocol = filter_input(INPUT_POST, 'storagenodeprotocol');
        $storagegroupSelector = self::getClass('StorageGroupManager')
            ->buildSelectBox($storagegroup);
        $storagenodeSelector = self::getClass('StorageNodeManager')
            ->buildSelectBox($storagenode);
        $storagenodeProtocolSelector = self::getClass('LocationManager')
            ->buildProtocolSelectBox($storagenodeprotocol);

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'location',
                _('Location Name')
            ) => self::makeInput(
                'form-control locationname-input',
                'location',
                _('Location Name'),
                'text',
                'location',
                $location,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Location Description')
            ) => self::makeTextarea(
                'form-control locationdescription-input',
                'description',
                _('Location Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group')
            ) => $storagegroupSelector,
            self::makeLabel(
                $labelClass,
                'storagenode',
                _('Storage Node')
            ) => $storagenodeSelector,
            self::makeLabel(
                $labelClass,
                'storagenodeprotocol',
                _('Storage Node Protocol')
            ) => $storagenodeProtocolSelector,
            self::makeLabel(
                $labelClass,
                'bootfrom',
                _('Boot files from')
            ) => self::makeInput(
                '',
                'bootfrom',
                '',
                'checkbox',
                'bootfrom',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            )
        ];

        self::$HookManager->processEvent(
            'LOCATION_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Location' => self::getClass('Location')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=location&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually create the location.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('LOCATION_ADD_POST');
        $location = trim(
            filter_input(INPUT_POST, 'location')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $storagegroup = trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $storagenode = trim(
            filter_input(INPUT_POST, 'storagenode')
        );
        $storagenodeprotocol = trim(
            filter_input(INPUT_POST, 'storagenodeprotocol')
        );
        $bootfrom = (int)isset($_POST['bootfrom']);

        $serverFault = false;
        try {
            $exists = self::getClass('LocationManager')
                ->exists($location);
            if ($exists) {
                throw new Exception(
                    _('A location already exists with this name!')
                );
            }
            if (!$storagegroup && !$storagenode) {
                throw new Exception(
                    _('A storage group must be selected.')
                );
            }
            if ($storagenode) {
                $storagegroup = self::getClass('StorageNode', $storagenode)
                    ->get('storagegroupID');
            }
            $Location = self::getClass('Location')
                ->set('name', $location)
                ->set('storagegroupID', $storagegroup)
                ->set('storagenodeID', $storagenode)
                ->set('tftp', $bootfrom)
                ->set('protocol', $storagenodeprotocol);
            if (!$Location->save()) {
                $serverFault = true;
                throw new Exception(_('Add location failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'LOCATION_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Location added!'),
                    'title' => _('Location Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'LOCATION_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Location Create Fail')
                ]
            );
        }
        // header(
        //     'Location: ../management/index.php?node=location&sub=edit&id='
        //     . $Location->get('id')
        // );
        self::$HookManager->processEvent(
            $hook,
            [
                'Location' => &$Location,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Location);
        echo $msg;
        exit;
    }
    /**
     * Displays the location general tab.
     *
     * @return void
     */
    public function locationGeneral()
    {
        $location = (
            filter_input(INPUT_POST, 'location') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $storagegroup = (
            filter_input(INPUT_POST, 'storagegroup') ?:
            $this->obj->get('storagegroupID')
        );
        $storagenode = (
            filter_input(INPUT_POST, 'storagenode') ?:
            $this->obj->get('storagenodeID')
        );
        $storagenodeprotocol = (
            filter_input(INPUT_POST, 'storagenodeprotocol') ?:
            $this->obj->get('protocol')
        );
        $storagegroupSelector = self::getClass('StorageGroupManager')
            ->buildSelectBox($storagegroup);
        $storagenodeSelector = self::getClass('StorageNodeManager')
            ->buildSelectBox($storagenode);
        $storagenodeProtocolSelector = self::getCLass('LocationManager')
            ->buildProtocolSelectBox($storagenodeprotocol);
        $bootfrom = (
            isset($_POST['bootfrom']) ?
            'checked' :
            (
                $this->obj->get('tftp') ?
                'checked' :
                ''
            )
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'location',
                _('Location Name')
            ) => self::makeInput(
                'form-control locationname-input',
                'location',
                _('Location Name'),
                'text',
                'location',
                $location,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Location Description')
            ) => self::makeTextarea(
                'form-control locationdescription-input',
                'description',
                _('Location Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'storagegroup',
                _('Storage Group')
            ) => $storagegroupSelector,
            self::makeLabel(
                $labelClass,
                'storagenode',
                _('Storage Node')
            ) => $storagenodeSelector,
            self::makeLabel(
                $labelClass,
                'storagenodeprotocol',
                _('Storage Node Protocol')
            ) => $storagenodeProtocolSelector,
            self::makeLabel(
                $labelClass,
                'bootfrom',
                _('Boot files from')
            ) => self::makeInput(
                '',
                'bootfrom',
                '',
                'checkbox',
                'bootfrom',
                '',
                false,
                false,
                -1,
                -1,
                $bootfrom
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'LOCATION_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Location' => $this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'location-general-form',
            self::makeTabUpdateURL(
                'location-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually update the general information.
     *
     * @return void
     */
    public function locationGeneralPost()
    {
        $location = trim(
            filter_input(INPUT_POST, 'location')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $storagegroup = trim(
            filter_input(INPUT_POST, 'storagegroup')
        );
        $storagenode = trim(
            filter_input(INPUT_POST, 'storagenode')
        );
        $storagenodeprotocol = trim(
            filter_input(INPUT_POST, 'storagenodeprotocol')
        );
        $bootfrom = (int)isset($_POST['bootfrom']);

        $exists = self::getClass('LocationManager')
            ->exists($location);
        if ($location != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A location already exists with this name!')
            );
        }
        if (!$storagegroup && !$storagenode) {
            throw new Exception(
                _('A storage group must be selected.')
            );
        }
        if ($storagenode) {
            $storagegroup = self::getClass('StorageNode', $storagenode)
                ->get('storagegroupID');
        }
        $this->obj
            ->set('name', $location)
            ->set('description', $description)
            ->set('storagegroupID', $storagegroup)
            ->set('storagenodeID', $storagenode)
            ->set('tftp', $bootfrom)
            ->set('protocol', $storagenodeprotocol);
    }
    /**
     * Present the hosts list.
     *
     * @return void
     */
    public function locationHosts()
    {
        $this->headerData = [
            _('Host Name'),
            _('Associated')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'location-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'location-host-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'location-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Location Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'location-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update host.
     *
     * @return void
     */
    public function locationHostPost()
    {
        if (isset($_POST['confirmadd'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['additems'];
            if (count($hosts ?: [])) {
                $this->obj->addHost($hosts);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['remitems'];
            if (count($hosts ?: [])) {
                $this->obj->removeHost($hosts);
            }
        }
    }
    /**
     * Present the location to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'location-general',
            'generator' => function () {
                $this->locationGeneral();
            }
        ];

        // Hosts
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Host Association'),
                        'id' => 'location-host',
                        'generator' => function () {
                            $this->locationHosts();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually update the location.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'LOCATION_EDIT_POST',
            ['Location' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'location-general':
                    $this->locationGeneralPost();
                    break;
                case 'location-host':
                    $this->locationHostPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Location update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'LOCATION_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Location updated!'),
                    'title' => _('Location Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'LOCATION_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Location Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Location' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Location -> host list
     *
     * @return void
     */
    public function getHostsList()
    {
        $join = [
            'LEFT OUTER JOIN `locationAssoc` ON '
            . "`hosts`.`hostID` = `locationAssoc`.`laHostID` "
            . "AND `locationAssoc`.`laLocationID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'locationAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'host',
            'locationassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Get storage node
     *
     * @return void
     */
    public function getStoragenode()
    {
        $nodeID = filter_input(INPUT_POST, 'nodeID');
        Route::indiv('storagenode', $nodeID);
        echo Route::getData();
        exit;
    }
    /**
     * Get storage group
     *
     * @return void
     */
    public function getStoragegroup()
    {
        $groupID = filter_input(INPUT_POST, 'groupID');
        Route::indiv('storagegroup', $groupID);
        echo Route::getData();
        exit;
    }
}
