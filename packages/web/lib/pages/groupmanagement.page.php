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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    private function _addFields()
    {
        $group = filter_input(INPUT_POST, 'group');
        $description = filter_input(INPUT_POST, 'description');
        $kernel = filter_input(INPUT_POST, 'kernel');
        $args = filter_input(INPUT_POST, 'args');
        $init = filter_input(INPUT_POST, 'init');
        $dev = filter_input(INPUT_POST, 'dev');

        $labelClass = 'col-sm-3 control-label';

        // The fields to display
        return [
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
    }
    /**
     * Create a new group.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'group',
            _('Create New Group'),
            'GROUP_ADD_FIELDS',
            'Group'
        );
    }
    /**
     * Create a new group.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'group',
            'GROUP_ADD_FIELDS',
            'Group'
        );
    }
    /**
     * When submitted to add post this is what's run
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
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
        $this->jsonHookResponse(
            [
                'Group' => &$Group,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Reports, for each requested host column, whether every member host
     * shares the same value and what that value is.
     *
     * NULL and '' are treated as the same ("empty") for uniformity, so a
     * field that is set on some hosts and unset on others reads as mixed.
     *
     * @param array $columns map of friendly key => hosts table column
     *
     * @return array friendly key => ['uniform' => bool, 'value' => string]
     */
    private function _uniformHostValues($columns)
    {
        $result = [];
        foreach ($columns as $key => $col) {
            $result[$key] = ['uniform' => false, 'value' => ''];
        }
        $hostIDs = array_map('intval', (array)$this->obj->get('hosts'));
        if (count($hostIDs) < 1) {
            return $result;
        }
        $selects = ['COUNT(*) AS `_n`'];
        foreach ($columns as $key => $col) {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '', $key);
            $selects[] = sprintf(
                "COUNT(DISTINCT COALESCE(`%s`, '')) AS `d_%s`",
                $col,
                $safe
            );
            $selects[] = sprintf(
                "MIN(COALESCE(`%s`, '')) AS `v_%s`",
                $col,
                $safe
            );
        }
        $sql = sprintf(
            'SELECT %s FROM `hosts` WHERE `hostID` IN (%s)',
            implode(',', $selects),
            implode(',', $hostIDs)
        );
        $row = self::$DB->query($sql)->fetch();
        $n = (int)$row->get('_n');
        foreach ($columns as $key => $col) {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '', $key);
            $distinct = (int)$row->get('d_' . $safe);
            $result[$key] = [
                'uniform' => ($n > 0 && $distinct <= 1),
                'value' => (string)$row->get('v_' . $safe),
            ];
        }
        return $result;
    }
    /**
     * Renders a muted "Hosts: ..." hint describing the members' shared value
     * for a field, from a _uniformHostValues() entry.
     *
     * @param array $info ['uniform' => bool, 'value' => string]
     *
     * @return string
     */
    private function _sharedHint($info)
    {
        return '<p class="help-block" style="margin:2px 0 0;">'
            . _('Hosts:') . ' ' . $this->_sharedValueText($info)
            . '</p>';
    }
    /**
     * The shared-state text for a _uniformHostValues() entry: the value with
     * "(all)", or "(varies)" / "(empty on all)".
     *
     * @param array $info ['uniform' => bool, 'value' => string]
     *
     * @return string
     */
    private function _sharedValueText($info)
    {
        if (!$info['uniform']) {
            return _('(varies)');
        }
        if ($info['value'] === '') {
            return _('(empty on all)');
        }
        return Initiator::e($info['value']) . ' ' . _('(all)');
    }
    /**
     * Summary box of the members' current Active Directory state, shown above
     * the group AD form so the admin sees what the no-change defaults preserve.
     *
     * @return string
     */
    private function _groupADStateHint()
    {
        $ad = $this->_uniformHostValues(
            [
                'useAD' => 'hostUseAD',
                'ADDomain' => 'hostADDomain',
                'ADOU' => 'hostADOU',
                'ADUser' => 'hostADUser',
            ]
        );
        if (!$ad['useAD']['uniform']) {
            $join = _('(varies)');
        } else {
            $join = ($ad['useAD']['value'] === '1')
                ? _('enabled (all)')
                : _('disabled (all)');
        }
        return '<div class="box box-info"><div class="box-body" '
            . 'style="padding:8px 12px;">'
            . '<strong>' . _('Current member-host AD state') . '</strong><br/>'
            . _('Domain joining') . ': ' . $join . '<br/>'
            . _('Domain name') . ': '
            . $this->_sharedValueText($ad['ADDomain']) . '<br/>'
            . _('Organizational Unit') . ': '
            . $this->_sharedValueText($ad['ADOU']) . '<br/>'
            . _('Domain username') . ': '
            . $this->_sharedValueText($ad['ADUser'])
            . '</div></div>';
    }
    /**
     * Whether every member host shares the same auto-logout time, and what it
     * is. Auto-logout lives in hostAutoLogOut (a host may have no row, which
     * means "unset / use the default"), so a LEFT JOIN treats missing as ''.
     *
     * @return array ['uniform' => bool, 'value' => string]
     */
    private function _uniformAloValue()
    {
        $info = ['uniform' => false, 'value' => ''];
        $hostIDs = array_map('intval', (array)$this->obj->get('hosts'));
        if (count($hostIDs) < 1) {
            return $info;
        }
        $sql = sprintf(
            'SELECT COUNT(*) AS `n`, '
            . "COUNT(DISTINCT COALESCE(`haloTime`, '')) AS `d`, "
            . "MIN(COALESCE(`haloTime`, '')) AS `v` "
            . 'FROM `hosts` h '
            . 'LEFT JOIN `hostAutoLogOut` halo '
            . 'ON halo.`haloHostID` = h.`hostID` '
            . 'WHERE h.`hostID` IN (%s)',
            implode(',', $hostIDs)
        );
        $row = self::$DB->query($sql)->fetch();
        $n = (int)$row->get('n');
        $info['uniform'] = ($n > 0 && (int)$row->get('d') <= 1);
        $info['value'] = (string)$row->get('v');
        return $info;
    }
    /**
     * "Hosts: ..." hint for the auto-logout time (treats unset/0 as default).
     *
     * @param array $info ['uniform' => bool, 'value' => string]
     *
     * @return string
     */
    private function _sharedAloHint($info)
    {
        if (!$info['uniform']) {
            $text = _('(varies)');
        } elseif ($info['value'] === '' || $info['value'] === '0') {
            $text = _('(default on all)');
        } else {
            $text = Initiator::e($info['value']) . ' ' . _('min (all)');
        }
        return '<p class="help-block" style="margin:2px 0 0;">'
            . _('Hosts:') . ' ' . $text
            . '</p>';
    }
    /**
     * Whether every member host shares the same default printer, and which.
     * The default is the printerAssoc row with paIsDefault=1; a host with no
     * default reads as '' (none), so mixed defaults register as "varies".
     *
     * @return array ['uniform' => bool, 'value' => string]
     */
    private function _uniformDefaultPrinter()
    {
        $info = ['uniform' => false, 'value' => ''];
        $hostIDs = array_map('intval', (array)$this->obj->get('hosts'));
        if (count($hostIDs) < 1) {
            return $info;
        }
        $sql = sprintf(
            'SELECT COUNT(*) AS `n`, '
            . "COUNT(DISTINCT COALESCE(pa.`paPrinterID`, '')) AS `d`, "
            . "MIN(COALESCE(pa.`paPrinterID`, '')) AS `v` "
            . 'FROM `hosts` h '
            . 'LEFT JOIN `printerAssoc` pa '
            . 'ON pa.`paHostID` = h.`hostID` AND pa.`paIsDefault` = 1 '
            . 'WHERE h.`hostID` IN (%s)',
            implode(',', $hostIDs)
        );
        $row = self::$DB->query($sql)->fetch();
        $info['uniform'] = ((int)$row->get('n') > 0 && (int)$row->get('d') <= 1);
        $info['value'] = (string)$row->get('v');
        return $info;
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

        // Per-field "Hosts: ..." hints showing the members' shared state.
        $shared = $this->_uniformHostValues(
            [
                'key' => 'hostProductKey',
                'kernel' => 'hostKernel',
                'args' => 'hostKernelArgs',
                'init' => 'hostInit',
                'dev' => 'hostDevice',
                'biosexit' => 'hostExitBios',
                'efiexit' => 'hostExitEfi',
            ]
        );

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
            ) . $this->_sharedHint($shared['key']),
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
            ) . $this->_sharedHint($shared['kernel']),
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
            ) . $this->_sharedHint($shared['args']),
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
            ) . $this->_sharedHint($shared['init']),
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
            ) . $this->_sharedHint($shared['dev']),
            self::makeLabel(
                $labelClass,
                'bootTypeExit',
                _('Group BIOS Exit')
            ) => $exitNorm . $this->_sharedHint($shared['biosexit']),
            self::makeLabel(
                $labelClass,
                'efiBootTypeExit',
                _('Group EFI Exit')
            ) => $exitEfi . $this->_sharedHint($shared['efiexit'])
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
        echo '<div class="alert alert-info" role="alert">'
            . _('Leave a field blank to keep each host\'s current value.')
            . ' '
            . _('Type')
            . ' <code>NULL</code> '
            . _('to clear the field on every host in this group.')
            . '</div>';
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
        self::checkAuthAndCSRF();
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

        // Propagate to hosts: empty = leave per-host value alone;
        // literal "NULL" (case-insensitive) = explicitly clear the field.
        $resolve = function ($value) {
            $trimmed = trim((string)$value);
            if (strcasecmp($trimmed, 'NULL') === 0) {
                return '';
            }
            return $trimmed !== '' ? $value : null;
        };
        $candidates = [
            'kernel'       => $kernel,
            'kernelArgs'   => $args,
            'kernelDevice' => $dev,
            'init'         => $init,
            'biosexit'     => $bte,
            'efiexit'      => $ebte,
            'productKey'   => trim($productKey),
        ];
        $updateHostItems = [];
        foreach ($candidates as $field => $value) {
            $resolved = $resolve($value);
            if ($resolved !== null) {
                $updateHostItems[$field] = $resolved;
            }
        }
        self::getClass('HostManager')
            ->update(
                ['id' => $this->obj->get('hosts')],
                '',
                $updateHostItems
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
        self::checkAuthAndCSRF();
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
        self::checkAuthAndCSRF();
        // Same no-clobber convention as the General tab: empty = leave each
        // host's value alone (null), literal "NULL" = clear it, anything else
        // = push to all. useAD is tri-state via the adstate select.
        $resolve = function ($value) {
            $trimmed = trim((string)$value);
            if (strcasecmp($trimmed, 'NULL') === 0) {
                return '';
            }
            return $trimmed !== '' ? $trimmed : null;
        };
        $adstate = (string)filter_input(INPUT_POST, 'adstate');
        $domain = $resolve(filter_input(INPUT_POST, 'domainname'));
        $ou = $resolve(filter_input(INPUT_POST, 'ou'));
        $user = $resolve(filter_input(INPUT_POST, 'domainuser'));
        $passRaw = (string)filter_input(INPUT_POST, 'domainpassword');
        // The 32-asterisk placeholder means "unchanged" (skip).
        $pass = preg_match('/^\*{32}$/', trim($passRaw))
            ? null
            : $resolve($passRaw);
        $this->obj->setAD($adstate, $domain, $ou, $user, $pass);
    }
    /**
     * Group hosts display.
     *
     * @return void
     */
    public function groupHosts()
    {
        $this->renderAssocTab(
            'group-host',
            _('Group Host Associations'),
            _('Host Name'),
            'host'
        );
    }
    /**
     * Update the group hosts.
     *
     * @return void
     */
    public function groupHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Group printers display.
     *
     * @return void
     */
    public function groupPrinters()
    {
        // Printer Associations
        $this->renderAssocTab(
            'group-printer',
            _('Group Printer Assignment'),
            _('Printer Name'),
            'printer',
            'btn btn-success pull-right',
            _('This will perform the action on all hosts in this group')
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-printer',
                $this->obj->get('id')
            )
            . '" ';

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
        self::checkAuthAndCSRF();
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
        $this->renderAssocTab(
            'group-snapin',
            _('Group Snapin Assignment'),
            _('Snapin Name'),
            'snapin',
            'btn btn-success pull-right',
            _(
                'This will perform the action on all hosts in this group. '
                . 'A snapin is checked when every host in the group has it.'
            )
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-snapin',
                $this->obj->get('id')
            )
            . '" ';

        $orderButton = self::makeButton(
            'group-snapin-order-save',
            _('Save order'),
            'btn btn-primary pull-right',
            $props
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Snapin Run Order');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _(
            'Only snapins shared by every host in the group can be ordered '
            . 'here. Saving sets this order on each host (shared snapins run '
            . 'first, in this order; any host-specific snapins run after). '
            . 'Order only changes execution when "Abort snapin sequence on '
            . 'failure" is enabled for the task.'
        );
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<ol id="group-snapin-order-list" class="list-group"></ol>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $orderButton;
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
        self::checkAuthAndCSRF();
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
        if (isset($_POST['snapinorder'])) {
            $order = filter_input_array(
                INPUT_POST,
                [
                    'snapinorder' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $order = $order['snapinorder'];
            if (count($order ?: []) > 0) {
                $this->obj->setSnapinOrder($order);
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
        $this->renderAssocTab(
            'group-module',
            _('Group Module Associations'),
            _('Module Name'),
            'module',
            'btn btn-primary pull-right',
            _('Disabled items are not displayed. Legacy items are removed.')
            . '<br/>'
            . _('Action will be perform on all hosts within this group')
        );

        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'group-module',
                $this->obj->get('id')
            )
            . '" ';

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
            // Blank by default so a save leaves each host's value alone
            // (no-clobber); the global minimum is just a placeholder hint.
            $tme = filter_input(INPUT_POST, 'tme');
            $aloMin = (string)(
                self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN') ?: 0
            );
            $aloInfo = $this->_uniformAloValue();
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
                    $aloMin,
                    'number',
                    'tme',
                    $tme
                ) . $this->_sharedAloHint($aloInfo)
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

        // Hostname change reboot/domain join reboot forced. Tri-state so a
        // save with "No change" leaves each host's value alone (no-clobber);
        // a plain checkbox both clobbered and -- because it posts "on" -- saved
        // (int)"on" = 0, so it could never actually enable enforcement.
        $enf = $this->_uniformHostValues(['enforce' => 'hostEnforce']);
        if (!$enf['enforce']['uniform']) {
            $enfText = _('(varies)');
        } else {
            $enfText = ($enf['enforce']['value'] === '1')
                ? _('enabled (all)')
                : _('disabled (all)');
        }
        $enforceControl = '<select class="form-control" id="enforce" '
            . 'name="enforce">'
            . '<option value="">' . _('No change') . '</option>'
            . '<option value="1">' . _('Enable on all hosts') . '</option>'
            . '<option value="0">' . _('Disable on all hosts') . '</option>'
            . '</select>'
            . '<p class="help-block" style="margin:2px 0 0;">'
            . _('Hosts:') . ' ' . $enfText
            . '</p>';
        $fields = [
            self::makeLabel(
                $labelClass,
                'enforce',
                _('Enforce Hostname | AD Join Reboots')
            ) => $enforceControl
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
        self::checkAuthAndCSRF();
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
            // No-clobber: blank = leave each host's auto-logout alone. A number
            // pushes to all (under 5 minutes disables it, as before).
            $raw = filter_input(INPUT_POST, 'tme');
            if ($raw !== null && trim((string)$raw) !== '') {
                $tme = (int)$raw;
                if (!($tme > 4)) {
                    $tme = 0;
                }
                $this->obj->setAlo($tme);
            }
        }
        if (isset($_POST['confirmenforcesend'])) {
            // Tri-state: '' = no change (no-clobber), '1'/'0' = force on all.
            // hostEnforce is enum('0','1'); pass the STRING, not an int -- an
            // int indexes the enum (1 -> '0', 0 -> truncation error).
            $enforce = (string)filter_input(INPUT_POST, 'enforce');
            if ($enforce === '1' || $enforce === '0') {
                self::getClass('HostManager')->update(
                    ['id' => $this->obj->get('hosts')],
                    '',
                    ['enforce' => $enforce]
                );
            }
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
        self::checkAuthAndCSRF();
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
        $hostnames = Route::getIds(
            'host',
            ['id' => $this->obj->get('hosts')],
            'name',
            'AND',
            'id'
        );

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
                            echo $this->_groupADStateHint();
                            $this->adFieldsToDisplay(
                                '',
                                '',
                                '',
                                '',
                                '',
                                true,
                                false,
                                true
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
        self::checkAuthAndCSRF();
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
        $this->jsonHookResponse(
            [
                'Group' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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
        return $this->assocItemsList(
            'host',
            'groupassociation',
            'groupMembers',
            '`hosts`.`hostID`',
            '`groupMembers`.`gmHostID`',
            '`groupMembers`.`gmGroupID`',
            [
                [
                    'db' => 'groupAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Presents the printers list table.
     *
     * @return void
     */
    public function getPrintersList()
    {
        return $this->_groupAssocList(
            'printer',
            'printerassociation',
            'printers',
            'pID',
            'printerAssoc',
            'paPrinterID',
            'paHostID'
        );
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

        $printersAvail = Route::getIds('printer', false);
        if (!count($printersAvail ?: [])) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'content' => _('No printers available to assign'),
                    'disablebtn' => true
                ]
            ));
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
        // Shared-default hint: which printer (if any) every member host
        // currently has as its default.
        $def = $this->_uniformDefaultPrinter();
        if (!$def['uniform']) {
            $defText = _('(varies)');
        } elseif ($def['value'] === '' || $def['value'] === '0') {
            $defText = _('(none on all)');
        } else {
            $defText = (
                isset($printers[$def['value']])
                ? Initiator::e($printers[$def['value']])
                : ('#' . $def['value'])
            ) . ' ' . _('(all)');
        }
        $hint = '<p class="help-block" style="margin:2px 0 6px;">'
            . _('Hosts default:') . ' ' . $defText
            . '</p>';
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'content' => $hint . $printerSelector,
                'disablebtn' => false
            ]
        ));
    }
    /**
     * Presents the snapins list table.
     *
     * @return void
     */
    public function getSnapinsList()
    {
        return $this->_groupAssocList(
            'snapin',
            'snapinassociation',
            'snapins',
            'sID',
            'snapinAssoc',
            'saSnapinID',
            'saHostID'
        );
    }
    /**
     * Builds a group association list with tri-state member coverage.
     *
     * A group owns no associations of its own, so each item is reduced to how
     * its member hosts cover it: 'all', 'some' or 'none'. getItemsList can only
     * express a direct association, so we hand it a custom query that computes
     * the coverage (and the covered-host count + group host total) per item
     * while complex() still handles paging/search/ordering server-side. The
     * extra columns surface as `association`, `assocCount` and `assocTotal`.
     *
     * @param string $primary      primary node (snapin|module|printer)
     * @param string $secondary    association node (drives getItemsList naming)
     * @param string $primaryTable  primary table name
     * @param string $pkColumn      primary table primary-key column
     * @param string $assocTable    association table name
     * @param string $itemColumn    association table item-id column
     * @param string $hostColumn    association table host-id column
     * @param string $extraWhere    extra association filter (e.g. enabled only)
     * @param string $where         primary-row filter passed to getItemsList
     *
     * @return void
     */
    private function _groupAssocList(
        $primary,
        $secondary,
        $primaryTable,
        $pkColumn,
        $assocTable,
        $itemColumn,
        $hostColumn,
        $extraWhere = '',
        $where = ''
    ) {
        $hostIDs = array_map('intval', (array)$this->obj->get('hosts'));
        $hostCount = count($hostIDs);
        if ($hostCount > 0) {
            $sub = sprintf(
                '(SELECT COUNT(*) FROM `%s` WHERE `%s` = `%s`.`%s` '
                . 'AND `%s` IN (%s)%s)',
                $assocTable,
                $itemColumn,
                $primaryTable,
                $pkColumn,
                $hostColumn,
                implode(',', $hostIDs),
                $extraWhere ? ' ' . $extraWhere : ''
            );
            $assocExpr = "CASE WHEN $sub = 0 THEN 'none' "
                . "WHEN $sub = $hostCount THEN 'all' ELSE 'some' END";
            $countExpr = $sub;
        } else {
            $assocExpr = "'none'";
            $countExpr = '0';
        }
        $qStr = 'SELECT `%s`,'
            . $assocExpr . ' AS `groupAssoc`,'
            . $countExpr . ' AS `groupAssocCount`,'
            . $hostCount . ' AS `groupAssocTotal` '
            . 'FROM `%s` %s %s %s';
        $addColumns = [
            ['do' => 'groupAssocCount', 'dt' => 'assocCount'],
            ['do' => 'groupAssocTotal', 'dt' => 'assocTotal'],
        ];
        return $this->obj->getItemsList(
            $primary,
            $secondary,
            [],
            $where,
            $addColumns,
            $qStr
        );
    }
    /**
     * Returns the snapins shared by every host in the group, in run order.
     *
     * @return void
     */
    public function getSnapinOrderList()
    {
        $hostIDs = (array)$this->obj->get('hosts');
        $hostCount = count($hostIDs);
        $data = [];
        if ($hostCount > 0) {
            Route::listem(
                'snapinassociation',
                ['hostID' => $hostIDs],
                false,
                'AND',
                'sequence'
            );
            $assocs = json_decode(Route::getData());
            $assocs = isset($assocs->data) ? $assocs->data : [];
            $counts = [];
            $minSeq = [];
            foreach ($assocs as $assoc) {
                $sid = (int)$assoc->snapinID;
                $seq = (int)$assoc->sequence;
                $counts[$sid] = ($counts[$sid] ?? 0) + 1;
                if (!isset($minSeq[$sid]) || $seq < $minSeq[$sid]) {
                    $minSeq[$sid] = $seq;
                }
            }
            // Intersection: snapins present on every host.
            $shared = [];
            foreach ($counts as $sid => $count) {
                if ($count === $hostCount) {
                    $shared[] = $sid;
                }
            }
            // Present them in a sensible starting order (lowest sequence).
            usort(
                $shared,
                function ($a, $b) use ($minSeq) {
                    return [$minSeq[$a], $a] <=> [$minSeq[$b], $b];
                }
            );
            if (count($shared) > 0) {
                Route::listem('snapin', ['id' => $shared]);
                $Snapins = json_decode(Route::getData());
                $Snapins = isset($Snapins->data) ? $Snapins->data : [];
                $names = [];
                foreach ($Snapins as $Snapin) {
                    $names[(int)$Snapin->id] = $Snapin->name;
                }
                foreach ($shared as $sid) {
                    $data[] = [
                        'id' => $sid,
                        'name' => $names[$sid] ?? ('#' . $sid)
                    ];
                }
            }
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['data' => $data]));
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
        $where = "`modules`.`short_name` IN ('"
            . implode("','", $keys)
            . "')";
        // A module counts as "had" by a host only when enabled (msState=1);
        // a disabled override (msState=0) keeps the item out of "all".
        return $this->_groupAssocList(
            'module',
            'moduleassociation',
            'modules',
            'id',
            'moduleStatusByHost',
            'msModuleID',
            'msHostID',
            'AND `msState` = 1',
            $where
        );
    }
    /**
     * On-demand drill-down for an item in the "some" state: which member
     * hosts have it (Has set) and which do not (Missing set).
     *
     * GET params: assoctype (snapin|module|printer), itemid.
     *
     * @return void
     */
    public function getAssocHostsList()
    {
        header('Content-type: application/json');
        $map = [
            'snapin' => [
                'class' => 'snapinassociation',
                'itemKey' => 'snapinID',
                'where' => []
            ],
            'printer' => [
                'class' => 'printerassociation',
                'itemKey' => 'printerID',
                'where' => []
            ],
            'module' => [
                'class' => 'moduleassociation',
                'itemKey' => 'moduleID',
                'where' => ['state' => 1]
            ],
        ];
        $type = (string)filter_input(INPUT_GET, 'assoctype');
        $itemID = (int)filter_input(INPUT_GET, 'itemid');
        if (!isset($map[$type]) || $itemID < 1) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['has' => [], 'missing' => []]));
        }
        $hostIDs = array_map('intval', (array)$this->obj->get('hosts'));
        $names = [];
        $order = [];
        if (count($hostIDs) > 0) {
            Route::listem('host', ['id' => $hostIDs]);
            $hosts = json_decode(Route::getData());
            $hosts = isset($hosts->data) ? $hosts->data : [];
            foreach ($hosts as $host) {
                $names[(int)$host->id] = $host->name;
                $order[] = (int)$host->id;
            }
        }
        $has = [];
        $missing = [];
        if (count($order) > 0) {
            $find = array_merge(
                $map[$type]['where'],
                [
                    $map[$type]['itemKey'] => $itemID,
                    'hostID' => $hostIDs
                ]
            );
            $hasSet = array_flip(
                array_map('intval', (array)Route::getIds($map[$type]['class'], $find, 'hostID'))
            );
            foreach ($order as $hostID) {
                $entry = [
                    'id' => $hostID,
                    'name' => $names[$hostID] ?? ('#' . $hostID)
                ];
                if (isset($hasSet[$hostID])) {
                    $has[] = $entry;
                } else {
                    $missing[] = $entry;
                }
            }
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(['has' => $has, 'missing' => $missing]));
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
            if ($TaskType->isSnapinTask()) {
                $fields = self::fastmerge(
                    $fields,
                    [
                        self::makeLabel(
                            $labelClass,
                            'snapinAbortOnFailure',
                            _('Abort snapin sequence on failure')
                        ) => self::makeInput(
                            '',
                            'snapinAbortOnFailure',
                            '',
                            'checkbox',
                            'snapinAbortOnFailure'
                        )
                    ]
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
                $this->scheduleTypeFields($labelClass, $isdebug, $type)
            );

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
                'application/x-www-form-urlencoded',
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
        $this->jsonSend($code, $msg);
    }
    /**
     * Actually creates the tasking.
     *
     * @return void
     */
    public function deployPost()
    {
        self::checkAuthAndCSRF();
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
            $hosts = Route::getIds(
                'host',
                $find
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
            $snapinAbortOnFailure = isset($_POST['snapinAbortOnFailure']);

            // Generic setup
            $imagingTasks = $TaskType->isImagingTask();
            $taskName = sprintf(
                '%s Task',
                $TaskType->get('name')
            );

            // Shutdown setup
            $enableShutdown = isset($_POST['shutdown']);

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
            if (TaskType::WAKE_UP == $type || $wolon) {
                $wol = true;
            }

            // Schedule Type setup + Delayed/Cron checks.
            [
                'scheduleType' => $scheduleType,
                'scheduleDeployTime' => $scheduleDeployTime,
                'min' => $min,
                'hour' => $hour,
                'dom' => $dom,
                'month' => $month,
                'dow' => $dow
            ] = $this->validateScheduleType();

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
                    $wol,
                    false,
                    $snapinAbortOnFailure
                );
            } else {
                $ScheduledTask = self::getClass('ScheduledTask')
                    ->set('taskTypeID', $type)
                    ->set('name', $taskName)
                    ->set('hostID', $this->obj->get('id'))
                    ->set('shutdown', $enableShutdown)
                    ->set('other1', (int)$snapinAbortOnFailure)
                    ->set('other2', $enableSnapins)
                    ->set('type', $scheduleType == 'single' ? 'S' : 'C')
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

        $this->jsonHookResponse(
            [
                'Group' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
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

        $snapinJobs = Route::getIds(
            'snapinjob',
            ['hostID' => $hostID]
        );
        $snapinJobs = array_filter(
            array_map('intval', (array)$snapinJobs),
            function ($id) {
                return $id > 0;
            }
        );

        // If there are no jobs for this group's hosts, return an empty
        // datatable payload and avoid an unscoped snapintask lookup.
        if (count($snapinJobs) < 1) {
            $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
                [
                    'draw' => (int)filter_input(INPUT_POST, 'draw') ?: 0,
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    '_lang' => 'snapintask'
                ]
            ));
        }

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
