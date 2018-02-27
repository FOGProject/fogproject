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
        $this->name = 'Location Management';
        self::$foglang['ExportLocation'] = _('Export Locations');
        self::$foglang['ImportLocation'] = _('Import Locations');
        parent::__construct($this->name);
        $this->headerData = [
            _('Location Name'),
            _('Storage Group'),
            _('Storage Node'),
            _('Kernels/Inits from location')
        ];
        $this->templates = [
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
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
        $storagegroupSelector = self::getClass('StorageGroupManager')
            ->buildSelectBox($storagegroup);
        $storagenodeSelector = self::getClass('StorageNodeManager')
            ->buildSelectBox($storagenode);
        $fields = [
            '<label class="col-sm-2 control-label" for="location">'
            . _('Location Name')
            . '</label>' => '<input type="text" name="location" '
            . 'value="'
            . $location
            . '" class="locationname-input form-control" '
            . 'id="location" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Location Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $storagegroupSelector,
            '<label class="col-sm-2 control-label" for="storagenode">'
            . _('Storage Node')
            . '</label>' => $storagenodeSelector,
            '<label class="col-sm-2 control-label" for="isen">'
            . _('Location Sends Boot')
            . '<br/>('
            . _('Location sends the inits and kernels')
            . ')</label>' => '<input type="checkbox" name="bootfrom" '
            . 'class="bootfrom" checked/>'
        ];
        self::$HookManager
            ->processEvent(
                'LOCATION_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'Location' => self::getClass('Location')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="location-create">';
        echo '<form id="location-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Location General -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Site');
        echo '</h3>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="send">'
            . _('Create')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
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
        $bootfrom = (int)isset($_POST['bootfrom']);
        $serverFault = false;
        try {
            if (!$location) {
                throw new Exception(
                    _('A location name is required!')
                );
            }
            if (self::getClass('LocationManager')->exists($location)) {
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
                ->set('tftp', $bootfrom);
            if (!$Location->save()) {
                $serverFault = false;
                throw new Exception(_('Add location failed!'));
            }
            $code = 201;
            $hook = 'LOCATION_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Location added!'),
                    'title' => _('Location Create Succes')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'LOCATION_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Location Create Fail')
                ]
            );
        }
        // header('Location: ../management/index.php?node=location&sub=edit&id=' . $Location->get('id'));
        self::$HookManager
            ->processEvent(
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
        $storagegroupSelector = self::getClass('StorageGroupManager')
            ->buildSelectBox($storagegroup);
        $storagenodeSelector = self::getClass('StorageNodeManager')
            ->buildSelectBox($storagenode);
        $bootfrom = (
            isset($_POST['bootfrom']) ?
            ' checked' :
            (
                $this->obj->get('tftp') ?
                ' checked' :
                ''
            )
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="location">'
            . _('Location Name')
            . '</label>' => '<input type="text" name="location" '
            . 'value="'
            . $location
            . '" class="locationname-input form-control" '
            . 'id="location" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Location Name')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="storagegroup">'
            . _('Storage Group')
            . '</label>' => $storagegroupSelector,
            '<label class="col-sm-2 control-label" for="storagenode">'
            . _('Storage Node')
            . '</label>' => $storagenodeSelector,
            '<label class="col-sm-2 control-label" for="isen">'
            . _('Location Sends Boot')
            . '<br/>('
            . _('Location sends the inits and kernels')
            . ')</label>' => '<input type="checkbox" name="bootfrom" '
            . 'class="bootfrom"'
            . $bootfrom
            . '/>'
        ];
        self::$HookManager
            ->processEvent(
                'LOCATION_GENERAL_FIELDS',
                [
                    'fields' => &$fields,
                    'Location' => self::getClass('Location')
                ]
            );
        $rendered = self::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<form id="location-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('location-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div.';
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
        $bootfrom = (int)isset($_POST['bootfrom']);
        if ($location != $this->obj->get('name')) {
            if ($this->obj->getManager()->exists($location)) {
                throw new Exception(
                    _('A location already exists with this name!')
                );
            }
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
            ->set('tftp', $bootfrom);
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
            'generator' => function() {
                $this->locationGeneral();
            }
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
        self::$HookManager
            ->processEvent(
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
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Location update failed!'));
            }
            $code = 201;
            $hook = 'LOCATION_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Location updated!'),
                    'title' => _('Location Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'LOCATION_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Location Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
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
}
