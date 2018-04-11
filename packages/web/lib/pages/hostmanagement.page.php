<?php
/**
 * Host management page
 *
 * PHP version 5
 *
 * The host represented to the GUI
 *
 * @category HostManagement
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
 * @category HostManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostManagement extends FOGPage
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
        $this->attributes = [
            [],
            []
        ];
        if (self::$fogpingactive) {
            $this->headerData[] = _('Ping Status');
            $this->attributes[] = [];
        }
        array_push(
            $this->headerData,
            _('Imaged'),
            _('Assigned Image'),
            _('Description')
        );
        array_push(
            $this->attributes,
            [],
            [],
            []
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
            $this->attributes[4]
        );

        // Reorder the arrays
        $this->headerData = array_values(
            $this->headerData
        );
        $this->attributes = array_values(
            $this->attributes
        );

        $buttons = self::makeButton(
            'approve',
            _('Approve selected'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'delete',
            _('Delete selected'),
            'btn btn-danger pull-left'
        );

        $modalApprovalBtns = self::makeButton(
            'confirmApproveModal',
            _('Approve'),
            'btn btn-outline pull-right'
        );
        $modalApprovalBtns .= self::makeButton(
            'cancelApprovalModal',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $approvalModal = self::makeModal(
            'approveModal',
            _('Approve Pending Hosts'),
            _('Approving the selected pending hosts.'),
            $modalApprovalBtns,
            '',
            'info'
        );

        $modalDeleteBtns = self::makeButton(
            'confirmDeleteModal',
            _('Delete'),
            'btn btn-outline pull-right'
        );
        $modalDeleteBtns .= self::makeButton(
            'closeDeleteModal',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $deleteModal = self::makeModal(
            'deleteModal',
            _('Confirm password'),
            '<div class="input-group">'
            . self::makeInput(
                'form-control',
                'deletePassword',
                _('Password'),
                'password',
                'deletePassword',
                '',
                true
            )
            . '</div>',
            $modalDeleteBtns,
            '',
            'danger'
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
        $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '<div class="box-footer">';
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
            self::checkauth();
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
            http_response_code(HTTPResponseCodes::HTTP_ACCEPTED);
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
        $this->attributes = [
            [],
            []
        ];

        self::$HookManager->processEvent(
            'HOST_PENDING_MAC_DATA',
            [
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
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'delete',
            _('Delete selected'),
            'btn btn-danger pull-left'
        );

        $modalApprovalBtns = self::makeButton(
            'confirmApproveModal',
            _('Approve'),
            'btn btn-outline pull-right'
        );
        $modalApprovalBtns .= self::makeButton(
            'cancelApprovalModal',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $approvalModal = self::makeModal(
            'approveModal',
            _('Approve Pending Hosts'),
            _('Approving the selected pending hosts.'),
            $modalApprovalBtns,
            '',
            'success'
        );

        $modalDeleteBtns = self::makeButton(
            'confirmDeleteModal',
            _('Delete'),
            'btn btn-outline pull-right'
        );
        $modalDeleteBtns .= self::makeButton(
            'closeDeleteModal',
            _('Cancel'),
            'btn btn-outline pull-left',
            'data-dismiss="modal"'
        );
        $deleteModal = self::makeModal(
            'deleteModal',
            _('Confirm password'),
            '<div class="input-group">'
            . self::makeInput(
                'form-control',
                'deletePassword',
                _('Password'),
                'password',
                'deletePassword',
                '',
                true
            )
            . '</div>',
            $modalDeleteBtns,
            '',
            'danger'
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
        $this->render(12, 'dataTable', $buttons);
        echo '</div>';
        echo '<div class="box-footer">';
        //echo $buttons;
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
            self::checkauth();
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
            http_response_code(HTTPResponseCodes::HTTP_ACCEPTED);
            echo json_encode(
                [
                    'msg' => _('Approved selected macs!'),
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
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');
        $domain = filter_input(INPUT_POST, 'domain');
        $domainname = filter_input(INPUT_POST, 'domainname');
        $ou = filter_input(INPUT_POST, 'ou');
        $domainuser = filter_input(INPUT_POST, 'domainuser');
        $domainpassword = filter_input(INPUT_POST, 'domainpassword');
        $enforce = isset($_POST['enforce']) ?: self::getSetting(
            'FOG_ENFORCE_HOST_CHANGES'
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image, '', 'id');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'host',
                _('Host Name')
            ) => self::makeInput(
                'form-control hostname-input',
                'host',
                _('Host Name'),
                'text',
                'host',
                $host,
                true,
                false,
                -1,
                15
            ),
            self::makeLabel(
                $labelClass,
                'mac',
                _('MAC Address')
            ) => self::makeInput(
                'form-control hostmac-input',
                'mac',
                '00:00:00:00:00:00',
                'text',
                'mac',
                $mac,
                true,
                false,
                -1,
                17,
                'exactlength="12"'
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Host Description')
            ) => self::makeTextarea(
                'form-control hostdescription-input',
                'description',
                _('Host Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Host Product Key')
            ) => self::makeInput(
                'form-control hostkey-input',
                'key',
                'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                'text',
                'key',
                $key,
                false,
                false,
                -1,
                29,
                'exactlength="25"'
            ),
            self::makeLabel(
                $labelClass,
                'image',
                _('Host Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Host Kernel')
            ) => self::makeInput(
                'form-control hostkernel-input',
                'kernel',
                'bzImage_Custom',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Host Kernel Arguments')
            ) => self::makeInput(
                'form-control hostargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Host Init')
            ) => self::makeInput(
                'form-control hostinit-input',
                'init',
                'customInit.xz',
                'text',
                'init',
                $init
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Host Primary Disk')
            ) => self::makeInput(
                'form-control hostdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            ),
            self::makeLabel(
                $labelClass,
                'enforce',
                _('Enforce Hostname | AD Join Reboots')
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
            ),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Host BIOS Exit Type')
            ) => $this->exitNorm,
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Host EFI Exit Type')
            ) => $this->exitEfi
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'HOST_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => self::getClass('Host')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $fieldads = $this->adFieldsToDisplay(
            $domain,
            $domainname,
            $ou,
            $domainuser,
            $domainpassword,
            false,
            true
        );

        self::$HookManager->processEvent(
            'HOST_ADD_AD_FIELDS',
            [
                'fields' => &$fieldads,
                'Host' => self::getClass('Host')
            ]
        );
        $renderedad = self::formFields($fieldads);
        unset($fieldads);

        echo self::makeFormTag(
            'form-horizontal',
            'host-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="host-create">';
        echo '<div class="box-body">';
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

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Active Directory');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $renderedad;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
        $description = trim(
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
        $enforce = (int)isset($_POST['enforce']);
        $image = (int)filter_input(INPUT_POST, 'image');
        $kernel = trim(
            filter_input(INPUT_POST, 'kernel')
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
            $exists = self::getClass('HostManager')
                ->exists($host);
            if ($exists) {
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
                ->set('description', $description)
                ->set('imageID', $image)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelArgs)
                ->set('kernelDevice', $kernelDevice)
                ->set('init', $init)
                ->set('biosexit', $bootTypeExit)
                ->set('efiexit', $efiBootTypeExit)
                ->set('productKey', $productKey)
                ->set('enforce', $enforce)
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
                    $productKey
                );
            if (!self::$Host->save()) {
                $serverFault = true;
                throw new Exception(_('Add host failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'HOST_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Host added!'),
                    'title' => _('Host Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'HOST_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=host&sub=edit&id='
        //    . $Host->get('id')
        //);
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
        $image = (
            filter_input(INPUT_POST, 'image') ?:
            $this->obj->get('imageID')
        );
        $imageSelector = self::getClass('ImageManager')
            ->buildSelectBox($image);
        // Either use the passed in or get the objects info.
        $host = (
            filter_input(INPUT_POST, 'host') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $productKey = (
            filter_input(INPUT_POST, 'key') ?:
            $this->obj->get('productKey')
        );
        $productKeytest = self::aesdecrypt($productKey);
        $test_base64 = base64_decode($productKeytest);
        $base64 = mb_detect_encoding($test_base64, 'utf-8', true);
        $enctest = mb_detect_encoding($productKeytest, 'utf-8', true);
        if ($base64) {
            $productKey = $test_base64;
        } elseif ($enctest) {
            $productKey = $productKeytest;
        }
        $kernel = (
            filter_input(INPUT_POST, 'kernel') ?:
            $this->obj->get('kernel')
        );
        $args = (
            filter_input(INPUT_POST, 'args') ?:
            $this->obj->get('kernelArgs')
        );
        $init = (
            filter_input(INPUT_POST, 'init') ?:
            $this->obj->get('init')
        );
        $dev = (
            filter_input(INPUT_POST, 'dev') ?:
            $this->obj->get('kernelDevice')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'host',
                _('Host Name')
            ) => self::makeInput(
                'form-control hostname-input',
                'host',
                _('Host Name'),
                'text',
                'host',
                $host,
                true,
                false,
                -1,
                15
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Host Description')
            ) => self::makeTextarea(
                'form-control hostdescription-input',
                'description',
                _('Host Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'key',
                _('Host Product Key')
            ) => self::makeInput(
                'form-control hostkey-input',
                'key',
                'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                'text',
                'key',
                $key,
                false,
                false,
                -1,
                29,
                'exactlength="25"'
            ),
            self::makeLabel(
                $labelClass,
                'image',
                _('Host Image')
            ) => $imageSelector,
            self::makeLabel(
                $labelClass,
                'kernel',
                _('Host Kernel')
            ) => self::makeInput(
                'form-control hostkernel-input',
                'kernel',
                'bzImage_Custom',
                'text',
                'kernel',
                $kernel
            ),
            self::makeLabel(
                $labelClass,
                'args',
                _('Host Kernel Arguments')
            ) => self::makeInput(
                'form-control hostargs-input',
                'args',
                'debug acpi=off',
                'text',
                'args',
                $args
            ),
            self::makeLabel(
                $labelClass,
                'init',
                _('Host Init')
            ) => self::makeInput(
                'form-control hostinit-input',
                'init',
                'customInit.xz',
                'text',
                'init',
                $init
            ),
            self::makeLabel(
                $labelClass,
                'dev',
                _('Host Primary Disk')
            ) => self::makeInput(
                'form-control hostdev-input',
                'dev',
                '/dev/md0',
                'text',
                'dev',
                $dev
            ),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Host BIOS Exit Type')
            ) => $this->exitNorm,
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Host EFI Exit Type')
            ) => $this->exitEfi
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= '<div class="btn-group">';
        $buttons .= self::makeButton(
            'reset-encryption-data',
            _('Reset Encryption Data'),
            'btn btn-warning pull-left'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );
        $buttons .= '</div>';

        self::$HookManager->processEvent(
            'HOST_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$this->obj
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
            'host-general-form',
            self::makeTabUpdateURL(
                'host-general',
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
        echo '<div class="box-footer">';
        echo $buttons;
        echo $modalreset;
        echo $this->deleteModal();
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
        $description = trim(
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
        if ($host != $this->obj->get('name')) {
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
        $this->obj
            ->set('name', $host)
            ->set('description', $description)
            ->set('imageID', $imageID)
            ->set('kernel', $kernel)
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
            . self::makeTabUpdateURL(
                'host-macaddress',
                $this->obj->get('id')
            )
            . '" ';

        $fields = [
            self::makeLabel(
                'col-sm-3 control-label',
                'newMac',
                _('Add New MAC')
            ) => self::makeInput(
                'form-control hostmac-input',
                'newMac',
                '00:00:00:00:00:00',
                'text',
                'newMac',
                $newMac,
                true
            )
        ];

        $buttons = self::makeButton(
            'newmac-send',
            _('Add'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'HOST_MACADDRESS_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

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
        echo self::makeFormTag(
            'form-horizontal',
            'macaddress-add-form',
            self::makeTabUpdateURL(
                'host-macaddress',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo self::makeInput(
            '',
            'macadd',
            '',
            'hidden',
            '',
            '1'
        );
        echo '</form>';
        echo '</div>';

        // MAC Address Table
        $buttons = self::makeButton(
            'macaddress-table-update',
            _('Update selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'macaddress-table-delete',
            _('Delete selected'),
            'btn btn-danger pull-left',
            $props
        );
        $this->headerData = [
            _('MAC Address'),
            _('Primary'),
            _('Ignore Imaging'),
            _('Ignore Client'),
            _('Pending')
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
            filter_input(INPUT_POST, 'domainname')
        );
        $ou = trim(
            filter_input(INPUT_POST, 'ou')
        );
        $user = trim(
            filter_input(INPUT_POST, 'domainuser')
        );
        $pass = trim(
            filter_input(INPUT_POST, 'domainpassword')
        );
        $this->obj->setAD(
            $useAD,
            $domain,
            $ou,
            $user,
            $pass,
            true,
            true,
            $productKey
        );
    }
    /**
     * Host groups dispay.
     *
     * @return void
     */
    public function hostGroups()
    {
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-groups',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'groups-add',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'groups-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        $this->headerData = [
            _('Group Name'),
            _('Group Associated')
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<!-- Groups -->';
        echo '<div class="box-group" id="groups">';
        echo '<div class="box box-solid">';
        echo '<div id="updategroups" class="">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Host Groups');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'host-groups-table', $buttons);
        echo '</div>';
        echo '<div clas="box-footer with-border">';
        echo $this->assocDelModal('group');
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Host groups modifications.
     *
     * @return void
     */
    public function hostGroupPost()
    {
        if (isset($_POST['updategroups'])) {
            $groups = filter_input_array(
                INPUT_POST,
                [
                    'group' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $groups = $groups['group'];
            if (count($groups ?: []) > 0) {
                $this->obj->addGroup($groups);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $groups = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $groups = $groups['remitems'];
            if (count($groups ?: []) > 0) {
                $this->obj->removeGroup($groups);
            }
        }
    }
    /**
     * Host printers display.
     *
     * @return void
     */
    public function hostPrinters()
    {
        $printerLevel = (
            filter_input(INPUT_POST, 'level') ?:
            $this->obj->get('printerLevel')
        );
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'host-printers',
                $this->obj->get('id')
            )
            . '" ';

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
        echo self::makeFormTag(
            'form-horizontal',
            'printer-config-form',
            self::makeTabUpdateURL(
                'host-printers',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
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
                'This setting will only allow FOG Assigned '
                . 'printers to be added to the host. Any '
                . 'printer that is not assigned will be '
                . 'removed including non-FOG managed printers.'
            )
            . '"'
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo self::makeButton(
            'printer-config-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        $buttons = self::makeButton(
            'printer-default',
            _('Update default'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'printer-add',
            _('Add selected'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'printer-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );
        $this->headerData = [
            _('Default'),
            _('Printer Alias'),
            _('Printer Type'),
            _('Printer Associated')
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
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('printer');
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

        $buttons = self::makeButton(
            'snapins-add',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'snapins-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        $this->headerData = [
            _('Snapin Name'),
            _('Snapin Created'),
            _('Snapin Associated')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];

        echo '<!-- Snapins -->';
        echo '<div class="box-group" id="snapins">';
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
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('snapin');
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
     * Display's the host service stuff
     *
     * @return void
     */
    public function hostService()
    {
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=host-service" ';
        $buttons = self::makeButton(
            'modules-update',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'modules-enable',
            _('Enable All'),
            'btn btn-success pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'modules-disable',
            _('Disable All'),
            'btn btn-danger pull-left',
            $props
        );
        $dispBtn = self::makeButton(
            'displayman-send',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        $aloBtn = self::makeButton(
            'alo-send',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );
        $this->headerData = [
            _('Module Name'),
            _('Module Associated')
        ];
        $this->attributes = [
            [],
            []
        ];
        $labelClass = 'col-sm-3 control-label';
        // Modules Enable/Disable/Selected
        echo '<!-- Modules/Service Settings -->';
        echo '<div class="box-group" id="modules">';
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
                self::makeLabel(
                    'col-sm-3 control-label',
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

        $rendered = self::formFields($fields);
        unset($fields);
        echo self::makeFormTag(
            'form-horizontal',
            'host-dispman',
            self::makeTabUpdateURL(
                'host-service',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
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
        echo self::makeInput(
            '',
            'dispmansend',
            '',
            'hidden',
            '',
            '1'
        );
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
            self::makeLabel(
                'col-sm-3 control-label',
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

        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'host-alo',
            self::makeTabUpdateURL(
                'host-service',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
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
        echo self::makeInput(
            '',
            'alosend',
            '',
            'hidden',
            '',
            1
        );
        echo '</div>';
        echo '<div class="box-footer">';
        echo $aloBtn;
        echo '</div>';
        echo '</div>';
        echo '</form>';

        // Hostname changer reboot/domain join reboot forced.
        $enforce = (
            filter_input(INPUT_POST, 'enforce') ?:
            $this->obj->get('enforce')
        );
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
        $enforcebtn = self::makeButton(
            'enforcebtn',
            _('Update'),
            'btn btn-primary pull-right',
            $props
        );

        self::$HookManager->processEvent(
            'HOST_ENFORCE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$enforcebtn,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'host-enforce',
            self::makeTabUpdateURL(
                'host-service',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-warning">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Enforce Hostname | AD Join Reboots');
        echo '</h4>';
        echo '<div>';
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
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo self::makeInput(
            '',
            'enforcesend',
            '',
            'hidden',
            '',
            '1'
        );
        echo $enforcebtn;
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
        if (isset($_POST['enforcesend'])) {
            $enforce = (int)isset($_POST['enforce']);
            $this->obj->set('enforce', $enforce);
        }
    }
    /**
     * Generates the powermanagement display items.
     *
     * @return void
     */
    public function hostPowermanagement()
    {
        //echo '<!-- Power Management -->';
        //echo $this->newPMDisplay();
        // The powermanagement table.
        $this->headerData = [
            _('Cron Schedule'),
            _('Action')
        ];
        $this->attributes = [
            [],
            []
        ];
        $buttons = self::makeButton(
            'ondemandBtn',
            _('Create New Immediate'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'scheduleCreateBtn',
            _('Create New Scheduled'),
            'btn btn-info pull-right'
        );
        $buttons .= self::makeButton(
            'pm-delete',
            _('Delete selected'),
            'btn btn-danger pull-left'
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
        echo '<div class="box box-info">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Scheduled Power Management Tasks');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'host-powermanagement-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer">';
        echo self::makeModal(
            'ondemandModal',
            _('Create Immediate Power task'),
            $this->newPMDisplay(true),
            $ondemandModalBtns,
            '',
            'info'
        );
        echo self::makeModal(
            'scheduleModal',
            _('Create Scheduled Power task'),
            $this->newPMDisplay(false),
            $scheduleModalBtns,
            '',
            'primary'
        );
        echo '</div>';
        echo '</div>';
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

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'pu',
                _('Primary User')
            ) => self::makeInput(
                'form-control',
                'pu',
                _('Primary User'),
                'text',
                'pu',
                $puser
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
                $other1
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
                $other2
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

        $buttons = self::makeButton(
            'inventory-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'HOST_INVENTORY_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'host-inventory-form',
            self::makeTabUpdateURL(
                'host-inventory',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<!-- Inventory -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo self::makeInput(
            '',
            'updateinv',
            '',
            'hidden',
            '',
            '1'
        );
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
        echo '<!-- Host Login History -->';
        echo '<div class="box-group" id="loginhistory">';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Host Login History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'host-login-table');
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display host imaging history.
     *
     * @return void
     */
    public function hostImageHistory()
    {
        $this->headerData = [
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
            []
        ];
        echo '<!-- Host Image History -->';
        echo '<div class="box-group" id="imagehistory">';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Host Image History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'host-image-table');
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Display host snapin history
     *
     * @return void
     */
    public function hostSnapinHistory()
    {
        $this->headerData = [
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
            []
        ];
        echo '<!-- Host Snapin History -->';
        echo '<div class="box-group" id="snapinhistory">';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Host Snapin History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'host-snapin-table');
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
            'generator' => function () {
                $this->hostGeneral();
            }
        ];

        // MAC Addresses
        $tabData[] = [
            'name' => _('MAC Addresses'),
            'id' => 'host-macaddress',
            'generator' => function () {
                $this->hostMacaddress();
            }
        ];

        // Tasks
        if (!$this->obj->get('pending')) {
            $tabData[] = [
                'name' =>  _('Tasks'),
                'id' => 'host-tasks',
                'generator' => function () {
                    $this->hostTasks();
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
                        'generator' => function () {
                            $this->hostGroups();
                        }
                    ],
                    [
                        'name' => _('Printers'),
                        'id' => 'host-printers',
                        'generator' => function () {
                            $this->hostPrinters();
                        }
                    ],
                    [
                        'name' => _('Snapins'),
                        'id' => 'host-snapins',
                        'generator' => function () {
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
                        'generator' => function () {
                            $this->hostService();
                        }
                    ],
                    [
                        'name' =>  _('Active Directory'),
                        'id' => 'host-active-directory',
                        'generator' => function () {
                            $this->adFieldsToDisplay(
                                $this->obj->get('useAD'),
                                $this->obj->get('ADDomain'),
                                $this->obj->get('ADOU'),
                                $this->obj->get('ADUser'),
                                $this->obj->get('ADPass')
                            );
                        }
                    ],
                    [
                        'name' => _('Power Management'),
                        'id' => 'host-powermanagement',
                        'generator' => function () {
                            $this->hostPowermanagement();
                        }
                    ]
                ]
            ]
        ];

        // Inventory
        $tabData[] = [
            'name' => _('Inventory'),
            'id' => 'host-inventory',
            'generator' => function () {
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
                        'generator' => function () {
                            $this->hostLoginHistory();
                        }
                    ],
                    [
                        'name' => _('Imaging History'),
                        'id' => 'host-image-history',
                        'generator' => function () {
                            $this->hostImageHistory();
                        }
                    ],
                    [
                        'name' => _('Snapin History'),
                        'id' => 'host-snapin-history',
                        'generator' => function () {
                            $this->hostSnapinHistory();
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
            case 'host-groups':
                $this->hostGroupPost();
                break;
            case 'host-printers':
                $this->hostPrinterPost();
                break;
            case 'host-snapins':
                $this->hostSnapinPost();
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'HOST_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Host updated!'),
                    'title' => _('Host Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Successfully added selected hosts to the group!'),
                    'title' => _('Host Add to Group Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
     * Presents the groups list table.
     *
     * @return void
     */
    public function getGroupsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        // Workable queries
        $groupsSqlStr = "SELECT `%s`,"
            . "IF(`gmHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `gmHostID`
            FROM `%s`
            LEFT OUTER JOIN `groupMembers`
            ON `groups`.`groupID` = `groupMembers`.`gmGroupID`
            AND `groupMembers`.`gmHostID` = '"
            . $this->obj->get('id')
            . "'
            %s
            %s
            %s";
        $groupsFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `groupMembers`
            ON `groups`.`groupID` = `groupMembers`.`gmGroupID`
            AND `groupMembers`.`gmHostID` = '"
            . $this->obj->get('id')
            . "'
            %s";
        $groupsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('GroupManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'gmHostID',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'groups',
                'groupID',
                $columns,
                $groupsSqlStr,
                $groupsFilterStr,
                $groupsTotalStr,
                $where
            )
        );
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
        $printersSqlStr = "SELECT `%s`,"
            . "IF(`paHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `paHostID`
            FROM `%s`
            LEFT OUTER JOIN `printerAssoc`
            ON `printers`.`pID` = `printerAssoc`.`paPrinterID`
            AND `printerAssoc`.`paHostID` = '"
            . $this->obj->get('id')
            . "'
            %s
            %s
            %s";
        $printersFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`paHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `paHostID`,`paIsDefault`
            FROM `%s`
            LEFT OUTER JOIN `printerAssoc`
            ON `printers`.`pID` = `printerAssoc`.`paPrinterID`
            AND `printerAssoc`.`paHostID` = '"
            . $this->obj->get('id')
            . "'
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

        // Workable queries
        $snapinsSqlStr = "SELECT `%s`,"
            . "IF(`saHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `saHostID`
            FROM `%s`
            LEFT OUTER JOIN `snapinAssoc`
            ON `snapins`.`sID` = `snapinAssoc`.`saSnapinID`
            AND `snapinAssoc`.`saHostID` = '"
            . $this->obj->get('id')
            . "'
            %s
            %s
            %s";
        $snapinsFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`saHostID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') AS `saHostID`
            FROM `%s`
            LEFT OUTER JOIN `snapinAssoc`
            ON `snapins`.`sID` = `snapinAssoc`.`saSnapinID`
            AND `snapinAssoc`.`saHostID` = '"
            . $this->obj->get('id')
            . "'
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

        $where = "`modules`.`short_name` "
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
            LEFT OUTER JOIN `moduleStatusByHost`
            ON `modules`.`id` = `moduleStatusByHost`.`msModuleID`
            AND `moduleStatusByHost`.`msHostID` = '"
            . $this->obj->get('id')
            . "'
            %s
            %s
            %s";
        $modulesFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `moduleStatusByHost`
            ON `modules`.`id` = `moduleStatusByHost`.`msModuleID`
            AND `moduleStatusByHost`.`msHostID` = '"
            . $this->obj->get('id')
            . "'
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
        header('Content-type: application/json');
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
        $this->attributes = [
            []
        ];

        $obj = self::getClass('HostManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            $this->headerData[] = $common;
            $this->attributes[] = [];
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
            . 'what is currently viewable on the screen. This includes searched '
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
    /**
     * Gets the current list of power management tasks.
     *
     * @return void
     */
    public function getPowermanagementList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $where = "`powerManagement`.`pmHostID` = '"
            . $this->obj->get('id')
            . "'";

        $sqlstr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";

        $filterstr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";

        $totalstr = "SELECT COUNT(`%s`)
            FROM `%s`
            WHERE $where";

        $dbcolumns = self::getClass('PowerManagementManager')->getColumns();

        $columns = [];

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

        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'powerManagement',
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
     * The host tasks items.
     *
     * @return void
     */
    public function hostTasks()
    {
        // Predefine needed variables for closure function.
        global $id;
        $data = [];
        /**
         * Closure allowing us to iterate from a common point.
         *
         * @param stdClass $TaskType The Task Type data.
         * @param int      $advanced The advanced flag.
         *
         * @uses array $data The data to store into.
         * @uses int   $id   The id of the object we are on.
         *
         * @return void
         */
        $taskTypeIterator = function (&$TaskType, $advanced) use (
            &$data,
            $id
        ) {
            if ($advanced != $TaskType->isAdvanced) {
                return;
            }
            $data['<a href="?node=host&sub=deploy&id='
                . $id
                . '&type='
                . $TaskType->id
                . '" class="taskitem"><i class="fa fa-'
                . $TaskType->icon
                . ' fa-2x"></i><br/>'
                . $TaskType->name
                . '</a>'
            ] = $TaskType->description;
            unset($TaskTYpe);
        };
        // The keys we need to search for.
        $key = [
            'ttIsAccess' => [
                'host',
                'both'
            ]
        ];
        // The items we're getting.
        Route::listem(
            'tasktype',
            $key
        );
        $items = json_decode(Route::getData());
        // Loop 1, the basic non-advanced tasks.
        foreach ($items->data as &$TaskType) {
            $taskTypeIterator($TaskType, 0);
            unset($TaskType);
        }
        self::$HookManager->processEvent(
            'HOST_BASICTASKS_DATA',
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
            'HOST_ADVANCEDTASKS_DATA',
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
        echo '<div class="box-footer">';
        echo $taskModal;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Tasking for this host.
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

            $image = $this->obj->getImage();

            if (!$TaskType->isValid()) {
                throw new Exception(_('Task type is invalid'));
            }
            if ($this->obj->get('pending') > 0) {
                throw new Exception(_('Cannot task pending hosts'));
            }
            if ($imagingTypes
                && !$image->isValid()
            ) {
                throw new Exception(_('Assigned image is invalid'));
            }
            if ($imagingTypes
                && $image->get('isEnabled') < 1
            ) {
                throw new Exception(_('Assigned image is not enabled'));
            }
            if ($imagingTypes
                && $iscapturetask
                && $image->get('protected')
            ) {
                throw new Exception(_('Assigned image is protected'));
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
            } else if (TaskType::PASSWORD_RESET == $type) {
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
                        '&nbsp;&nbsp;'
                        . self::makeLabel(
                            'control-label',
                            '',
                            '<div class="croninput fogcron hidden"></div>'
                            . '<br/>'
                        )
                        . '<div class="croninput hidden">'
                        . self::makeLabel(
                            $labelClass,
                            '',
                            _('Cron Entry')
                        ) => self::makeInput(
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
                'HOST_CREATE_TASKING',
                [
                    'fields' => &$fields,
                    'buttons' => &$buttons,
                    'Host' => &$this->obj
                ]
            );
            $rendered = self::formFields($fields);
            unset($fields);
            ob_start();
            echo self::makeFormTag(
                'form-horizontal',
                'host-deploy-form',
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
        self::$HookManager->processEvent('HOST_DEPLOY_POST');

        $serverFault = false;
        try {
            global $type;
            if (!is_numeric($type) && $type > 0) {
                $type = 1;
            }
            // Pending check.
            if ($this->obj->get('pending')) {
                throw new Exception(_('Pending hosts cannot be tasked'));
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

            // WOL setup
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
                break;
            }

            // Task Type Imaging Checks.
            if ($TaskType->isImagingTask()) {
                $Image = $this->obj->getImage();
                if (!$Image->isValid()) {
                    throw new Exception(_('Image is invalid'));
                }
                if (!$Image->get('isEnabled')) {
                    throw new Exception(_('Image is not enabled'));
                }
                if ($TaskType->isCapture()
                    && $Image->get('protected')
                ) {
                    throw new Exception(_('Image is protected'));
                }
            }

            // Actually create tasking
            if ($scheduleType == 'instant') {
                $this->obj->createImagePackage(
                    $TaskType->get('id'),
                    $taskName,
                    $enableShutdown,
                    $enableDebug,
                    $enableSnapins,
                    false,
                    self::$FOGUser->get('name'),
                    $passreset,
                    false,
                    $wol
                );
            } else {
                $ScheduledTask = self::getClass('ScheduledTask')
                    ->set('taskType', $TaskType->get('id'))
                    ->set('name', $taskName)
                    ->set('hostID', $this->obj->get('id'))
                    ->set('shutdown', $enableShutdown)
                    ->set('other2', $enableSnapins)
                    ->set('type', 'single' == $scheduleType ? 'S' : 'C')
                    ->set('isGroupTask', 0)
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
            $hook = 'HOST_DEPLOY_SUCCESS';
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
            $hook = 'HOST_DEPLOY_FAIL';
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
     * Get the login history for this host.
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

        $where = "`userTracking`.`utHostID` = '"
            . $this->obj->get('id')
            . "'";

        $sqlstr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";

        $filterstr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";

        $totalstr = "SELECT COUNT(`%s`)
            FROM `%s`
            WHERE $where";

        $dbcolumns = self::getClass('UserTrackingManager')->getColumns();

        $columns = [];

        foreach ($dbcolumns as $common => &$real) {
            switch ($common) {
            case 'createdTime':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        return self::niceDate($d)->format('Y-m-d H:i:s');
                    }
                ];
                break;
            case 'action':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        switch ($d) {
                        case '0':
                            return _('Logout');
                        case '1':
                            return _('Login');
                        case '99':
                            return _('Service Start');
                        }
                    }
                ];
                break;
            case 'id':
                $tableID = $real;
            default:
                $columns[] = [
                    'db' => $real,
                    'dt' => $common
                ];
                break;
            }
            unset($real);
        }

        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'userTracking',
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
     * Get the image history for this host.
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

        $where = "`imagingLog`.`ilHostID` = '"
            . $this->obj->get('id')
            . "'";

        $sqlstr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";

        $filterstr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";

        $totalstr = "SELECT COUNT(`%s`)
            FROM `%s`
            WHERE $where";

        $dbcolumns = self::getClass('ImagingLogManager')->getColumns();

        $columns = [];

        foreach ($dbcolumns as $common => &$real) {
            switch ($common) {
            case 'start':
            case 'finish':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        return self::niceDate($d)->format('Y-m-d H:i:s');
                    }
                ];
                break;
            case 'image':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        $Image = self::getClass('Image')
                            ->set('name', $d)
                            ->load('name');
                        if ($Image->isValid()) {
                            return '<a href="../management/index.php'
                                . '?node=image&sub=edit&id='
                                . $Image->get('id')
                                . '">'
                                . $d
                                . '</a>';
                        } else {
                            return $d;
                        }
                    }
                ];
                break;
            case 'id':
                $tableID = $real;
            default:
                $columns[] = [
                    'db' => $real,
                    'dt' => $common
                ];
                break;
            }
            unset($real);
        }
        $columns[] = [
            'dt' => 'duration',
            'formatter' => function ($d, $row) {
                return self::diff($row['ilStartTime'], $row['ilFinishTime']);
            }
        ];

        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'imagingLog',
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
