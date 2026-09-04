<?php
/**
 * The Bootmenu Management Page
 *
 * PHP version 7.4+
 *
 * @category IpxeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Items\PXEMenuOptions;
use FOG\Managers\PXEMenuOptionsManager;

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
        $this->name = _('iPXE Management');
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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $ipxe = filter_input(INPUT_POST, 'ipxe');
        $description = filter_input(INPUT_POST, 'description');
        $params = filter_input(INPUT_POST, 'params');
        $options = filter_input(INPUT_POST, 'options');
        $regmenu = filter_input(INPUT_POST, 'regmenu');
        $default = isset($_POST['default']);
        $hotkey = isset($_POST['hotkey']);
        $keysequence = filter_input(INPUT_POST, 'keysequence');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
            ) => (new PXEMenuOptionsManager())->regSelect(
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
    }
    /**
     * Presents for creating a new menu item.
     *
     * @return void
     */
    public function add()
    {
        $fields = $this->_addFields();

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'IPXE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Ipxe' => new PXEMenuOptions()
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderCreateForm(
            'ipxe',
            [[_('Create New Ipxe Menu'), $rendered]],
            $buttons
        );
    }
    /**
     * Presents for creating a new menu entry.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'ipxe',
            'IPXE_ADD_FIELDS',
            'Ipxe',
            'PXEMenuOptions'
        );
    }
    /**
     * Creates the new menu item.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Ipxe',
            'IPXE_ADD',
            _('Menu added!'),
            _('iPXE Menu Create Success'),
            _('iPXE Menu Create Fail'),
            function (&$serverFault) {
                $ipxe = trim(
                    (string)filter_input(INPUT_POST, 'ipxe')
                );
                $description = trim(
                    (string)filter_input(INPUT_POST, 'description')
                );
                $params = trim(
                    (string)filter_input(INPUT_POST, 'params')
                );
                $options = trim(
                    (string)filter_input(INPUT_POST, 'options')
                );
                $regmenu = trim(
                    (string)filter_input(INPUT_POST, 'regmenu')
                );
                $default = isset($_POST['default']);
                $hotkey = isset($_POST['hotkey']);
                $keysequence = trim(
                    (string)filter_input(INPUT_POST, 'keysequence')
                );
                $exists = (new PXEMenuOptionsManager())
                    ->exists($ipxe);
                if ($exists) {
                    throw new \Exception(
                        _('A menu entry already exists with this name!')
                    );
                }
                $iPXE = (new PXEMenuOptions())
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
                    throw new \Exception(_('Add menu failed!'));
                }
                return $iPXE;
            }
        );
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

        $labelClass = 'col-sm-3 col-form-label';

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
            ) => (new PXEMenuOptionsManager())->regSelect(
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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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

        $this->renderGeneralForm('ipxe', $rendered, $buttons);
    }
    /**
     * Actually updates the information.
     *
     * @return void
     */
    public function ipxeGeneralPost()
    {
        self::checkAuthAndCSRF();
        $ipxe = trim(
            (string)filter_input(INPUT_POST, 'ipxe')
        );
        $description = trim(
            (string)filter_input(INPUT_POST, 'description')
        );
        $params = trim(
            (string)filter_input(INPUT_POST, 'params')
        );
        $options = trim(
            (string)filter_input(INPUT_POST, 'options')
        );
        $regmenu = trim(
            (string)filter_input(INPUT_POST, 'regmenu')
        );
        $default = isset($_POST['default']);
        $hotkey = isset($_POST['hotkey']);
        $keysequence = trim(
            (string)filter_input(INPUT_POST, 'keysequence')
        );
        $exists = (new PXEMenuOptionsManager())
            ->exists($ipxe);
        if ($this->obj->get('name') != $ipxe && $exists) {
            throw new \Exception(
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
        $tabData = [];

        $tabData[] = [
            'name' => _('General'),
            'id' => 'ipxe-general',
            'generator' => function () {
                $this->ipxeGeneral();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Submit save/update the menu item.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Ipxe',
            'IPXE_EDIT',
            _('Menu updated!'),
            _('Menu Update Success'),
            _('Menu Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'ipxe-general':
                        $this->ipxeGeneralPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Menu update failed!'));
                }
            }
        );
    }
}
