<?php
/**
 * Group management page
 *
 * PHP version 5
 *
 * The group represented to the GUI
 *
 * @category GroupManagementPage
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
 * @category GroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupManagementPage extends FOGPage
{
    private static $_common = [];
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
        if ($this->obj) {
            $this->_getHostCommon();
        }
        $this->headerData = [
            _('Name'),
            _('Members')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            [
                'width' => 5
            ]
        ];
    }
    /**
     * Get host common items
     *
     * @return void
     */
    private function _getHostCommon()
    {
        $HostCount = $this->obj->getHostCount();
        $hostids = $this->obj->get('hosts');
        $getItems = [
            'imageID',
            'productKey',
            'printerLevel',
            'useAD',
            'enforce',
            'ADDomain',
            'ADOU',
            'ADUser',
            'ADPass',
            'biosexit',
            'efiexit',
        ];
        foreach ($getItems as &$idField) {
            $tmp = self::getClass('HostManager')
                ->distinct(
                    $idField,
                    ['id' => $hostids]
                );
            self::$_common[] = (bool)($tmp == 1);
            unset($idField);
        }
        self::$Host = new Host(max($hostids));
    }
    /**
     * Create a new group.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Group');
        // Check all the post fields if they've already been set.
        $group = filter_input(INPUT_POST, 'group');
        $description = filter_input(INPUT_POST, 'description');
        $kern = filter_input(INPUT_POST, 'kern');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');

        // The fields to display
        $fields = [
            '<label class="col-sm-2 control-label" for="group">'
            . _('Group Name')
            . '</label>' => '<input type="text" name="group" '
            . 'value="'
            . $group
            . '" class="groupname-input form-control" '
            . 'id="group" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Group Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height: 50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="kern">'
            . _('Group Product Key')
            . '</label>' => '<input type="text" name="kern" '
            . 'value="'
            . $kern
            . '" class="form-control" id="kern"/>',
            '<label class="col-sm-2 control-label" for="args">'
            . _('Group Kernel Arguments')
            . '</label>' => '<input type="text" name="args" id="args" value="'
            . $args
            . '" class="form-control"/>',
            '<label class="col-sm-2 control-label" for="init">'
            . _('Group Init')
            . '</label>' => '<input type="text" name="init" value="'
            . $init
            . '" id="init" class="form-control"/>',
            '<label class="col-sm-2 control-label" for="dev">'
            . _('Group Primary Disk')
            . '</label>' => '<input type="text" name="dev" value="'
            . $dev
            . '" id="dev" class="form-control"/>'
        ];
        self::$HookManager
            ->processEvent(
                'GROUP_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'Group' => self::getClass('Group')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="group-create">';
        echo '<form id="group-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Group General -->';
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
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="send">'
            . _('Create')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
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
        $desc = trim(
            filter_input(INPUT_POST, 'description')
        );
        $kern = trim(
            filter_input(INPUT_POST, 'kern')
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
            if (!$group) {
                throw new Exception(
                    _('A group name is required!')
                );
            }
            if (self::getClass('GroupManager')->exists($group)) {
                throw new Exception(
                    _('A group already exists with this name!')
                );
            }
            $Group = self::getClass('Group')
                ->set('name', $group)
                ->set('description', $desc)
                ->set('kernel', $kern)
                ->set('kernelArgs', $args)
                ->set('kernelDevice', $dev)
                ->set('init', $init);
            if (!$Group->save()) {
                $serverFault = true;
                throw new Exception(_('Add group failed!'));
            }
            $code = 201;
            $hook = 'GROUP_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Group added!'),
                    'title' => _('Group Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'GROUP_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Create Fail')
                ]
            );
        }
        //header('Location: ../management/index.php?node=group&sub=edit&id=' . $Group->get('id'));
        self::$HookManager
            ->processEvent(
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
        list(
            $imageIDs,
            $groupKey,
            $printerLevel,
            $aduse,
            $enforcetest,
            $adDomain,
            $adOU,
            $adUser,
            $adPass,
            $biosExit,
            $efiExit
        ) = self::$_common;
        $exitNorm = Service::buildExitSelector(
            'bootTypeExit',
            (
                filter_input(INPUT_POST, 'bootTypeExit') ?: (
                    $biosExit ?
                    self::$Host->get('biosexit') :
                    ''
                )
            ),
            true,
            'bootTypeExit'
        );
        $exitEfi = Service::buildExitSelector(
            'efiBootTypeExit',
            (
                filter_input(INPUT_POST, 'efiBootTypeExit') ?: (
                    $efiExit ?
                    self::$Host->get('efiexit') :
                    ''
                )
            ),
            true,
            'efiBootTypeExit'
        );
        $group = (
            filter_input(INPUT_POST, 'group') ?: $this->obj->get('name')
        );
        $desc = (
            filter_input(INPUT_POST, 'description') ?: $this->obj->get('description')
        );
        $productKey = (
            filter_input(INPUT_POST, 'key') ?: (
                $groupKey ?
                self::$Host->get('productKey') :
                ''
            )
        );
        $productKeytest = self::aesdecrypt($productKey);
        if ($test_base64 = base64_decode($productKeytest)) {
            if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                $productKey = $test_base64;
            }
        } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
            $productKey = $productKeytest;
        }
        $kern = (
            filter_input(INPUT_POST, 'kern') ?: (
                $kern ?
                self::$Host->get('kernel') :
                $this->obj->get('kernel')
            )
        );
        $args = (
            filter_input(INPUT_POST, 'args') ?: (
                $args ?
                self::$Host->get('kernelArgs') :
                $this->obj->get('kernelArgs')
            )
        );
        $init = (
            filter_input(INPUT_POST, 'init') ?: (
                $init ?
                self::$Host->get('init') :
                $this->obj->get('init')
            )
        );
        $dev = (
            filter_input(INPUT_POST, 'dev') ?: (
                $dev ?
                self::$Host->get('kernelDevice')
                : $this->obj->get('kernelDevice')
            )
        );
        $modalresetBtn = self::makeButton(
            'resetencryptionConfirm',
            _('Confirm'),
            'btn btn-primary',
            ' method="post" action="../management/index.php?sub=clearAES" '
        );
        $modalresetBtn .= self::makeButton(
            'resetencryptionCancel',
            _('Cancel'),
            'btn btn-danger pull-right'
        );
        $modalreset = self::makeModal(
            'resetencryptionmodal',
            _('Reset Encryption Data'),
            _('Resetting encryption data should only be done if you re-installed the FOG Client or are using Debugger'),
            $modalresetBtn
        );
        $fields = [
            '<label for="name" class="col-sm-2 control-label">'
            . _('Group Name')
            . '</label>' => '<input id="group" class="form-control" placeholder="'
            . _('Group Name')
            . '" type="text" value="'
            . $group
            . '" name="group" required>',
            '<label for="description" class="col-sm-2 control-label">'
            . _('Group Description')
            . '</label>' => '<textarea style="resize:vertical;'
            . 'min-height:50px;" id="description" name="description" class="form-control">'
            . $desc
            . '</textarea>',
            '<label for="productKey" class="col-sm-2 control-label">'
            . _('Group Product Key')
            . '</label>' => '<input id="productKey" name="key" class="form-control" '
            . 'value="'
            . $productKey
            . '" maxlength="29" exactlength="25">',
            '<label for="kern" class="col-sm-2 control-label">'
            . _('Group Kernel')
            . '</label>' => '<input id="kern" name="kern" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $kern
            . '">',
            '<label for="args" class="col-sm-2 control-label">'
            . _('Group Kernel Arguments')
            . '</label>' => '<input id="args" name="args" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $args
            . '">',
            '<label for="init" class="col-sm-2 control-label">'
            . _('Group Init')
            . '</label>' => '<input id="init" name="init" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $init
            . '">',
            '<label for="dev" class="col-sm-2 control-label">'
            . _('Group Primary Disk')
            . '</label>' => '<input id="dev" name="dev" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $dev
            . '">',
            '<label for="bootTypeExit" class="col-sm-2 control-label">'
            . _('Group Bios Exit Type')
            . '</label>' => $exitNorm,
            '<label for="efiBootTypeExit" class="col-sm-2 control-label">'
            . _('Group EFI Exit Type')
            . '</label>' => $exitEfi
        ];
        self::$HookManager->processEvent(
            'GROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'Group' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<form id="group-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('group-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<div class="btn-group">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-warning" id="reset-encryption-data">'
            . _('Reset Encryption Data')
            . '</button>';
        echo '</div>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo $modalreset;
        echo '</div>';
        echo '</form>';
        echo '</div>';
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
        $kern = trim(
            filter_input(INPUT_POST, 'kern')
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
        $this->obj
            ->set('name', $group)
            ->set('description', $desc)
            ->set('kernel', $kern)
            ->set('kernelArgs', $args)
            ->set('kernelDevice', $dev)
            ->set('init', $init);
        self::getClass('HostManager')
            ->update(
                [
                    'id' => $this->obj->get('hosts')
                ],
                '',
                [
                    'kernel' => $kern,
                    'kernelArgs' => $args,
                    'kernelDevice' => $dev,
                    'init' => $init,
                    'efiexit' => $ebte,
                    'biosexit' => $bte,
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
        $imageID = (
            self::$_common[0] ?
            self::$Host->get('imageID') :
            ''
        );
        // Group Images
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($imageID, 'image');
        $fields = [
            '<label for="image" class="col-sm-2 control-label">'
            . _('Group Image')
            . '</label>' => $imageSelector
        ];
        self::$HookManager
            ->processEvent(
                'GROUP_IMAGE_FIELDS',
                [
                    'fields' => &$fields,
                    'Group' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo '<form id="group-image-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('group-image', $this->obj->get('id'))
            . '" novalidate>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="image-send">' . _('Update') . '</button>';
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
        $image = trim(
            filter_input(INPUT_POST, 'image')
        );
        $this->obj->addImage($image);
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
        $enforce = isset($_POST['enforcesel']);
        $this->obj->setAD(
            $useAD,
            $domain,
            $ou,
            $user,
            $pass,
            $enforce
        );
    }
    /**
     * Group printers display.
     *
     * @return void
     */
    public function groupPrinters()
    {
        $printerLevel = (
            filter_input(INPUT_POST, 'level') ?: (
                self::$_common[2] ?
                self::$Host->get('printerLevel') :
                0
            )
        );

        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=group-printers" ';

        // =========================================================
        // Printer Configuration
        echo '<!-- Printers -->';
        echo '<div class="box-group" id="printers">';
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo _('Group Printer Configuration');
        echo '</h4>';
        echo '</div>';
        echo '<div id="printerconf" class="">';
        echo '<form id="printer-config-form" class="form-horizontal"' . $props . ' novalidate>';
        echo '<div class="box-body">';
        echo '<div class="radio">';
        echo '<label for="nolevel" data-toggle="tooltip" data-placement="left" '
            . 'title="'
            . _('This setting turns off all FOG Printer Management')
            . '. '
            . _('Although there are multiple levels already')
            . ' '
            . _('between host and global settings')
            . ', '
            . _('this is just another to ensure safety')
            . '.">';
        echo '<input type="radio" name="level" value="0" '
            . 'id="nolevel"'
            . (
                $printerLevel == 0 ?
                ' checked' :
                ''
            )
            . '/> ';
        echo _('No Printer Management');
        echo '</label>';
        echo '</div>';
        echo '<div class="radio">';
        echo '<label for="addlevel" data-toggle="tooltip" data-placement="left" '
            . 'title="'
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
            . '">';
        echo '<input type="radio" name="level" value="1" '
            . 'id="addlevel"'
            . (
                $printerLevel == 1 ?
                ' checked' :
                ''
            )
            . '/> ';
        echo _('FOG Managed Printers');
        echo '</label>';
        echo '</div>';
        echo '<div class="radio">';
        echo '<label for="alllevel" data-toggle="tooltip" data-placement="left" '
            . 'title="'
            . _(
                'This setting will only allow FO GAssigned '
                . 'printers to be added to the host. Any '
                . 'printer that is not assigned will be '
                . 'removed including non-FOG managed printers.'
            )
            . '">';
        echo '<input type="radio" name="level" value="2" '
            . 'id="alllevel"'
            . (
                $printerLevel  == 2 ?
                ' checked' :
                ''
            )
            . '/> ';
        echo _('Only Assigned Printers');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button type="submit" name="levelup" class='
            . '"btn btn-primary" id="printer-config-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        // =========================================================
        // Associated Printers
        $buttons = self::makeButton(
            'printer-default',
            _('Update default'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'printer-add',
            _('Add selected'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'printer-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Default'),
            _('Printer Alias'),
            _('Printer Type')
        ];
        $this->templates = [
            '',
            '',
            ''
        ];
        $this->attributes = [
            [
                'class' => 'col-md-1'
            ],
            [],
            []
        ];
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo _('Update/Remove printers');
        echo '</h4>';
        echo '<div>';
        echo '<p class="help-block">';
        echo _('Changes will automatically be saved');
        echo '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div id="updateprinters" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'group-printers-table', $buttons);
        echo '</div>';
        echo '</div>';
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
        if (isset($_POST['levelup'])) {
            $level = filter_input(INPUT_POST, 'level');
            self::getClass('HostManager')
                ->update(
                    [
                        'id' => $this->get('hosts'),
                    ],
                    '',
                    ['printerLevel' => $level]
                );
        }
        if (isset($_POST['updateprinters'])) {
            $printers = filter_input_array(
                INPUT_POST,
                [
                    'printer' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $printers = $printers['printer'];
            if (count($printers ?: []) > 0) {
                $this->obj->addPrinter($printers);
            }
        }
        if (isset($_POST['defaultsel'])) {
            $this->obj->updateDefault(
                filter_input(
                    INPUT_POST,
                    'default'
                ),
                isset($_POST['default'])
            );
        }
        if (isset($_POST['printdel'])) {
            $printers = filter_input_array(
                INPUT_POST,
                [
                    'printerRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $printers = $printers['printerRemove'];
            if (count($printers ?: []) > 0) {
                $this->obj->removePrinter($printers);
            }
        }
    }
    /**
     * Group snapins.
     *
     * @return void
     */
    public function groupSnapins()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=group-snapins" ';

        echo '<!-- Snapins -->';
        echo '<div class="box-group" id="snapins">';
        // =================================================================
        // Associated Snapins
        $buttons = self::makeButton(
            'snapins-add',
            _('Add selected'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'snapins-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );

        $this->headerData = [
            _('Snapin Name'),
            _('Snapin Created')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div id="updatesnapins" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'group-snapins-table', $buttons);
        echo '</div>';
        echo '</div>';
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
        if (isset($_POST['updatesnapins'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'snapin' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['snapin'];
            if (count($snapins ?: []) > 0) {
                $this->obj->addSnapin($snapins);
            }
        }
        if (isset($_POST['snapdel'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                [
                    'snapinRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $snapins = $snapins['snapinRemove'];
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
    public function groupService()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=group-service" ';
        echo '<!-- Modules/Service Settings -->';
        echo '<div class="box-group" id="modules">';
        // =============================================================
        // Associated Modules
        // Buttons for this.
        $buttons = self::makeButton(
            'modules-update',
            _('Update'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'modules-enable',
            _('Enable All'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'modules-disable',
            _('Disable All'),
            'btn btn-danger',
            $props
        );
        $this->headerData = [
            _('Module Name'),
            _('Module Association')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];
        // Modules Enable/Disable/Selected
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo _('Group module settings');
        echo '</h4>';
        echo '<div>';
        echo '<p class="help-block">';
        echo _('Modules disabled globally cannot be enabled here');
        echo '<br/>';
        echo _('Changes will automatically be saved');
        echo '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div id="updatemodules" class="">';
        echo '<div class="box-body">';
        echo $this->render(12, 'modules-to-update', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Display Manager Element.
        list(
            $r,
            $x,
            $y
        ) = self::getSubObjectIDs(
            'Service',
            [
                'name' => [
                    'FOG_CLIENT_DISPLAYMANAGER_R',
                    'FOG_CLIENT_DISPLAYMANAGER_X',
                    'FOG_CLIENT_DISPLAYMANAGER_Y'
                ]
            ],
            'value'
        );
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
                break;
            }
            $fields[
                '<label for="'
                . $name
                . '" class="col-sm-2 control-label">'
                . $get[1]
                . '</label>'
            ] = '<input type="number" id="'
            . $name
            . '" class="form-control" name="'
            . $name
            . '" value="'
            . $val
            . '"/>';
            unset($get);
        }
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="group-dispman" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=group-service" novalidate>';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Display Manager Settings');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '<input type="hidden" name="dispmansend" value="1"/>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="displayman-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';

        // Auto Log Out
        $tme = filter_input(INPUT_POST, 'tme');
        if (!$tme) {
            $tme = self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN');
        }
        $fields = [
            '<label for="tme" class="col-sm-2 control-label">'
            . _('Auto Logout Time')
            . '<br/>('
            . _('in minutes')
            . ')</label>' => '<input type="number" name="tme" class="form-control" '
            . 'value="'
            . $tme
            . '" id="tme"/>'
        ];
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="group-alo" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=group-service" novalidate>';
        echo '<div class="box box-warning">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Auto Logout Settings');
        echo '</h4>';
        echo '<div>';
        echo '<p class="help-block">';
        echo _('Minimum time limit for Auto Logout to become active is 5 minutes.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '<input type="hidden" name="alosend" value="1"/>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="alo-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        // End Box Group
        echo '</div>';
    }
    /**
     * Group Service post.
     *
     * @return void
     */
    public function groupServicePost()
    {
        if (isset($_POST['enablemodulessel'])) {
            $enablemodules = filter_input_array(
                INPUT_POST,
                [
                    'enablemodules' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $enablemodules = $enablemodules['enablemodules'];
            $this->obj->addModule($enablemodules);
        }
        if (isset($_POST['disablemodulessel'])) {
            $disablemodules = filter_input_array(
                INPUT_POST,
                [
                    'disablemodules' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $disablemodules = $disablemodules['disablemodules'];
            $this->obj->removeModule($disablemodules);
        }
        if (isset($_POST['dispmansend'])) {
            $x = filter_input(INPUT_POST, 'x');
            $y = filter_input(INPUT_POST, 'y');
            $r = filter_input(INPUT_POST, 'r');
            $this->obj->setDisp($x, $y, $r);
        }
        if (isset($_POST['alosend'])) {
            $tme = (int)filter_input(INPUT_POST, 'tme');
            if (!(is_numeric($tme) && $tm > 4)) {
                $tme = 0;
            }
            $this->obj->setAlo($tme);
        }
    }
    /**
     * Display the group PM stuff.
     *
     * @return void
     */
    public function groupPowermanagement()
    {
        echo '<!-- Power Management -->';
        echo $this->newPMDisplay();
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
            self::getClass('PowerManagementManager')->destroy(
                ['hostID' => $hostIDs]
            );
        }
    }
    /**
     * The group edit display method
     *
     * @return void
     */
    public function edit()
    {
        list(
            $imageIDs,
            $groupKey,
            $printerLevel,
            $aduse,
            $enforcetest,
            $adDomain,
            $adOU,
            $adUser,
            $adPass,
            $biosExit,
            $efiExit
        ) = self::$_common;
        self::$HookManager->processEvent(
            'GROUP_COMMON_HOOK',
            ['common' => &self::$_common]
        );
        $hostids = $this->obj->get('hosts');
        self::$Host = new Host(@max($hostids));
        echo '<input type="hidden" name="hostID" value="'
            . self::$Host->get('id')
            . '"/>';
        // Set Field Information
        $printerLevel = (
            $printerLevel ?
            self::$Host->get('printerLevel') :
            ''
        );
        $useAD = (
            $aduse ?
            self::$Host->get('useAD') :
            ''
        );
        $enforce = (
            $enforcetest ?
            self::$Host->get('enforce') :
            ''
        );
        $ADDomain = (
            $adDomain ?
            self::$Host->get('ADDomain') :
            ''
        );
        $ADOU = (
            $adOU ?
            self::$Host->get('ADOU') :
            ''
        );
        $ADUser = (
            $adUser ?
            self::$Host->get('ADUser') :
            ''
        );
        $adPass = (
            $adPass ?
            self::$Host->get('ADPass') :
            ''
        );
        $ADPass = self::$Host->get('ADPass');

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
            'generator' => function() {
                $this->groupGeneral();
            }
        ];

        // Image
        $tabData[] = [
            'name' => _('Image'),
            'id' => 'group-image',
            'generator' => function() {
                $this->groupImage();
            }
        ];

        // Active Directory
        $tabData[] = [
            'name' => _('Active Directory'),
            'id' => 'group-active-directory',
            'generator' => function() {
                $this->adFieldsToDisplay(
                    $useAD,
                    $ADDomain,
                    $ADOU,
                    $ADUser,
                    $ADPass,
                    $enforce
                );
            }
        ];

        // Tasks
        $tabData[] = [
            'name' => _('Tasks'),
            'id' => 'group-tasks',
            'generator' => function() {
                $this->basictasksOptions();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Hosts'),
                        'id' => 'group-hosts',
                        'generator' => function() {
                            //$this->groupMembership();
                            echo 'TODO: Make functional';
                        }
                    ],
                    [
                        'name' => _('Printers'),
                        'id' => 'group-printers',
                        'generator' => function() {
                            $this->groupPrinters();
                        }
                    ],
                    [
                        'name' => _('Snapins'),
                        'id' => 'group-snapins',
                        'generator' => function() {
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
                        'name' => _('Client Module Settings'),
                        'id' => 'group-service',
                        'generator' => function() {
                            $this->groupService();
                        }
                    ],
                    [
                        'name' => _('Power Management'),
                        'id' => 'group-powermanagement',
                        'generator' => function() {
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
            'generator' => function() {
                $this->groupInventory();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Display inventory page, separated as groups can contain
     * a lot of information
     *
     * @return void
     */
    public function groupInventory()
    {
        echo 'TODO: Make Functional';
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
            case 'group-printers':
                $this->groupPrinterPost();
                break;
            case 'group-snapins':
                $this->groupSnapinPost();
                break;
            case 'group-service':
                $this->groupServicePost();
                break;
            case 'group-powermanagement':
                $this->groupPowermanagementPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Group update failed!'));
            }
            $code = 201;
            $hook = 'GROUP_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Group updated!'),
                    'title' => _('Group Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'GROUP_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
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
     * Presents the printers list table.
     *
     * @return void
     */
    public function getPrintersList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        // Workable queries
        $printersSqlStr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";
        $printersFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";
        $printersTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('PrinterManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'printers',
                'pID',
                $columns,
                $printersSqlStr,
                $printersFilterStr,
                $printersTotalStr,
                $where
            )
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
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        // Workable queries
        $snapinsSqlStr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";
        $snapinsFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";
        $snapinsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('SnapinManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'snapins',
                'sID',
                $columns,
                $snapinsSqlStr,
                $snapinsFilterStr,
                $snapinsTotalStr
            )
        );
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
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

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

        $where = "`modules`.`short_name` "
            . "NOT IN ('"
            . implode("','", $notWhere)
            . "') AND `modules`.`short_name` IN ('"
            . implode("','", $keys)
            . "')";

        // Workable queries
        $modulesSqlStr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";
        $modulesFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";
        $modulesTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            WHERE `modules`.`short_name` "
            . "NOT IN ('"
            . implode("','", $notWhere)
            . "')";

        foreach (self::getClass('ModuleManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'modules',
                'id',
                $columns,
                $modulesSqlStr,
                $modulesFilterStr,
                $modulesTotalStr,
                $where
            )
        );
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

        $obj = self::getClass('GroupManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            array_push($this->headerData, $common);
            array_push($this->templates, '');
            array_push($this->attributes, []);
            unset($real);
        }

        $this->title = _('Export Groups');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Groups');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'When you click on the item you want to export, it can only select '
            . 'what is currently viewable on the screen. This includes searched'
            . 'and the current page. Please use the selector to choose the amount '
            . 'of items you would like to export.'
        );
        echo '</p>';
        $this->render(12, 'group-export-table');
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
        $obj = self::getClass('GroupManager');
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
            'GROUP_EXPORT_ITEMS',
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
