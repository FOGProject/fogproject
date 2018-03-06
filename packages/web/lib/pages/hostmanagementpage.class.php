<?php
/**
 * Host management page
 *
 * PHP version 5
 *
 * The host represented to the GUI
 *
 * @category HostManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Host management page
 *
 * The host represented to the GUI
 *
 * @category HostManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostManagementPage extends FOGPage
{
    /**
     * The node that uses this class.
     *
     * @var string
     */
    public $node = 'host';
    /**
     * Initializes the host page
     *
     * @param string $name the name to construct with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Host Management';
        parent::__construct($this->name);
        if (self::$pendingHosts > 0) {
            $this->menu['pending'] = self::$foglang['PendingHosts'];
        }
        if (!($this->obj instanceof Host && $this->obj->isValid())) {
            $this->exitNorm = filter_input(INPUT_POST, 'bootTypeExit');
            $this->exitEfi = filter_input(INPUT_POST, 'efiBootTypeExit');
        } else {
            $this->exitNorm = (
                filter_input(INPUT_POST, 'bootTypeExit') ?: 
                $this->obj->get('biosexit')
            );
            $this->exitEfi = (
                filter_input(INPUT_POST, 'efiBootTypeExit') ?:
                $this->obj->get('efiexit')
            );
        }
        $this->exitNorm = Service::buildExitSelector(
            'bootTypeExit',
            $this->exitNorm,
            true,
            'bootTypeExit'
        );
        $this->exitEfi = Service::buildExitSelector(
            'efiBootTypeExit',
            $this->exitEfi,
            true,
            'efiBootTypeExit'
        );
        $this->headerData = [
            _('Host'),
            _('Primary MAC')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];
        if (self::$fogpingactive) {
            $this->headerData[] = _('Ping Status');
            $this->templates[] = '';
            $this->attributes[] = [];
        }
        $this->headerData = self::fastmerge(
            $this->headerData,
            [
                _('Imaged'),
                _('Assigned Image'),
                _('Description')
            ]
        );
        $this->templates = self::fastmerge(
            $this->templates,
            [
                '',
                '',
                ''
            ]
        );
        $this->attributes = self::fastmerge(
            $this->attributes,
            [
                [],
                [],
                []
            ]
        );
    }
    /**
     * Lists the pending hosts
     *
     * @return false
     */
    public function pending()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('All Pending Hosts');

        // Remove unnecessary elements.
        unset(
            $this->headerData[2],
            $this->headerData[3],
            $this->headerData[4],
            $this->attributes[2],
            $this->attributes[3],
            $this->attributes[4],
            $this->templates[2],
            $this->templates[3],
            $this->templates[4]
        );

        // Reorder the arrays
        $this->headerData = array_values(
            $this->headerData
        );
        $this->attributes = array_values(
            $this->attributes
        );
        $this->templates = array_values(
            $this->templates
        );

        self::$HookManager->processEvent(
            'HOST_PENDING_DATA',
            [
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                'headerData' => &$this->headerData
            ]
        );
        self::$HookManager->processEvent(
            'HOST_PENDING_HEADER_DATA',
            ['headerData' => &$this->headerData]
        );

        $buttons = self::makeButton(
            'approve',
            _('Approve selected'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'delete',
            _('Delete selected'),
            'btn btn-danger pull-right'
        );

        $modalApprovalBtns = self::makeButton(
            'confirmApproveModal',
            _('Approve'),
            'btn btn-success pull-right'
        );
        $modalApprovalBtns .= self::makeButton(
            'cancelApprovalModal',
            _('Cancel'),
            'btn btn-warning pull-left',
            'data-dismiss="modal"'
        );
        $approvalModal = self::makeModal(
            'approveModal',
            _('Approve Pending Hosts'),
            _('Approving the selected pending hosts.'),
            $modalApprovalBtns
        );

        $modalDeleteBtns = self::makeButton(
            'confirmDeleteModal',
            _('Delete'),
            'btn btn-danger pull-right'
        );
        $modalDeleteBtns .= self::makeButton(
            'closeDeleteModal',
            _('Cancel'),
            'btn btn-warning pull-left',
            'data-dismiss="modal"'
        );
        $deleteModal = self::makeModal(
            'deleteModal',
            _('Confirm password'),
            '<div class="input-group">'
            . '<input id="deletePassword" class="form-control" placeholder="'
            . _('Password')
            . '" autocomplete="off" type="password">'
            . '</div>',
            $modalDeleteBtns
        );

        echo self::makeFormTag(
            'form-horizontal',
            'host-pending-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12);
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo $approvalModal;
        echo $deleteModal;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually performs the update/delete actions
     *
     * @return void
     */
    public function pendingAjax()
    {
        header('Content-type: application/json');

        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(
            INPUT_POST,
            [
                'remitems' => $flags,
                'pending' => $flags
            ]
        );
        $remitems = $items['remitems'];
        $pending = $items['pending'];
        if (isset($_POST['confirmdel'])) {
            if (self::getSetting('FOG_REAUTH_ON_DELETE')) {

                $user = filter_input(INPUT_POST, 'fogguiuser');

                if (empty($user)) {
                    $user = self::$FOGUser->get('name');
                }
                $pass = filter_input(INPUT_POST, 'fogguipass');
                $validate = self::getClass('User')
                    ->passwordValidate(
                        $user,
                        $pass,
                        true
                    );
                if (!$validate) {
                    echo json_encode(
                        ['error' => self::$foglang['InvalidLogin']]
                    );
                    http_response_code(401);
                    exit;
                }
            }
            self::getClass('HostManager')->destroy(
                [
                    'id' => $remitems,
                    'pending' => 1
                ]
            );
        }
        if (isset($_POST['approvepending'])) {
            self::getClass('HostManager')->update(
                [
                    'id' => $pending,
                    'pending' => 1
                ],
                '',
                ['pending' => 0]
            );
            http_response_code(201);
            echo json_encode(
                [
                    'msg' => _('Approved selected hosts!'),
                    'title' => _('Host Approval Success')
                ]
            );
            exit;
        }
    }
    /**
     * Lists the pending macs
     *
     * @return false
     */
    public function pendingMacs()
    {
        if (false === self::$showhtml) {
            return;
        }
        $this->title = _('All Pending MACs');

        $this->headerData = [
            _('Host Name'),
            _('MAC Address')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];

        self::$HookManager->processEvent(
            'HOST_PENDING_MAC_DATA',
            [
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                'headerData' => &$this->headerData
            ]
        );
        self::$HookManager->processEvent(
            'HOST_PENDING_MAC_HEADER_DATA',
            ['headerData' => &$this->headerData]
        );

        $buttons = self::makeButton(
            'approve',
            _('Approve selected'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'delete',
            _('Delete selected'),
            'btn btn-danger pull-right'
        );

        $modalApprovalBtns = self::makeButton(
            'confirmApproveModal',
            _('Approve'),
            'btn btn-success pull-right'
        );
        $modalApprovalBtns .= self::makeButton(
            'cancelApprovalModal',
            _('Cancel'),
            'btn btn-warning pull-left',
            'data-dismiss="modal"'
        );
        $approvalModal = self::makeModal(
            'approveModal',
            _('Approve Pending Hosts'),
            _('Approving the selected pending hosts.'),
            $modalApprovalBtns
        );

        $modalDeleteBtns = self::makeButton(
            'confirmDeleteModal',
            _('Delete'),
            'btn btn-danger pull-right'
        );
        $modalDeleteBtns .= self::makeButton(
            'closeDeleteModal',
            _('Cancel'),
            'btn btn-warning pull-left',
            'data-dismiss="modal"'
        );
        $deleteModal = self::makeModal(
            'deleteModal',
            _('Confirm password'),
            '<div class="input-group">'
            . '<input id="deletePassword" class="form-control" placeholder="'
            . _('Password')
            . '" autocomplete="off" type="password">'
            . '</div>',
            $modalDeleteBtns
        );

        echo self::makeFormTag(
            'form-horizontal',
            'mac-pending-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12);
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo $approvalModal;
        echo $deleteModal;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually performs the update/delete actions
     *
     * @return void
     */
    public function pendingMacsAjax()
    {
        header('Content-type: application/json');

        $flags = ['flags' => FILTER_REQUIRE_ARRAY];
        $items = filter_input_array(
            INPUT_POST,
            [
                'remitems' => $flags,
                'pending' => $flags
            ]
        );
        $remitems = $items['remitems'];
        $pending = $items['pending'];
        if (isset($_POST['confirmdel'])) {
            if (self::getSetting('FOG_REAUTH_ON_DELETE')) {

                $user = filter_input(INPUT_POST, 'fogguiuser');

                if (empty($user)) {
                    $user = self::$FOGUser->get('name');
                }
                $pass = filter_input(INPUT_POST, 'fogguipass');
                $validate = self::getClass('User')
                    ->passwordValidate(
                        $user,
                        $pass,
                        true
                    );
                if (!$validate) {
                    echo json_encode(
                        ['error' => self::$foglang['InvalidLogin']]
                    );
                    http_response_code(401);
                    exit;
                }
            }
            self::getClass('MACAddressAssociationManager')->destroy(
                [
                    'id' => $remitems,
                    'pending' => 1
                ]
            );
        }
        if (isset($_POST['approvepending'])) {
            self::getClass('MACAddressAssociationManager')->update(
                [
                    'id' => $pending,
                    'pending' => 1
                ],
                '',
                ['pending' => 0]
            );
            http_response_code(201);
            echo json_encode(
                [
                    'msg' => _('Approved selected macss!'),
                    'title' => _('MAC Approval Success')
                ]
            );
            exit;
        }
    }
    /**
     * Creates a new host.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Host');
        // Check all the post fields if they've already been set.
        $host = filter_input(INPUT_POST, 'host');
        $mac = filter_input(INPUT_POST, 'mac');
        $description = filter_input(INPUT_POST, 'description');
        $key = filter_input(INPUT_POST, 'key');
        $image = filter_input(INPUT_POST, 'image');
        $kern = filter_input(INPUT_POST, 'kern');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');
        $domain = filter_input(INPUT_POST, 'domain');
        $domainname = filter_input(INPUT_POST, 'domainname');
        $ou = filter_input(INPUT_POST, 'ou');
        $domainuser = filter_input(INPUT_POST, 'domainuser');
        $domainpassword = filter_input(INPUT_POST, 'domainpassword');
        $enforcesel = isset($_POST['enforcesel']);

        // The fields to display
        $fields = [
            '<label class="col-sm-2 control-label" for="host">'
            . _('Host Name')
            . '</label>' => '<input type="text" name="host" '
            . 'value="'
            . $host
            . '" maxlength="15" '
            . 'class="hostname-input form-control" '
            . 'id="host" required/>',
            '<label class="col-sm-2 control-label" for="mac">'
            . _('Primary MAC')
            . '</label>' => '<input type="text" name="mac" class="macaddr form-control" '
            . 'id="mac" value="'
            . $mac
            . '" maxlength="17" exactlength="12" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Host Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="productKey">'
            . _('Host Product Key')
            . '</label>' => '<input id="productKey" type="text" '
            . 'name="key" value="'
            . $key
            . '" class="form-control" maxlength="29" exactlength="25"/>',
            '<label class="col-sm-2 control-label" for="image">'
            . _('Host Image')
            . '</label>' => self::getClass('ImageManager')->buildSelectBox(
                $image,
                '',
                'id'
            ),
            '<label class="col-sm-2 control-label" for="kern">'
            . _('Host Kernel')
            . '</label>' => '<input type="text" name="kern" '
            . 'value="'
            . $kern
            . '" class="form-control" id="kern"/>',
            '<label class="col-sm-2 control-label" for="args">'
            . _('Host Kernel Arguments')
            . '</label>' => '<input type="text" name="args" id="args" value="'
            . $args
            . '" class="form-control"/>',
            '<label class="col-sm-2 control-label" for="init">'
            . _('Host Init')
            . '</label>' => '<input type="text" name="init" value="'
            . $init
            . '" id="init" class="form-control"/>',
            '<label class="col-sm-2 control-label" for="dev">'
            . _('Host Primary Disk')
            . '</label>' => '<input type="text" name="dev" value="'
            . $dev
            . '" id="dev" class="form-control"/>',
            '<label class="col-sm-2 control-label" for="bootTypeExit">'
            . _('Host Bios Exit Type')
            . '</label>' => $this->exitNorm,
            '<label class="col-sm-2 control-label" for="efiBootTypeExit">'
            . _('Host EFI Exit Type')
            . '</label>' => $this->exitEfi,
        ];
        self::$HookManager
            ->processEvent(
                'HOST_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'Host' => self::getClass('Host')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="host-create">';
        echo '<form id="host-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Host General -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Host');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '<!-- Active Directory -->';
        if (!isset($_POST['enforcesel'])) {
            $enforcesel = self::getSetting('FOG_ENFORCE_HOST_CHANGES');
        } else {
            $enforcesel = true;
        }
        $fields = $this->adFieldsToDisplay(
            $domain,
            $domainname,
            $ou,
            $domainuser,
            $domainpassword,
            $enforcesel,
            false,
            true
        );
        $rendered = self::formFields($fields);
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Active Directory');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
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
     * Handles the forum submission process.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('HOST_ADD_POST');
        $host = trim(
            filter_input(INPUT_POST, 'host')
        );
        $mac = trim(
            filter_input(INPUT_POST, 'mac')
        );
        $desc = trim(
            filter_input(INPUT_POST, 'description')
        );
        $password = trim(
            filter_input(INPUT_POST, 'domainpassword')
        );
        $useAD = (int)isset($_POST['domain']);
        $domain = trim(
            filter_input(INPUT_POST, 'domainname')
        );
        $ou = trim(
            filter_input(INPUT_POST, 'ou')
        );
        $user = trim(
            filter_input(INPUT_POST, 'domainuser')
        );
        $pass = $password;
        $key = trim(
            filter_input(INPUT_POST, 'key')
        );
        $productKey = preg_replace(
            '/([\w+]{5})/',
            '$1-',
            str_replace(
                '-',
                '',
                strtoupper($key)
            )
        );
        $productKey = substr($productKey, 0, 29);
        $enforce = (int)isset($_POST['enforcesel']);
        $image = (int)filter_input(INPUT_POST, 'image');
        $kernel = trim(
            filter_input(INPUT_POST, 'kern')
        );
        $kernelArgs = trim(
            filter_input(INPUT_POST, 'args')
        );
        $kernelDevice = trim(
            filter_input(INPUT_POST, 'dev')
        );
        $init = trim(
            filter_input(INPUT_POST, 'init')
        );
        $bootTypeExit = trim(
            filter_input(INPUT_POST, 'bootTypeExit')
        );
        $efiBootTypeExit = trim(
            filter_input(INPUT_POST, 'efiBootTypeExit')
        );
        $serverFault = false;
        try {
            if (!$host) {
                throw new Exception(
                    _('A host name is required!')
                );
            }
            if (!$mac) {
                throw new Exception(
                    _('A mac address is required!')
                );
            }
            if (self::getClass('HostManager')->exists($host)) {
                throw new Exception(
                    _('A host already exists with this name!')
                );
            }
            $MAC = new MACAddress($mac);
            if (!$MAC->isValid()) {
                throw new Exception(_('MAC Format is invalid'));
            }
            self::getClass('HostManager')->getHostByMacAddresses($MAC);
            if (self::$Host->isValid()) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        _('A host with this mac already exists with name'),
                        self::$Host->get('name')
                    )
                );
            }
            $ModuleIDs = self::getSubObjectIDs(
                'Module',
                ['isDefault' => 1]
            );
            self::$Host
                ->set('name', $host)
                ->set('description', $desc)
                ->set('imageID', $image)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelArgs)
                ->set('kernelDevice', $kernelDevice)
                ->set('init', $init)
                ->set('biosexit', $bootTypeExit)
                ->set('efiexit', $efiBootTypeExit)
                ->set('productKey', $productKey)
                ->addModule($ModuleIDs)
                ->addPriMAC($MAC)
                ->setAD(
                    $useAD,
                    $domain,
                    $ou,
                    $user,
                    $pass,
                    true,
                    true,
                    $productKey,
                    $enforce
                );
            if (!self::$Host->save()) {
                $serverFault = true;
                throw new Exception(_('Add host failed!'));
            }
            $code = 201;
            $hook = 'HOST_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Host added!'),
                    'title' => _('Host Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'HOST_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Create Fail')
                ]
            );
        }
        //header('Location: ../management/index.php?node=host&sub=edit&id=' . $Host->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'Host' => &$Host,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
        unset($Host);
        echo $msg;
        exit;
    }
    /**
     * Displays the host general tab.
     *
     * @return void
     */
    public function hostGeneral()
    {
        $image = filter_input(INPUT_POST, 'image') ?: $this->obj->get('imageID');
        $imageSelect = self::getClass('ImageManager')
            ->buildSelectBox($image);
        // Either use the passed in or get the objects info.
        $host = (
            filter_input(INPUT_POST, 'host') ?: $this->obj->get('name')
        );
        $desc = (
            filter_input(INPUT_POST, 'description') ?: $this->obj->get('description')
        );
        $productKey = (
            filter_input(INPUT_POST, 'key') ?: $this->obj->get('productKey')
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
            filter_input(INPUT_POST, 'kern') ?: $this->obj->get('kernel')
        );
        $args = (
            filter_input(INPUT_POST, 'args') ?: $this->obj->get('kernelArgs')
        );
        $init = (
            filter_input(INPUT_POST, 'init') ?: $this->obj->get('init')
        );
        $dev = (
            filter_input(INPUT_POST, 'dev') ?: $this->obj->get('kernelDevice')
        );
        $fields = [
            '<label for="name" class="col-sm-2 control-label">'
            . _('Host Name')
            . '</label>' => '<input id="name" class="form-control" placeholder="'
            . _('Host Name')
            . '" type="text" value="'
            . $host
            . '" maxlength="15" name="host" required>',
            '<label for="description" class="col-sm-2 control-label">'
            . _('Host description')
            . '</label>' => '<textarea style="resize:vertical;'
            . 'min-height:50px;" id="description" name="description" class="form-control">'
            . $desc
            . '</textarea>',
            '<label for="productKey" class="col-sm-2 control-label">'
            . _('Host Product Key')
            . '</label>' => '<input id="productKey" name="key" class="form-control" '
            . 'value="'
            . $productKey
            . '" maxlength="29" exactlength="25">',
            '<label class="col-sm-2 control-label" for="image">'
            . _('Host Image')
            . '</label>' => $imageSelect,
            '<label for="kern" class="col-sm-2 control-label">'
            . _('Host Kernel')
            . '</label>' => '<input id="kern" name="kern" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $kern
            . '">',
            '<label for="args" class="col-sm-2 control-label">'
            . _('Host Kernel Arguments')
            . '</label>' => '<input id="args" name="args" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $args
            . '">',
            '<label for="init" class="col-sm-2 control-label">'
            . _('Host Init')
            . '</label>' => '<input id="init" name="init" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $init
            . '">',
            '<label for="dev" class="col-sm-2 control-label">'
            . _('Host Primary Disk')
            . '</label>' => '<input id="dev" name="dev" class="form-control" '
            . 'placeholder="" type="text" value="'
            . $dev
            . '">',
            '<label for="bootTypeExit" class="col-sm-2 control-label">'
            . _('Host Bios Exit Type')
            . '</label>' => $this->exitNorm,
            '<label for="efiBootTypeExit" class="col-sm-2 control-label">'
            . _('Host EFI Exit Type')
            . '</label>' => $this->exitEfi
        ];
        self::$HookManager->processEvent(
            'HOST_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
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
        echo '<form id="host-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('host-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box box-solid">';
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
        echo '</div>';
        echo '</form>';
    }
    /**
     * Host general post update.
     *
     * @return void
     */
    public function hostGeneralPost()
    {
        $host = trim(
            filter_input(INPUT_POST, 'host')
        );
        $desc = trim(
            filter_input(INPUT_POST, 'description')
        );
        $imageID = trim(
            filter_input(INPUT_POST, 'image')
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
        if (empty($host)) {
            throw new Exception(_('Please enter a hostname'));
        }
        if ($host != $this->obj->get('name')
        ) {
            if (!$this->obj->isHostnameSafe($host)) {
                throw new Exception(_('Please enter a valid hostname'));
            }
            if ($this->obj->getManager()->exists($host)) {
                throw new Exception(_('Please use another hostname'));
            }
        }
        $Task = $this->obj->get('task');
        if ($Task->isValid()
            && $imageID != $this->obj->get('imageID')
        ) {
            throw new Exception(_('Cannot change image when in tasking'));
        }
        $this
            ->obj
            ->set('name', $host)
            ->set('description', $desc)
            ->set('imageID', $imageID)
            ->set('kernel', $kern)
            ->set('kernelArgs', $args)
            ->set('kernelDevice', $dev)
            ->set('init', $init)
            ->set('biosexit', $bte)
            ->set('efiexit', $ebte)
            ->set('productKey', $productKey);
    }
    /**
     * Host MAC Address listing.
     *
     * @return void
     */
    public function hostMacaddress()
    {
        $newMac = (
            filter_input(INPUT_POST, 'newMac')
        );

        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=host-macaddress" ';

        $fields = [
            '<label class="col-sm-2 control-label" for="newMac">'
            . _('Add New MAC')
            . '</label>' => '<input type="text" name="newMac" value="'
            . $newMac
            . '" id="newMac" class="form-control" required/>'
        ];
        self::$HookManager->processEvent(
            'HOST_MACADDRESS_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);
        $buttons = self::makeButton(
            'newmac-send',
            _('Add'),
            'btn btn-primary'
        );

        // =========================================================
        // New MAC Address add.
        echo '<!-- MAC Addresses -->';
        echo '<div class="box-group" id="macaddresses">';
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo _('Add New MAC Address');
        echo '</h4>';
        echo '</div>';
        echo '<div id="newmacadd" class="">';
        echo '<form id="macaddress-add-form" class="form-horizontal"'
            . $props
            . 'novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '<input type="hidden" name="macadd" value="1"/>';
        echo '</form>';
        echo '</div>';

        // MAC Address Table
        $buttons = self::makeButton(
            'macaddress-table-update',
            _('Update selected'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'macaddress-table-delete',
            _('Delete selected'),
            'btn btn-danger pull-right',
            $props
        );
        $this->headerData = [
            _('MAC Address'),
            _('Primary'),
            _('Ignore Imaging'),
            _('Ignore Client'),
            _('Pending')
        ];
        $this->templates = [
            '',
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            ['width' => 16],
            ['width' => 16],
            ['width' => 16],
            ['width' => 16]
        ];
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '<h4 class="box-title">';
        echo _('Update/Remove MAC addresses');
        echo '</h4>';
        echo '<div>';
        echo '<p class="help-block">';
        echo _('Changes will automatically be saved');
        echo '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div id="updatemacaddresses" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'host-macaddresses-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host MAC Address update.
     *
     * @return void
     */
    public function hostMacaddressPost()
    {
        if (isset($_POST['macadd'])) {
            $mac = trim(
                filter_input(
                    INPUT_POST,
                    'newMac'
                )
            );
            if (!$mac) {
                throw new Exception(_('MAC Address is required!'));
            }
            $mact = new MACAddress($mac);
            if (!$mact->isValid()) {
                throw new Exception(_('MAC Address is invalid!'));
            }
            $host = $mact->getHost();
            if ($host->isValid()
                && $host->get('id') != $this->obj->get('id')
            ) {
                throw new Exception(_('MAC Address is assigned to another host!'));
            }
            $this->obj->addAddMac($mac);
        }
        if (isset($_POST['updateprimary'])) {
            $primary = (int)filter_input(
                INPUT_POST,
                'primary'
            );
            self::getClass('MACAddressAssociationManager')
                ->update(
                    ['hostID' => $this->obj->get('id')],
                    '',
                    ['primary' => 0]
                );
            if ($primary) {
                self::getClass('MACAddressASsociationManager')
                    ->update(
                        [
                            'id' => $primary,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['primary' => 1]
                    );
            }
        }
        if (isset($_POST['updatechecks'])) {
            $flags = ['flags' => FILTER_REQUIRE_ARRAY];
            $items = filter_input_array(
                INPUT_POST,
                [
                    'imageIgnore' => $flags,
                    'clientIgnore' => $flags,
                    'pending' => $flags
                ]
            );
            $imageIgnore = $items['imageIgnore'];
            $clientIgnore = $items['clientIgnore'];
            $pending = $items['pending'];
            self::getClass('MACAddressAssociationManager')
                ->update(
                    ['hostID' => $this->obj->get('id')],
                    '',
                    [
                        'imageIgnore' => 0,
                        'clientIgnore' => 0,
                        'pending' => 0
                    ]
                );
            if (count($imageIgnore) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $imageIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['imageIgnore' => 1]
                    );
            }
            if (count($clientIgnore) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $clientIgnore,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['clientIgnore' => 1]
                    );
            }
            if (count($pending) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->update(
                        [
                            'id' => $pending,
                            'hostID' => $this->obj->get('id')
                        ],
                        '',
                        ['pending' => 1]
                    );
            }
        }
    }
    /**
     * Host active directory post element.
     *
     * @return void
     */
    public function hostADPost()
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
            true,
            true,
            $productKey,
            $enforce
        );
    }
    /**
     * Host printers display.
     *
     * @return void
     */
    public function hostPrinters()
    {
        $printerLevel = (
            filter_input(INPUT_POST, 'level') ?: $this->obj->get('printerLevel')
        );
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=host-printers" ';

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
        echo _('Host Printer Configuration');
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
                'This setting will only allow FOG Assigned '
                . 'printers to be added to the host. Any '
                . 'printer that is not assigned will be '
                . 'removed including non-FOG managed printers.'
            )
            . '">';
        echo '<input type="radio" name="level" value="2" '
            . 'id="alllevel"'
            . (
                $printerLevel == 2 ?
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
            _('Printer Type'),
            _('Printer Associated')
        ];
        $this->templates = [
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
            [
                'class' => 'col-md-1'
            ],
            [],
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
        $this->render(12, 'host-printers-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host printer post.
     *
     * @return void
     */
    public function hostPrinterPost()
    {
        if (isset($_POST['levelup'])) {
            $level = filter_input(INPUT_POST, 'level');
            $this->obj->set('printerLevel', $level);
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
     * Host snapins.
     *
     * @return void
     */
    public function hostSnapins()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=host-snapins" ';

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
            _('Snapin Created'),
            _('Snapin Associated')
        ];
        $this->templates = [
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<div class="box box-solid">';
        echo '<div id="updatesnapins" class="">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Host Snapins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'host-snapins-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host snapin post
     *
     * @return void
     */
    public function hostSnapinPost()
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
     * Display's the host service stuff
     *
     * @return void
     */
    public function hostService()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=host-service" ';
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
        $dispBtn = self::makeButton(
            'displayman-send',
            _('Update'),
            'btn btn-primary',
            $props
        );
        $aloBtn = self::makeButton(
            'alo-send',
            _('Update'),
            'btn btn-primary',
            $props
        );
        $this->headerData = [
            _('Module Name'),
            _('Module Associated')
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
        echo _('Host module settings');
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
        // If the x, y, and/or r inputs are set.
        $ix = filter_input(INPUT_POST, 'x');
        $iy = filter_input(INPUT_POST, 'y');
        $ir = filter_input(INPUT_POST, 'r');
        if (!$ix) {
            // If x not set check hosts setting
            $ix = $this->obj->get('hostscreen')->get('width');
            if ($ix) {
                $x = $ix;
           }
        } else {
            $x = $ix;
        }
        if (!$iy) {
            // If y not set check hosts setting
            $iy = $this->obj->get('hostscreen')->get('height');
            if ($iy) {
                $y = $iy;
            }
        } else {
            $y = $iy;
        }
        if (!$ir) {
            // If r not set check hosts setting
            $ir = $this->obj->get('hostscreen')->get('refresh');
            if ($ir) {
                $r = $ir;
            }
        } else {
            $r = $ir;
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
        echo '<form id="host-dispman" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-service" novalidate>';
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
        echo $dispBtn;
        echo '</div>';
        echo '</div>';
        echo '</form>';

        // Auto Log Out
        $tme = filter_input(INPUT_POST, 'tme');
        if (!$tme) {
            $tme = $this->obj->getAlo();
        }
        if (!$tme) {
            $tme = 0;
        }
        $fields = [
            '<label for="tme" class="col-sm-2 control-label">'
            . _('Auto Logout Time')
            . '<br/>('
            . _('in minutes')
            . ')</label>' => '<input type="number" name="tme" class="form-control" '
            . 'value="'
            . $tme
            . '" id="tme" required/>'
        ];
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="host-alo" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-service" novalidate>';
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
        echo $aloBtn;
        echo '</div>';
        echo '</div>';
        echo '</form>';
        // End Box Group
        echo '</div>';
    }
    /**
     * Update the actual thing.
     *
     * @return void
     */
    public function hostServicePost()
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
            if (!(is_numeric($tme) && $tme > 4)) {
                $tme = 0;
            }
            $this->obj->setAlo($tme);
        }
    }
    /**
     * Generates the powermanagement display items.
     *
     * @return void
     */
    public function hostPowermanagement()
    {
        echo '<!-- Power Management -->';
        echo $this->newPMDisplay();
    }
    /**
     * Host power management post.
     *
     * @return void
     */
    public function hostPowermanagementPost()
    {
        if (isset($_POST['pmupdate'])) {
            $onDemand = (int)isset($_POST['onDemand']);
            $items = [];
            $flags = ['flags' => FILTER_REQUIRE_ARRAY];
            if (isset($_POST['pmupdate'])) {
                $items = filter_input_array(
                    INPUT_POST,
                    [
                        'scheduleCronMin' => $flags,
                        'scheduleCronHour' => $flags,
                        'scheduleCronDOM' => $flags,
                        'scheduleCronMonth' => $flags,
                        'scheduleCronDOW' => $flags,
                        'pmid' => $flags,
                        'action' => $flags
                    ]
                );
                extract($items);
                if (!$action) {
                    throw new Exception(
                        _('You must select an action to perform')
                    );
                }
                $items = [];
                foreach ((array)$pmid as $index => &$pm) {
                    $onDemandItem = array_search(
                        $pm,
                        $onDemand
                    );
                    $items[] = [
                        $pm,
                        $this->obj->get('id'),
                        $scheduleCronMin[$index],
                        $scheduleCronHour[$index],
                        $scheduleCronDOM[$index],
                        $scheduleCronMonth[$index],
                        $scheduleCronDOW[$index],
                        $onDemandItem !== -1
                        && $onDemand[$onDemandItem] === $pm ?
                        1 :
                        0,
                        $action[$index]
                    ];
                    unset($pm);
                }
                self::getClass('PowerManagementManager')
                    ->insertBatch(
                        [
                            'id',
                            'hostID',
                            'min',
                            'hour',
                            'dom',
                            'month',
                            'dow',
                            'onDemand',
                            'action'
                        ],
                        $items
                    );
            }
        }
        if (isset($_POST['pmadd'])) {
            $min = trim(
                filter_input(
                    INPUT_POST,
                    'scheduleCronMin'
                )
            );
            $hour = trim(
                filter_input(
                    INPUT_POST,
                    'scheduleCronHour'
                )
            );
            $dom = trim(
                filter_input(
                    INPUT_POST,
                    'scheduleCronDOM'
                )
            );
            $month = trim(
                filter_input(
                    INPUT_POST,
                    'scheduleCronMonth'
                )
            );
            $dow = trim(
                filter_input(
                    INPUT_POST,
                    'scheduleCronDOW'
                )
            );
            $action = trim(
                filter_input(
                    INPUT_POST,
                    'action'
                )
            );
            if ($onDemand && $action === 'wol') {
                $this->obj->wakeOnLAN();
                return;
            }
            self::getClass('PowerManagement')
                ->set('hostID', $this->obj->get('id'))
                ->set('min', $min)
                ->set('hour', $hour)
                ->set('dom', $dom)
                ->set('month', $month)
                ->set('dow', $dow)
                ->set('onDemand', $onDemand)
                ->set('action', $action)
                ->save();
        }
        if (isset($_POST['pmdelete'])) {
            $pmid = filter_input_array(
                INPUT_POST,
                ['rempowermanagements' => $flags]
            );
            $pmid = $pmid['rempowermanagements'];
            self::getClass('PowerManagementManager')
                ->destroy(
                    ['id' => $pmid]
                );
        }
    }
    /**
     * Displays Host Inventory
     *
     * @return void
     */
    public function hostInventory()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=host-inventory" ';
        $cpus = ['cpuman', 'spuversion'];
        foreach ($cpus as &$x) {
            $this->obj->get('inventory')
                ->set(
                    $x,
                    implode(
                        ' ',
                        array_unique(
                            explode(
                                ' ',
                                $this->obj->get('inventory')->get($x)
                            )
                        )
                    )
                )
                ->set('hostID', $this->obj->get('id'));
            unset($x);
        }
        $Inv = $this->obj->get('inventory');
        $puser = $Inv->get('primaryUser');
        $other1 = $Inv->get('other1');
        $other2 = $Inv->get('other2');
        $sysman = $Inv->get('sysman');
        $sysprod = $Inv->get('sysproduct');
        $sysver = $Inv->get('sysversion');
        $sysser = $Inv->get('sysserial');
        $systype = $Inv->get('systype');
        $sysuuid = $Inv->get('sysuuid');
        $biosven = $Inv->get('biosvendor');
        $biosver = $Inv->get('biosversion');
        $biosdate = $Inv->get('biosdate');
        $mbman = $Inv->get('mbman');
        $mbprod = $Inv->get('mbproductname');
        $mbver = $Inv->get('mbversion');
        $mbser = $Inv->get('mbserial');
        $mbast = $Inv->get('mbasset');
        $cpuman = $Inv->get('cpuman');
        $cpuver = $Inv->get('cpuversion');
        $cpucur = $Inv->get('cpucurrent');
        $cpumax = $Inv->get('cpumax');
        $mem = $Inv->getMem();
        $hdmod = $Inv->get('hdmodel');
        $hdfirm = $Inv->get('hdfirmware');
        $hdser = $Inv->get('hdserial');
        $caseman = $Inv->get('caseman');
        $casever = $Inv->get('caseversion');
        $caseser = $Inv->get('caseserial');
        $caseast = $Inv->get('caseasset');
        $fields = [
            '<label for="pu" class="col-sm-2 control-label">'
            . _('Primary User')
            . '</label>' => '<input class="form-control" type="text" value="'
            . $puser
            . '" name="pu" id="pu"/>',
            '<label for="other1" class="col-sm-2 control-label"/>'
            . _('Other Tag #1')
            . '</label>' => '<input class="form-control" type="text" value="'
            . $other1
            . '" name="other1" id="other1"/>',
            '<label for="other2" class="col-sm-2 control-label"/>'
            . _('Other Tag #2')
            . '</label>' => '<input class="form-control" type="text" value="'
            . $other2
            . '" name="other2" id="other2"/>',
            '<label class="col-sm-2 control-label">'
            . _('System Manufacturer')
            . '</label>' => '<input type="text" class="form-control" value="'
            . $sysman
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('System Product')
            . '</label>' => '<input type="text" class="form-control" value="'
            . $sysprod
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('System Version')
            . '</label>' => '<input type="text" class="form-control" value="'
            . $sysver
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('System Serial Number')
            . '</label>' => '<input type="text"  class="form-control" value="'
            . $sysser
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('System UUID') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $sysuuid
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('System Type') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $systype
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('BIOS Vendor') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $biosven
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('BIOS Version') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $biosver
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('BIOS Date') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $biosdate
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Motherboard Manufacturer') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $mbman
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Motherboard Product Name') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $mbprod
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Motherboard Version') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $mbver
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Motherboard Serial Number') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $mbser
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Motherboard Asset Tag') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $mbast
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('CPU Manufacturer') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $cpuman
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('CPU Version') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $cpuver
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('CPU Normal Speed') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $cpucur
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('CPU Max Speed') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $cpumax
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Memory') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $mem
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Hard Disk Model') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $hdmod
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Hard Disk Firmware') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $hdfirm
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Hard Disk Serial Number') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $hdser
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Chassis Manufacturer') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $caseman
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Chassis Version') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $casever
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Chassis Serial') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $caseser
            . '" readonly/>',
            '<label class="col-sm-2 control-label">'
            . _('Chassis Asset') 
            . '</label>' => '<input type="text" class="form-control" value="'
            . $caseast
            . '" readonly/>'
        ];
        self::$HookManager
            ->processEvent(
                'HOST_INVENTORY_FIELDS',
                [
                    'fields' => &$fields,
                    'Host' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        echo '<!-- Inventory -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo '<form id="host-inventory-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL(
                'host-inventory',
                $this->obj->get('id')
            )
            . '" novalidate>';
        echo $rendered;
        echo '<input type="hidden" name="updateinv" value="1"/>';
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="inventory-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually submit inventory data.
     *
     * @return void
     */
    public function hostInventoryPost()
    {
        if (isset($_POST['updateinv'])) {
            $pu = filter_input(INPUT_POST, 'pu');
            $other1 = filter_input(INPUT_POST, 'other1');
            $other2 = filter_input(INPUT_POST, 'other2');
            $this->obj
                ->get('inventory')
                ->set('primaryUser', $pu)
                ->set('other1', $other1)
                ->set('other2', $other2)
                ->set('hostID', $this->obj->get('id'))
                ->save();
        }
    }
    /**
     * Display Login History for Host.
     *
     * @return void
     */
    public function hostLoginHistory()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = [
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
        $this->templates = [
            '${user_time}',
            '${action}',
            '${user_name}',
            '${user_desc}',
        ];
        $dte = filter_input(INPUT_GET, 'dte');
        if (!$dte) {
            self::niceDate()->format('Y-m-d');
        }
        $Dates = self::getSubObjectIDs(
            'UserTracking',
            ['id' => $this->obj->get('users')],
            'date'
        );
        if (count($Dates) > 0) {
            rsort($Dates);
            $dateSel = self::selectForm(
                'dte',
                $Dates,
                $dte,
                false,
                'loghist-date'
            );
        }
        Route::listem(
            'usertracking',
            'name',
            false,
            [
                'hostID' => $this->obj->get('id'),
                'date' => $dte,
                'action' => ['', 0, 1]
            ]
        );
        $UserLogins = json_decode(
            Route::getData()
        );
        $UserLogins = $UserLogins->usertrackings;
        $Data = [];
        foreach ((array)$UserLogins as &$UserLogin) {
            $time = self::niceDate(
                $UserLogin->datetime
            )->format('U');
            if (!isset($Data[$UserLogin->username])) {
                $Data[$UserLogin->username] = [];
            }
            if (array_key_exists('login', $Data[$UserLogin->username])) {
                if ($UserLogin->action > 0) {
                    $this->data[] = [
                        'action' => _('Logout'),
                        'user_name' => $UserLogin->username,
                        'user_time' => (
                            self::niceDate()
                            ->setTimestamp($time - 1)
                            ->format('Y-m-d H:i:s')
                        ),
                        'user_desc' => _('Logout not found')
                        . '<br/>'
                        . _('Setting logout to one second prior to next login')
                    ];
                    $Data[$UserLogin->username] = [];
                }
            }
            if ($UserLogin->action > 0) {
                $Data[$UserLogin->username]['login'] = true;
                $this->data[] = [
                    'action' => _('Login'),
                    'user_name' => $UserLogin->username,
                    'user_time' => (
                        self::niceDate()
                        ->setTimestamp($time)
                        ->format('Y-m-d H:i:s')
                    ),
                    'user_desc' => $UserLogin->description
                ];
            } elseif ($UserLogin->action < 1) {
                $this->data[] = [
                    'action' => _('Logout'),
                    'user_name' => $UserLogin->username,
                    'user_time' => (
                        self::niceDate()
                        ->setTimestamp($time)
                        ->format('Y-m-d H:i:s')
                    ),
                    'user_desc' => $UserLogin->description
                ];
                $Data[$UserLogin->username] = [];
            }
            unset($UserLogin);
        }
        self::$HookManager
            ->processEvent(
                'HOST_USER_LOGIN',
                [
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                ]
            );
        echo '<!-- Login History -->';
        echo '<div class="tab-pane fade" id="host-login-history">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Login History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-login-history" novalidate>';
        if (count($Dates) > 0) {
            echo '<div class="form-group">';
            echo '<label class="control-label col-xs-4" for="dte">';
            echo _('View History For');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo $dateSel;
            echo '</div>';
            echo '</div>';
        }
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Selected Logins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('History Graph');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body" id="login-history">';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Display host imaging history.
     *
     * @return void
     */
    public function hostImageHistory()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = [
            _('Engineer'),
            _('Imaged From'),
            _('Start'),
            _('End'),
            _('Duration'),
            _('Image'),
            _('Type'),
            _('State'),
        ];
        $this->templates = [
            '${createdBy}',
            sprintf(
                '<small>%s: ${group_name}</small><br/><small>%s: '
                . '${node_name}</small>',
                _('Storage Group'),
                _('Storage Node')
            ),
            '<small>${start_date}</small><br/><small>${start_time}</small>',
            '<small>${end_date}</small><br/><small>${end_time}</small>',
            '${duration}',
            '${image_name}',
            '${type}',
            '${state}',
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];
        Route::listem(
            'imaginglog',
            'name',
            false,
            ['hostID' => $this->obj->get('id')]
        );
        $Logs = json_decode(
            Route::getData()
        );
        $Logs = $Logs->data;
        $imgTypes = [
            'up' => _('Capture'),
            'down' => _('Deploy'),
        ];
        foreach ((array)$Logs as &$Log) {
            $start = $Log->start;
            $finish = $Log->finish;
            if (!self::validDate($start)
                || !self::validDate($finish)
            ) {
                continue;
            }
            $diff = self::diff($start, $finish);
            $start = self::niceDate($start);
            $finish = self::niceDate($finish);
            $TaskIDs = self::getSubObjectIDs(
                'Task',
                [
                    'checkInTime' => $Log->start,
                    'hostID' => $this->obj->get('id')
                ]
            );
            $taskID = @max($TaskIDs);
            if (!$taskID) {
                continue;
            }
            Route::indiv('task', $taskID);
            $Task = json_decode(
                Route::getData()
            );
            $groupName = $Task->storagegroup->name;
            $nodeName = $Task->storagenode->name;
            $typeName = $Task->type->name;
            if (!$typeName) {
                $typeName = $Log->type;
            }
            if (in_array($typeName, ['up', 'down'])) {
                $typeName = $imgTypes[$typeName];
            }
            $stateName = $Task->state->name;
            unset($Task);
            $createdBy = (
                $log->createdBy ?:
                self::$FOGUser->get('name')
            );
            $Image = $Log->image;
            if (!$Image->id) {
                $imgName = $Image;
                $imgPath = _('N/A');
            } else {
                $imgName = $Image->name;
                $imgPath = $Image->path;
            }
            $this->data[] = [
                'createdBy' => $createdBy,
                'group_name' => $groupName,
                'node_name' => $nodeName,
                'start_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'end_date' => $finish->format('Y-m-d'),
                'end_time' => $finish->format('H:i:s'),
                'duration' => $diff,
                'image_name' => $imgName,
                'type' => $typeName,
                'state' => $stateName,
            ];
            unset($Image, $Log);
        }
        self::$HookManager
            ->processEvent(
                'HOST_IMAGE_HIST',
                [
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                ]
            );
        echo '<!-- Image History -->';
        echo '<div class="tab-pane fade" id="host-image-history">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Imaging History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Display host snapin history
     *
     * @return void
     */
    public function hostSnapinHistory()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = [
            _('Snapin Name'),
            _('Start Time'),
            _('Complete'),
            _('Duration'),
            _('Return Code')
        ];
        $this->templates = [
            '${snapin_name}',
            '${snapin_start}',
            '${snapin_end}',
            '${snapin_duration}',
            '${snapin_return}'
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];
        $SnapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            ['hostID' => $this->obj->get('id')]
        );
        $doneStates = [
            self::getCompleteState(),
            self::getCancelledState()
        ];
        Route::listem(
            'snapintask',
            'name',
            false,
            ['jobID' => $SnapinJobIDs]
        );
        $SnapinTasks = json_decode(
            Route::getData()
        );
        $SnapinTasks = $SnapinTasks->snapintasks;
        foreach ((array)$SnapinTasks as &$SnapinTask) {
            $Snapin = $SnapinTask->snapin;
            $start = self::niceDate($SnapinTask->checkin);
            $end = self::niceDate($SnapinTask->complete);
            if (!self::validDate($start)) {
                continue;
            }
            if (!in_array($SnapinTask->stateID, $doneStates)) {
                $diff = _('Snapin task not completed');
            } elseif (!self::validDate($end)) {
                $diff = _('No complete time recorded');
            } else {
                $diff = self::diff($start, $end);
            }
            $this->data[] = [
                'snapin_name' => $Snapin->name,
                'snapin_start' => $start->format('Y-m-d H:i:s'),
                'snapin_end' => sprintf(
                    '<span data-toggle="tooltip" data-placement="left" '
                    . 'class="icon" title="%s">%s</span>',
                    $end->format('Y-m-d H:i:s'),
                    $SnapinTask->state->name
                ),
                'snapin_duration' => $diff,
                'snapin_return'=> $SnapinTask->return,
            ];
            unset($SnapinTask);
        }
        self::$HookManager
            ->processEvent(
                'HOST_SNAPIN_HIST',
                [
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                ]
            );
        echo '<div class="tab-pane fade" id="host-snapin-history">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Snapin History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Edits an existing item.
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
            'id' => 'host-general',
            'generator' => function() {
                $this->hostGeneral();
            }
        ];

        // MAC Addresses
        $tabData[] = [
            'name' => _('MAC Addresses'),
            'id' => 'host-macaddress',
            'generator' => function() {
                $this->hostMacaddress();
            }
        ];

        // Tasks
        if (!$this->obj->get('pending')) {
            $tabData[] = [
                'name' =>  _('Tasks'),
                'id' => 'host-tasks',
                'generator' => function() {
                    $this->basictasksOptions();
                }
            ];
        }

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Groups'),
                        'id' => 'host-groups',
                        'generator' => function() {
                            //$this->hostMembership();
                            echo 'TODO: Make functional';
                        }
                    ],
                    [
                        'name' => _('Printers'),
                        'id' => 'host-printers',
                        'generator' => function() {
                            $this->hostPrinters();
                        }
                    ],
                    [
                        'name' => _('Snapins'),
                        'id' => 'host-snapins',
                        'generator' => function() {
                            $this->hostSnapins();
                        }
                    ],
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
                        'id' => 'host-service',
                        'generator' => function() {
                            $this->hostService();
                        }
                    ],
                    [
                        'name' =>  _('Active Directory'),
                        'id' => 'host-active-directory',
                        'generator' => function() {
                            $this->adFieldsToDisplay(
                                $this->obj->get('useAD'),
                                $this->obj->get('ADDomain'),
                                $this->obj->get('ADOU'),
                                $this->obj->get('ADUser'),
                                $this->obj->get('ADPass'),
                                $this->obj->get('enforce')
                            );
                        }
                    ],
                    [
                        'name' => _('Power Management'),
                        'id' => 'host-powermanagement',
                        'generator' => function() {
                            $this->hostPowerManagement();
                        }
                    ]
                ]
            ]
        ];

        // Inventory
        $tabData[] = [
            'name' => _('Inventory'),
            'id' => 'host-inventory',
            'generator' => function() {
                $this->hostInventory();
            }
        ];

        // History Items
        $tabData[] = [
            'tabs' => [
                'name' => _('History Items'),
                'tabData' => [
                    [
                        'name' => _('Login History'),
                        'id' => 'host-login-history',
                        'generator' => function() {
                            //$this->hostLoginHistory();
                            echo 'TODO: Make functional';
                        }
                    ],
                    [
                        'name' => _('Imaging History'),
                        'id' => 'host-image-history',
                        'generator' => function() {
                            //$this->hostImageHistory();
                            echo 'TODO: Make functional';
                        }
                    ],
                    [
                        'name' => _('Snapin History'),
                        'id' => 'host-snapin-history',
                        'generator' => function() {
                            //$this->hostSnapinHistory();
                            echo 'TODO: Make functional';
                        }
                    ],
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Updates the host when form is submitted
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'HOST_EDIT_POST',
            ['Host' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'host-general':
                $this->hostGeneralPost();
                break;
            case 'host-macaddress':
                $this->hostMacaddressPost();
                break;
            case 'host-active-directory':
                $this->hostADPost();
                break;
            case 'host-powermanagement':
                $this->hostPowermanagementPost();
                break;
            case 'host-printers':
                $this->hostPrinterPost();
                break;
            case 'host-snapins':
                $this->hostSnapinPost();
                break;
            case 'host-groups':
                $this->hostGroupPost();
                break;
            case 'host-service':
                $this->hostServicePost();
                break;
            case 'host-inventory':
                $this->hostInventoryPost();
                break;
            case 'host-login-history':
                $dte = filter_input(INPUT_POST, 'dte');
                self::redirect(
                    '../management/index.php?node='
                    . $this->node
                    . '&sub=edit&id='
                    . $this->obj->get('id')
                    . '&dte='
                    . $dte
                    . '#'
                    . $tab
                );
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Host update failed!'));
            }
            $this->obj->setAD();
            if ($tab == 'host-general') {
                $igstuff = filter_input_array(
                    INPUT_POST,
                    [
                        'igimage' => [
                            'flags' => FILTER_REQUIRE_ARRAY
                        ],
                        'igclient' => [
                            'flags' => FILTER_REQUIRE_ARRAY
                        ]
                    ]
                );
                $igimage = $igstuff['igimage'];
                $igclient = $igstuff['igclient'];
                $this->obj->ignore($igimage, $igclient);
            }
            $code = 201;
            $hook = 'HOST_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Host updated!'),
                    'title' => _('Host Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'HOST_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'Host' => &$this->obj,
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
     * Saves host to a selected or new group depending on action.
     *
     * @return void
     */
    public function saveGroup()
    {
        $group = filter_input(INPUT_POST, 'group');
        $newgroup = filter_input(INPUT_POST, 'group_new');
        $hostids = filter_input(
            INPUT_POST,
            'hostIDArray'
        );
        $hostids = array_values(
            array_filter(
                array_unique(
                    explode(',', $hostids)
                )
            )
        );
        try {
            $Group = new Group($group);
            if ($newgroup) {
                $Group
                    ->set('name', $newgroup)
                    ->load('name');
            }
            $Group->addHost($hostids);
            if (!$Group->save()) {
                $serverFault = true;
                throw new Exception(_('Failed to create new Group'));
            }
            $code = 201;
            $msg = json_encode(
                [
                    'msg' => _('Successfully added selected hosts to the group!'),
                    'title' => _('Host Add to Group Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Add to Group Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Gets the host user tracking info.
     *
     * @return void
     */
    public function hostlogins()
    {
        $date = filter_input(INPUT_GET, 'dte');
        $MainDate = self::niceDate($date)
            ->getTimestamp();
        $MainDate_1 = self::niceDate($date)
            ->modify('+1 day')
            ->getTimestamp();
        Route::listem('UserTracking');
        $UserTracks = json_decode(
            Route::getData()
        );
        $UserTracks = $UserTracks->usertrackings;
        $data = null;
        $Data = [];
        foreach ((array)$UserTracks as &$Login) {
            $ldate = self::niceDate($Login->date)
                ->format('Y-m-d');
            if ($Login->hostID != $this->obj->get('id')
                || $ldate != $date
                || !in_array($Login->action, ['', 0, 1])
            ) {
                continue;
            }
            $time = self::niceDate($Login->datetime);
            $Data[$Login->username] = [
                'user' => $Login->username,
                'min' => $MainDate,
                'max' => $MainDate_1
            ];
            if (array_key_exists('login', $Data[$Login->username])) {
                if ($Login->action > 0) {
                    $Data[$Login->username]['logout'] = (int)$time - 1;
                    $data[] = $Data[$Login->username];
                } elseif ($Login->action < 1) {
                    $Data[$Login->username]['logout'] = (int)$time;
                    $data[] = $Data[$Login->username];
                }
                $Data[$Login->username] = [
                    'user' => $Login->username,
                    'min' => $MainDate,
                    'max' => $MainDate_1
                ];
            }
            if ($Login->action > 0) {
                $Data[$Login->username]['login'] = (int)$time;
            }
            unset($Login);
        }
        unset($UserTracks);
        echo json_encode($data);
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

        $where = "`hosts`.`hostID` = '"
            . $this->obj->get('id')
            . "'";

        // Workable queries
        $printersSqlStr = "SELECT `%s`,"
            . "IF(`paHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `paHostID`,`paIsDefault`
            FROM `%s`
            CROSS JOIN `hosts`
            LEFT OUTER JOIN `printerAssoc`
            ON `printers`.`pID` = `printerAssoc`.`paPrinterID`
            AND `hosts`.`hostID` = `printerAssoc`.`paHostID`
            %s
            %s
            %s";
        $printersFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`paHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `paHostID`,`paIsDefault`
            FROM `%s`
            CROSS JOIN `hosts`
            LEFT OUTER JOIN `printerAssoc`
            ON `printers`.`pID` = `printerAssoc`.`paPrinterID`
            AND `hosts`.`hostID` = `printerAssoc`.`paHostID`
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
        $columns[] = [
            'db' => 'paIsDefault',
            'dt' => 'isDefault'
        ];
        $columns[] = [
            'db' => 'paHostID',
            'dt' => 'association'
        ];
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

        $where = "`hosts`.`hostID` = '"
            . $this->obj->get('id')
            . "'";

        // Workable queries
        $snapinsSqlStr = "SELECT `%s`,"
            . "IF(`saHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `saHostID`
            FROM `%s`
            CROSS JOIN `hosts`
            LEFT OUTER JOIN `snapinAssoc`
            ON `snapins`.`sID` = `snapinAssoc`.`saSnapinID`
            AND `hosts`.`hostID` = `snapinAssoc`.`saHostID`
            %s
            %s
            %s";
        $snapinsFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`saHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `saHostID`
            FROM `%s`
            CROSS JOIN `hosts`
            LEFT OUTER JOIN `snapinAssoc`
            ON `snapins`.`sID` = `snapinAssoc`.`saSnapinID`
            AND `hosts`.`hostID` = `snapinAssoc`.`saHostID`
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
        $columns[] = [
            'db' => 'saHostID',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'snapins',
                'sID',
                $columns,
                $snapinsSqlStr,
                $snapinsFilterStr,
                $snapinsTotalStr,
                $where
            )
        );
        exit;
    }
    /**
     * Returns the module list as well as the associated
     * for the host being edited.
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

        $where = "`hosts`.`hostID` = '"
            . $this->obj->get('id')
            . "' AND `modules`.`short_name` "
            . "NOT IN ('"
            . implode("','", $notWhere)
            . "') AND `modules`.`short_name` IN ('"
            . implode("','", $keys)
            . "')";

        // Workable queries
        $modulesSqlStr = "SELECT `%s`,"
            . "IF(`msHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `msHostID`
            FROM `%s`
            CROSS JOIN `hosts`
            LEFT OUTER JOIN `moduleStatusByHost`
            ON `modules`.`id` = `moduleStatusByHost`.`msModuleID`
            AND `hosts`.`hostID` = `moduleStatusByHost`.`msHostID`
            %s
            GROUP BY `modules`.`short_name`
            %s
            %s";
        $modulesFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `hosts`
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
        $columns[] = [
            'db' => 'msHostID',
            'dt' => 'association'
        ];
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
     * Get's the hosts mac address list.
     *
     * @return void
     */
    public function getMacaddressesList()
    {
        $where = "`hostMAC`.`hmHostID` = '"
            . $this->obj->get('id')
            . "'";

        // Workable queries
        $macaddressesSqlStr = "SELECT `%s`
            FROM `%s`
            LEFT OUTER JOIN `hosts`
            ON `hostMAC`.`hmHostID` = `hosts`.`hostID`
            %s
            %s
            %s";
        $macaddressesFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `hosts`
            ON `hostMAC`.`hmHostID` = `hosts`.`hostID`
            %s";
        $macaddressesTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            WHERE $where";

        foreach (self::getClass('MACAddressAssociationManager')
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
                'hostMAC',
                'hmID',
                $columns,
                $macaddressesSqlStr,
                $macaddressesFilterStr,
                $macaddressesTotalStr,
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
        $this->headerData = [
            'primac'
        ];
        $this->templates = [
            ''
        ];
        $this->attributes = [
            []
        ];

        $obj = self::getClass('HostManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            array_push($this->headerData, $common);
            array_push($this->templates, '');
            array_push($this->attributes, []);
            unset($real);
        }

        $this->title = _('Export Hosts');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export Hosts');
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
        $this->render(12, 'host-export-table');
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
        $obj = self::getClass('HostManager');
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
        $columns[] = ['db' => 'hmMAC', 'dt' => 'primac'];
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
            'HOST_EXPORT_ITEMS',
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
    /**
     * Get pending host list.
     *
     * @return void
     */
    public function getPendingList()
    {
        header('Content-type: application/json');

        $where = "`hosts`.`hostPending` > '0'";

        $obj = self::getClass('HostManager');
        $table = $obj->getTable();
        $sqlstr = "SELECT `%s`
            FROM `%s`
            LEFT OUTER JOIN `images`
            ON `hosts`.`hostImage` = `images`.`imageID`
            LEFT OUTER JOIN `hostMAC`
            ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
            AND `hostMAC`.`hmPrimary` = '1'
            %s
            %s
            %s";
        $filterstr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `images`
            ON `hosts`.`hostImage` = `images`.`imageID`
            LEFT OUTER JOIN `hostMAC`
            ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
            AND `hostMAC`.`hmPrimary` = '1'
            %s";
        $totalstr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `images`
            ON `hosts`.`hostImage` = `images`.`imageID`
            LEFT OUTER JOIN `hostMAC`
            ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
            AND `hostMAC`.`hmPrimary` = '1'
            WHERE "
            . $where;
        $dbcolumns = $obj->getColumns();
        $pass_vars = $columns = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        // Setup our columns for the CSVn.
        // Automatically removes the id column.
        $columns[] = ['db' => 'hmMAC', 'dt' => 'primac'];
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = ['db' => 'imageName', 'dt' => 'imagename'];
        self::$HookManager->processEvent(
            'HOST_PENDING_HOSTS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr,
                $where
            )
        );
        exit;
    }
    /**
     * Get pending mac list.
     *
     * @return void
     */
    public function getPendingMacList()
    {
        header('Content-type: application/json');

        $where = "`hostMAC`.`hmPending` > '0'";

        $obj = self::getClass('MACAddressAssociationManager');
        $table = $obj->getTable();
        $sqlstr = "SELECT `%s`
            FROM `%s`
            LEFT OUTER JOIN `hosts`
            ON `hostMAC`.`hmHostID` = `hosts`.`hostID`
            %s
            %s
            %s";
        $filterstr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `hosts`
            ON `hostMAC`.`hmHostID` = `hosts`.`hostID`
            %s";
        $totalstr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `hosts`
            ON `hostMAC`.`hmHostID` = `hosts`.`hostID`
            WHERE "
            . $where;
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
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = ['db' => 'hostID', 'dt' => 'hostid'];
        $columns[] = ['db' => 'hostName', 'dt' => 'hostname'];
        self::$HookManager->processEvent(
            'HOST_PENDING_MACS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr,
                $where
            )
        );
        exit;
    }
}
