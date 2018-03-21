<?php
/**
 * The capone page.
 *
 * PHP version 5
 *
 * @category CaponeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The capone page.
 *
 * @category CaponeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class CaponeManagement extends FOGPage
{
    /**
     * The node this page displays with.
     *
     * @var string
     */
    public $node = 'capone';
    /**
     * Initializes the WOL Page.
     *
     * @param string $name The name to pass with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Capone Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Edit Capone'),
            _('Image Name'),
            _('Image OS'),
            _('Search Key')
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
     * Create new capone entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Capone');

        $image = filter_input(INPUT_POST, 'image');
        $key = filter_input(INPUT_POST, 'key');
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image);

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'image',
                _('Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'key',
                _('Key to match')
            ) => self::makeInput(
                'form-control caponekey-input',
                'key',
                _('Key to match'),
                'text',
                'key',
                $key,
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );

        self::$HookManager->processEvent(
            'CAPONE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Capone' => self::getClass('Capone')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'capone-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="capone-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Capone');
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
     * Actually create the broadcast.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('CAPONE_ADD_POST');
        $imageID = trim(
            filter_input(INPUT_POST, 'image')
        );
        $key = trim(
            filter_input(INPUT_POST, 'key')
        );
        $image = new Image($imageID);
        $os = $image->getOS();
        $osID = $os->get('id');

        $serverFault = false;
        try {
            if (!$image->isValid()) {
                throw new Exception(
                    _('Please select a valid image')
                );
            }
            if (!$os->isValid()) {
                throw new Exception(
                    _('The image associated does not have a valid OS!')
                );
            }
            $Capone = self::getClass('Capone')
                ->set('imageID', $imageID)
                ->set('osID', $osID)
                ->set('key', $key);
            if (!$Capone->save()) {
                $serverFault = true;
                throw new Exception(_('Add capone failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'CAPONE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Capone added!'),
                    'title' => _('Capone Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'CAPONE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Capone Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=capone&sub=edit&id='
        //    $Capone->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'Capone' => &$Capone,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Capone);
        echo $msg;
        exit;
    }
    /**
     * Capone General tab.
     *
     * @return void
     */
    public function caponeGeneral()
    {
        $this->title = _('Editing Capone ID')
            . ': '
            . $this->obj->get('id');
            
        $image = (
            filter_input(INPUT_POST, 'image') ?:
            $this->obj->get('imageID')
        );
        $key = (
            filter_input(INPUT_POST, 'key') ?:
            $this->obj->get('key')
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image);

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'image',
                _('Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'key',
                _('Key to match')
            ) => self::makeInput(
                'form-control caponekey-input',
                'key',
                _('Key to match'),
                'text',
                'key',
                $key,
                true
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-right'
        );

        self::$HookManager->processEvent(
            'CAPONE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Capone' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'capone-general-form',
            self::makeTabUpdateURL(
                'capone-general',
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
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates the capone general elements.
     *
     * @return void
     */
    public function caponeGeneralPost()
    {
        $imageID = trim(
            filter_input(INPUT_POST, 'image')
        );
        $key = trim(
            filter_input(INPUT_POST, 'key')
        );
        $image = new Image($imageID);
        $os = $image->getOS();
        $osID = $os->get('id');

        if (!$image->isValid()) {
            throw new Exception(
                _('Please select a valid image')
            );
        }
        if (!$os->isValid()) {
            throw new Exception(
                _('The image associated does not have a valid OS!')
            );
        }

        $this->obj
            ->set('imageID', $imageID)
            ->set('osID', $osID)
            ->set('key', $key);
    }
    /**
     * Present the wol broadcast to edit the page.
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
            'id' => 'capone-general',
            'generator' => function () {
                $this->caponeGeneral();
            }
        ];

        echo self::tabFields($tabData);
    }
    /**
     * The capone global settings options.
     *
     * @return void
     */
    public function globalsettings()
    {
        $this->title = _('Editing Global Capone Settings');

        list(
            $dmiField,
            $actionType
        ) = self::getSubObjectIDs(
            'Service',
            [
                'name' => [
                    'FOG_PLUGIN_CAPONE_DMI',
                    'FOG_PLUGIN_CAPONE_SHUTDOWN'
                ]
            ],
            'value'
        );

        $dmiFields = [
            'bios-vendor',
            'bios-version',
            'bios-release-date',
            'system-manufacturer',
            'system-product-name',
            'system-version',
            'system-serial-number',
            'system-uuid',
            'baseboard-manufacturer',
            'baseboard-product-name',
            'baseboard-version',
            'baseboard-serial-number',
            'baseboard-asset-tag',
            'chassis-manufacturer',
            'chassis-type',
            'chassis-version',
            'chassis-serial-number',
            'chassis-asset-tag',
            'processor-family',
            'processor-manufacturer',
            'processor-version',
            'processor-frequency'
        ];
        $actionFields = [
            _('Reboot after deploy'),
            _('Shutdown after deploy')
        ];

        $dmifield = (
            filter_input(INPUT_POST, 'dmifield') ?:
            $dmiField
        );
        $action = (
            filter_input(INPUT_POST, 'action') ?:
            $actionType
        );

        $dmiSelector = self::selectForm(
            'dmifield',
            $dmiFields,
            $dmifield
        );
        $actionSelector = self::selectForm(
            'action',
            $actionFields,
            $action,
            true
        );

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'dmifield',
                _('DMI Field')
            ) => $dmiSelector,
            self::makeLabel(
                $labelClass,
                'action',
                _('Action')
            ) => $actionSelector
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary'
        );

        self::$HookManager->processEvent(
            'CAPONE_GLOBAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'capone-global-form',
            self::makeTabUpdateURL(
                'capone-global',
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
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Update the global settings options.
     *
     * @return void
     */
    public function globalsettingsPost()
    {
        header('Content-type: application/json');
        $dmifield = trim(
            filter_input(INPUT_POST, 'dmifield')
        );
        $action = trim(
            filter_input(INPUT_POST, 'action')
        );

        $serverFault = false;
        try {
            if (!$dmifield) {
                throw new Exception(_('A dmi field must be set!'));
            }
            if (!self::setSetting('FOG_PLUGIN_CAPONE_DMI', $dmifield)) {
                $serverFault = true;
                throw new Exception(_('Unable to set dmi field'));
            }
            if (!self::setSetting('FOG_PLUGIN_CAPONE_SHUTDOWN', $action)) {
                $serverFault = true;
                throw new Exception(_('Unable to set action field'));
            }
            $hook = 'CAPONE_GLOBAL_EDIT_SUCCESS';
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Global settings updated!'),
                    'title' => _('Global Settings Update Success')
                ]
            );
        } catch (Exception $e) {
            $hook = 'CAPONE_GLOBAL_EDIT_FAIL';
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Global Settings Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
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
     * Actually update the wol broadcast.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'CAPONE_EDIT_POST',
            ['Capone' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'capone-general':
                $this->caponeGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Capone update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'CAPONE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Capone updated!'),
                    'title' => _('Capone Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'CAPONE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Capone Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
            $hook,
            [
                'Capone' => &$this->obj,
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
     * Present the export information.
     *
     * @return void
     */
    public function export()
    {
        // The data to use for building our table.
        $this->headerData = [];
        $this->templates = [];
        $this->attributes = [];

        $obj = self::getClass('CaponeManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            $this->headerData[] = $common;
            $this->templates[] = '';
            $this->attributes[] = [];
            unset($real);
        }

        $this->title = _('Export Capones');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Capones');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'When you click on the item you want to export, it can only select '
            . 'what is currently viewable on the screen. This includes searched '
            . 'and the current page. Please use the selector to choose the amount '
            . 'of items you would like to export.'
        );
        echo '</p>';
        $this->render(12, 'capone-export-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Present the export list.
     *
     * @return void
     */
    public function getExportList()
    {
        header('Content-type: application/json');
        $obj = self::getClass('CaponeManager');
        $table = $obj->getTable();
        $sqlstr = $obj->getQueryStr();
        $filterstr = $obj->getFilterStr();
        $totalstr = $obj->getTotalStr();
        $dbcolumns = $obj->getColumns();
        $pass_vars = $columns = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        // Setup our columns for the CSVn.
        // Automatically removes the id column.
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        self::$HookManager->processEvent(
            'CAPONE_EXPORT_ITEMS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr
            )
        );
        exit;
    }
}
