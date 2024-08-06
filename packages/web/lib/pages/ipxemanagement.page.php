<?php
/**
 * The Bootmenu Management Page
 *
 * PHP version 5
 *
 * @category IpxeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The Bootmenu Management Page
 *
 * @category IpxeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class IpxeManagement extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'ipxe';
    /**
     * Initializes the ipxe class.
     *
     * @param string $name The name to load this as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'iPXE Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Description'),
            _('Default'),
            _('Display With'),
            _('Hot Key Enabled'),
            _('Hot Key')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Presents for creating a new menu item.
     *
     * @return void
     */
    public function add()
    {
        $ipxe = filter_input(INPUT_POST, 'ipxe');
        $description = filter_input(INPUT_POST, 'description');
        $params = filter_input(INPUT_POST, 'params');
        $options = filter_input(INPUT_POST, 'options');
        $regmenu = filter_input(INPUT_POST, 'regmenu');
        $default = isset($_POST['default']);
        $hotkey = isset($_POST['hotkey']);
        $keysequence = filter_input(INPUT_POST, 'keysequence');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ipxe',
                _('Menu Name')
            ) => self::makeInput(
                'form-control ipxename-input',
                'ipxe',
                'fog.customname',
                'text',
                'ipxe',
                $ipxe,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Menu Description')
            ) => self::makeTextarea(
                'form-control ipxedesc-input',
                'description',
                _('Some nice description, should be short.'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'params',
                _('Menu Parameters')
            ) => self::makeTextarea(
                'form-control ipxeparam-input',
                'params',
                "echo hello world\nsleep 3",
                'params',
                $params
            ),
            self::makeLabel(
                $labelClass,
                'options',
                _('Boot Options')
            ) => self::makeInput(
                'form-control ipxeoption-input',
                'options',
                'debug loglevel=7 isdebug=yes',
                'text',
                'options',
                $options
            ),
            self::makeLabel(
                $labelClass,
                'regmenu',
                _('Show with')
            ) => self::getClass('PXEMenuOptionsManager')->regSelect(
                $regmenu,
                'regmenu'
            ),
            self::makeLabel(
                $labelClass,
                'isDefault',
                _('Default Choice')
            ) => self::makeInput(
                'default-choice',
                'default',
                '',
                'checkbox',
                'isDefault',
                '',
                false,
                false,
                -1,
                -1,
                $default ? 'checked' : ''
            ),
            self::makeLabel(
                $labelClass,
                'hotkey',
                _('Hotkey Enabled')
            ) => self::makeInput(
                'hotkey-enabled',
                'hotkey',
                '',
                'checkbox',
                'hotkey',
                '',
                false,
                false,
                -1,
                -1,
                $hotkey ? 'checked' : ''
            ),
            self::makeLabel(
                $labelClass,
                'keysequence',
                _('Menu Keysequence')
            ) => self::makeInput(
                'form-control ipxekey-input',
                'keysequence',
                'w',
                'text',
                'keysequence',
                $keysequence
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'IPXE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Ipxe' => self::getClass('PXEMenuOptions')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ipxe-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="ipxe-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Ipxe Menu');
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
     * Presents for creating a new menu entry.
     *
     * @return void
     */
    public function addModal()
    {
        $ipxe = filter_input(INPUT_POST, 'ipxe');
        $description = filter_input(INPUT_POST, 'description');
        $params = filter_input(INPUT_POST, 'params');
        $options = filter_input(INPUT_POST, 'options');
        $regmenu = filter_input(INPUT_POST, 'regmenu');
        $default = isset($_POST['default']);
        $hotkey = isset($_POST['hotkey']);
        $keysequence = filter_input(INPUT_POST, 'keysequence');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ipxe',
                _('Menu Name')
            ) => self::makeInput(
                'form-control ipxename-input',
                'ipxe',
                'fog.customname',
                'text',
                'ipxe',
                $ipxe,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Menu Description')
            ) => self::makeTextarea(
                'form-control ipxedesc-input',
                'description',
                _('Some nice description, should be short.'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'params',
                _('Menu Parameters')
            ) => self::makeTextarea(
                'form-control ipxeparam-input',
                'params',
                "echo hello world\nsleep 3",
                'params',
                $params
            ),
            self::makeLabel(
                $labelClass,
                'options',
                _('Boot Options')
            ) => self::makeInput(
                'form-control ipxeoption-input',
                'options',
                'debug loglevel=7 isdebug=yes',
                'text',
                'options',
                $options
            ),
            self::makeLabel(
                $labelClass,
                'regmenu',
                _('Show with')
            ) => self::getClass('PXEMenuOptionsManager')->regSelect(
                $regmenu,
                'regmenu'
            ),
            self::makeLabel(
                $labelClass,
                'isDefault',
                _('Default Choice')
            ) => self::makeInput(
                'default-choice',
                'default',
                '',
                'checkbox',
                'isDefault',
                '',
                false,
                false,
                -1,
                -1,
                $default ? 'checked' : ''
            ),
            self::makeLabel(
                $labelClass,
                'hotkey',
                _('Hotkey Enabled')
            ) => self::makeInput(
                'hotkey-enabled',
                'hotkey',
                '',
                'checkbox',
                'hotkey',
                '',
                false,
                false,
                -1,
                -1,
                $hotkey ? 'checked' : ''
            ),
            self::makeLabel(
                $labelClass,
                'keysequence',
                _('Menu Keysequence')
            ) => self::makeInput(
                'form-control ipxekey-input',
                'keysequence',
                'w',
                'text',
                'keysequence',
                $keysequence
            )
        ];

        self::$HookManager->processEvent(
            'IPXE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Ipxe' => self::getClass('PXEMenuOptions')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=ipxe&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Creates the new menu item.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('IPXE_ADD_POST');
        $ipxe = trim(
            filter_input(INPUT_POST, 'ipxe')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $params = trim(
            filter_input(INPUT_POST, 'params')
        );
        $options = trim(
            filter_input(INPUT_POST, 'options')
        );
        $regmenu = trim(
            filter_input(INPUT_POST, 'regmenu')
        );
        $default = isset($_POST['default']);
        $hotkey = isset($_POST['hotkey']);
        $keysequence = trim(
            filter_input(INPUT_POST, 'keysequence')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('PXEMenuOptionsManager')
                ->exists($ipxe);
            if ($exists) {
                throw new Exception(
                    _('A menu entry already exists with this name!')
                );
            }
            $iPXE = self::getClass('PXEMenuOptions')
                ->set('name', $ipxe)
                ->set('description', $description)
                ->set('params', $params)
                ->set('args', $options)
                ->set('regMenu', $regmenu)
                ->set('default', intval($default))
                ->set('hotkey', intval($hotkey))
                ->set('keysequence', $keysequence);
            if ($default) {
                $iPXE->getManager()->update(
                    ['default' => 1],
                    '',
                    ['default' => 0]
                );
            }
            if (!$iPXE->save()) {
                $serverFault = true;
                throw new Exception(_('Add menu failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'IPXE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Menu added!'),
                    'title' => _('iPXE Menu Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'IPXE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('iPXE Menu Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=ipxe&sub=edit&id='
        //    . $iPXE->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'Ipxe' => &$iPXE,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($iPXE);
        echo $msg;
        exit;
    }
    /**
     * The iPXE general edit page.
     *
     * @return void
     */
    public function ipxeGeneral()
    {
        $ipxe = (
            filter_input(INPUT_POST, 'ipxe') ?:
            ($this->obj->get('name') ?: '')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            ($this->obj->get('description') ?: '')
        );
        $params = (
            filter_input(INPUT_POST, 'params') ?:
            ($this->obj->get('params') ?: '')
        );
        $options = (
            filter_input(INPUT_POST, 'options') ?:
            ($this->obj->get('args') ?: '')
        );
        $regmenu = (
            filter_input(INPUT_POST, 'regmenu') ?:
            ($this->obj->get('regMenu') ?: '')
        );
        $default = (
            isset($_POST['default']) ?:
            ($this->obj->get('default') ?: '')
        );
        $hotkey = (
            isset($_POST['hotkey']) ?:
            ($this->obj->get('hotkey') ?: '')
        );
        $keysequence = (
            filter_input(INPUT_POST, 'keysequence') ?:
            ($this->obj->get('keysequence') ?: '')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ipxe',
                _('Menu Name')
            ) => self::makeInput(
                'form-control ipxename-input',
                'ipxe',
                'fog.customname',
                'text',
                'ipxe',
                $ipxe,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Menu Description')
            ) => self::makeTextarea(
                'form-control ipxedesc-input',
                'description',
                _('Some nice description, should be short.'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'params',
                _('Menu Parameters')
            ) => self::makeTextarea(
                'form-control ipxeparam-input',
                'params',
                "echo hello world\nsleep 3",
                'params',
                $params
            ),
            self::makeLabel(
                $labelClass,
                'options',
                _('Boot Options')
            ) => self::makeInput(
                'form-control ipxeoption-input',
                'options',
                'debug loglevel=7 isdebug=yes',
                'text',
                'options',
                $options
            ),
            self::makeLabel(
                $labelClass,
                'regmenu',
                _('Show with')
            ) => self::getClass('PXEMenuOptionsManager')->regSelect(
                $regmenu,
                'regmenu'
            ),
            self::makeLabel(
                $labelClass,
                'isDefault',
                _('Default Choice')
            ) => self::makeInput(
                'default-choice',
                'default',
                '',
                'checkbox',
                'isDefault',
                '',
                false,
                false,
                -1,
                -1,
                $default ? 'checked' : ''
            ),
            self::makeLabel(
                $labelClass,
                'hotkey',
                _('Hotkey Enabled')
            ) => self::makeInput(
                'hotkey-enabled',
                'hotkey',
                '',
                'checkbox',
                'hotkey',
                '',
                false,
                false,
                -1,
                -1,
                $hotkey ? 'checked' : ''
            ),
            self::makeLabel(
                $labelClass,
                'keysequence',
                _('Menu Keysequence')
            ) => self::makeInput(
                'form-control ipxekey-input',
                'keysequence',
                'w',
                'text',
                'keysequence',
                $keysequence
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
            'IPXE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Ipxe' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ipxe-general-form',
            self::makeTabUpdateURL(
                'ipxe-general',
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
     * Actually updates the information.
     *
     * @return void
     */
    public function ipxeGeneralPost()
    {
        $ipxe = trim(
            filter_input(INPUT_POST, 'ipxe')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $params = trim(
            filter_input(INPUT_POST, 'params')
        );
        $options = trim(
            filter_input(INPUT_POST, 'options')
        );
        $regmenu = trim(
            filter_input(INPUT_POST, 'regmenu')
        );
        $default = isset($_POST['default']);
        $hotkey = isset($_POST['hotkey']);
        $keysequence = trim(
            filter_input(INPUT_POST, 'keysequence')
        );
        $exists = self::getClass('PXEMenuOptionsManager')
            ->exists($ipxe);
        if ($this->obj->get('name') != $ipxe && $exists) {
            throw new Exception(
                _('A menu entry already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $ipxe)
            ->set('description', $description)
            ->set('params', $params)
            ->set('args', $options)
            ->set('regMenu', $regmenu)
            ->set('default', intval($default))
            ->set('hotkey', intval($hotkey))
            ->set('keysequence', $keysequence);
        if ($default) {
            $this->obj->getManager()->update(
                ['default' => 1],
                '',
                ['default' => 0]
            );
        }
    }
    /**
     * Edit this menu item.
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

        $tabData[] = [
            'name' => _('General'),
            'id' => 'ipxe-general',
            'generator' => function () {
                $this->ipxeGeneral();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Submit save/update the menu item.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'IPXE_EDIT_POST',
            ['Ipxe' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'ipxe-general':
                    $this->ipxeGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Menu update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'IPXE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Menu updated!'),
                    'title' => _('Menu Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'IPXE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Menu Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $event,
            [
                'Ipxe' => &$this->obj,
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
