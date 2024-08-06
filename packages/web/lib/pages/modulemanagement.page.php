<?php
/**
 * Module management page
 *
 * PHP version 5
 *
 * The module represented to the GUI
 *
 * @category ModuleManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Module management page
 *
 * The Module represented to the GUI
 *
 * @category ModuleManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ModuleManagement extends FOGPage
{
    /**
     * The node that uses this class
     *
     * @var string
     */
    public $node = 'module';
    /**
     * Initializes the module page
     *
     * @param string $name the name to construct with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Module Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Short Name')
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Create a new module.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Module');

        $module = filter_input(INPUT_POST, 'module');
        $description = filter_input(INPUT_POST, 'description');
        $shortname = filter_input(INPUT_POST, 'shortname');
        $isDefault = isset($_POST['isDefault']) ? ' checked' : '';

        $labelClass = 'col-sm-3 control-label';

        // The fields to display
        $fields = [
            self::makeLabel(
                $labelClass,
                'module',
                _('Module Name')
            ) => self::makeInput(
                'form-control modulename-input',
                'module',
                _('Module Name'),
                'text',
                'module',
                $module,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Module Description')
            ) => self::makeTextarea(
                'form-control moduledescription-input',
                'description',
                _('Module Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'shortname',
                _('Module Short Name')
            ) => self::makeInput(
                'form-control moduleshortname-input',
                'shortname',
                'short',
                'text',
                'shortname',
                $shortname
            ),
            self::makeLabel(
                $labelClass,
                'isDefault',
                _('Module Default?')
            ) => self::makeInput(
                'moduleisdefault-input',
                'isDefault',
                '',
                'checkbox',
                'isDefault',
                '',
                false,
                false,
                -1,
                -1,
                $isDefault
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'MODULE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => self::getClass('Module')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'module-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="module-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Module');
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
     * Create a new module.
     *
     * @return void
     */
    public function addModal()
    {
        $module = filter_input(INPUT_POST, 'module');
        $description = filter_input(INPUT_POST, 'description');
        $shortname = filter_input(INPUT_POST, 'shortname');
        $isDefault = isset($_POST['isDefault']) ? ' checked' : '';

        $labelClass = 'col-sm-3 control-label';

        // The fields to display
        $fields = [
            self::makeLabel(
                $labelClass,
                'module',
                _('Module Name')
            ) => self::makeInput(
                'form-control modulename-input',
                'module',
                _('Module Name'),
                'text',
                'module',
                $module,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Module Description')
            ) => self::makeTextarea(
                'form-control moduledescription-input',
                'description',
                _('Module Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'shortname',
                _('Module Short Name')
            ) => self::makeInput(
                'form-control moduleshortname-input',
                'shortname',
                'short',
                'text',
                'shortname',
                $shortname
            ),
            self::makeLabel(
                $labelClass,
                'isDefault',
                _('Module Default?')
            ) => self::makeInput(
                'moduleisdefault-input',
                'isDefault',
                '',
                'checkbox',
                'isDefault',
                '',
                false,
                false,
                -1,
                -1,
                $isDefault
            )
        ];

        self::$HookManager->processEvent(
            'MODULE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Module' => self::getClass('Module')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=module&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * When submitted to add post this is what's run
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('MODULE_ADD_POST');
        $module = trim(
            filter_input(INPUT_POST, 'module')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $shortname = trim(
            filter_input(INPUT_POST, 'shortname')
        );
        $isDefault = (int)isset($_POST['isDefault']);

        $serverFault = false;
        try {
            $exists = self::getClass('ModuleManager')
                ->exists($module);
            if ($exists) {
                throw new Exception(
                    _('A module already exists with this name!')
                );
            }
            $Module = self::getClass('Module')
                ->set('name', $module)
                ->set('description', $description)
                ->set('shortName', $shortname)
                ->set('isDefault', $isDefault);
            if (!$Module->save()) {
                $serverFault = true;
                throw new Exception(_('Add module failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'MODULE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Module added!'),
                    'title' => _('Module Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'MODULE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Module Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=module&sub=edit&id='
        //    . $Module->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'Module' => &$Module,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Module);
        echo $msg;
        exit;
    }
    /**
     * Displays the module general tab.
     *
     * @return void
     */
    public function moduleGeneral()
    {
        $module = (
            filter_input(INPUT_POST, 'module') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $shortname = (
            filter_input(INPUT_POST, 'shortname') ?:
            $this->obj->get('shortName')
        );
        $isDefault = (
            isset($_POST['isDefault']) ?
            ' checked' :
            (
                $this->obj->get('isDefault') ?
                ' checked' :
                ''
            )
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'module',
                _('Module Name')
            ) => self::makeInput(
                'form-control modulename-input',
                'module',
                _('Module Name'),
                'text',
                'module',
                $module,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Module Description')
            ) => self::makeTextarea(
                'form-control moduledescription-input',
                'description',
                _('Module Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'shortname',
                _('Module Short Name')
            ) => self::makeInput(
                'form-control moduleshortname-input',
                'shortname',
                'short',
                'text',
                'shortname',
                $shortname
            ),
            self::makeLabel(
                $labelClass,
                'isDefault',
                _('Module Default?')
            ) => self::makeInput(
                'moduleisdefault-input',
                'isDefault',
                '',
                'checkbox',
                'isDefault',
                '',
                false,
                false,
                -1,
                -1,
                $isDefault
            )
        ];

        $buttons .= self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger'
        );

        self::$HookManager->processEvent(
            'MODULE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Module' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'module-general-form',
            self::makeTabUpdateURL(
                'module-general',
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
     * Module general post element
     *
     * @return void
     */
    public function moduleGeneralPost()
    {
        $module = trim(
            filter_input(INPUT_POST, 'module')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $shortname = trim(
            filter_input(INPUT_POST, 'shortname')
        );
        $isDefault = (int)isset($_POST['isDefault']);
        if ($module != $this->obj->get('name')) {
            if ($this->obj->getManager()->exists($module)) {
                throw new Exception(_('Please use another module name'));
            }
        }
        // Set the module relative items.
        $this->obj
            ->set('name', $module)
            ->set('description', $description)
            ->set('shortName', $shortname)
            ->set('isDefault', $isDefault);
    }
    /**
     * Module hosts display.
     *
     * @return void
     */
    public function moduleHosts()
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
                'module-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'module-host-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'module-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Module Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'module-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update the module hosts.
     *
     * @return void
     */
    public function moduleHostPost()
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
            if (count($hosts ?: []) > 0) {
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
            if (count($hosts ?: []) > 0) {
                $this->obj->removeHost($hosts);
            }
        }
    }
    /**
     * The module edit display method
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
            'id' => 'module-general',
            'generator' => function () {
                $this->moduleGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Host Associations'),
                        'id' => 'module-host',
                        'generator' => function () {
                            $this->moduleHosts();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Submit the edit function.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: appication/json');
        self::$HookManager->processEvent(
            'MODULE_EDIT_POST',
            ['Module' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'module-general':
                    $this->moduleGeneralPost();
                    break;
                case 'module-host':
                    $this->moduleHostPost();
                    break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Module update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'MODULE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Module updated!'),
                    'title' => _('Module Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'MODULE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Module Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Module' => &$this->obj,
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
     * Presents the hosts list table.
     *
     * @return void
     */
    public function getHostsList()
    {
        $join = [
            'LEFT OUTER JOIN `moduleStatusByHost` ON '
            . "`hosts`.`hostID` = `moduleStatusByHost`.`msHostID` "
            . "AND `moduleStatusByHost`.`msModuleID` = '" . $this->obj->get('id') . "'"
        ];

        $columns[] = [
            'db' => 'moduleAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'host',
            'moduleassociation',
            $join,
            '',
            $columns
        );
    }
}
