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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    private function _addFields()
    {
        $module = filter_input(INPUT_POST, 'module');
        $description = filter_input(INPUT_POST, 'description');
        $shortname = filter_input(INPUT_POST, 'shortname');
        $isDefault = isset($_POST['isDefault']) ? ' checked' : '';

        $labelClass = 'col-sm-3 control-label';

        // The fields to display
        return [
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
    }
    /**
     * Create a new module.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'module',
            _('Create New Module'),
            'MODULE_ADD_FIELDS',
            'Module'
        );
    }
    /**
     * Create a new module.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'module',
            'MODULE_ADD_FIELDS',
            'Module'
        );
    }
    /**
     * When submitted to add post this is what's run
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Module',
            'MODULE_ADD',
            _('Module added!'),
            _('Module Create Success'),
            _('Module Create Fail'),
            function (&$serverFault) {
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
                return $Module;
            }
        );
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
        self::checkAuthAndCSRF();
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
        $this->renderAssocTab(
            'module-host',
            _('Module Host Associations'),
            _('Host Name'),
            'host'
        );
    }
    /**
     * Update the module hosts.
     *
     * @return void
     */
    public function moduleHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * The module edit display method
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s %s: %s',
            _('Edit'),
            $this->obj->get('name'),
            _('ID'),
            $this->obj->get('id')
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
        self::checkAuthAndCSRF();
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
        $this->jsonHookResponse(
            [
                'Module' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Presents the hosts list table.
     *
     * @return void
     */
    public function getHostsList()
    {
        return $this->assocItemsList(
            'host',
            'moduleassociation',
            'moduleStatusByHost',
            '`hosts`.`hostID`',
            '`moduleStatusByHost`.`msHostID`',
            '`moduleStatusByHost`.`msModuleID`',
            [
                [
                    'db' => 'moduleAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}
