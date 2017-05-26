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
        global $id;
        if ($id) {
            $linkstr = "$this->linkformat#host-%s";
            $this->subMenu = array(
                sprintf(
                    $linkstr,
                    'general'
                ) => self::$foglang['General'],
            );
            if (!$this->obj->get('pending')) {
                $this->subMenu = self::fastmerge(
                    $this->subMenu,
                    array(
                        sprintf(
                            $linkstr,
                            'tasks'
                        ) => self::$foglang['BasicTasks'],
                    )
                );
            }
            $this->subMenu = self::fastmerge(
                $this->subMenu,
                array(
                    sprintf(
                        $linkstr,
                        'active-directory'
                    ) => self::$foglang['AD'],
                    sprintf(
                        $linkstr,
                        'printers'
                    ) => self::$foglang['Printers'],
                    sprintf(
                        $linkstr,
                        'snapins'
                    ) => self::$foglang['Snapins'],
                    sprintf(
                        $linkstr,
                        'service'
                    ) => sprintf(
                        '%s %s',
                        self::$foglang['Service'],
                        self::$foglang['Settings']
                    ),
                    sprintf(
                        $linkstr,
                        'powermanagement'
                    ) => self::$foglang['PowerManagement'],
                    sprintf(
                        $linkstr,
                        'hardware-inventory'
                    ) => self::$foglang['Inventory'],
                    sprintf(
                        $linkstr,
                        'virus-history'
                    ) => self::$foglang['VirusHistory'],
                    sprintf(
                        $linkstr,
                        'login-history'
                    ) => self::$foglang['LoginHistory'],
                    sprintf(
                        $linkstr,
                        'image-history'
                    ) => self::$foglang['ImageHistory'],
                    sprintf(
                        $linkstr,
                        'snapin-history'
                    ) => self::$foglang['SnapinHistory'],
                    $this->membership => self::$foglang['Membership'],
                    $this->delformat => self::$foglang['Delete'],
                )
            );
            $this->notes = array(
                self::$foglang['Host'] => $this->obj->get('name'),
                self::$foglang['MAC'] => $this->obj->get('mac'),
                self::$foglang['Image'] => $this->obj->getImageName(),
                self::$foglang['LastDeployed'] => $this->obj->get('deployed'),
            );
            $primaryGroup = @min($this->obj->get('groups'));
            $Group = new Group($primaryGroup);
            if ($Group->isValid()) {
                $this->notes[self::$foglang['PrimaryGroup']] = $Group->get('name');
                unset($Group);
            }
        }
        if (!($this->obj instanceof Host && $this->obj->isValid())) {
            $this->exitNorm = $_REQUEST['bootTypeExit'];
            $this->exitEfi = $_REQUEST['efiBootTypeExit'];
        } else {
            $this->exitNorm = $this->obj->get('biosexit');
            $this->exitEfi = $this->obj->get('efiexit');
        }
        $this->exitNorm = Service::buildExitSelector(
            'bootTypeExit',
            $this->exitNorm,
            true
        );
        $this->exitEfi = Service::buildExitSelector(
            'efiBootTypeExit',
            $this->exitEfi,
            true
        );
        self::$HookManager->processEvent(
            'SUB_MENULINK_DATA',
            array(
                'menu' => &$this->menu,
                'submenu' => &$this->subMenu,
                'notes' => &$this->notes,
                'biosexit' => &$this->exitNorm,
                'efiexit' => &$this->exitEfi,
                'object' => &$this->obj,
                'linkformat' => &$this->linkformat,
                'delformat' => &$this->delformat,
                'membership' => &$this->membership
            )
        );
        $this->headerData = array(
            '',
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
        );
        self::$fogpingactive ? array_push($this->headerData, '') : null;
        array_push(
            $this->headerData,
            _('Host'),
            _('Imaged'),
            _('Task'),
            _('Assigned Image')
        );
        $this->templates = array(
            '<span class="icon fa fa-question hand" '
            . 'title="${host_desc}"></span>',
            '<input type="checkbox" name="host[]" '
            . 'value="${id}" class="toggle-action" id="host-${id}"/>'
            . '<label for="host-${id}"></label>',
        );
        if (self::$fogpingactive) {
            array_push(
                $this->templates,
                '${pingstatus}'
            );
        }
        $up = new TaskType(2);
        $down = new TaskType(1);
        $mc = new TaskType(8);
        array_push(
            $this->templates,
            '<a href="?node=host&sub=edit&id=${id}" title="Edit: '
            . '${host_name}" id="host-${host_name}">${host_name}</a>'
            . '<br /><small>${host_mac}</small>',
            '<small>${deployed}</small>',
            sprintf(
                '<a href="?node=host&sub=deploy&sub=deploy&type=1&id=${id}">'
                . '<i class="icon fa fa-%s" title="%s"></i></a> '
                . '<a href="?node=host&sub=deploy&sub=deploy&type=2&id=${id}">'
                . '<i class="icon fa fa-%s" title="%s"></i></a> '
                . '<a href="?node=host&sub=deploy&type=8&id=${id}">'
                . '<i class="icon fa fa-%s" title="%s"></i></a> '
                . '<a href="?node=host&sub=edit&id=${id}#host-tasks">'
                . '<i class="icon fa fa-arrows-alt" title="%s"></i></a>',
                $down->get('icon'),
                $down->get('name'),
                $up->get('icon'),
                $up->get('name'),
                $mc->get('icon'),
                $mc->get('name'),
                _('Goto task list')
            ),
            '<small><a href="?node=image&sub=edit&id=${image_id}">'
            . '${image_name}</a></small>'
        );
        unset($up, $down, $mc);
        $this->attributes = array(
            array(
                'width' => 16,
                'id' => 'host-${host_name}',
                'class' => 'l filter-false'
            ),
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
        );
        if (self::$fogpingactive) {
            array_push(
                $this->attributes,
                array(
                    'width' => 16,
                    'class' => 'l filter-false'
                )
            );
        }
        array_push(
            $this->attributes,
            array('width' => 50),
            array('width' => 145),
            array(
                'width' => 60,
                'class' => 'r filter-false'
            ),
            array(
                'width' => 20,
                'class' => 'r'
            )
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Host the object to use.
         *
         * @return void
         */
        /**
         * Use when api is established.
        self::$returnData = function (&$Host) {
            $this->data[] = array(
                'id' => $Host->id,
                'deployed' => self::formatTime(
                    $Host->deployed,
                    'Y-m-d H:i:s'
                ),
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'host_desc' => $Host->description,
                'image_id' => $Host->imageID,
                'image_name' => $Host->imagename,
                'pingstatus' => $Host->pingstatus,
            );
            unset($Host);
        };
         */
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Host the object to use.
         *
         * @return void
         */
        self::$returnData = function (&$Host) {
            $this->data[] = array(
                'id' => $Host->get('id'),
                'deployed' => self::formatTime(
                    $Host->get('deployed'),
                    'Y-m-d H:i:s'
                ),
                'host_name' => $Host->get('name'),
                'host_mac' => $Host->get('mac')->__toString(),
                'host_desc' => $Host->get('description'),
                'image_id' => $Host->get('imageID'),
                'image_name' => $Host->getImageName(),
                'pingstatus' => $Host->getPingCodeStr(),
            );
            unset($Host);
        };
    }
    /**
     * Lists the pending hosts
     *
     * @return false
     */
    public function pending()
    {
        $this->title = _('Pending Host List');
        $this->data = array();
        $Hosts = self::getClass('HostManager')->find(
            array(
                'pending' => 1
            )
        );
        array_map(self::$returnData, $Hosts);
        self::$HookManager->processEvent(
            'HOST_DATA',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        self::$HookManager->processEvent(
            'HOST_HEADER_DATA',
            array(
                'headerData' => &$this->headerData
            )
        );
        if (count($this->data) > 0) {
            printf(
                '<form method="post" action="%s">',
                $this->formAction
            );
        }
        $this->render();
        if (count($this->data) > 0) {
            echo '<p class="c"><input name="approvependhost" type="submit" ';
            printf(
                'value="%s"/>&nbsp;&nbsp;'
                . '<input name="delpendhost" type="submit" value="%s"/>'
                . '</p></form>',
                _('Approve selected hosts'),
                _('Delete selected hosts')
            );
        }
    }
    /**
     * Pending host form submitting
     *
     * @return void
     */
    public function pendingPost()
    {
        if (isset($_REQUEST['approvependhost'])) {
            self::getClass('HostManager')->update(
                array(
                    'id' => $_REQUEST['host']
                ),
                '',
                array('pending' => 0)
            );
        }
        if (isset($_REQUEST['delpendhost'])) {
            self::getClass('HostManager')->destroy(
                array(
                    'id' => $_REQUEST['host']
                )
            );
        }
        if (isset($_REQUEST['approvependhost'])) {
            $appdel = _('approved');
        } else {
            $appdel = _('deleted');
        }
        $msg = sprintf(
            '%s %s %s',
            _('All hosts'),
            $appdel,
            _('successfully')
        );
        self::redirect("?node=$this->node");
    }
    /**
     * Creates a new host entry manually.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Host');
        unset($this->data);
        echo '<!-- General -->';
        echo '<div id="host-general">';
        $this->headerData = '';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Host Name') => sprintf(
                '<input type="text" name="host" '
                . 'value="%s" maxlength="15" '
                . 'class="hostname-input"/>*',
                $_REQUEST['host']
            ),
            _('Primary MAC') => sprintf(
                '<input type="text" name="mac" class="macaddr" '
                . 'id="mac" value="%s" maxlength="17"/>*'
                . '<span id="priMaker"></span>'
                . '<span class="mac-manufactor"></span>'
                . '<i class="icon add-mac fa fa-plus-circle hand" '
                . 'title="%s"></i>',
                $_REQUEST['mac'],
                _('Add MAC')
            ),
            _('Host Description') => sprintf(
                '<textarea name="description" '
                . 'rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            _('Host Product Key') => sprintf(
                '<input id="productKey" type="text" '
                . 'name="key" value="%s"/>',
                $_REQUEST['key']
            ),
            _('Host Image') => self::getClass('ImageManager')->buildSelectBox(
                $_REQUEST['image'],
                '',
                'id'
            ),
            _('Host Kernel') => sprintf(
                '<input type="text" name="kern" '
                . 'value="%s"/>',
                $_REQUEST['kern']
            ),
            _('Host Kernel Arguments') => sprintf(
                '<input type="text" name="args" value="%s"/>',
                $_REQUEST['args']
            ),
            _('Host Init') => sprintf(
                '<input type="text" name="init" value="%s"/>',
                $_REQUEST['init']
            ),
            _('Host Primary Disk') => sprintf(
                '<input type="text" name="dev" value="%s"/>',
                $_REQUEST['dev']
            ),
            _('Host Bios Exit Type') => $this->exitNorm,
            _('Host EFI Exit Type') => $this->exitEfi,
        );
        printf(
            '<h2>%s</h2><form method="post" action="%s">',
            _('Add new host definition'),
            $this->formAction
        );
        self::$HookManager
            ->processEvent(
                'HOST_FIELDS',
                array(
                    'fields' => &$fields,
                    'Host' => self::getClass('Host')
                )
            );
        foreach ($fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($field, $input);
        }
        self::$HookManager
            ->processEvent(
                'HOST_ADD_GEN',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'fields' => &$fields
                )
            );
        $this->render();
        echo '</div>';
        if (!isset($_REQUEST['enforcesel'])) {
            $_REQUEST['enforcesel'] = self::getSetting('FOG_ENFORCE_HOST_CHANGES');
        }
        echo $this->adFieldsToDisplay(
            Initiator::sanitizeItems(
                $_REQUEST['domain']
            ),
            Initiator::sanitizeItems(
                $_REQUEST['domainname']
            ),
            Initiator::sanitizeItems(
                $_REQUEST['ou']
            ),
            Initiator::sanitizeItems(
                $_REQUEST['domainuser']
            ),
            Initiator::sanitizeItems(
                $_REQUEST['domainpassword']
            ),
            Initiator::sanitizeItems(
                $_REQUEST['domainpasswordlegacy']
            ),
            isset($_REQUEST['enforcesel'])
        );
        echo '</form>';
    }
    /**
     * Handles the forum submission process.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager
            ->processEvent('HOST_ADD_POST');
        try {
            $hostName = trim($_REQUEST['host']);
            if (empty($hostName)) {
                throw new Exception(_('Please enter a hostname'));
            }
            if (!self::getClass('Host')->isHostnameSafe($hostName)) {
                throw new Exception(_('Please enter a valid hostname'));
            }
            if (self::getClass('HostManager')->exists($hostName)) {
                throw new Exception(_('Hostname Exists already'));
            }
            if (empty($_REQUEST['mac'])) {
                throw new Exception(_('MAC Address is required'));
            }
            $MAC = self::getClass('MACAddress', $_REQUEST['mac']);
            if (!$MAC->isValid()) {
                throw new Exception(_('MAC Format is invalid'));
            }
            $Host = self::getClass('HostManager')->getHostByMacAddresses($MAC);
            if ($Host && $Host->isValid()) {
                throw new Exception(
                    sprintf(
                        '%s: %s',
                        _('A host with this mac already exists with name'),
                        $Host->get('name')
                    )
                );
            }
            $ModuleIDs = self::getSubObjectIDs('Module', array('isDefault' => 1));
            $password = $_REQUEST['domainpassword'];
            if ($_REQUEST['domainpassword']) {
                $password = self::encryptpw($_REQUEST['domainpassword']);
            }
            $useAD = isset($_REQUEST['domain']);
            $domain = trim($_REQUEST['domainname']);
            $ou = trim($_REQUEST['ou']);
            $user = trim($_REQUEST['domainuser']);
            $pass = $password;
            $passlegacy = trim($_REQUEST['domainpasswordlegacy']);
            $productKey = preg_replace(
                '/([\w+]{5})/',
                '$1-',
                str_replace(
                    '-',
                    '',
                    strtoupper(
                        trim($_REQUEST['key'])
                    )
                )
            );
            $productKey = substr($productKey, 0, 29);
            $enforce = isset($_REQUEST['enforcesel']);
            $Host = self::getClass('Host')
                ->set('name', $hostName)
                ->set('description', $_REQUEST['description'])
                ->set('imageID', $_REQUEST['image'])
                ->set('kernel', $_REQUEST['kern'])
                ->set('kernelArgs', $_REQUEST['args'])
                ->set('kernelDevice', $_REQUEST['dev'])
                ->set('init', $_REQUEST['init'])
                ->set('biosexit', $_REQUEST['bootTypeExit'])
                ->set('efiexit', $_REQUEST['efiBootTypeExit'])
                ->set('productKey', self::encryptpw($productKey))
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
                    $passlegacy,
                    $productKey,
                    $enforce
                );
            if (!$Host->save()) {
                throw new Exception(_('Host create failed'));
            }
            $hook = 'HOST_ADD_SUCCESS';
            $msg = _('Host added');
        } catch (Exception $e) {
            $hook = 'HOST_ADD_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Host' => &$Host)
            );
        unset(
            $Host,
            $passlegacy,
            $pass,
            $user,
            $ou,
            $domain,
            $useAD,
            $password,
            $ModuleIDs,
            $MAC,
            $hostName
        );
        self::setMessage($msg);
        self::redirect($this->formAction);
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
        if ($_REQUEST['approveHost']) {
            $this->obj->set('pending', null);
            if ($this->obj->save()) {
                self::setMessage(_('Host approved'));
            } else {
                self::setMessage(_('Host approval failed.'));
            }
            self::redirect(
                sprintf(
                    '?node=%s&sub=edit&id=%s',
                    $this->node,
                    $_REQUEST['id']
                )
            );
        }
        if ($this->obj->get('pending')) {
            printf(
                '<h2><a href="%s&approveHost=1">%s</a></h2>',
                $this->formAction,
                _('Approve this host?')
            );
        }
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if ($_REQUEST['confirmMAC']) {
            try {
                $this->obj->addPendtoAdd($_REQUEST['confirmMAC']);
                if ($this->obj->save()) {
                    $msg = sprintf(
                        '%s: %s %s!',
                        _('MAC'),
                        $_REQUEST['confirmMAC'],
                        _('Approved')
                    );
                    self::setMessage($msg);
                    unset($msg);
                }
            } catch (Exception $e) {
                self::setMessage($e->getMessage());
            }
            self::redirect(
                sprintf(
                    '?node=%s&sub=edit&id=%s',
                    $this->node,
                    $_REQUEST['id']
                )
            );
        } elseif ($_REQUEST['approveAll']) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    array(
                        'hostID' => $this->obj->get('id')
                    ),
                    '',
                    array(
                        'pending' => 0
                    )
                );
            $msg = sprintf(
                '%s.',
                _('All Pending MACs approved')
            );
            self::setMessage($msg);
            self::redirect(
                sprintf(
                    '?node=%s&sub=edit&id=%s',
                    $this->node,
                    $_REQUEST['id']
                )
            );
        }
        ob_start();
        foreach ((array)$this->obj->get('additionalMACs') as $ind => &$MAC) {
            if (!$MAC->isValid()) {
                continue;
            }
            printf(
                '<div><input class="additionalMAC" '
                . 'type="text" name="additionalMACs[]" '
                . 'value="%s"/>&nbsp;&nbsp;'
                . '<i class="icon fa fa-minus-circle '
                . 'remove-mac hand" title="%s"></i>'
                . '<span class="icon icon-hand" title="%s">'
                . '<input type="checkbox" name="igclient[]" '
                . 'value="%s" id="igclient'
                . ($ind + 1)
                . '" %s/><label for="igclient'
                . ($ind + 1)
                . '"></label></span>'
                . '<span class="icon icon-hand" title="%s">'
                . '<input type="checkbox" name="igimage[]" '
                . 'value="%s" id="igimage'
                . ($ind + 1)
                . '" %s/><label for="igimage'
                . ($ind + 1)
                . '"></label></span>'
                . '<br/><span class="mac-manufactor"></span></div>',
                $MAC,
                _('Remove MAC'),
                _('Ignore MAC on Client'),
                $MAC,
                $this->obj->clientMacCheck($MAC),
                _('Ignore MAC for imaging'),
                $MAC,
                $this->obj->imageMacCheck($MAC),
                $MAC
            );
            unset($MAC);
        }
        $addMACs = ob_get_clean();
        ob_start();
        foreach ((array)$this->obj->get('pendingMACs') as &$MAC) {
            if (!$MAC->isValid()) {
                continue;
            }
            printf(
                '<div><input class="pending-mac" type="text" '
                . 'name="pendingMACs[]" value="%s"/>'
                . '<a href="%s&confirmMAC=%s">'
                . '<i class="icon fa fa-check-circle"></i>'
                . '</a><span class="mac-manufactor"></span></div>',
                $MAC,
                $this->formAction,
                $MAC
            );
            unset($MAC);
        }
        if (ob_get_contents()) {
            printf(
                '<div>%s<a href="%s&approveAll=1">'
                . '<i class="icon fa fa-check-circle"></i></a></div>',
                _('Approve All MACs?'),
                $this->formAction
            );
        }
        $pending = ob_get_clean();
        $imageSelect = self::getClass('ImageManager')
            ->buildSelectBox(
                $this->obj->get('imageID')
            );
        $fields = array(
            _('Host Name') => sprintf(
                '<input type="text" name="host" value="%s"'
                . 'maxlength="15" class="hostname-input" />*',
                $this->obj->get('name')
            ),
            _('Primary MAC') => sprintf(
                '<input type="text" name="mac" class="macaddr" '
                . 'id="mac" value="%s" maxlength="17"/>*'
                . '<span id="priMaker"></span>'
                . '<i class="icon add-mac fa fa-plus-circle hand" '
                . 'title="%s"></i><span class="icon icon-hand" '
                . 'title="%s"><input type="checkbox" name="igclient[]" '
                . 'value="%s" id="igclient" %s/>'
                . '<label for="igclient"></label>'
                . '</span><span class="icon icon-hand" '
                . 'title="%s"><input type="checkbox" name="igimage[]" '
                . 'value="%s" id="igimage" %s/>'
                . '<label for="igimage"></label>'
                . '</span><br/>'
                . '<span class="mac-manufactor"></span>',
                $this->obj->get('mac')->__toString(),
                _('Add MAC'),
                _('Ignore MAC on Client'),
                $this->obj->get('mac')->__toString(),
                $this->obj->clientMacCheck(),
                _('Ignore MAC for Imaging'),
                $this->obj->get('mac')->__toString(),
                $this->obj->imageMacCheck()
            ),
            sprintf(
                '<div id="additionalMACsRow">%s</div>',
                _('Additional MACs')
            ) => sprintf(
                '<div id="additionalMACsCell">%s</div>',
                $addMACs
            ),
            (
                $this->obj->get('pendingMACs') ?
                _('Pending MACs') :
                null
            ) => (
                $this->obj->get('pendingMACs') ?
                $pending :
                null
            ),
            _('Host Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            _('Host Product Key') => sprintf(
                '<input id="productKey" type="text" name="key" value="%s"/>',
                self::aesdecrypt($this->obj->get('productKey'))
            ),
            _('Host Image') => $imageSelect,
            _('Host Kernel') => sprintf(
                '<input type="text" name="kern" value="%s"/>',
                $this->obj->get('kernel')
            ),
            _('Host Kernel Arguments') => sprintf(
                '<input type="text" name="args" value="%s"/>',
                $this->obj->get('kernelArgs')
            ),
            _('Host Init') => sprintf(
                '<input type="text" name="init" value="%s"/>',
                $this->obj->get('init')
            ),
            _('Host Primary Disk') => sprintf(
                '<input type="text" name="dev" value="%s"/>',
                $this->obj->get('kernelDevice')
            ),
            _('Host Bios Exit Type') => $this->exitNorm,
            _('Host EFI Exit Type') => $this->exitEfi,
            '&nbsp;' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager
            ->processEvent(
                'HOST_FIELDS',
                array(
                    'fields' => &$fields,
                    'Host' => &$this->obj
                )
            );
        echo '<div id="tab-container"><!-- General --><div id="host-general">';
        if ($this->obj->get('pub_key')
            || $this->obj->get('sec_tok')
        ) {
            $this->form = '<div class="c" id="resetSecDataBox">'
                . '<input type="button" id="resetSecData"/></div><br/>';
        }
        array_walk($fields, $this->fieldsToData);
        unset($input);
        self::$HookManager
            ->processEvent(
                'HOST_EDIT_GEN',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'Host'=>&$this->obj
                )
            );
        printf(
            '<form method="post" action="%s&tab=host-general"><h2>%s</h2>',
            $this->formAction, _('Edit host definition')
        );
        $this->render();
        echo '</form></div>';
        unset($this->data, $this->form);
        unset($this->data, $this->headerData, $this->attributes);
        if (!$this->obj->get('pending')) {
            $this->basictasksOptions();
        }
        $this->adFieldsToDisplay(
            $this->obj->get('useAD'),
            $this->obj->get('ADDomain'),
            $this->obj->get('ADOU'),
            $this->obj->get('ADUser'),
            $this->obj->get('ADPass'),
            $this->obj->get('ADPassLegacy'),
            $this->obj->get('enforce')
        );
        printf(
            '<!-- Printers --><div id="host-printers">'
            . '<form method="post" action="%s&tab=host-printers">',
            $this->formAction
        );
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxprint" '
            . 'class="toggle-checkboxprint" id="toggler1"/>'
            . '<label for="toggler1"></label>',
            _('Printer Name'),
            _('Configuration'),
        );
        $this->templates = array(
            '<input type="checkbox" name="printer[]" '
            . 'value="${printer_id}" class="toggle-print"${is_default} id="'
            . 'printer-${printer_id}"/>'
            . '<label for="printer-${printer_id}"></label>',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>50,'class'=>'l'),
            array('width'=>50,'class'=>'r'),
        );
        $Printers = self::getClass('PrinterManager')
            ->find(
                array(
                    'id' => $this->obj->get('printersnotinme')
                )
            );
        foreach ((array)$Printers as &$Printer) {
            if (!$Printer->isValid()) {
                continue;
            }
            $this->data[] = array(
                'printer_id' => $Printer->get('id'),
                'is_default' => (
                    $this->obj->getDefault($Printer->get('id')) ?
                    ' checked' :
                    ''
                ),
                'printer_name' => $Printer->get('name'),
                'printer_type' => (
                    stripos($Printer->get('config'), 'local') !== false ?
                    _('TCP/IP') :
                    $Printer->get('config')
                ),
            );
            unset($Printer);
        }
        $PrintersFound = false;
        if (count($this->data) > 0) {
            $PrintersFound = true;
            self::$HookManager
                ->processEvent(
                    'HOST_ADD_PRINTER',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            printf(
                '<p class="c">'
                . '%s&nbsp;&nbsp;<input type="checkbox" '
                . 'name="hostPrinterShow" id="hostPrinterShow"/>'
                . '<label for="hostPrinterShow"></label>'
                . '</p><div id="printerNotInHost">'
                . '<h2>%s</h2>',
                _('Check here to see what printers can be added'),
                _('Add new printer(s) to this host')
            );
            $this->render();
            echo '</div>';
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler2"/>'
            . '<label for="toggler2"></label>',
            _('Default'),
            _('Printer Alias'),
            _('Printer Type'),
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array('class'=>'l filter-false','width'=>22),
            array(),
            array(),
        );
        $this->templates = array(
            '<input type="checkbox" name="printerRemove[]" '
            . 'value="${printer_id}" class="toggle-action" id="'
            . 'printerrm-${printer_id}"/>'
            . '<label for="printerrm-${printer_id}"></label>',
            sprintf(
                '<input class="default" type="radio" '
                . 'name="default" id="printer${printer_id}" '
                . 'value="${printer_id}" ${is_default}/>'
                . '<label for="printer${printer_id}" '
                . 'class="icon icon-hand" title="%s">'
                . '&nbsp;</label><input type="hidden" '
                . 'name="printerid[]" value="${printer_id}"/>',
                _('Default Printer Select')
            ),
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}',
        );
        $Printers = self::getClass('PrinterManager')
            ->find(
                array(
                    'id' => $this->obj->get('printers')
                )
            );
        foreach ((array)$Printers as &$Printer) {
            if (!$Printer->isValid()) {
                continue;
            }
            $this->data[] = array(
                'printer_id' => $Printer->get('id'),
                'is_default' => (
                    $this->obj->getDefault($Printer->get('id')) ?
                    ' checked' :
                    ''
                ),
                'printer_name' => $Printer->get('name'),
                'printer_type' => (
                    stripos($Printer->get('config'), 'local') !== false ?
                    _('TCP/IP') :
                    $Printer->get('config')
                ),
            );
            unset($Printer);
        }
        self::$HookManager
            ->processEvent(
                'HOST_EDIT_PRINTER',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<h2>%s</h2><p>%s</p><p>'
            . '<span class="icon fa fa-question hand" '
            . 'title="%s"></span><input type="radio" '
            . 'name="level" value="0"%s/>%s<br/>'
            . '<span class="icon fa fa-question hand" '
            . 'title="%s"></span><input type="radio" '
            . 'name="level" value="1"%s/>%s<br/>'
            . '<span class="icon fa fa-question hand" '
            . 'title="%s"></span><input type="radio" '
            . 'name="level" value="2"%s/>%s<br/></p>',
            _('Host Printer Configuration'),
            _('Select Management Level for this Host'),
            sprintf(
                '%s. %s %s, %s.',
                _('This setting turns off all FOG Printer Management'),
                _('Although there are multiple levels already'),
                _('between host and global settings'),
                _('this is just another to ensure safety')
            ),
            (
                $this->obj->get('printerLevel') == 0 ?
                ' checked' :
                ''
            ),
            _('No Printer Management'),
            _(
                'This setting only adds and removes '
                . 'printers that are managed by FOG. '
                . 'If the printer exists in printer '
                . 'management but is not assigned to a '
                . 'host, it will remove the printer if '
                . 'it exists on the unsigned host. '
                . 'It will add printers to the host '
                . 'that are assigned.'
            ),
            (
                $this->obj->get('printerLevel') == 1 ?
                ' checked' :
                ''
            ),
            _('FOG Managed Printers'),
            _(
                'This setting will only allow FOG Assigned '
                . 'printers to be added to the host. Any '
                . 'printer that is not assigned will be '
                . 'removed including non-FOG managed printers.'
            ),
            (
                $this->obj->get('printerLevel') == 2 ?
                ' checked':
                ''
            ),
            _('Only Assigned Printers')
        );
        $this->render();
        if ($PrintersFound || count($this->data) > 0) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s" name="updateprinters"/>',
                _('Update')
            );
        }
        if (count($this->data) > 0) {
            printf(
                '&nbsp;&nbsp;<input type="submit" '
                . 'value="%s" name="printdel"/></p>',
                _('Remove selected printers')
            );
        }
        unset($this->data, $this->headerData);
        echo '</form></div>';
        printf(
            '<!-- Snapins --><div id="host-snapins">'
            . '<h2>%s</h2><form method="post" '
            . 'action="%s&tab=host-snapins">',
            _('Snapins'),
            $this->formAction
        );
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxsnapin" '
            . 'class="toggle-checkboxsnapin" id="toggler3"/>'
            . '<label for="toggler3"></label>',
            _('Snapin Name'),
            _('Created'),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapin[]" '
            . 'value="${snapin_id}" class="toggle-snapin" id="'
            . 'snapin-${snapin_id}"/>'
            . '<label for="snapin-${snapin_id}"></label>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${snapin_id}" '
                . 'title="%s">${snapin_name}</a>',
                'snapin',
                _('Edit')
            ),
            '${snapin_created}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>90,'class'=>'l'),
            array('width'=>20,'class'=>'r'),
        );
        $Snapins = self::getClass('SnapinManager')
            ->find(
                array('id' => $this->obj->get('snapinsnotinme'))
            );
        foreach ($Snapins as &$Snapin) {
            if (!$Snapin->isValid()) {
                continue;
            }
            $this->data[] = array(
                'snapin_id' => $Snapin->get('id'),
                'snapin_name' => $Snapin->get('name'),
                'snapin_created' => $Snapin->get('createdTime'),
            );
            unset($Snapin);
        }
        if (count($this->data) > 0) {
            printf(
                '<p class="c">'
                . '%s&nbsp;&nbsp;<input type="checkbox" '
                . 'name="hostSnapinShow" id="hostSnapinShow"/>'
                . '<label for="hostSnapinShow"></label><div id="snapinNotInHost">',
                _('Check here to see what snapins can be added')
            );
            self::$HookManager
                ->processEvent(
                    'HOST_SNAPIN_JOIN',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            $this->render();
            printf(
                '<input type="submit" value="%s"/>'
                . '</form></div></p><form method="post" '
                . 'action="%s&tab=host-snapins">',
                _('Add Snapin(s)'),
                $this->formAction
            );
            unset($this->data);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler4"/>'
            . '<label for="toggler4"></label>',
            _('Snapin Name'),
        );
        $this->attributes = array(
            array('class'=>'l filter-false','width'=>16),
            array(),
        );
        $this->templates = array(
            '<input type="checkbox" name="snapinRemove[]" '
            . 'value="${snap_id}" class="toggle-action" id="'
            . 'snapinrm-${snap_id}"/>'
            . '<label for="snapinrm-${snap_id}"></label>',
            '<a href="?node=snapin&sub=edit&id=${snap_id}">${snap_name}</a>',
        );
        $Snapins = self::getClass('SnapinManager')
            ->find(
                array('id' => $this->obj->get('snapins'))
            );
        foreach ((array)$Snapins as &$Snapin) {
            if (!$Snapin->isValid()) {
                continue;
            }
            $this->data[] = array(
                'snap_id'=>$Snapin->get('id'),
                'snap_name'=>$Snapin->get('name'),
            );
            unset($Snapin);
        }
        self::$HookManager->processEvent(
            'HOST_EDIT_SNAPIN',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        $this->render();
        if (count($this->data)) {
            $inputremove = sprintf(
                '<input type="submit" name="snaprem" value="%s"/>',
                _('Remove selected snapins')
            );
        }
        echo "<p class='c'>$inputremove</p></form></div>";
        unset($this->data, $this->headerData);
        echo '<!-- Service Configuration -->';
        $this->attributes = array(
            array('width'=>270),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${mod_name}',
            '${input}',
            '${span}',
        );
        $this->data[] = array(
            'mod_name' => _('Select/Deselect All'),
            'input' => '<input type="checkbox" class="checkboxes" '
            . 'id="checkAll" name="checkAll" value="checkAll"/>'
            . '<label for="checkAll"></label>',
            'span' => '&nbsp;'
        );
        printf(
            '<div id="host-service"><h2>%s</h2>'
            . '<form method="post" '
            . 'action="%s&tab=host-service">'
            . '<fieldset><legend>%s</legend>',
            _('Service Configuration'),
            $this->formAction,
            _('General')
        );
        $dcnote = sprintf(
            '%s. %s. %s %s.',
            _('This module is only used on the old client'),
            _('The old client is what was distributed with FOG 1.2.0 and earlier'),
            _('This module did not work past Windows XP due to'),
            _('UAC introduced in Vista and up')
        );
        $gfnote = sprintf(
            '%s. %s %s. %s %s %s. %s.',
            _('This module is only used on the old client'),
            _('The old client is what was distributed with'),
            _('FOG 1.2.0 and earlier'),
            _('This module has been replaced in the new client'),
            _('and the equivalent module for what Green'),
            _('FOG did is now called Power Management'),
            _('This is only here to maintain old client operations')
        );
        $ucnote = sprintf(
            '%s. %s %s. %s %s.',
            _('This module is only used on the old client'),
            _('The old client is what was distributed with'),
            _('FOG 1.2.0 and earlier'),
            _('This module did not work past Windows XP due'),
            _('to UAC introduced in Vista and up')
        );
        $cunote = sprintf(
            '%s (%s) %s.',
            _('This module is only used'),
            _('with modules and config'),
            _('on the old client')
        );
        $moduleName = self::getGlobalModuleStatus();
        $ModuleOn = $this->obj->get('modules');
        $Modules = self::getClass('ModuleManager')->find();
        foreach ((array)$Modules as &$Module) {
            if (!$Module->isValid()) {
                return;
            }
            switch ($Module->get('shortName')) {
            case 'dircleanup':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" title="%s"></i>',
                    $dcnote
                );
                break;
            case 'greenfog':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" title="%s"></i>',
                    $gfnote
                );
                break;
            case 'usercleanup':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" title="%s"></i>',
                    $ucnote
                );
                break;
            case 'clientupdater':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" title="%s"></i>',
                    $cunote
                );
                break;
            default:
                $note = '';
                break;
            }
            $this->data[] = array(
                'input' => sprintf(
                    '<input id="%s" %stype="checkbox" name="modules[]" value="%s"'
                    . ' %s%s/><label for="%s"></label>',
                    $Module->get('shortName'),
                    (
                        ($moduleName[$Module->get('shortName')]
                        || $moduleName[$Module->get('shortName')])
                        && $Module->get('isDefault') ?
                        'class="checkboxes" ':
                        ''
                    ),
                    $Module->get('id'),
                    (
                        in_array($Module->get('id'), $ModuleOn) ?
                        ' checked' :
                        ''
                    ),
                    (
                        !$moduleName[$Module->get('shortName')] ?
                        ' disabled' :
                        ''
                    ),
                    $Module->get('shortName')
                ),
                'span' => sprintf(
                    '%s<span class="icon fa fa-question fa-1x hand" '
                    . 'title="%s"></span>',
                    $note,
                    str_replace(
                        '"',
                        '\"',
                        $Module->get('description')
                    )
                ),
                'mod_name' => $Module->get('name'),
            );
            unset($Module);
        }
        unset($moduleName, $ModuleOn);
        $this->data[] = array(
            'mod_name' => '',
            'input' => '',
            'span' => sprintf(
                '<input type="submit" name="updatestatus" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager
            ->processEvent(
                'HOST_EDIT_SERVICE',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
        printf(
            '</fieldset><fieldset><legend>%s</legend>',
            _('Host Screen Resolution')
        );
        $this->attributes = array(
            array('class'=>'l','style'=>'padding-right: 25px'),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${span}',
        );
        list(
            $refresh,
            $width,
            $height,
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_CLIENT_DISPLAYMANAGER_R',
                    'FOG_CLIENT_DISPLAYMANAGER_X',
                    'FOG_CLIENT_DISPLAYMANAGER_Y',
                )
            ),
            'description',
            false,
            'AND',
            'name',
            false,
            false
        );
        $names = array(
            'x' => array(
                'width',
                $width,
                _('Screen Width (in pixels)'),
            ),
            'y' => array(
                'height',
                $height,
                _('Screen Height (in pixels)'),
            ),
            'r' => array(
                'refresh',
                $refresh,
                _('Screen Refresh Rate (in Hz)'),
            )
        );
        foreach ($names as $name => &$get) {
            $this->data[] = array(
                'input' => sprintf(
                    '<input type="text" name="%s" value="%s"/>',
                    $name,
                    $this->obj->getDispVals($get[0])
                ),
                'span' => sprintf(
                    '<span class="icon fa fa-question fa-1x hand" '
                    . 'title="%s"></span>',
                    $get[1]
                ),
                'field' => $get[2],
            );
            unset($get);
        }
        $this->data[] = array(
            'field' => '',
            'input' => '',
            'span' => sprintf(
                '<input type="submit" name="updatedisplay" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager
            ->processEvent(
                'HOST_EDIT_DISPSERV',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
        printf(
            '</fieldset><fieldset><legend>%s</legend>',
            _('Auto Log Out Settings')
        );
        $this->attributes = array(
            array('width'=>270),
            array('class'=>'c'),
            array('class'=>'r'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
            '${desc}',
        );
        $alodesc = self::getClass('Service')
            ->set('name', 'FOG_CLIENT_AUTOLOGOFF_MIN')
            ->load('name')
            ->get('description');
        $this->data[] = array(
            'field' => _('Auto Log Out Time (in minutes)'),
            'input' => '<input type="text" name="tme" value="${value}"/>',
            'desc' => '<span class="icon fa fa-question fa-1x hand" '
            . 'title="${serv_desc}"></span>',
            'value'=>$this->obj->getAlo(),
            'serv_desc' => $alodesc,
        );
        $this->data[] = array(
            'field' => '',
            'input' => '',
            'desc' => sprintf(
                '<input type="submit" name="updatealo" value="%s"/>',
                _('Update')
            ),
        );
        self::$HookManager
            ->processEvent(
                'HOST_EDIT_ALO',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data, $fields);
        echo '</fieldset></form></div>';
        echo '<!-- Power Management Items -->'
            . '<div id="host-powermanagement"><p id="cronOptions">';
        $this->headerData = array(
            '<input type="checkbox" id="rempowerselectors"/>'
            . '<label for="rempowerselectors"></label>',
            _('Cron Schedule'),
            _('Action'),
        );
        $this->templates = array(
            '<input type="checkbox" name="rempowermanagements[]" '
            . 'class="rempoweritems" value="${id}" id="rmpm-${id}"/>'
            . '<label for="rmpm-${id}"></label>',
            '<div class="deploy-container" class="l">'
            . '<p id="cronOptions"><input type="hidden" '
            . 'name="pmid[]" value="${id}"/><input '
            . 'type="text" name="scheduleCronMin[]" '
            . 'id="scheduleCronMin" autocomplete="off" '
            . 'value="${min}"/><input type="text" '
            . 'name="scheduleCronHour[]" id="scheduleCronHour" '
            . 'autocomplete="off" value="${hour}"/>'
            . '<input type="text" name="scheduleCronDOM[]" '
            . 'id="scheduleCronDOM" autocomplete="off" '
            . 'value="${dom}"/><input type="text" '
            . 'name="scheduleCronMonth[]" id="scheduleCronMonth" '
            . 'autocomplete="off" value="${month}"/>'
            . '<input type="text" name="scheduleCronDOW[]" '
            . 'id="scheduleCronDOW" autocomplete="off" '
            . 'value="${dow}"/></p></div>',
            '${action}',
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('class'=>'filter-false'),
            array('class'=>'filter-false'),
        );
        $PowerManagements = self::getClass('PowerManagementManager')
            ->find(
                array(
                    'id' => $this->obj->get('powermanagementtasks')
                )
            );
        foreach ((array)$PowerManagements as &$PowerManagement) {
            if (!$PowerManagement->isValid()) {
                continue;
            }
            if ($PowerManagement->get('onDemand')) {
                continue;
            }
            $this->data[] = array(
                'id' => $PowerManagement->get('id'),
                'min' => $PowerManagement->get('min'),
                'hour' => $PowerManagement->get('hour'),
                'dom' => $PowerManagement->get('dom'),
                'month' => $PowerManagement->get('month'),
                'dow' => $PowerManagement->get('dow'),
                'is_selected' => (
                    $PowerManagement->get('action') ?
                    ' selected' :
                    ''
                ),
                'action' => $PowerManagement->getActionSelect(),
            );
        }
        if (count($this->data) > 0) {
            printf(
                '<form method="post" action="%s&tab=host-powermanagement" '
                . 'class="deploy-container">',
                $this->formAction
            );
            $this->render();
            printf(
                '<center><input type="submit" name="pmupdate" '
                . 'value="%s"/>&nbsp;<input type="submit" '
                . 'name="pmdelete" value="%s"/></center><br/>',
                _('Update Values'),
                _('Remove selected')
            );
            echo '</form>';
        }
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->data
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Schedule Power') => sprintf(
                '<p id="cronOptions"><input type="text" '
                . 'name="scheduleCronMin" id="scheduleCronMin" '
                . 'placeholder="min" autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronHour" '
                . 'id="scheduleCronHour" placeholder="hour" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronDOM" '
                . 'id="scheduleCronDOM" placeholder="dom" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronMonth" '
                . 'id="scheduleCronMonth" placeholder="month" '
                . 'autocomplete="off" value="%s"/>'
                . '<input type="text" name="scheduleCronDOW" '
                . 'id="scheduleCronDOW" placeholder="dow" '
                . 'autocomplete="off" value="%s"/></p>',
                $_REQUEST['scheduleCronMin'],
                $_REQUEST['scheduleCronHour'],
                $_REQUEST['scheduleCronDOM'],
                $_REQUEST['scheduleCronMonth'],
                $_REQUEST['scheduleCronDOW']
            ),
            _('Perform Immediately?') => sprintf(
                '<input type="checkbox" name="onDemand" id="scheduleOnDemand"%s/>'
                . '<label for="scheduleOnDemand"></label>',
                (
                    !is_array($_REQUEST['onDemand'])
                    && isset($_REQUEST['onDemand']) ?
                    ' checked' :
                    ''
                )
            ),
            _('Action') => self::getClass('PowerManagementManager')->getActionSelect(
                $_REQUEST['action']
            ),
        );
        foreach ($fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        printf(
            '<form method="post" action="%s&tab=host-powermanagement" '
            . 'class="deploy-container">',
            $this->formAction
        );
        $this->render();
        printf(
            '<center><input type="submit" name="pmsubmit" '
            . 'value="%s"/></center></form></div>',
            _('Add Option')
        );
        unset(
            $this->headerData,
            $this->templates,
            $this->data,
            $this->attributes
        );
        echo '<!-- Inventory -->';
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $cpus = array('cpuman', 'spuversion');
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
                );
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
        $fields = array(
            _('Primary User') => sprintf(
                '<input type="text" value="%s" name="pu"/>',
                $puser
            ),
            _('Other Tag #1') => sprintf(
                '<input type="text" value="%s" name="other1"/>',
                $other1
            ),
            _('Other Tag #2') => sprintf(
                '<input type="text" value="%s" name="other2"/>',
                $other2
            ),
            _('System Manufacturer') => $sysman,
            _('System Product') => $sysprod,
            _('System Version') => $sysver,
            _('System Serial Number') => $sysser,
            _('System Type') => $systype,
            _('BIOS Vendor') => $biosven,
            _('BIOS Version') => $biosver,
            _('BIOS Date') => $biosdate,
            _('Motherboard Manufacturer') => $mbman,
            _('Motherboard Product Name') => $mbprod,
            _('Motherboard Version') => $mbver,
            _('Motherboard Serial Number') => $mbser,
            _('Motherboard Asset Tag') => $mbast,
            _('CPU Manufacturer') => $cpuman,
            _('CPU Version') => $cpuver,
            _('CPU Normal Speed') => $cpucur,
            _('CPU Max Speed') => $cpumax,
            _('Memory') => $mem,
            _('Hard Disk Model') => $hdmod,
            _('Hard Disk Firmware') => $hdfirm,
            _('Hard Disk Serial Number') => $hdser,
            _('Chassis Manufacturer') => $caseman,
            _('Chassis Version') => $casever,
            _('Chassis Serial') => $caseser,
            _('Chassis Asset') => $caseast,
            '&nbsp;' => sprintf(
                '<input name="update" type="submit" value="%s"/>',
                _('Update')
            ),
        );
        printf(
            '<div id="host-hardware-inventory">'
            . '<form method="post" action="%s&tab=host-hardware-inventory">'
            . '<h2>%s</h2>',
            $this->formAction,
            _('Host Hardware Inventory')
        );
        if ($this->obj->get('inventory')->isValid()) {
            array_walk($fields, $this->fieldsToData);
        }
        self::$HookManager
            ->processEvent(
                'HOST_INVENTORY',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data, $fields);
        echo '</form></div><!-- Virus -->';
        $this->headerData = array(
            _('Virus Name'),
            _('File'),
            _('Mode'),
            _('Date'),
            _('Clear'),
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        $this->templates = array(
            '<a href="http://www.google.com/search?q='
            . '${virus_name}" target="_blank">${virus_name}</a>',
            '${virus_file}',
            '${virus_mode}',
            '${virus_date}',
            sprintf(
                '<input type="checkbox" id="vir_del${virus_id}" '
                . 'class="delvid" name="delvid" value="${virus_id}"/>'
                . '<label for="${virus_id}" class="icon icon-hand" '
                . 'title="%s ${virus_name}">'
                . '<i class="icon fa fa-minus-circle link"></i>'
                . '</label>',
                _('Delete')
            ),
        );
        printf(
            '<div id="host-virus-history">'
            . '<form method="post" action="%s&tab=host-virus-history">'
            . '<h2>%s</h2>'
            . '<h2><a href="#">'
            . '<input type="checkbox" class="delvid" id="all" '
            . 'name="delvid" value="all"/>'
            . '<label for="all">(%s)</label></a></h2>',
            $this->formAction,
            _('Virus History'),
            _('clear all history')
        );
        $virHists = self::getClass('VirusManager')
            ->find(
                array(
                    'mac' => $this->obj->getMyMacs()
                ),
                'OR'
            );
        foreach ((array)$virHists as &$Virus) {
            if (!$Virus->isValid()) {
                continue;
            }
            switch (strtolower($Virus->get('mode'))) {
            case 'q':
                $mode = _('Quarantine');
                break;
            case 's':
                $mode = _('Report');
                break;
            default:
                $mode = _('N/A');
            }
            $this->data[] = array(
                'virus_name' => $Virus->get('name'),
                'virus_file' => $Virus->get('file'),
                'virus_mode' => $mode,
                'virus_date' => $Virus->get('date'),
                'virus_id' => $Virus->get('id'),
            );
            unset($Virus);
        }
        self::$HookManager
            ->processEvent(
                'HOST_VIRUS',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data, $this->headerData);
        printf(
            '</form></div>'
            . '<!-- Login History --><div id="host-login-history">'
            . '<h2>%s</h2>'
            . '<form id="dte" method="post" action="%s&tab=host-login-history">',
            _('Host Login History'),
            $this->formAction
        );
        $this->headerData = array(
            _('Time'),
            _('Action'),
            _('Username'),
            _('Description')
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        $this->templates = array(
            '${user_time}',
            '${action}',
            '${user_name}',
            '${user_desc}',
        );
        $Dates = self::getSubObjectIDs(
            'UserTracking',
            array(
                'id' => $this->obj->get('users')
            ),
            'date'
        );
        if (count($Dates) > 0) {
            rsort($Dates);
            printf(
                '<p>%s</p>',
                _('View History for')
            );
            ob_start();
            foreach ((array)$Dates as $i => &$Date) {
                if ($_REQUEST['dte'] == '') {
                    $_REQUEST['dte'] = $Date;
                }
                printf(
                    '<option value="%s"%s>%s</option>',
                    $Date,
                    (
                        $Date == $_REQUEST['dte'] ?
                        ' selected' :
                        ''
                    ),
                    $Date
                );
                unset($Date);
            }
            unset($Dates);
            printf(
                '<select name="dte" class="loghist-date" size="1">'
                . '%s</select><a class="loghist-date" href="#">'
                . '<i class="icon fa fa-play noBorder"></i></a></p>',
                ob_get_clean()
            );
            $UserLogins = self::getClass('UserTrackingManager')
                ->find(
                    array(
                        'hostID' => $this->obj->get('id'),
                        'date' => $_REQUEST['dte'],
                        'action' => array(
                            '',
                            0,
                            1
                        )
                    ),
                    'AND',
                    array('username','datetime','action'),
                    array('ASC','ASC','DESC')
                );
            $Data = array();
            foreach ((array)$UserLogins as &$Login) {
                $time = self::niceDate($Login->get('datetime'))
                    ->format('U');
                if (!isset($Data[$Login->get('username')])) {
                    $Data[$Login->get('username')] = array();
                }
                if (array_key_exists('login', $Data[$Login->get('username')])) {
                    if ($Login->get('action') > 0) {
                        $this->data[] = array(
                            'action' => _('Logout'),
                            'user_name' => $Login->get('username'),
                            'user_time' => (
                                self::niceDate()
                                ->setTimestamp($time - 1)
                                ->format('Y-m-d H:i:s')
                            ),
                            'user_desc' => sprintf(
                                '%s.<br/><small>%s.</small>',
                                _('Logout not found'),
                                _('Setting logout to one second prior to next login')
                            )
                        );
                        $Data[$Login->get('username')] = array();
                    }
                }
                if ($Login->get('action') > 0) {
                    $Data[$Login->get('username')]['login'] = true;
                    $this->data[] = array(
                        'action' => _('Login'),
                        'user_name' => $Login->get('username'),
                        'user_time' => (
                            self::niceDate()
                            ->setTimestamp($time)
                            ->format('Y-m-d H:i:s')
                        ),
                        'user_desc' => $Login->get('description')
                    );
                } elseif ($Login->get('action') < 1) {
                    $this->data[] = array(
                        'action' => _('Logout'),
                        'user_name' => $Login->get('username'),
                        'user_time' => (
                            self::niceDate()
                            ->setTimestamp($time)
                            ->format('Y-m-d H:i:s')
                        ),
                        'user_desc' => $Login->get('description')
                    );
                    $Data[$Login->get('username')] = array();
                }
                unset($Login);
            }
            self::$HookManager
                ->processEvent(
                    'HOST_USER_LOGIN',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            $this->render();
        } else {
            printf('<p>%s</p>', _('No user history data found!'));
        }
        unset($this->data, $this->headerData);
        printf(
            '<div id="login-history"/></div></form>'
            . '</div><div id="host-image-history"><h2>%s</h2>',
            _('Host Imaging History')
        );
        $this->headerData = array(
            _('Engineer'),
            _('Imaged From'),
            _('Start'),
            _('End'),
            _('Duration'),
            _('Image'),
            _('Type'),
            _('State'),
        );
        $this->templates = array(
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
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
        );
        $imagingLogs = self::getClass('ImagingLogManager')
            ->find(
                array(
                    'hostID' => $this->obj->get('id')
                )
            );
        $imgTypes = array(
            'up' => _('Capture'),
            'down' => _('Deploy'),
        );
        foreach ((array)$imagingLogs as &$log) {
            if (!$log->isValid()) {
                continue;
            }
            $start = $log->get('start');
            $end = $log->get('finish');
            if (!self::validDate($start) || !self::validDate($end)) {
                continue;
            }
            $diff = self::diff($start, $end);
            $start = self::niceDate($start);
            $end = self::niceDate($end);
            $TaskIDs = self::getSubObjectIDs(
                'Task',
                array(
                    'checkInTime' => $log->get('start'),
                    'hostID' => $this->obj->get('id')
                )
            );
            $taskID = @max($TaskIDs);
            unset($TaskIDs);
            $Task = new Task($taskID);
            if (!$Task->isValid()) {
                continue;
            }
            $groupName = $Task->getStorageGroup()->get('name');
            $nodeName = $Task->getStorageNode()->get('name');
            $typeName = $Task->getTaskType()->get('name');
            $stateName = $Task->getTaskState()->get('name');
            unset($Task);
            if (!$typeName) {
                $typeName = $log->get('type');
            }
            if (in_array($typeName, array('up', 'downl'))) {
                $typeName = $imgTypes[$typeName];
            }
            $createdBy = (
                $log->get('createdBy') ?
                $log->get('createdBy') :
                self::$FOGUser->get('name')
            );
            $Image = self::getClass('Image')
                ->set('name', $log->get('image'))
                ->load('name');
            if ($Image->isValid()) {
                $imgName = $Image->get('name');
                $imgPath = $Image->get('path');
            } else {
                $imgName = $log->get('image');
                $imgPath = 'N/A';
            }
            unset($Image, $log);
            $this->data[] = array(
                'createdBy' => $createdBy,
                'group_name' => $groupName,
                'node_name' => $nodeName,
                'start_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'end_date' => $end->format('Y-m-d'),
                'end_time' => $end->format('H:i:s'),
                'duration' => $diff,
                'image_name' => $imgName,
                'type' => $typeName,
                'state' => $stateName,
            );
        }
        self::$HookManager
            ->processEvent(
                'HOST_IMAGE_HIST',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        unset($this->data);
        echo '</div><div id="host-snapin-history">';
        $this->headerData = array(
            _('Snapin Name'),
            _('Start Time'),
            _('Complete'),
            _('Duration'),
            _('Return Code'),
        );
        $this->templates = array(
            '${snapin_name}',
            '${snapin_start}',
            '${snapin_end}',
            '${snapin_duration}',
            '${snapin_return}',
        );
        $SnapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            array(
                'hostID' => $this->obj->get('id')
            )
        );
        $SnapinTasks = self::getClass('SnapinTaskManager')
            ->find(
                array(
                    'jobID' => $SnapinJobIDs
                )
            );
        $doneStates = array(
            self::getCompleteState(),
            self::getCancelledState()
        );
        foreach ((array)$SnapinTasks as &$SnapinTask) {
            if (!$SnapinTask->isValid()) {
                continue;
            }
            $Snapin = $SnapinTask->getSnapin();
            if (!$Snapin->isValid()) {
                continue;
            }
            $start = self::niceDate($SnapinTask->get('checkin'));
            $end = self::niceDate($SnapinTask->get('complete'));
            if (!self::validDate($start)) {
                continue;
            }
            if (!in_array($SnapinTask->get('stateID'), $doneStates)) {
                $diff = _('Snapin task not completed');
            } elseif (!self::validDate($end)) {
                $diff = _('No complete time recorded');
            } else {
                $diff = self::diff($start, $end);
            }
            $this->data[] = array(
                'snapin_name' => $Snapin->get('name'),
                'snapin_start' => self::formatTime(
                    $SnapinTask->get('checkin'), 'Y-m-d H:i:s'
                ),
                'snapin_end' => sprintf(
                    '<span class="icon" title="%s">%s</span>',
                    self::formatTime(
                        $SnapinTask->get('complete'), 'Y-m-d H:i:s'
                    ),
                    self::getClass(
                        'TaskState',
                        $SnapinTask->get('stateID')
                    )->get('name')
                ),
                'snapin_duration' => $diff,
                'snapin_return'=> $SnapinTask->get('return'),
            );
            unset($Snapin, $SnapinTask);
        }
        self::$HookManager
            ->processEvent(
                'HOST_SNAPIN_HIST',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
        echo '</div></div>';
    }
    /**
     * Updates the host when form is submitted
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager->processEvent(
            'HOST_EDIT_POST',
            array('Host' => &$this->obj)
        );
        try {
            global $tab;
            switch ($tab) {
            case 'host-general':
                $hostName = trim($_REQUEST['host']);
                if (empty($hostName)) {
                    throw new Exception(
                        _('Please enter a hostname')
                    );
                }
                if ($this->obj->get('name') != $hostName
                    && !$this->obj->isHostnameSafe($hostName)
                ) {
                    throw new Exception(
                        _('Please enter a valid hostname')
                    );
                }
                if ($this->obj->get('name') != $hostName
                    && $this->obj->getManager()->exists($hostName)
                ) {
                    throw new Exception(
                        _('Hostname Exists already')
                    );
                }
                if (empty($_REQUEST['mac'])) {
                    throw new Exception(
                        _('MAC Address is required')
                    );
                }
                $mac = self::parseMacList($_REQUEST['mac']);
                if (count($mac) < 1) {
                    throw new Exception(
                        _('No valid macs returned')
                    );
                }
                $mac = array_shift($mac);
                if (!$mac->isValid()) {
                    throw new Exception(
                        _('The returned MAC is invalid')
                    );
                }
                $Task = $this->obj->get('task');
                if ($Task->isValid()
                    && $_REQUEST['image'] != $this->obj->get('imageID')
                ) {
                    throw new Exception(
                        sprintf(
                            '%s.<br/>%s.',
                            _('Cannot change image'),
                            _('Host is in a tasking')
                        )
                    );
                }
                $key = $_REQUEST['key'];
                $key = trim($key);
                $key = strtoupper($key);
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
                $this
                    ->obj
                    ->set('name', $hostName)
                    ->set('description', $_REQUEST['description'])
                    ->set('imageID', $_REQUEST['image'])
                    ->set('kernel', $_REQUEST['kern'])
                    ->set('kernelArgs', $_REQUEST['args'])
                    ->set('kernelDevice', $_REQUEST['dev'])
                    ->set('init', $_REQUEST['init'])
                    ->set('biosexit', $_REQUEST['bootTypeExit'])
                    ->set('efiexit', $_REQUEST['efiBootTypeExit'])
                    ->set('productKey', self::encryptpw($productKey));
                $primac = $this->obj->get('mac')->__toString();
                $setmac = $mac->__toString();
                if ($primac != $setmac) {
                    $this->obj->addPriMAC($mac->__toString());
                }
                $addmacs = self::parseMacList($_REQUEST['additionalMACs']);
                $macs = array();
                foreach ((array)$addmacs as &$addmac) {
                    if (!$addmac->isValid()) {
                        continue;
                    }
                    $macs[] = $addmac->__toString();
                    unset($addmac);
                }
                $removeMACs = array_diff(
                    (array)self::getSubObjectIDs(
                        'MACAddressAssociation',
                        array(
                            'hostID' => $this->obj->get('id'),
                            'primary' => 0,
                            'pending' => 0
                        ),
                        'mac'
                    ),
                    $macs
                );
                $this
                    ->obj
                    ->addAddMAC($macs)
                    ->removeAddMAC($removeMACs);
                break;
            case 'host-active-directory':
                $useAD = isset($_REQUEST['domain']);
                $domain = trim($_REQUEST['domainname']);
                $ou = trim($_REQUEST['ou']);
                $user = trim($_REQUEST['domainuser']);
                $pass = trim($_REQUEST['domainpassword']);
                $passlegacy = trim($_REQUEST['domainpasswordlegacy']);
                $enforce = isset($_REQUEST['enforcesel']);
                $this->obj->setAD(
                    $useAD,
                    $domain,
                    $ou,
                    $user,
                    $pass,
                    true,
                    true,
                    $passlegacy,
                    $productKey,
                    $enforce
                );
                break;
            case 'host-powermanagement':
                $min = $_REQUEST['scheduleCronMin'];
                $hour = $_REQUEST['scheduleCronHour'];
                $dom = $_REQUEST['scheduleCronDOM'];
                $month = $_REQUEST['scheduleCronMonth'];
                $dow = $_REQUEST['scheduleCronDOW'];
                $onDemand = (string)intval(isset($_REQUEST['onDemand']));
                $action = $_REQUEST['action'];
                if (!$action) {
                    throw new Exception(
                        _('You must select an action to perform')
                    );
                }
                $items = array();
                if (isset($_REQUEST['pmupdate'])) {
                    $pmid = $_REQUEST['pmid'];
                    $items = array();
                    foreach ((array)$pmid as $index => &$pm) {
                        $onDemandItem = array_search($pm, $onDemand);
                        $items[] = array(
                            $pm,
                            $this->obj->get('id'),
                            $min[$index],
                            $hour[$index],
                            $dom[$index],
                            $month[$index],
                            $dow[$index],
                            $onDemandItem !== -1
                            && $onDemand[$onDemandItem] === $pm ?
                            1 :
                            0,
                            $action[$index]
                        );
                        unset($pm);
                    }
                    self::getClass('PowerManagementManager')
                        ->insertBatch(
                            array(
                                'id',
                                'hostID',
                                'min',
                                'hour',
                                'dom',
                                'month',
                                'dow',
                                'onDemand',
                                'action'
                            ),
                            $items
                        );
                }
                if (isset($_REQUEST['pmsubmit'])) {
                    if ($onDemand && $action === 'wol') {
                        $this->obj->wakeOnLAN();
                        break;
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
                if (isset($_REQUEST['pmdelete'])) {
                    self::getClass('PowerManagementManager')
                        ->destroy(
                            array(
                                'id' => $_REQUEST['rempowermanagements']
                            )
                        );
                }
                break;
            case 'host-printers':
                $PrinterManager = self::getClass('PrinterAssociationManager');
                if (isset($_REQUEST['level'])) {
                    $this->obj->set('printerLevel', $_REQUEST['level']);
                }
                if (isset($_REQUEST['updateprinters'])) {
                    if (isset($_REQUEST['printer'])) {
                        $this->obj->addPrinter($_REQUEST['printer']);
                    }
                    $this->obj->updateDefault(
                        $_REQUEST['default'],
                        isset($_REQUEST['default'])
                    );
                    unset($printerid);
                }
                if (isset($_REQUEST['printdel'])) {
                    $this->obj->removePrinter($_REQUEST['printerRemove']);
                }
                break;
            case 'host-snapins':
                if (!isset($_REQUEST['snapinRemove'])) {
                    $this->obj->addSnapin($_REQUEST['snapin']);
                }
                if (isset($_REQUEST['snaprem'])) {
                    $this->obj->removeSnapin($_REQUEST['snapinRemove']);
                }
                break;
            case 'host-service':
                $x = $_REQUEST['x'];
                $y = $_REQUEST['y'];
                $r = $_REQUEST['r'];
                $tme = $_REQUEST['tme'];
                $modOn = (array)$_REQUEST['modules'];
                $modOff = self::getSubObjectIDs(
                    'Module',
                    array(
                        'id' => $modOn
                    ),
                    'id',
                    true
                );
                $this->obj->addModule($modOn);
                $this->obj->removeModule($modOff);
                $this->obj->setDisp($x, $y, $r);
                $this->obj->setAlo($tme);
                break;
            case 'host-hardware-inventory':
                $pu = trim($_REQUEST['pu']);
                $other1 = trim($_REQUEST['other1']);
                $other2 = trim($_REQUEST['other2']);
                if (isset($_REQUEST['update'])) {
                    $this->obj
                        ->get('inventory')
                        ->set('primaryUser', $pu)
                        ->set('other1', $other1)
                        ->set('other2', $other2)
                        ->save();
                }
                break;
            case 'host-login-history':
                self::setMessage(_('Date Changed'));
                self::redirect(
                    sprintf(
                        '?node=host&sub=edit&id=%s&dte=%s#%s',
                        $this->obj->get('id'),
                        $_REQUEST['dte'],
                        $_REQUEST['tab']
                    )
                );
                break;
            case 'host-virus-history':
                if (isset($_REQUEST['delvid'])
                    && $_REQUEST['delvid'] == 'all'
                ) {
                    $this->obj->clearAVRecordsForHost();
                    self::setMessage(
                        _('All virus history cleared for this host')
                    );
                } elseif (isset($_REQUEST['delvid'])) {
                    self::getClass('VirusManager')
                        ->destroy(
                            array(
                                'id' => $_REQUEST['delvid']
                            )
                        );
                    self::setMessage(_('Selected virus history item cleaned'));
                }
                self::redirect(
                    sprintf(
                        '?node=host&sub=edit&id=%s#%s',
                        $this->obj->get('id'),
                        $_REQUEST['tab']
                    )
                );
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Host Update Failed'));
            }
            $this->obj->setAD();
            if ($_REQUEST['tab'] == 'host-general') {
                $this->obj->ignore($_REQUEST['igimage'], $_REQUEST['igclient']);
            }
            $hook = 'HOST_EDIT_SUCCESS';
            $msg = _('Host updated');
        } catch (Exception $e) {
            $hook = 'HOST_EDIT_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Host' => &$this->obj)
            );
        self::setMessage($msg);
        self::redirect($this->formAction);
    }
    /**
     * Saves host to a selected or new group depending on action.
     *
     * @return void
     */
    public function saveGroup()
    {
        try {
            $Group = self::getClass('Group', $_REQUEST['group']);
            if (!empty($_REQUEST['group_new'])) {
                $Group
                    ->set('name', $_REQUEST['group_new'])
                    ->load('name');
            }
            $Group->addHost($_REQUEST['hostIDArray']);
            if (!$Group->save()) {
                throw new Exception(_('Failed to create new Group'));
            }
            return print _('Successfully associated Hosts with the Group ');
        } catch (Exception $e) {
            echo sprintf(
                '%s<br/>%s',
                _('Failed to Associate Hosts with Group'),
                $e->getMessage()
            );
            exit;
        }
    }
    /**
     * Gets the host user tracking info.
     *
     * @return void
     */
    public function hostlogins()
    {
        $MainDate = self::niceDate($_REQUEST['dte'])
            ->getTimestamp();
        $MainDate_1 = self::niceDate($_REQUEST['dte'])
            ->modify('+1 day')
            ->getTimestamp();
        $UserTracks = self::getClass('UserTrackingManager')
            ->find(
                array(
                    'hostID' => $this->obj->get('id'),
                    'date' => $_REQUEST['dte'],
                    'action' => array(
                        '',
                        0,
                        1
                    )
                ),
                'AND',
                array('username','datetime','action'),
                array('ASC','ASC','DESC')
            );
        $data = null;
        $Data = array();
        foreach ((array)$UserTracks as &$Login) {
            $time = self::niceDate($Login->get('datetime'))
                ->format('U');
            $Data[$Login->get('username')]['user'] = $Login->get('username');
            $Data[$Login->get('username')]['min'] = $MainDate;
            $Data[$Login->get('username')]['max'] = $MainDate_1;
            if (array_key_exists('login', $Data[$Login->get('username')])) {
                if ($Login->get('action') > 0) {
                    $Data[$Login->get('username')]['logout'] = (int)$time - 1;
                    $data[] = $Data[$Login->get('username')];
                    $Data[$Login->get('username')] = array(
                        'user' => $Login->get('username'),
                        'min' => $MainDate,
                        'max' => $MainDate_1
                    );
                } elseif ($Login->get('action') < 1) {
                    $Data[$Login->get('username')]['logout'] = (int)$time;
                    $data[] = $Data[$Login->get('username')];
                    $Data[$Login->get('username')] = array(
                        'user' => $Login->get('username'),
                        'min' => $MainDate,
                        'max' => $MainDate_1
                    );
                }
            }
            if ($Login->get('action') > 0) {
                $Data[$Login->get('username')]['login'] = (int)$time;
            }
            unset($Login);
        }
        unset($UserTracks);
        echo json_encode($data);
        exit;
    }
}
