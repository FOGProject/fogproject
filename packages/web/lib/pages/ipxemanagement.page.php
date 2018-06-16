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
                _('Menu Boot Options')
            ) => self::makeInput(
                'form-control ipxeoption-input',
                'options',
                'debug loglevel=7 isdebug=yes',
                'text',
                'options',
                $options
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
    }
}
