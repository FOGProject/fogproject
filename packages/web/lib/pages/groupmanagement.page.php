<?php
/**
 * Group management page
 *
 * PHP version 5
 *
 * The group represented to the GUI
 *
 * @category GroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Group management page
 *
 * The group represented to the GUI
 *
 * @category GroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupManagement extends FOGPage
{
    /**
     * The node that uses this class
     *
     * @var string
     */
    public $node = 'group';
    /**
     * Initializes the group page
     *
     * @param string $name the name to construct with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Group Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Members')
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
    }
    /**
     * Create a new group.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Group');

        $group = filter_input(INPUT_POST, 'group');
        $description = filter_input(INPUT_POST, 'description');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');

        $labelClass = 'col-sm-3 control-label';

        // The fields to display
        $fields = [
            self::makeLabel(
                $labelClass,
                'group',
                _('Group Name')
            ) => self::makeInput(
                'form-control groupname-input',
                'group',
                _('Group Name'),
                'text',
                'group',
                $group,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control groupdescription-input',
                'description',
                _('Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Group Kernel')
            ) => self::makeInput(
                'form-control groupkernel-input',
                'kernel',
                'customBzimage',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Group Kernel Arguments')
            ) => self::makeInput(
                'form-control groupkernelargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Group Init')
            ) => self::makeInput(
                'form-control groupinit-input',
                'init',
                'customInit.xz',
                'text',
                'init',
                $init
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Group Primary Disk')
            ) => self::makeInput(
                'form-control groupdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'GROUP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Group' => self::getClass('Group')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'group-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="group-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Group');
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
     * Create a new group.
     *
     * @return void
     */
    public function addModal()
    {
        $group = filter_input(INPUT_POST, 'group');
        $description = filter_input(INPUT_POST, 'description');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');

        $labelClass = 'col-sm-3 control-label';

        // The fields to display
        $fields = [
            self::makeLabel(
                $labelClass,
                'group',
                _('Group Name')
            ) => self::makeInput(
                'form-control groupname-input',
                'group',
                _('Group Name'),
                'text',
                'group',
                $group,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control groupdescription-input',
                'description',
                _('Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Group Kernel')
            ) => self::makeInput(
                'form-control groupkernel-input',
                'kernel',
                'customBzimage',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Group Kernel Arguments')
            ) => self::makeInput(
                'form-control groupkernelargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Group Init')
            ) => self::makeInput(
                'form-control groupinit-input',
                'init',
                'customInit.xz',
                'text',
                'init',
                $init
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Group Primary Disk')
            ) => self::makeInput(
                'form-control groupdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            )
        ];

        self::$HookManager->processEvent(
            'GROUP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Group' => self::getClass('Group')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=group&sub=add',
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
        self::$HookManager->processEvent('GROUP_ADD_POST');
        $group = trim(
            filter_input(INPUT_POST, 'group')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $kernel = trim(
            filter_input(INPUT_POST, 'kernel')
        );
        $args = trim(
            filter_input(INPUT_POST, 'args')
        );
        $init = trim(
            filter_input(INPUT_POST, 'init')
        );
        $dev = trim(
            filter_input(INPUT_POST, 'dev')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('GroupManager')
                ->exists($group);
            if ($exists) {
                throw new Exception(
                    _('A group already exists with this name!')
                );
            }
            $Group = self::getClass('Group')
                ->set('name', $group)
                ->set('description', $description)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $args)
                ->set('kernelDevice', $dev)
                ->set('init', $init);
            if (!$Group->save()) {
                $serverFault = true;
                throw new Exception(_('Add group failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'GROUP_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Group added!'),
                    'title' => _('Group Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'GROUP_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=group&sub=edit&id='
        //    . $Group->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'Group' => &$Group,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($Group);
        echo $msg;
        exit;
    }
    /**
     * Displays the group general tab.
     *
     * @return void
     */
    public function groupGeneral()
    {
        $exitNorm = Setting::buildExitSelector(
            'bootTypeExit',
            filter_input(INPUT_POST, 'bootTypeExit'),
            true,
            'bootTypeExit'
        );
        $exitEfi = Setting::buildExitSelector(
            'efiBootTypeExit',
            filter_input(INPUT_POST, 'efiBootTypeExit'),
            true,
            'efiBootTypeExit'
        );
        $group = (
            filter_input(INPUT_POST, 'group') ?:
            ($this->obj->get('name') ?: '')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            ($this->obj->get('description') ?: '')
        );
        $productKey = filter_input(INPUT_POST, 'key');
        $kernel = (
            filter_input(INPUT_POST, 'kernel') ?:
            ($this->obj->get('kernel') ?: '')
        );
        $args = (
            filter_input(INPUT_POST, 'args') ?:
            ($this->obj->get('kernelArgs') ?: '')
        );
        $init = (
            filter_input(INPUT_POST, 'init') ?:
            ($this->obj->get('init') ?: '')
        );
        $dev = (
            filter_input(INPUT_POST, 'dev') ?:
            ($this->obj->get('kernelDevice') ?: '')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'group',
                _('Group Name')
            ) => self::makeInput(
                'form-control groupname-input',
                'group',
                _('Group Name'),
                'text',
                'group',
                $group,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Group Description')
            ) => self::makeTextarea(
                'form-control groupdescription-input',
                'description',
                _('Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Group Product Key')
            ) => self::makeInput(
                'form-control groupkey-input',
                'key',
                'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                'text',
                'key',
                $productKey,
                false,
                false,
                -1,
                29,
                'exactlength="25"'
            ),
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Group Kernel')
            ) => self::makeInput(
                'form-control groupkernel-input',
                'kernel',
                'customBzimage',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Group Kernel Arguments')
            ) => self::makeInput(
                'form-control groupkernelargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Group Init')
            ) => self::makeInput(
                'form-control groupinit-input',
                'init',
                'customInit.xz',
                'text',
                'init',
                $init
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Group Primary Disk')
            ) => self::makeInput(
                'form-control groupdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            ),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Group BIOS Exit')
            ) => $exitNorm,
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Group EFI Exit')
            ) => $exitEfi
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= '<div class="btn-group pull-left">';
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger'
        );
        $buttons .= self::makeButton(
            'reset-encryption-data',
            _('Reset Encryption Data'),
            'btn btn-warning'
        );
        $buttons .= '</div>';

        self::$HookManager->processEvent(
            'GROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Group' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $modalresetBtn = self::makeButton(
            'resetencryptionConfirm',
            _('Confirm'),
            'btn btn-outline pull-right',
            ' method="post" action="../management/index.php?sub=clearAES" '
        );
        $modalresetBtn .= self::makeButton(
            'resetencryptionCancel',
            _('Cancel'),
            'btn btn-outline pull-left'
        );
        $modalreset = self::makeModal(
            'resetencryptionmodal',
            _('Reset Encryption Data'),
            _(
                'Resetting encryption data should only be done '
                . 'if you re-installed the FOG Client or are using Debugger'
            ),
            $modalresetBtn,
            '',
            'warning'
        );
        echo self::makeFormTag(
            'form-horizontal',
            'group-general-form',
            self::makeTabUpdateURL(
                'group-general',
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
        echo $modalreset;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Group general post element
     *
     * @return void
     */
    public function groupGeneralPost()
    {
        $group = trim(
            filter_input(INPUT_POST, 'group')
        );
        $desc = trim(
            filter_input(INPUT_POST, 'description')
        );
        $key = strtoupper(
            trim(
                filter_input(INPUT_POST, 'key')
            )
        );
        $productKey = preg_replace(
            '/([\w+]{5})/',
            '$1-',
            str_replace(
                '-',
                '',
                $key
            )
        );
        $productKey = substr($productKey, 0, 29);
        $kernel = trim(
            filter_input(INPUT_POST, 'kernel')
        );
        $args = trim(
            filter_input(INPUT_POST, 'args')
        );
        $dev = trim(
            filter_input(INPUT_POST, 'dev')
        );
        $init = trim(
            filter_input(INPUT_POST, 'init')
        );
        $bte = trim(
            filter_input(INPUT_POST, 'bootTypeExit')
        );
        $ebte = trim(
            filter_input(INPUT_POST, 'efiBootTypeExit')
        );
        if ($group != $this->obj->get('name')) {
            if ($this->obj->getManager()->exists($group)) {
                throw new Exception(_('Please use another group name'));
            }
        }
        // Set the group relative items.
        $this->obj
            ->set('name', $group)
            ->set('description', $desc)
            ->set('kernel', $kernel)
            ->set('kernelArgs', $args)
            ->set('kernelDevice', $dev)
            ->set('init', $init);

        // Same but set all hosts in this group
        self::getClass('HostManager')
            ->update(
                ['id' => $this->obj->get('hosts')],
                '',
                [
                    'kernel' => $kernel,
                    'kernelArgs' => $args,
                    'kernelDevice' => $dev,
                    'init' => $init,
                    'biosexit' => $bte,
                    'efiexit' => $ebte,
                    'productKey' => trim($productKey)
                ]
            );
    }
    /**
     * Prints the group image element.
     *
     * @return void
     */
    public function groupImage()
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-image',
                $this->obj->get('id')
            )
            . '" ';
        $image = filter_input(INPUT_POST, 'image');
        // Group Images
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image, 'image');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'image',
                _('Group Image')
            ) => $imageSelector
        ];

        $buttons = self::makeButton(
            'group-image-send',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );

        self::$HookManager->processEvent(
            'GROUP_IMAGE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Group' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Image Association');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group image post element
     *
     * @return void
     */
    public function groupImagePost()
    {
        if (isset($_POST['confirmimage'])) {
            $image = trim(
                filter_input(INPUT_POST, 'image')
            );
            $this->obj->addImage($image);
        }
    }
    /**
     * Group active directory post element
     *
     * @return void
     */
    public function groupADPost()
    {
        $useAD = isset($_POST['domain']);
        $domain = trim(
            filter_input(
                INPUT_POST,
                'domainname'
            )
        );
        $ou = trim(
            filter_input(
                INPUT_POST,
                'ou'
            )
        );
        $user = trim(
            filter_input(
                INPUT_POST,
                'domainuser'
            )
        );
        $pass = trim(
            filter_input(
                INPUT_POST,
                'domainpassword'
            )
        );
        $this->obj->setAD(
            $useAD,
            $domain,
            $ou,
            $user,
            $pass
        );
    }
    /**
     * Group hosts display.
     *
     * @return void
     */
    public function groupHosts()
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
                'group-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'group-host-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'group-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'group-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update the group hosts.
     *
     * @return void
     */
    public function groupHostPost()
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
     * Group printers display.
     *
     * @return void
     */
    public function groupPrinters()
    {
        // Printer Associations
        $this->headerData = [
            _('Printer Name')
        ];
        $this->attributes = [
            []
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-printer',
                $this->obj->get('id')
            )
            . '" ';
        $buttons = self::makeButton(
            'group-printer-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'group-printer-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Printer Assignment');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('This will perform the action on all hosts in this group');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'group-printer-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('printer');
        echo '</div>';
        echo '</div>';

        // DEFAULT Printer
        $buttons = self::makeButton(
            'group-printer-default-send',
            _('Update'),
            'btn btn-info pull-right',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Default Printer');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('This will add and set '
            . '(as needed) the default printer for all hosts in this group');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<span id="printerselector"></span>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';

        // =========================================================
        // Printer Configuration
        $printerLevel = filter_input(INPUT_POST, 'level');
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Printer Configuration');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('This will set the configuration level to all hosts in this group');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div class="radio">';
        echo self::makeLabel(
            '',
            'noLevel',
            self::makeInput(
                'printer-nolevel',
                'level',
                '',
                'radio',
                'noLevel',
                '0',
                false,
                false,
                -1,
                -1,
                ($printerLevel == 0 ? 'checked' : '')
            )
            . ' '
            . _('No Printer Management'),
            'data-toggle="tooltip" data-placement="right" title="'
            . _(
                'This setting turns off all FOG Printer Management. '
                . 'Although there are multiple levels already, this '
                . 'is just another level if needed.'
            )
            . '"'
        );
        echo '</div>';
        echo '<div class="radio">';
        echo self::makeLabel(
            '',
            'addlevel',
            self::makeInput(
                'printer-addlevel',
                'level',
                '',
                'radio',
                'addlevel',
                '1',
                false,
                false,
                -1,
                -1,
                ($printerLevel == 1 ? 'checked' : '')
            )
            . ' '
            . _('Add/Remove Managed Printers'),
            'data-toggle="tooltip" data-placement="right" title="'
            . _(
                'This setting only adds and removes '
                . 'printers that are managed by FOG. '
                . 'If the printer exists in printer '
                . 'management but is not assigned to a '
                . 'host, it will remove the printer if '
                . 'it exists on the unassigned host. '
                . 'It will add printers to the host '
                . 'that are assigned.'
            )
            . '"'
        );
        echo '</div>';
        echo '<div class="radio">';
        echo self::makeLabel(
            '',
            'alllevel',
            self::makeInput(
                'printer-alllevel',
                'level',
                '',
                'radio',
                'alllevel',
                '2',
                false,
                false,
                -1,
                -1,
                ($printerLevel == 2 ? 'checked' : '')
            )
            . ' '
            . _('All Printers'),
            'data-toggle="tooltip" data-placement="right" title="'
            . _(
                'This setting will only allow FO GAssigned '
                . 'printers to be added to the host. Any '
                . 'printer that is not assigned will be '
                . 'removed including non-FOG managed printers.'
            )
            . '"'
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo self::makeButton(
            'printer-config-send',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group Printer Post.
     *
     * @return void
     */
    public function groupPrinterPost()
    {
        if (isset($_POST['confirmadd'])) {
            $printers = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $printers = $printers['additems'];
            if (count($printers ?: []) > 0) {
                $this->obj->addPrinter($printers);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $printers = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $printers = $printers['remitems'];
            if (count($printers ?: []) > 0) {
                $this->obj->removePrinter($printers);
            }
        }
        if (isset($_POST['confirmdefault'])) {
            $default = filter_input(INPUT_POST, 'default');
            $this->obj->addPrinter($default);
            $this->obj->updateDefault(
                filter_input(
                    INPUT_POST,
                    'default'
                )
            );
        }
        if (isset($_POST['confirmlevelup'])) {
            $level = filter_input(INPUT_POST, 'level');
            self::getClass('HostManager')->update(
                ['id' => $this->get('hosts')],
                '',
                ['printerLevel' => $level]
            );
        }
    }
    /**
     * Group snapins.
     *
     * @return void
     */
    public function groupSnapins()
    {
        $this->headerData = [
            _('Snapin Name')
        ];
        $this->attributes = [
            []
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-snapin',
                $this->obj->get('id')
            )
            . '" ';
        $buttons = self::makeButton(
            'group-snapin-send',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'group-snapin-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Snapin Assignment');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('This will perform the action on all hosts in this group');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $this->render(12, 'group-snapin-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('snapin');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group snapin post
     *
     * @return void
     */
    public function groupSnapinPost()
    {
        if (isset($_POST['confirmadd'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['additems'];
            if (count($snapins ?: []) > 0) {
                $this->obj->addSnapin($snapins);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['remitems'];
            if (count($snapins ?: []) > 0) {
                $this->obj->removeSnapin($snapins);
            }
        }
    }
    /**
     * Display's the group service stuff
     *
     * @return void
     */
    public function groupModules()
    {
        // Association Area
        $this->headerData = [
            _('Module Name')
        ];
        $this->attributes = [
            []
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-module',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'group-module-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'group-module-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Module Associations');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Disabled items are not displayed. Legacy items are removed.');
        echo '<br/>';
        echo _('Action will be perform on all hosts within this group');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'group-module-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('module');
        echo '</div>';
        echo '</div>';

        $labelClass = 'col-sm-3 control-label';
        // Display Manager area
        $dispEnabled = self::getSEtting('FOG_CLIENT_DISPLAYMANAGER_ENABLED');
        if ($dispEnabled) {
            $buttons = self::makeButton(
                'group-displayman-send',
                _('Update'),
                'btn btn-primary pull-right',
                $props
            );
            list(
                $gr,
                $gx,
                $gy
            ) = self::getSetting(
                [
                    'FOG_CLIENT_DISPLAYMANAGER_R',
                    'FOG_CLIENT_DISPLAYMANAGER_X',
                    'FOG_CLIENT_DISPLAYMANAGER_Y'
                ]
            );
            // If the x, y, and/or r inputs are set.
            $x = filter_input(INPUT_POST, 'x');
            $y = filter_input(INPUT_POST, 'y');
            $r = filter_input(INPUT_POST, 'r');
            if (!$x) {
                // If x not set, set to global
                $x = $gx;
            }
            if (!$y) {
                // If y not set, set to global
                $y = $gy;
            }
            if (!$r) {
                // If r not set, set to global
                $r = $gr;
            }
            $names = [
                'x' => [
                    'width',
                    _('Screen Width')
                    . '<br/>('
                    . _('in pixels')
                    . ')'
                ],
                'y' => [
                    'height',
                    _('Screen Height')
                    . '<br/>('
                    . _('in pixels')
                    . ')'
                ],
                'r' => [
                    'refresh',
                    _('Screen Refresh Rate')
                    . '<br/>('
                    . _('in Hz')
                    . ')'
                ]
            ];
            foreach ($names as $name => &$get) {
                switch ($name) {
                case 'r':
                    $val = $r;
                    break;
                case 'x':
                    $val = $x;
                    break;
                case 'y':
                    $val = $y;
                }
                $fields[
                    self::makeLabel(
                        $labelClass,
                        $name,
                        $get[1]
                    )
                ] = self::makeInput(
                    'form-control',
                    $name,
                    '',
                    'number',
                    $name,
                    $val
                );
                unset($get);
            }

            self::$HookManager->processEvent(
                'GROUP_DISPLAYMAN_FIELDS',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Group' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);

            echo '<div class="box box-primary">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo _('Group Display Manager Settings');
            echo '</h4>';
            echo '</div>';
            echo '<div class="box-body">';
            echo self::makeFormTag(
                'form-horizontal',
                'group-displayman-form',
                self::makeTabUpdateURL(
                    'group-module',
                    $this->obj->get('id')
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo '</form>';
            echo '</div>';
            echo '<div class="box-footer with-border">';
            echo $buttons;
            echo '</div>';
            echo '</div>';
        }

        // Auto log out area
        $aloEnabled = self::getSetting('FOG_CLIENT_AUTOLOGOFF_ENABLED');
        if ($aloEnabled) {
            $buttons = self::makeButton(
                'group-alo-send',
                _('Update'),
                'btn btn-primary pull-right',
                $props
            );
            $tme = filter_input(INPUT_POST, 'tme');
            if (!$tme) {
                $tme = self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN');
            }
            if (!$tme) {
                $tme = 0;
            }
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'tme',
                    _('Auto Logout Time')
                    . '<br/>('
                    . _('in minutes')
                    . ')'
                ) => self::makeInput(
                    'form-control',
                    'tme',
                    '',
                    'number',
                    'tme',
                    $tme
                )
            ];

            self::$HookManager->processEvent(
                'GROUP_ALO_FIELDS',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Group' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);

            echo '<div class="box box-warning">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo _('Auto Logout Settings');
            echo '</h4>';
            echo '<p class="help-block">';
            echo _('Minimum time limmit for Auto Logout to become active is 5 minutes.');
            echo '</p>';
            echo '</div>';
            echo '<div class="box-body">';
            echo self::makeFormTag(
                'form-horizontal',
                'group-alo-form',
                self::makeTabUpdateURL(
                    'group-module',
                    $this->obj->get('id')
                ),
                'post',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo '</form>';
            echo '</div>';
            echo '<div class="box-footer with-border">';
            echo $buttons;
            echo '</div>';
            echo '</div>';
        }

        // Hostname change reboot/domain join reboot forced.
        $enforce = filter_input(INPUT_POST, 'enforce');
        $fields = [
            self::makeLabel(
                $labelClass,
                'enforce',
                _('Force Reboot')
            ) => self::makeInput(
                '',
                'enforce',
                '',
                'checkbox',
                'enforce',
                '',
                false,
                false,
                -1,
                -1,
                ($enforce ? 'checked' : '')
            )
        ];
        $buttons = self::makeButton(
            'group-enforce-send',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );

        self::$HookManager->processEvent(
            'GROUP_ENFORCE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Group' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo '<div class="box box-warning">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Enforce Hostname | AD Join Reboots');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _(
            'This tells the client to force reboots for host name '
            . 'changing and AD Joining.'
        );
        echo '</p>';
        echo '<p class="help-block">';
        echo _(
            'If disabled, the client will not make changes until all users '
            . 'are logged off'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo self::makeFormTag(
            'form-horizontal',
            'group-enforce-form',
            self::makeTabUpdateURL(
                'group-module',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Group Service post.
     *
     * @return void
     */
    public function groupModulePost()
    {
        if (isset($_POST['confirmadd'])) {
            $modules = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $modules = $modules['additems'];
            if (count($modules ?: [])) {
                $this->obj->addModule($modules);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $modules = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $modules = $modules['remitems'];
            if (count($modules ?: [])) {
                $this->obj->removeModule($modules);
            }
        }
        if (isset($_POST['confirmdisplaysend'])) {
            $x = (int)filter_input(INPUT_POST, 'x');
            $y = (int)filter_input(INPUT_POST, 'y');
            $r = (int)filter_input(INPUT_POST, 'r');
            $this->obj->setDisp($x, $y, $r);
        }
        if (isset($_POST['confirmalosend'])) {
            $tme = (int)filter_input(INPUT_POST, 'tme');
            if (!(is_numeric($tme) && $tme > 4)) {
                $tme = 0;
            }
            $this->obj->setAlo($tme);
        }
        if (isset($_POST['confirmenforcesend'])) {
            $enforce = (
                filter_input(INPUT_POST, $_POST['enforce']) >= 1 ?
                1 :
                0
            );
            self::getClass('HostManager')->update(
                ['id' => $this->obj->get('hosts')],
                '',
                ['enforce' => $enforce]
            );
        }
    }
    /**
     * Display the group PM stuff.
     *
     * @return void
     */
    public function groupPowermanagement()
    {
        $buttons = self::makeButton(
            'powermanagement-delete',
            _('Delete All'),
            'btn btn-danger pull-left'
        );
        $splitButtons = self::makeSplitButton(
            'scheduleBtn',
            _('Create New Scheduled'),
            [
                [
                    'id' => 'ondemandBtn',
                    'text' => _('Create New Immediate')
                ]
            ],
            'right',
            'primary'
        );
        $ondemandModalBtns = self::makeButton(
            'ondemandCancelBtn',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $ondemandModalBtns .= self::makeButton(
            'ondemandCreateBtn',
            _('Create'),
            'btn btn-outline pull-right'
        );
        $scheduleModalBtns = self::makeButton(
            'scheduleCancelBtn',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $scheduleModalBtns .= self::makeButton(
            'scheduleCreateBtn',
            _('Create'),
            'btn btn-outline pull-right'
        );
        $modaldeleteBtns = self::makeButton(
            'deletepowermanagementConfirm',
            _('Confirm'),
            'btn btn-ouline pull-right',
            ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-powermanagement',
                $this->obj->get('id')
            )
            . '" '
        );
        $modaldeleteBtns .= self::makeButton(
            'deletepowermanagementCancel',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $modalondemand = self::makeModal(
            'ondemandModal',
            _('Create Immediate Power task'),
            $this->newPMDisplay(true),
            $ondemandModalBtns,
            '',
            'info'
        );
        $modalschedule = self::makeModal(
            'scheduleModal',
            _('Create Scheduled Power task'),
            $this->newPMDisplay(false),
            $scheduleModalBtns,
            '',
            'primary'
        );
        $modaldelete = self::makeModal(
            'deletepowermanagementmodal',
            _('Delete All Powermanagement Items'),
            _(
                'This will delete all powermanagement '
                . 'items from all hosts in this group'
            ),
            $modaldeleteBtns,
            '',
            'warning'
        );
        echo '<!-- Power Management -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Power Management');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'Use the buttons below to create a new power management task to all '
            . 'hosts in this group.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $splitButtons;
        echo $modalondemand;
        echo $modalschedule;
        echo $modaldelete;
        echo '</div>';
        echo '</div>';
    }
    /**
     * Modify the power management stuff.
     *
     * @return void
     */
    public function groupPowermanagementPost()
    {
        $hostIDs = (array)$this->obj->get('hosts');
        if (isset($_POST['pmadd'])) {
            $onDemand = (int)isset($_POST['onDemand']);
            $min = filter_input(INPUT_POST, 'scheduleCronMin');
            $hour = filter_input(INPUT_POST, 'scheduleCronHour');
            $dom = filter_input(INPUT_POST, 'scheduleCronDOM');
            $month = filter_input(INPUT_POST, 'scheduleCronMonth');
            $dow = filter_input(INPUT_POST, 'scheduleCronDOW');
            $action = filter_input(INPUT_POST, 'action');
            if (!$action) {
                throw new Exception(_('You must select an action to perform'));
            }
            $items = [];
            if ($onDemand && $action === 'wol') {
                $this->obj->wakeOnLAN();
                return;
            }
            foreach ((array)$hostIDs as &$hostID) {
                $items[] = [
                    $hostID,
                    $min,
                    $hour,
                    $dom,
                    $month,
                    $dow,
                    $onDemand,
                    $action
                ];
                unset($hostID);
            }
            $fields = [
                'hostID',
                'min',
                'hour',
                'dom',
                'month',
                'dow',
                'onDemand',
                'action'
            ];
            if (count($items) > 0) {
                self::getClass('PowerManagementManager')
                    ->insertBatch($fields, $items);
            }
        }
        if (isset($_POST['pmdelete'])) {
            Route::deletemass(
                'powermanagement',
                ['hostID' => $hostIDs]
            );
        }
    }
    /**
     * Displays Group Host Inventories
     *
     * @return void
     */
    public function groupInventory()
    {
        // Get this group's Host Inventory items.
        Route::listem(
            'inventory',
            ['hostID' => $this->obj->get('hosts')],
            false,
            'AND',
            'hostID'
        );
        $inventories = json_decode(Route::getData());

        // Get the host names
        Route::ids(
            'host',
            ['id' => $this->obj->get('hosts')],
            'name',
            'AND',
            'id'
        );
        $hostnames = json_decode(Route::getData(), true);

        // Just to make the fields nice and formatted.
        $labelClass = 'col-sm-3 control-label';

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Host Inventories');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box box-body">';
        if (!count($hostnames)) {
            echo _('No hosts associated to this group yet');
            echo '</div>';
            echo '</div>';
            return;
        }
        // Loop and print the inventory data broken out by host names.
        foreach ($inventories->data as $i => &$inventory) {
            if (!isset($hostnames[$i])) {
                continue;
            }
            echo '<div class="panel box box-primary">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo '<a data-toggle="collapse" data-parent="#accordion" href="#'
                . $hostnames[$i]
                . '">';
            echo $hostnames[$i] . ' ' . _('Inventory Data');
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="'
                . $hostnames[$i]
                . '" class="panel collapse collapse">';
            $puser = $inventory->primaryUser;
            $other1 = $inventory->other1;
            $other2 = $inventory->other2;
            $sysman = $inventory->sysman;
            $sysprod = $inventory->sysproduct;
            $sysver = $inventory->sysversion;
            $sysser = $inventory->sysserial;
            $systype = $inventory->systype;
            $sysuuid = $inventory->sysuuid;
            $biosven = $inventory->biosvendor;
            $biosver = $inventory->biosversion;
            $biosdate = $inventory->biosversion;
            $mbman = $inventory->mbman;
            $mbprod = $inventory->mbproductname;
            $mbver = $inventory->mbversion;
            $mbser = $inventory->mbserial;
            $mbast = $inventory->mbasset;
            $cpuman = $inventory->cpuman;
            $cpuver = $inventory->cpuversion;
            $cpucur = $inventory->cpucurrent;
            $cpumax = $inventory->cpumax;
            $mem = $inventory->mem;
            $hdmod = $inventory->hdmodel;
            $hdfirm = $inventory->hdfirmware;
            $hdser = $inventory->hdserial;
            $caseman = $inventory->caseman;
            $casever = $inventory->casever;
            $caseser = $inventory->caseserial;
            $caseast = $inventory->caseasset;
            $fields = [
                self::makeLabel(
                    $labelClass,
                    'pu',
                    _('Primary User')
                ) => self::makeInput(
                    'form-control',
                    'pu',
                    '',
                    'text',
                    'pu',
                    $puser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'other1',
                    _('Other Tag #1')
                ) => self::makeInput(
                    'form-control',
                    'other1',
                    '',
                    'text',
                    'other1',
                    $other1,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    'other2',
                    _('Other Tag #2')
                ) => self::makeInput(
                    'form-control',
                    'other2',
                    '',
                    'text',
                    'other2',
                    $other2,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('System Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $sysman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('System Product')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $sysprod,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('System Version')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $sysver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('System Serial')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $sysser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('System UUID')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $sysuuid,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('System Type')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $systype,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('BIOS Vendor')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $biosven,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('BIOS Version')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $biosver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('BIOS Date')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $biosdate,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Motherboard Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $mbman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Motherboard Product Name')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $mbprod,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Motherboard Version')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $mbver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Motherboard Serial Number')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $mbser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Motherboard Asset Tag')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $mbast,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('CPU Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $cpuman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('CPU Version')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $cpuver,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('CPU Normal Speed')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $cpucur,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('CPU Max Speed')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $cpumax,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Memory')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $mem,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Hard Drive Model')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $hdmod,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Hard Drive Firmware')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $hdfirm,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Hard Drive Serial Number')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $hdser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Chassis Manufacturer')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $caseman,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Chassis Version')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $casever,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Chassis Serial Number')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $caseser,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                ),
                self::makeLabel(
                    $labelClass,
                    '',
                    _('Chassis Asset Tag')
                ) => self::makeInput(
                    'form-control',
                    '',
                    '',
                    'text',
                    '',
                    $caseast,
                    false,
                    false,
                    -1,
                    -1,
                    '',
                    true
                )
            ];
            $rendered = self::formFields($fields);
            unset($fields);
            echo '<div class="box-body">';
            echo self::makeFormTag(
                'form-horizontal',
                'group-inventory-form-' . $hostnames[$i],
                '#',
                'get',
                'application/x-www-form-urlencoded',
                true
            );
            echo $rendered;
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($inventory);
        }
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display Login History for Hosts in this Group
     *
     * @return void
     */
    public function groupLoginHistory()
    {
        $this->headerData = [
            _('Host Name'),
            _('Time'),
            _('Action'),
            _('Username'),
            _('Description')
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Login History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'group-login-history-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display Image History for Hosts in this Group
     *
     * @return void
     */
    public function groupImageHistory()
    {
        $this->headerData = [
            _('Host Name'),
            _('Engineer'),
            _('Start'),
            _('End'),
            _('Duration'),
            _('Image'),
            _('Type')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Image History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'group-image-history-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display Snapin History for Hosts in this Group
     *
     * @return void
     */
    public function groupSnapinHistory()
    {
        $this->headerData = [
            _('Host Name'),
            _('Snapin Name'),
            _('Start Time'),
            _('Complete'),
            _('Duration'),
            _('Return Code')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            []
        ];
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Group Snapin History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'group-snapin-history-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * The group edit display method
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
            'id' => 'group-general',
            'generator' => function () {
                $this->groupGeneral();
            }
        ];

        // Image
        $tabData[] = [
            'name' => _('Image'),
            'id' => 'group-image',
            'generator' => function () {
                $this->groupImage();
            }
        ];

        // Tasks
        $tabData[] = [
            'name' => _('Tasks'),
            'id' => 'group-tasks',
            'generator' => function () {
                $this->groupTasks();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Host Associations'),
                        'id' => 'group-host',
                        'generator' => function () {
                            $this->groupHosts();
                        }
                    ],
                    [
                        'name' => _('Printer Associations'),
                        'id' => 'group-printer',
                        'generator' => function () {
                            $this->groupPrinters();
                        }
                    ],
                    [
                        'name' => _('Snapin Associations'),
                        'id' => 'group-snapin',
                        'generator' => function () {
                            $this->groupSnapins();
                        }
                    ]
                ]
            ]
        ];

        // FOG Client settings.
        $tabData[] = [
            'tabs' => [
                'name' => _('Service Settings'),
                'tabData' => [
                    [
                        'name' => _('Client Settings'),
                        'id' => 'group-module',
                        'generator' => function () {
                            $this->groupModules();
                        }
                    ],
                    [
                        'name' => _('Active Directory'),
                        'id' => 'group-active-directory',
                        'generator' => function () {
                            $this->adFieldsToDisplay(
                                '',
                                '',
                                '',
                                '',
                                ''
                            );
                        }
                    ],
                    [
                        'name' => _('Power Management'),
                        'id' => 'group-powermanagement',
                        'generator' => function () {
                            $this->groupPowermanagement();
                        }
                    ]
                ]
            ]
        ];

        // Inventory
        $tabData[] = [
            'name' => _('Inventory'),
            'id' => 'group-inventory',
            'generator' => function () {
                $this->groupInventory();
            }
        ];

        // History Items
        $tabData[] = [
            'tabs' => [
                'name' => _('History Items'),
                'tabData' => [
                    [
                        'name' => _('Login History'),
                        'id' => 'group-login-history',
                        'generator' => function () {
                            $this->groupLoginHistory();
                        }
                    ],
                    [
                        'name' => _('Imaging History'),
                        'id' => 'group-imaging-history',
                        'generator' => function () {
                            $this->groupImageHistory();
                        }
                    ],
                    [
                        'name' => _('Snapin History'),
                        'id' => 'group-snapin-history',
                        'generator' => function () {
                            $this->groupSnapinHistory();
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
            'GROUP_EDIT_POST',
            ['Group' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'group-general':
                $this->groupGeneralPost();
                break;
            case 'group-image':
                $this->groupImagePost();
                break;
            case 'group-active-directory':
                $this->groupADPost();
                break;
            case 'group-powermanagement':
                $this->groupPowermanagementPost();
                break;
            case 'group-host':
                $this->groupHostPost();
                break;
            case 'group-printer':
                $this->groupPrinterPost();
                break;
            case 'group-snapin':
                $this->groupSnapinPost();
                break;
            case 'group-module':
                $this->groupModulePost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Group update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'GROUP_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Group updated!'),
                    'title' => _('Group Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'GROUP_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'Group' => &$this->obj,
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
     * The group tasks items.
     *
     * @return void
     */
    public function groupTasks()
    {
        // Predefine needed variables for closure function.
        global $id;
        $data = [];
        // The closure we want to use.
        $taskTypeIterator = function ($TaskType, $advanced) use (
            &$data,
            $id
        ) {
            if ($advanced != $TaskType->isAdvanced) {
                return;
            }
            $data['<a href="?node=group&sub=deploy&id='
                . $id
                . '&type='
                . $TaskType->id
                . '" class="taskitem"><i class="fa fa-'
                . $TaskType->icon
                . ' fa-2x"></i><br/>'
                . $TaskType->name
                . '</a>'
            ] = $TaskType->description;
        };
        // The keys we need to search for.
        $key = [
            'access' => [
                'group',
                'both'
            ]
        ];
        // The items we're getting.
        Route::listem(
            'tasktype',
            $key,
            false,
            'AND',
            'id'
        );
        $items = json_decode(Route::getData());
        // Loop 1, the basic non-advanced tasks.
        foreach ($items->data as &$TaskType) {
            $taskTypeIterator($TaskType, 0);
            unset($TaskType);
        }
        self::$HookManager->processEvent(
            'GROUP_BASICTASKS_DATA',
            ['data' => &$data]
        );
        $basic = self::stripedTable($data);

        $data = [];
        $advanced = 1;
        // Loop 2, the advanced tasks.
        foreach ($items->data as &$TaskType) {
            $taskTypeIterator($TaskType, 1);
            unset($TaskType);
        }
        self::$HookManager->processEvent(
            'GROUP_ADVANCEDTASKS_DATA',
            ['data' => &$data]
        );
        $advanced = self::stripedTable($data);
        unset($data);
        unset($items);
        $modalApprovalBtns = self::makeButton(
            'tasking-send',
            _('Create'),
            'btn btn-outline pull-right'
        );
        $modalApprovalBtns .= self::makeButton(
            'tasking-close',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $taskModal = self::makeModal(
            'task-modal',
            '<h4 class="box-title">'
            . _('Create new tasking')
            . '<span class="task-name"></span></h4>',
            '<div id="task-form-holder"></div>',
            $modalApprovalBtns,
            '',
            'success'
        );

        echo '<div class="box box-solid" id="host-tasks">';
        echo '<div class="box-body">';
        echo '<div id="taskAccordian" class="box-group">';

        // Basic Tasks
        echo '<div class="panel box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo '<a href="#tasksBasic" class="" data-toggle="collapse" '
            . 'data-parent="#taskAccordian">';
        echo _('Basic Tasks');
        echo '</a>';
        echo '</h4>';
        echo '</div>';
        echo '<div id="tasksBasic" class="panel-collapse collapse in">';
        echo '<div class="box-body">';
        echo '<table class="table table-striped">';
        echo '<tbody>';
        echo $basic;
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Advanced Tasks
        echo '<div class="panel box box-warning">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo '<a href="#tasksAdvanced" class="" data-toggle="collapse" '
            . 'data-parent="#taskAccordian">';
        echo _('Advanced Tasks');
        echo '</a>';
        echo '</h4>';
        echo '</div>';
        echo '<div id="tasksAdvanced" class="panel-collapse collapse">';
        echo '<div class="box-body">';
        echo '<table class="table table-striped">';
        echo '<tbody>';
        echo $advanced;
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $taskModal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Presents the hosts list table.
     *
     * @return void
     */
    public function getHostsList()
    {
        $join = [
            'LEFT OUTER JOIN `groupMembers` ON '
            . "`hosts`.`hostID` = `groupMembers`.`gmHostID` "
            . "AND `groupMembers`.`gmGroupID` = '" . $this->obj->get('id') . "'"
        ];

        $columns[] = [
            'db' => 'groupAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'host',
            'groupassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Presents the printers list table.
     *
     * @return void
     */
    public function getPrintersList()
    {
        Route::listem('printer');
        echo Route::getData();
        exit;
    }
    /**
     * Presents the selector for groups
     *
     * @return void
     */
    public function getPrintersSelect()
    {
        header('Content-tyep: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        $printerID = trim(filter_input(INPUT_GET, 'printerID'));

        Route::ids('printer', false);
        $printersAvail = json_decode(Route::getData(), true);
        if (!count($printersAvail ?: [])) {
            echo json_encode(
                [
                    'content' => _('No printers available to assign'),
                    'disablebtn' => true
                ]
            );
            exit;
        }
        Route::names('printer');
        $printerNames = json_decode(Route::getData());
        foreach ($printerNames as &$printer) {
            $printers[$printer->id] = $printer->name;
            unset($printer);
        }
        $printerSelector = self::selectForm(
            'printer',
            $printers,
            $printerID,
            true,
            '',
            true
        );
        echo json_encode(
            [
                'content' => $printerSelector,
                'disablebtn' => false
            ]
        );
        exit;
    }
    /**
     * Presents the snapins list table.
     *
     * @return void
     */
    public function getSnapinsList()
    {
        Route::listem('snapin');
        echo Route::getData();
        exit;
    }
    /**
     * Returns the module list as well as the associated
     * for the group being edited.
     *
     * @return void
     */
    public function getModulesList()
    {
        $moduleName = self::getGlobalModuleStatus();
        $keys = [];
        foreach ((array)$moduleName as $short_name => $bool) {
            if ($bool) {
                $keys[] = $short_name;
            }
        }
        $notWhere = [
            'clientupdater',
            'dircleanup',
            'greenfog',
            'usercleanup'
        ];
        $keys = array_diff($keys, $notWhere);

        Route::listem('module', ['shortName' => $keys]);
        echo Route::getData();
        exit;
    }
    /**
     * Tasking for this group.
     *
     * @return void
     */
    public function deploy()
    {
        header('Content-type: application/json');
        global $type;
        global $id;

        try {
            if (!is_numeric($type) || $type < 1) {
                $type = 1;
            }

            $TaskType = new TaskType($type);

            $this->title = $TaskType->get('name')
                . ' '
                . $this->obj->get('name');

            $imagingTypes = $TaskType->isImagingTask();
            $iscapturetask = $TaskType->isCapture();
            $issnapintask = $TaskType->isSnapinTasking();
            $isinitneeded = $TaskType->isInitNeededTasking();
            $isdebug = $TaskType->isDebug();
            $hosts = $this->obj->get('hosts');

            if (!$TaskType->isValid()) {
                throw new Exception(_('Task type is invalid'));
            }
            if (count($hosts ?: []) < 1) {
                throw new Exception(_('There are no hosts to task'));
            }
            if ($iscapturetask) {
                throw new Exception(_('Groups cannot create capture tasks'));
            }

            $labelClass = 'col-sm-3 control-label';

            $fields = [];

            if ($issnapintask
                && TaskType::SINGLE_SNAPIN == $type
            ) {
                $snapinSelector = self::getClass('SnapinManager')
                    ->buildSelectBox('', 'snapin');
                $fields[
                    self::makeLabel(
                        $labelClass,
                        'snapin',
                        _('Select Snapin to run')
                    )
                ] = $snapinSelector;
            } elseif (TaskType::PASSWORD_RESET == $type) {
                $fields [
                    self::makeLabel(
                        $labelClass,
                        'account',
                        _('Account Name')
                    )
                ] = self::makeInput(
                    'form-control',
                    'account',
                    'Administrator',
                    'text',
                    'account',
                    '',
                    true
                );
            }
            if ($isinitneeded
                && !$isdebug
            ) {
                $shutdownchecked = self::getSetting(
                    'FOG_TASKING_ADV_SHUTDOWN_ENABLED'
                ) ? ' checked' : '';
                $fields = self::fastmerge(
                    $fields,
                    [
                        '<div class="hideFromDebug">'
                        . self::makeLabel(
                            $labelClass,
                            'shutdown',
                            _('Shutdown when complete')
                        ) => self::makeInput(
                            '',
                            'shutdown',
                            '',
                            'checkbox',
                            'shutdown',
                            '',
                            false,
                            false,
                            -1,
                            -1,
                            $shutdownchecked
                        )
                        . '</div>'
                    ]
                );
            }
            if (TaskType::WAKE_UP != $type) {
                $wolchecked = self::getSetting(
                    'FOG_TASKING_ADV_WOL_ENABLED'
                ) ? ' checked' : '';
                $fields = self::fastmerge(
                    $fields,
                    [
                        self::makeLabel(
                            $labelClass,
                            'wol',
                            _('Wake Up')
                        ) => self::makeInput(
                            '',
                            'wol',
                            '',
                            'checkbox',
                            'wol',
                            '',
                            false,
                            false,
                            -1,
                            -1,
                            $wolchecked
                        )
                    ]
                );
            }
            if (TaskType::PASSWORD_RESET != $type
                && !$isdebug
                && $isinitneeded
            ) {
                $debugchecked = self::getSetting(
                    'FOG_TASKING_ADV_DEBUG_ENABLED'
                ) ? ' checked' : '';
                $fields = self::fastmerge(
                    $fields,
                    [
                        self::makeLabel(
                            $labelClass,
                            'checkdebug',
                            _('Debug Task')
                        ) => self::makeInput(
                            '',
                            'isDebugTask',
                            '',
                            'checkbox',
                            'checkdebug',
                            '',
                            false,
                            false,
                            -1,
                            -1,
                            $debugchecked
                        )
                    ]
                );
            }
            $fields = self::fastmerge(
                $fields,
                [
                    self::makeLabel(
                        $labelClass,
                        'instant',
                        _('Schedule Immediately')
                    ) => self::makeInput(
                        'instant',
                        'scheduleType',
                        '',
                        'radio',
                        'instant',
                        'instant',
                        false,
                        false,
                        -1,
                        -1,
                        ' checked'
                    )
                ]
            );
            if (!$isdebug
                && TaskType::PASSWORD_RESET != $type
            ) {
                $fields = self::fastmerge(
                    $fields,
                    [
                        '<div class="hideFromDebug">'
                        . self::makeLabel(
                            $labelClass,
                            'delayed',
                            _('Schedule Later')
                        ) => self::makeInput(
                            'delayed',
                            'scheduleType',
                            '',
                            'radio',
                            'delayed',
                            'single'
                        )
                        . '</div>',
                        '<div class="delayedinput hidden">'
                        . self::makeLabel(
                            $labelClass,
                            'delayedinput',
                            _('Start Time')
                        ) => self::makeInput(
                            'form-control',
                            'scheduleSingleTime',
                            self::niceDate()->format('Y-m-d H:i:s'),
                            'text',
                            'delayedinput',
                            ''
                        )
                        . '</div>',
                        '<div class="hideFromDebug">'
                        . self::makeLabel(
                            $labelClass,
                            'cron',
                            _('Schedule Crontab Style')
                        ) => self::makeInput(
                            'croninput',
                            'scheduleType',
                            '',
                            'radio',
                            'cron',
                            'cron'
                        )
                        . '</div>',
                        '<div class="croninput hidden">'
                        . self::makeLabel(
                            $labelClass,
                            '',
                            _('Cron Entry')
                        ) => '<div class="croninput fogcron hidden"></div><br/>'
                        . self::makeInput(
                            'col-sm-2 croninput cronmin hidden',
                            'scheduleCronMin',
                            _('min'),
                            'text',
                            'cronMin'
                        )
                        . self::makeInput(
                            'col-sm-2 croninput cronhour hidden',
                            'scheduleCronHour',
                            _('hour'),
                            'text',
                            'cronHour'
                        )
                        . self::makeInput(
                            'col-sm-2 croninput crondom hidden',
                            'scheduleCronDOM',
                            _('day'),
                            'text',
                            'cronDom'
                        )
                        . self::makeInput(
                            'col-sm-2 croninput cronmonth hidden',
                            'scheduleCronMonth',
                            _('month'),
                            'text',
                            'cronMonth'
                        )
                        . self::makeInput(
                            'col-sm-2 croninput crondow hidden',
                            'scheduleCronDOW',
                            _('weekday'),
                            'text',
                            'cronDow'
                        )
                        . '</div>'
                    ]
                );
            }

            self::$HookManager->processEvent(
                'GROUP_CREATE_TASKING',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Group' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);
            ob_start();
            echo self::makeFormTag(
                'form-horizontal',
                'group-deploy-form',
                $this->formAction,
                'post',
                'application/x-www-form-url-encoded',
                true
            );
            echo $rendered;
            echo '</form>';
            $msg = json_encode(
                [
                    'msg' => ob_get_clean(),
                    'title' => _('Create task form success')
                ]
            );
            $code = HTTPResponseCodes::HTTP_SUCCESS;
        } catch (Exception $e) {
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create task form fail')
                ]
            );
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Actually creates the tasking.
     *
     * @return void
     */
    public function deployPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('GROUP_DEPLOY_POST');

        $serverFault = false;
        try {
            global $type;
            if (!is_numeric($type) && $type > 0) {
                $type = 1;
            }
            // Host checks:
            $hosts = $this->obj->get('hosts');
            $find = [
                'id' => $hosts,
                'pending' => ['', 0]
            ];
            Route::ids(
                'host',
                $find
            );
            $hosts = json_decode(
                Route::getData(),
                true
            );
            if (count($hosts ?: []) < 1) {
                throw new Exception(_('No hosts available to be tasked'));
            }
            $nhosts = [];
            $hostImages = [];
            Route::listem(
                'host',
                ['id' => $hosts]
            );
            $Hosts = json_decode(
                Route::getData()
            );
            foreach ($Hosts->data as &$host) {
                if (!$host->imageID) {
                    continue;
                }
                $nhosts[] = $host->id;
                $hostImages[] = $host->imageID;
                unset($host);
            }
            if (count($nhosts ?: []) < 1) {
                throw new Exception(_('No hosts are assigned an image'));
            }

            // Multicast task requires all hosts in the group to have the same
            // imageID set.
            if (TaskType::MULTICAST == $type) {
                $hostImages = array_filter(
                    array_unique(
                        $hostImages
                    )
                );
                if (count($hostImages ?: []) != 1) {
                    throw new Exception(
                        _('All hosts must have the same image assigned')
                    );
                }
            }

            // Task Type setup
            $TaskType = new TaskType($type);
            if (!$TaskType->isValid()) {
                throw new Exception(_('Task Type is invalid'));
            }

            // Password reset setup
            $passreset = trim(
                filter_input(INPUT_POST, 'account')
            );
            if (TaskType::PASSWORD_RESET == $type
                && !$passreset
            ) {
                throw new Exception(_('Password reset requires a user account'));
            }

            // Snapin setup
            $enableSnapins = (int)filter_input(INPUT_POST, 'snapin');
            if (0 === $enableSnapins) {
                $enableSnapins = -1;
            }
            if (TaskType::DEPLOY_NO_SNAPINS === $type || $enableSnapins < -1) {
                $enableSnapins = 0;
            }

            // Generic setup
            $imagingTasks = $TaskType->isImagingTask();
            $taskName = sprintf(
                '%s Task',
                $TaskType->get('name')
            );

            // Shutdown setup
            $shutdown = isset($_POST['shutdown']);
            if ($shutdown) {
                $enableShutdown = true;
            }

            // Debug setup
            $enableDebug = false;
            $debug = isset($_POST['debug']);
            $isdebug = isset($_POST['isDebugTask']);
            if ($debug || $isdebug) {
                $enableDebug = true;
            }

            // WOL Setup
            $wol = false;
            $wolon = isset($_POST['wol']);
            if (TaskType::WAKE_UP || $wolon) {
                $wol = true;
            }

            // Schedule Type setup
            $scheduleType = strtolower(
                filter_input(INPUT_POST, 'scheduleType')
            );
            $scheduleTypes = [
                'cron',
                'instant',
                'single'
            ];
            self::$HookManager->processEvent(
                'SCHEDULE_TYPES',
                ['scheduleTypes' => &$scheduleTypes]
            );
            foreach ($scheduleTypes as $ind => &$val) {
                $scheduleTypes[$ind] = trim(
                    strtolower(
                        $val
                    )
                );
                unset($val);
            }
            if (!in_array($scheduleType, $scheduleTypes)) {
                throw new Exception(_('Invalid scheduling type'));
            }
            // Schedule Delayed/Cron checks.
            switch ($scheduleType) {
            case 'single':
                $scheduleDeployTime = self::niceDate(
                    filter_input(INPUT_POST, 'scheduleSingleTime')
                );
                if ($scheduleDeployTime < self::niceDate()) {
                    throw new Exception(_('Scheduled time is in the past'));
                }
                break;
            case 'cron':
                $min = strval(
                    filter_input(INPUT_POST, 'scheduleCronMin')
                );
                $hour = strval(
                    filter_input(INPUT_POST, 'scheduleCronHour')
                );
                $dom = strval(
                    filter_input(INPUT_POST, 'scheduleCronDOM')
                );
                $month = strval(
                    filter_input(INPUT_POST, 'scheduleCronMonth')
                );
                $dow = strval(
                    filter_input(INPUT_POST, 'scheduleCronDOW')
                );
                $tmin = FOGCron::checkMinutesField($min);
                $thour = FOGCron::checkHoursField($hour);
                $tdom = FOGCron::checkDOMField($dom);
                $tmonth = FOGCron::checkMonthField($month);
                $tdow = FOGCron::checkDOWField($dow);
                if (!$tmin) {
                    throw new Exception(_('Minutes field is invalid'));
                }
                if (!$thour) {
                    throw new Exception(_('Hours field is invalid'));
                }
                if (!$tdom) {
                    throw new Exception(_('Day of Month field is invalid'));
                }
                if (!$tmonth) {
                    throw new Exception(_('Month field is invalid'));
                }
                if (!$tdow) {
                    throw new Exception(_('Day of Week field is invalid'));
                }
            }

            // Task Type Imaging Checks
            if ($TaskType->isImagingTask()) {
                if ($TaskType->isCapture()) {
                    throw new Exception(_('Groups cannot create capture tasks'));
                }
            }

            // Actually create tasking
            if ($scheduleType == 'instant') {
                Route::indiv('tasktype', $type);
                $tasktype = json_decode(Route::getData());
                $this->obj->createImagePackage(
                    $tasktype,
                    $taskName,
                    $enableShutdown,
                    $enableDebug,
                    $enableSnapins,
                    true,
                    self::$FOGUser->get('name'),
                    $passreset,
                    false,
                    $wol
                );
            } else {
                $ScheduledTask = self::getClass('ScheduledTask')
                    ->set('taskTypeID', $type)
                    ->set('name', $taskName)
                    ->set('hostID', $this->obj->get('id'))
                    ->set('shutdown', $enableShutdown)
                    ->set('other2', $enableSnapins)
                    ->set('type', $scheduleType = 'single' ? 'S' : 'C')
                    ->set('isGroupTask', 1)
                    ->set('other3', self::$FOGUser->get('name'))
                    ->set('isActive', 1)
                    ->set('other4', $wol);
                if ($scheduleType == 'single') {
                    $ScheduledTask->set(
                        'scheduleTime',
                        $scheduleDeployTime->getTimestamp()
                    );
                } elseif ($scheduleType == 'cron') {
                    $ScheduledTask
                        ->set('minute', $min)
                        ->set('hour', $hour)
                        ->set('dayOfMonth', $dom)
                        ->set('month', $month)
                        ->set('dayOfWeek', $dow);
                }
                if (!$ScheduledTask->save()) {
                    $serverFault = true;
                    throw new Exception(_('Failed to create scheduled task'));
                }
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'GROUP_DEPLOY_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Create tasking succeeded'),
                    'title' => _('Create Task Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'GROUP_DEPLOY_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Create Task Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
            $hook,
            [
                'Group' => &$this->obj,
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
     * Get the login history for hosts in this group
     *
     * @return void
     */
    public function getLoginHist()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostID = $this->obj->get('hosts');
        Route::listem(
            'usertracking',
            ['hostID' => $hostID]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Get the image history for hosts in this group.
     *
     * @return void
     */
    public function getImageHist()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostID = $this->obj->get('hosts');
        Route::listem(
            'imagingLog',
            ['hostID' => $hostID]
        );
        echo Route::getData();
        exit;
    }
    /**
     * Gets the snapin history for hosts in this group.
     *
     * @return void
     */
    public function getSnapinHist()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostID = $this->obj->get('hosts');

        $checkStates = [
            self::getCancelledState(),
            self::getCompleteState()
        ];

        Route::ids(
            'snapinjob',
            ['hostID' => $hostID]
        );

        $snapinJobs = json_decode(Route::getData());

        Route::listem(
            'snapintask',
            [
                'jobID' => $snapinJobs,
                'stateID' => $checkStates
            ]
        );

        echo Route::getData();
        exit;
    }
}
