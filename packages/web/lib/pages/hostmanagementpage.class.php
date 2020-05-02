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
            $this->exitNorm = filter_input(INPUT_POST, 'bootTypeExit');
            $this->exitEfi = filter_input(INPUT_POST, 'efiBootTypeExit');
        } else {
            $this->exitNorm = $this->obj->get('biosexit');
            $this->exitEfi = $this->obj->get('efiexit');
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
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler"/>'
            . '</label>',
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
            '<i class="icon fa fa-question hand"></i>',
            '<label for="host-${id}">'
            . '<input type="checkbox" name="host[]" '
            . 'value="${id}" class="toggle-action" id="host-${id}"/>'
            . '</label>',
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
            '<a href="?node=host&sub=edit&id=${id}" '
            . 'title="'
            . _('Edit')
            . ': ${host_name}" id="host-${host_name}" '
            . 'data-toggle="tooltip" data-placement="right">'
            . '${host_name}'
            . '</a>'
            . '<br/>'
            . '<small>${host_mac}</small>',
            '<small>${deployed}</small>',
            sprintf(
                '<a href="?node=host&sub=deploy&type=1&id=${id}">'
                . '<i class="icon fa fa-%s" title="%s"></i></a> '
                . '<a href="?node=host&sub=deploy&type=2&id=${id}">'
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
                'class' => 'filter-false',
                'title' => '${host_desc}',
                'data-toggle' => 'tooltip',
                'data-placement' => 'right'
            ),
            array(
                'class' => 'l filter-false form-group',
                'width' => 16
            ),
        );
        if (self::$fogpingactive) {
            array_push(
                $this->attributes,
                array(
                    'width' => 16,
                    'class' => 'filter-false'
                )
            );
        }
        array_push(
            $this->attributes,
            array('width' => 50),
            array('width' => 145),
            array(
                'width' => 60,
                'class' => 'filter-false'
            ),
            array(
                'width' => 20
            )
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Host the object to use.
         *
         * @return void
         */
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
        Route::listem(
            'host',
            'name',
            false,
            array('pending' => 1)
        );
        $Hosts = json_decode(
            Route::getData()
        );
        $Hosts = $Hosts->hosts;
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
                '<form class="form-horizontal" method="post" action="%s">',
                $this->formAction
            );
        }
        $this->render();
        if (count($this->data) > 0) {
            echo '<button name="approvependhost" type="submit" id='
                . '"approvependhost" class='
                . '"btn btn-info">'
                . _('Approve selected hosts')
                . '</button>'
                . '<button name="delpendhost" type="submit" id='
                . '"delpendhost" class='
                . '"btn btn-danger">'
                . _('Delete selected hosts')
                . '</button>';
            echo '</form>';
        }
    }
    /**
     * Pending host form submitting
     *
     * @return void
     */
    public function pendingPost()
    {
        $host = filter_input_array(
            INPUT_POST,
            array(
                'host' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $host = $host['host'];
        if (isset($_POST['approvependhost'])) {
            self::getClass('HostManager')->update(
                array(
                    'id' => $host
                ),
                '',
                array('pending' => 0)
            );
            $title = _('Approve Success');
            $msg = _('Selected hosts approved successfully');
        }
        if (isset($_POST['delpendhost'])) {
            self::getClass('HostManager')->destroy(
                array(
                    'id' => $host
                )
            );
            $title = _('Deleted Success');
            $msg = _('Selected hosts deleted successfully');
        }
        $msg = json_encode(
            array(
                'msg' => $msg,
                'title' => $title
            )
        );
        echo $msg;
        exit;
    }
    /**
     * Creates a new host entry manually.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Host');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $fields = array(
            '<label for="host">'
            . _('Host Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="host" '
            . 'value="'
            . filter_input(INPUT_POST, 'host')
            . '" maxlength="15" '
            . 'class="hostname-input form-control" '
            . 'id="host" required/>'
            . '</div>',
            '<label for="mac">'
            . _('Primary MAC')
            . '</label>' => '<div class="input-group">'
            . '<span class="mac-manufactor input-group-addon">'
            . '</span>'
            . '<input type="text" name="mac" class="macaddr form-control" '
            . 'id="mac" value="'
            . filter_input(INPUT_POST, 'mac')
            . '" maxlength="17" required/>'
            . '</div>',
            '<label for="description">'
            . _('Host Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control" '
            . 'id="description" name="description">'
            . filter_input(INPUT_POST, 'description')
            . '</textarea>'
            . '</div>',
            '<label for="productKey">'
            . _('Host Product Key')
            . '</label>' => '<div class="input-group">'
            . '<input id="productKey" type="text" '
            . 'name="key" value="'
            . filter_input(INPUT_POST, 'key')
            . '" class="form-control"/>'
            . '</div>',
            '<label for="image">'
            . _('Host Image')
            . '</label>' => '<div class="input-group">'
            . self::getClass('ImageManager')->buildSelectBox(
                filter_input(INPUT_POST, 'image'),
                '',
                'id'
            )
            . '</div>',
            '<label for="kern">'
            . _('Host Kernel')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="kern" '
            . 'value="'
            . filter_input(INPUT_POST, 'kern')
            . '" class="form-control" id="kern"/>'
            . '</div>',
            '<label for="args">'
            . _('Host Kernel Arguments')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="args" id="args" value="'
            . filter_input(INPUT_POST, 'args')
            . '" class="form-control"/>'
            . '</div>',
            '<label for="init">'
            . _('Host Init')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="init" value="'
            . filter_input(INPUT_POST, 'init')
            . '" id="init" class="form-control"/>',
            '<label for="dev">'
            . _('Host Primary Disk')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="dev" value="'
            . filter_input(INPUT_POST, 'dev')
            . '" id="dev" class="form-control"/>'
            . '</div>',
            '<label for="bootTypeExit">'
            . _('Host Bios Exit Type')
            . '</label>' => '<div class="input-group">'
            . $this->exitNorm
            . '</div>',
            '<label for="efiBootTypeExit">'
            . _('Host EFI Exit Type')
            . '</label>' => '<div class="input-group">'
            . $this->exitEfi
            . '</div>',
        );
        self::$HookManager
            ->processEvent(
                'HOST_FIELDS',
                array(
                    'fields' => &$fields,
                    'Host' => self::getClass('Host')
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'HOST_ADD_GEN',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes,
                    'headerData' => &$this->headerData
                )
            );
        echo '<div class="col-xs-9">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if (!isset($_POST['enforcesel'])) {
            $_POST['enforcesel'] = self::getSetting('FOG_ENFORCE_HOST_CHANGES');
        }
        echo '<!-- Host General -->';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        $this->adFieldsToDisplay(
            filter_input(INPUT_POST, 'domain'),
            filter_input(INPUT_POST, 'domainname'),
            filter_input(INPUT_POST, 'ou'),
            filter_input(INPUT_POST, 'domainuser'),
            filter_input(INPUT_POST, 'domainpassword'),
            filter_input(INPUT_POST, 'domainpasswordlegacy'),
            isset($_POST['enforcesel']),
            false
        );
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
        self::$HookManager->processEvent('HOST_ADD_POST');
        $name = trim(
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
        $passlegacy = trim(
            filter_input(INPUT_POST, 'domainpasswordlegacy')
        );
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
        try {
            if (!$name) {
                throw new Exception(
                    _('A host name is required!')
                );
            }
            if (!$mac) {
                throw new Exception(
                    _('A mac address is required!')
                );
            }
            if (self::getClass('HostManager')->exists($name)) {
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
                array('isDefault' => 1)
            );
            self::$Host
                ->set('name', $name)
                ->set('description', $desc)
                ->set('imageID', $image)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelArgs)
                ->set('kernelDevice', $kernelDevice)
                ->set('init', $init)
                ->set('biosexit', $bootTypeExit)
                ->set('efiexit', $efiBootTypeExit)
                ->set('productKey', $productKey)
                ->set('modules', $ModuleIDs)
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
            if (!self::$Host->save()) {
                throw new Exception(_('Add host failed!'));
            }
            $hook = 'HOST_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Host added!'),
                    'title' => _('Host Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'HOST_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Host Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Host' => &$Host)
            );
        echo $msg;
        exit;
    }
    /**
     * Generates the powermanagement display items.
     *
     * @return void
     */
    public function hostPMDisplay()
    {
        echo '<!-- Power Management Items -->';
        echo '<div class="tab-pane fade" id="host-powermanagement">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Power Management');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->newPMDisplay();
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        // PowerManagement
        $this->headerData = array(
            '<div class="checkbox">'
            . '<label for="rempowerselectors">'
            . '<input type="checkbox" id="rempowerselectors"/>'
            . '</label>'
            . '</div>',
            _('Cron Schedule'),
            _('Action'),
        );
        $this->templates = array(
            '<input type="checkbox" name="rempowermanagements[]" '
            . 'class="rempoweritems" value="${id}" id="rmpm-${id}"/>'
            . '<label for="rmpm-${id}"></label>',
            '<div class="cronOptions input-group">'
            . FOGCron::buildSpecialCron()
            . '</div>'
            . '<div class="col-xs-12">'
            . '<div class="cronInputs">'
            . '<div class="col-xs-2">'
            . '<input type="hidden" name="pmid[]" value="${id}"/>'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronMin[]" '
            . 'class="scheduleCronMin form-control cronInput" value="${min}" '
            . 'id="scheduleCronMin"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronHour[]" '
            . 'class="scheduleCronHour form-control cronInput" value="${hour}" '
            . 'id="scheduleCronHour"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronDOM[]" '
            . 'class="scheduleCronDOM form-control cronInput" value="${dom}" '
            . 'id="scheduleCronDOM"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronMonth[]" '
            . 'class="scheduleCronMonth form-control cronInput" value="${month}" '
            . 'id="scheduleCronMonth"/>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-2">'
            . '<div class="input-group">'
            . '<input type="text" name="scheduleCronDOW[]" '
            . 'class="scheduleCronDOW form-control cronInput" value="${dow}" '
            . 'id="scheduleCronDOW"/>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>',
            '${action}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
                'class' => 'filter-false'
            ),
            array(
                'class' => 'filter-false'
            )
        );
        Route::listem('powermanagement');
        $PowerManagements = json_decode(
            Route::getData()
        );
        $PowerManagements = $PowerManagements->powermanagements;
        foreach ((array)$PowerManagements as &$PowerManagement) {
            $mine = in_array(
                $PowerManagement->id,
                $this->obj->get('powermanagementtasks')
            );
            if (!$mine) {
                continue;
            }
            if ($PowerManagement->onDemand) {
                continue;
            }
            $this->data[] = array(
                'id' => $PowerManagement->id,
                'min' => $PowerManagement->min,
                'hour' => $PowerManagement->hour,
                'dom' => $PowerManagement->dom,
                'month' => $PowerManagement->month,
                'dow' => $PowerManagement->dow,
                'action' => self::getClass('PowerManagementManager')
                    ->getActionSelect(
                        $PowerManagement->action,
                        true
                    )
            );
            unset($PowerManagement);
        }
        // Current data.
        if (is_array($this->data) && count($this->data) > 0) {
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Current Power Management settings');
            echo '</h4>';
            echo '</div>';
            echo '<div class="body">';
            echo '<form class="deploy-container form-horizontal" '
                . 'method="post" action="'
                . $this->formAction
                . '&tab=host-powermanagement">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label class="col-xs-4 control-label" for="pmupdate">';
            echo _('Update PM Values');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="pmupdate" class='
                . '"btn btn-info btn-block" id="pmupdate">';
            echo _('Update');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label class="col-xs-4 control-label" for="pmdelete">';
            echo _('Delete selected');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="pmdelete" class='
                . '"btn btn-danger btn-block" id="pmdelete">';
            echo _('Remove');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Displays the host general tab.
     *
     * @return void
     */
    public function hostGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        ob_start();
        foreach ((array)$this->obj->get('additionalMACs') as $ind => &$MAC) {
            echo '<div class="addrow">';
            echo '<div class="col-xs-10">';
            echo '<div class="input-group">';
            echo '<span class="mac-manufactor input-group-addon"></span>';
            echo '<input type="text" class="macaddr additionalMAC form-control" '
                . 'name="additionalMACs[]" '
                . 'value="'
                . $MAC
                . '" maxlength="17"/>';
            echo '<span class="icon remove-mac fa fa-minus-circle hand '
                . 'input-group-addon" '
                . 'data-toggle="tooltip" data-placement="top" '
                . 'title="'
                . _('Remove MAC')
                . '"></span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-1">';
            echo '<div class="row">';
            echo '<span data-toggle="tooltip" data-placement="top" '
                . 'title="'
                . _('Ignore MAC on Client')
                . '" class="hand">'
                . _('I.M.C.')
                . '</span>';
            echo '</div>';
            echo '<div class="checkbox">';
            echo '<label>';
            echo '<input type="checkbox" name="igclient[]" value="'
                . $MAC
                . '"'
                . $this->obj->clientMacCheck($MAC)
                . '/>';
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<div class="col-xs-1">';
            echo '<div class="row">';
            echo '<span data-toggle="tooltip" data-placement="top" '
                . 'title="'
                . _('Ignore MAC on Image')
                . '" class="hand">'
                . _('I.M.I.')
                . '</span>';
            echo '</div>';
            echo '<div class="checkbox">';
            echo '<label>';
            echo '<input type="checkbox" name="igimage[]" value="'
                . $MAC
                . '"'
                . $this->obj->imageMacCheck($MAC)
                . '/>';
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        $addMACs = ob_get_clean();
        ob_start();
        foreach ((array)$this->obj->get('pendingMACs') as &$MAC) {
            echo '<div class="addrow">';
            echo '<div class="col-xs-10">';
            echo '<div class="input-group">';
            echo '<span class="mac-manufactor input-group-addon"></span>';
            echo '<input type="text" class="macaddr pending-mac form-control" '
                . 'name="pendingMACs[]" '
                . 'value="'
                . $MAC
                . '" maxlength="17"/>';
            echo '<a class="input-group-addon" href="'
                . $this->formAction
                . '&confirmMAC='
                . $MAC
                . '" data-toggle="tooltip" data-placement="top" '
                . 'title="'
                . _('Approve MAC')
                . '">'
                . '<i class="icon fa fa-check-circle"></i>'
                . '</a>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($MAC);
        }
        $pending = ob_get_clean();
        if ($pending) {
            $pending .= '<div class="addrow">'
                . '<div class="col-xs-10">'
                . _('Approve all pending? ')
                . '<a href="'
                . $this->formAction
                . '&approveAll=1" '
                . 'data-toggle="tooltip" data-placement="top" '
                . 'title="'
                . _('Approve all pending macs')
                . '">'
                . '<i class="icon fa fa-check-circle"></i>'
                . '</a>'
                . '</div>'
                . '</div>';
        }
        $imageSelect = self::getClass('ImageManager')
            ->buildSelectBox(
                filter_input(INPUT_POST, 'image') ?: $this->obj->get('imageID')
            );

        // Either use the passed in or get the objects info.
        $name = (
            filter_input(INPUT_POST, 'name') ?: $this->obj->get('name')
        );
        $mac = (
            filter_input(INPUT_POST, 'mac') ?: $this->obj->get('mac')
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
            } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
                $productKey = $productKeytest;
            }
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
        $fields = array(
            '<label for="name">'
            . _('Host Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="host" value="'
            . $name
            . '" maxlength="15" class="hostname-input form-control" '
            . 'id="name" required/>'
            . '</div>',
            '<label for="mac">'
            . _('Primary MAC')
            . '</label>' => '<div class="col-xs-10">'
            . '<div class="input-group">'
            . '<span class="mac-manufactor input-group-addon"></span>'
            . '<input type="text" class="macaddr form-control" '
            . 'name="mac" '
            . 'value="'
            . $mac
            . '" id="mac" '
            . 'maxlength="17" required/>'
            . '<span class="icon add-mac fa fa-plus-circle hand '
            . 'input-group-addon" '
            . 'data-toggle="tooltip" data-placement="top" title="'
            . _('Add MAC')
            . '"></span>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-1">'
            . '<div class="row">'
            . '<span data-toggle="tooltip" data-placement="top" '
            . 'title="'
            . _('Ignore MAC on Client')
            . '" class="hand">'
            . _('I.M.C.')
            . '</span>'
            . '</div>'
            . '<div class="checkbox">'
            . '<label>'
            . '<input type="checkbox" name="igclient[]" value="'
            . $mac
            . '"'
            . $this->obj->clientMacCheck()
            . '/>'
            . '</label>'
            . '</div>'
            . '</div>'
            . '<div class="col-xs-1">'
            . '<div class="row">'
            . '<span data-toggle="tooltip" data-placement="top" '
            . 'title="'
            . _('Ignore MAC on Image')
            . '" class="hand">'
            . _('I.M.I.')
            . '</span>'
            . '</div>'
            . '<div class="checkbox">'
            . '<label>'
            . '<input type="checkbox" name="igimage[]" value="'
            . $mac
            . '"'
            . $this->obj->imageMacCheck()
            . '/>'
            . '</label>'
            . '</div>'
            . '</div>'
            . '</div>',
            '<div class="additionalMACsRow">'
            . '<label>'
            . _('Additional MACs')
            . '</label>'
            . '</div>' => '<div class="additionalMACsCell">'
            . $addMACs
            . '</div>',
            '<div class="pendingMACsRow">'
            . '<label>'
            . _('Pending MACs')
            . '</label>'
            . '</div>' => '<div class="pendingMACsCell">'
            . $pending
            . '</div>',
            '<label for="description">'
            . _('Host description')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control" id="description" '
            . 'name="description">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="productKey">'
            . _('Host Product Key')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="key" value="'
            . $productKey
            . '" id="productKey" class="form-control"/>'
            . '</div>',
            '<label for="image">'
            . _('Host Image')
            . '</label>' => $imageSelect,
            '<label for="kern">'
            . _('Host Kernel')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="kern" id="kern" '
            . 'class="form-control" value="'
            . $kern
            . '"/>'
            . '</div>',
            '<label for="args">'
            . _('Host Kernel Arguments')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="args" id="args" '
            . 'class="form-control" value="'
            . $args
            . '"/>'
            . '</div>',
            '<label for="init">'
            . _('Host Init')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="init" id="init" '
            . 'class="form-control" value="'
            . $init
            . '"/>'
            . '</div>',
            '<label for="dev">'
            . _('Host Primary Disk')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="dev" id="dev" '
            . 'class="form-control" value="'
            . $dev
            . '"/>'
            . '</div>',
            '<label for="bootTypeExit">'
            . _('Host Bios Exit Type')
            . '</label>' => $this->exitNorm,
            '<label for="efiBootTypeExit">'
            . _('Host EFI Exit Type')
            . '</label>' => $this->exitEfi,
            '<label for="updategen">'
            . _('Make Changes?')
           . '</label>' => '<button type="submit" class="btn btn-info btn-block" '
           . 'id="updategen">'
           . _('Update')
           . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'HOST_FIELDS',
                array(
                    'fields' => &$fields,
                    'Host' => &$this->obj
                )
            );
        array_walk($fields, $this->fieldsToData);
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
        if ($this->obj->get('pub_key')
            || $this->obj->get('sec_tok')
        ) {
            $this->form = '<div class="text-center" id="resetSecDataBox">'
                . '<button type="button" '
                . 'id="resetSecData" '
                . 'class="btn btn-warning btn-block">'
                . _('Reset Encryption Data')
                . '</button>'
                . '</div>';
        }
        echo '<!-- General -->';
        echo '<div id="host-general" class="'
            . 'tab-pane fade in active">';
        echo '<div class="addrowempty hiddeninitially">';
        echo '<div class="col-xs-10">';
        echo '<div class="input-group">';
        echo '<span class="mac-manufactor input-group-addon"></span>';
        echo '<input type="text" class="macaddr additionalMAC form-control" '
            . 'name="additionalMACs[]" '
            . 'value="'
            . '" maxlength="17"/>';
        echo '<span class="icon remove-mac fa fa-minus-circle hand '
            . 'input-group-addon" '
            . 'data-toggle="tooltip" data-placement="top" '
            . 'title="'
            . _('Remove MAC')
            . '"></span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-xs-1">';
        echo '<div class="row">';
        echo '<span data-toggle="tooltip" data-placement="top" '
            . 'title="'
            . _('Ignore MAC on Client')
            . '" class="hand">'
            . _('I.M.C.')
            . '</span>';
        echo '</div>';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="igclient[]" value="'
            . '"'
            . '/>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-xs-1">';
        echo '<div class="row">';
        echo '<span data-toggle="tooltip" data-placement="top" '
            . 'title="'
            . _('Ignore MAC on Image')
            . '" class="hand">'
            . _('I.M.I.')
            . '</span>';
        echo '</div>';
        echo '<div class="checkbox">';
        echo '<label>';
        echo '<input type="checkbox" name="igimage[]" value="'
            . '"'
            . '/>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host general');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" '
            . 'action="'
            . $this->formAction
            . '&tab=host-general">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Host general post update.
     *
     * @return void
     */
    public function hostGeneralPost()
    {
        $name = trim(
            filter_input(INPUT_POST, 'host')
        );
        $mac = trim(
            filter_input(INPUT_POST, 'mac')
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
        if (empty($name)) {
            throw new Exception(_('Please enter a hostname'));
        }
        if ($name != $this->obj->get('name')
        ) {
            if (!$this->obj->isHostnameSafe($name)) {
                throw new Exception(_('Please enter a valid hostname'));
            }
            if ($this->obj->getManager()->exists($name)) {
                throw new Exception(_('Please use another hostname'));
            }
        }
        if (empty($mac)) {
            throw new Exception(_('Please enter a mac address'));
        }
        $mac = self::parseMacList($mac);
        if (count($mac) < 1) {
            throw new Exception(_('Please enter a valid mac address'));
        }
        $mac = array_shift($mac);
        if (!$mac->isValid()) {
            throw new Exception(_('Please enter a valid mac address'));
        }
        $Task = $this->obj->get('task');
        if ($Task->isValid()
            && $imageID != $this->obj->get('imageID')
        ) {
            throw new Exception(_('Cannot change image when in tasking'));
        }
        $this
            ->obj
            ->set('name', $name)
            ->set('description', $desc)
            ->set('imageID', $imageID)
            ->set('kernel', $kern)
            ->set('kernelArgs', $args)
            ->set('kernelDevice', $dev)
            ->set('init', $init)
            ->set('biosexit', $bte)
            ->set('efiexit', $ebte)
            ->set('productKey', $productKey);
        $primac = $this->obj->get('mac')->__toString();
        $setmac = $mac->__toString();
        if ($primac != $setmac) {
            $this->obj->addPriMAC($mac->__toString());
        }
        $addMACs = filter_input_array(
            INPUT_POST,
            array(
                'additionalMACs' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $addMACs = $addMACs['additionalMACs'];
        $addmacs = self::parseMacList($addMACs);
        unset($addMACs);
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
    }
    /**
     * Host printers display.
     *
     * @return void
     */
    public function hostPrinters()
    {
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->form,
            $this->data
        );
        $this->headerData = array(
            '<label for="toggler1">'
            . '<input type="checkbox" name="toggle-checkboxprint" class='
            . '"toggle-checkboxprint" id="toggler1"/></label>',
            _('Printer Alias'),
            _('Printer Type')
        );
        $this->templates = array(
            '<label for="printer-${printer_id}">'
            . '<input type="checkbox" name="printer[]" class='
            . '"toggle-print"${is_default} id="printer-${printer_id}" '
            . 'value="${printer_id}"/></label>',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(),
            array()
        );
        Route::listem('printer');
        $Printers = json_decode(
            Route::getData()
        );
        $Printers = $Printers->printers;
        foreach ((array)$Printers as &$Printer) {
            if (!in_array($Printer->id, $this->obj->get('printersnotinme'))) {
                continue;
            }
            $this->data[] = array(
                'printer_id' => $Printer->id,
                'is_default' => (
                    $this->obj->getDefault($Printer->id) ?
                    ' checked' :
                    ''
                ),
                'printer_name' => $Printer->name,
                'printer_type' => (
                    stripos($Printer->config, 'local') !== false ?
                    _('TCP/IP') :
                    $Printer->config
                )
            );
            unset($Printer);
        }
        echo '<!-- Printers -->';
        echo '<div class="tab-pane fade" id="host-printers">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Printers');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-printers">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host printer configuration');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<h5 class="title text-center">';
        echo _('Select management level for this host');
        echo '</h5>';
        echo '<div class="col-xs-offset-4">';
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
                $this->obj->get('printerLevel') == 0 ?
                ' checked' :
                ''
            )
            . '/>';
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
                $this->obj->get('printerLevel') == 1 ?
                ' checked' :
                ''
            )
            . '/>';
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
                $this->obj->get('printerLevel') == 2 ?
                ' checked' :
                ''
            )
            . '/>';
        echo _('Only Assigned Printers');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<br/>';
        echo '<div class="form-group">';
        echo '<label for="levelup" class="control-label col-xs-4">';
        echo _('Update printer configuration');
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<button type="submit" name="levelup" class='
            . '"btn btn-info btn-block" id="levelup">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        if (is_array($this->data) && count($this->data) > 0) {
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
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="hostPrinterShow">';
            echo '<input type="checkbox" name="hostPrinterShow" '
                . 'id="hostPrinterShow"/>';
            echo _('Check here to see what printers can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially printerNotInHost panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Printers');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updateprinters" class="control-label col-xs-4">';
            echo _('Add selected printers');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="updateprinters" class='
                . '"btn btn-info btn-block" id="updateprinters" value="1">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler2">'
            . '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler2"/></label>',
            _('Default'),
            _('Printer Alias'),
            _('Printer Type')
        );
        $this->templates = array(
            '<label for="printerrm-${printer_id}">'
            . '<input type="checkbox" name="printerRemove[]" class='
            . '"toggle-action" id="printerrm-${printer_id}" '
            . 'value="${printer_id}"/></label>',
            '<div class="radio">'
            . '<input type="radio" class="default" '
            . 'name="default" id="printer${printer_id}" '
            . 'value="${printer_id}" ${is_default}/>'
            . '<label for="printer${printer_id}">'
            . '</label>'
            . '</div>',
            '<a href="?node=printer&sub=edit&id=${printer_id}">${printer_name}</a>',
            '${printer_type}'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false col-xs-1'
            ),
            array(
                'class' => 'filter-false col-xs-1'
            ),
            array(),
            array()
        );
        foreach ((array)$Printers as $Printer) {
            if (!in_array($Printer->id, $this->obj->get('printers'))) {
                continue;
            }
            $this->data[] = array(
                'printer_id' => $Printer->id,
                'is_default' => (
                    $this->obj->getDefault($Printer->id) ?
                    ' checked' :
                    ''
                ),
                'printer_name' => $Printer->name,
                'printer_type' => (
                    stripos($Printer->config, 'local') !== false ?
                    _('TCP/IP') :
                    $Printer->config
                )
            );
            unset($Printer);
        }
        if (is_array($this->data) && count($this->data) > 0) {
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
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Update/Remove printers');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="defaultsel" class="control-label col-xs-4">';
            echo _('Update default printer');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="defaultsel" class='
                . '"btn btn-info btn-block" id="defaultsel">'
                . _('Update')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="form-group">';
            echo '<label for="printdel" class="control-label col-xs-4">';
            echo _('Remove selected printers');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="printdel" class='
                . '"btn btn-danger btn-block" id="printdel">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->form,
            $this->data
        );
    }
    /**
     * Host snapins.
     *
     * @return void
     */
    public function hostSnapins()
    {
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->form,
            $this->data
        );
        $this->headerData = array(
            '<label for="toggler3">'
            . '<input type="checkbox" name="toggle-checkboxsnapin" class='
            . '"toggle-checkboxsnapin" id="toggler3"/></label>',
            _('Snapin Name'),
            _('Snapin Created')
        );
        $this->templates = array(
            '<label for="snapin-${snapin_id}">'
            . '<input type="checkbox" name="snapin[]" class='
            . '"toggle-snapin" id="snapin-${snapin_id}" '
            . 'value="${snapin_id}"/></label>',
            '<a href="?node=snapin&sub=edit&id=${snapin_id}">${snapin_name}</a>',
            '${snapin_created}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(),
            array()
        );
        Route::listem('snapin');
        $Snapins = json_decode(
            Route::getData()
        );
        $Snapins = $Snapins->snapins;
        foreach ((array)$Snapins as &$Snapin) {
            if (!in_array($Snapin->id, $this->obj->get('snapinsnotinme'))) {
                continue;
            }
            $this->data[] = array(
                'snapin_id' => $Snapin->id,
                'snapin_name' => $Snapin->name,
                'snapin_created' => self::niceDate(
                    $Snapin->createdTime
                )->format('Y-m-d H:i:s')
            );
            unset($Snapin);
        }
        echo '<!-- Snapins -->';
        echo '<div id="host-snapins" class="tab-pane fade">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Snapins');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-snapins">';
        if (is_array($this->data) && count($this->data) > 0) {
            self::$HookManager
                ->processEvent(
                    'HOST_ADD_SNAPIN',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="hostSnapinShow">';
            echo '<input type="checkbox" name="hostSnapinShow" '
                . 'id="hostSnapinShow"/>';
            echo _('Check here to see what snapins can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially snapinNotInHost panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Snapins');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updatesnapins" class="control-label col-xs-4">';
            echo _('Add selected snapins');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="updatesnapins" class='
                . '"btn btn-info btn-block" id="updatesnapins">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->form,
            $this->data
        );
        $this->headerData = array(
            '<label for="toggler4">'
            . '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler4"/></label>',
            _('Snapin Name'),
            _('Snapin Created')
        );
        $this->templates = array(
            '<label for="snapinrm-${snapin_id}">'
            . '<input type="checkbox" name="snapinRemove[]" class='
            . '"toggle-action" id="snapinrm-${snapin_id}" '
            . 'value="${snapin_id}"/></label>',
            '<a href="?node=snapin&sub=edit&id=${snapin_id}">${snapin_name}</a>',
            '${snapin_created}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(),
            array()
        );
        foreach ((array)$Snapins as $Snapin) {
            if (!in_array($Snapin->id, $this->obj->get('snapins'))) {
                continue;
            }
            $this->data[] = array(
                'snapin_id' => $Snapin->id,
                'snapin_name' => $Snapin->name,
                'snapin_created' => self::niceDate(
                    $Snapin->createdTime
                )->format('Y-m-d H:i:s')
            );
            unset($Snapin);
        }
        if (is_array($this->data) && count($this->data) > 0) {
            self::$HookManager
                ->processEvent(
                    'HOST_EDIT_SNAPIN',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Remove snapins');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="snapdel" class="control-label col-xs-4">';
            echo _('Remove selected snapins');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="snapdel" class='
                . '"btn btn-danger btn-block" id="snapdel">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->headerData,
            $this->templates,
            $this->attributes,
            $this->form,
            $this->data
        );
    }
    /**
     * Display's the host service stuff
     *
     * @return void
     */
    public function hostService()
    {
        // Client module stuff
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
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
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-4 form-group'),
            array('class' => 'col-xs-4'),
        );
        $this->templates = array(
            '${mod_name}',
            '${input}',
            '${span}',
        );
        $this->data[] = array(
            'mod_name' => '<label for="checkAll">'
            . _('Select/Deselect All')
            . '</label>',
            'input' => '<div class="checkbox">'
            . '<input type="checkbox" class="checkboxes" '
            . 'id="checkAll" name="checkAll" value="checkAll"/>'
            . '</div>',
            'span' => ' '
        );
        $moduleName = self::getGlobalModuleStatus();
        $ModuleOn = $this->obj->get('modules');
        Route::listem('module');
        $Modules = json_decode(
            Route::getData()
        );
        $Modules = $Modules->modules;
        foreach ((array)$Modules as &$Module) {
            switch ($Module->shortName) {
            case 'dircleanup':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="%s"></i>',
                    $dcnote
                );
                break;
            case 'greenfog':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="%s"></i>',
                    $gfnote
                );
                break;
            case 'usercleanup':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="%s"></i>',
                    $ucnote
                );
                break;
            case 'clientupdater':
                $note = sprintf(
                    '<i class="icon fa fa-exclamation-triangle '
                    . 'fa-1x hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="%s"></i>',
                    $cunote
                );
                break;
            default:
                $note = '';
                break;
            }
            if ($note) {
                $note = '<div class="col-xs-2">'
                    . $note
                    . '</div>';
            }
            $this->data[] = array(
                'input' => sprintf(
                    '<div class="checkbox">'
                    . '<input id="%s"%stype="checkbox" name="modules[]" value="%s"'
                    . '%s%s/>'
                    . '</div>',
                    $Module->shortName,
                    (
                        ($moduleName[$Module->shortName]
                        || $moduleName[$Module->shortName])
                        && $Module->isDefault ?
                        ' class="checkboxes" ':
                        ''
                    ),
                    $Module->id,
                    (
                        in_array($Module->id, $ModuleOn) ?
                        ' checked' :
                        ''
                    ),
                    (
                        !$moduleName[$Module->shortName] ?
                        ' disabled' :
                        ''
                    ),
                    $Module->shortName
                ),
                'span' => sprintf(
                    '<div class="col-xs-2">'
                    . '<span class="icon fa fa-question fa-1x hand" '
                    . 'data-toggle="tooltip" data-placement="left" '
                    . 'title="%s"></span>'
                    . '</div>'
                    . '%s',
                    str_replace(
                        '"',
                        '\"',
                        $Module->description
                    ),
                    $note
                ),
                'mod_name' => '<label for="'
                . $Module->shortName
                . '">'
                . $Module->name
                . '</label>',
            );
            unset($Module);
        }
        unset($moduleName, $ModuleOn);
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
        echo '<!-- Service Configuration -->';
        echo '<div class="tab-pane fade" id="host-service">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host FOG Client Module configuration');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-service">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host module settings');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '<label class="control-label col-xs-4" for="updatestatus">';
        echo _('Update module configurations');
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<button type="submit" name="updatestatus" id="updatestatus" '
            . 'class="btn btn-info btn-block">';
        echo _('Update');
        echo '</button>';
        echo '<input type="hidden" name="modulesend" value="1"/>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array(
                'class' => 'col-xs-4'
            ),
            array(
                'class' => 'col-xs-4 form-group'
            ),
            array(
                'class' => 'col-xs-4'
            )
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
                    '<div class="input-group">'
                    . '<input type="number" id="%s" name="%s" value="%s" '
                    . 'class="form-control"/>'
                    . '</div>',
                    $name,
                    $name,
                    $this->obj->getDispVals($get[0])
                ),
                'span' => sprintf(
                    '<div class="col-xs-2">'
                    . '<span class="icon fa fa-question fa-1x hand" '
                    . 'data-toggle="tooltip" data-placement="right" '
                    . 'title="%s"></span>'
                    . '</div>',
                    $get[1]
                ),
                'field' => '<label for="'
                . $name
                . '">'
                . $get[2]
                . '</label>',
            );
            unset($get);
        }
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
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-service">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Screen Resolution');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '<label class="control-label col-xs-4" for="updatedisplay">';
        echo _('Update display resolution');
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<button type="submit" name="updatedisplay" id="updatedisplay" '
            . 'class="btn btn-info btn-block">';
        echo _('Update');
        echo '</button>';
        echo '<input type="hidden" name="dispupdate" value="1"/>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-4 form-group'),
            array('class' => 'col-xs-4')
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
            'field' => '<label for="tme">'
            . _('Auto Log Out Time (in minutes)')
            . '</label>',
            'input' => '<div class="input-group">'
            . '<input type="number" name="tme" value="${value}" class='
            . '"form-control" id="tme"/>'
            . '</div>',
            'desc' => '<div class="col-xs-2">'
            . '<span class="icon fa fa-question fa-1x hand" '
            . 'data-toggle="tooltip" data-placement="right" '
            . 'title="${serv_desc}"></span>'
            . '</div>',
            'value' => $this->obj->getAlo(),
            'serv_desc' => $alodesc,
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
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-service">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Auto Logout');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '<label class="control-label col-xs-4" for="updatealo">';
        echo _('Update auto-logout time');
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<button type="submit" name="updatealo" id="updatealo" '
            . 'class="btn btn-info btn-block">';
        echo _('Update');
        echo '</button>';
        echo '<input type="hidden" name="alosend" value="1"/>';
        echo '</div>';
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
     * Displays Host Inventory
     *
     * @return void
     */
    public function hostInventory()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
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
        $fields = array(
            '<label for="pu">'
            . _('Primary User')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" value="'
            . $puser
            . '" name="pu" id="pu"/>'
            . '</div>',
            '<label for="other1"/>'
            . _('Other Tag #1')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" value="'
            . $other1
            . '" name="other1" id="other1"/>'
            . '</div>',
            '<label for="other2"/>'
            . _('Other Tag #2')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" value="'
            . $other2
            . '" name="other2" id="other2"/>'
            . '</div>',
            _('System Manufacturer') => $sysman,
            _('System Product') => $sysprod,
            _('System Version') => $sysver,
            _('System Serial Number') => $sysser,
            _('System UUID') => $sysuuid,
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
            '<label for="updateinv">'
            . _('Make Changes?')
            . '</label>' => '<button name="update" type="submit" class="'
            . 'btn btn-info btn-block" id="updateinv">'
            . _('Update')
            . '</button>'
        );
        $this->title = _('Host Hardware Inventory');
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
        echo '<!-- Inventory -->';
        echo '<div class="tab-pane fade" id="host-hardware-inventory">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-hardware-inventory">';
        $this->render(12);
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
     * Display host virus information.
     *
     * @return void
     */
    public function hostVirus()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
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
                . 'class="delvid" name="delvidarr[]" value="${virus_id}"/>'
                . '<label for="${virus_id}" class="icon icon-hand" '
                . 'title="%s ${virus_name}">'
                . '<i class="icon fa fa-minus-circle link"></i>'
                . '</label>',
                _('Delete')
            ),
        );
        Route::listem(
            'virus',
            'name',
            false,
            array('mac' => $this->obj->getMyMacs())
        );
        $Viruses = json_decode(
            Route::getData()
        );
        $Viruses = $Viruses->viruss;
        foreach ((array)$Viruses as &$Virus) {
            if (!in_array($Virus->mac, $this->obj->getMyMacs())) {
                continue;
            }
            switch (strtolower($Virus->mode)) {
            case 'q':
                $mode = _('Quarantine');
                break;
            case 's':
                $mode = _('Report');
                break;
            default:
                $mode = _('N/A');
                break;
            }
            $this->data[] = array(
                'virus_name' => $Virus->name,
                'virus_file' => $Virus->file,
                'virus_mode' => $mode,
                'virus_date' => $Virus->date,
                'virus_id' => $Virus->id,
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
        $paneltype = 'info';
        if (is_array($this->data) && count($this->data) > 0) {
            $paneltype = 'warning';
        }
        echo '<!-- Virus -->';
        echo '<div class="tab-pane fade" id="host-virus-history">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Virus History');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=host-virus-history">';
        echo '<h4 class="title text-center">';
        echo '<a href="#">';
        echo '<div class="checkbox">';
        echo '<input type="checkbox" class="delvid" id="all" '
            . 'name="delvid" value="all"/>';
        echo '<label for="all">';
        echo _('Clear all history');
        echo '</label>';
        echo '</div>';
        echo '</a>';
        echo '</h4>';
        echo '<div class="panel panel-'
            . $paneltype
            . '">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Virus Report');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
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
        $dte = filter_input(INPUT_GET, 'dte');
        if (!$dte) {
            self::niceDate()->format('Y-m-d');
        }
        $Dates = self::getSubObjectIDs(
            'UserTracking',
            array(
                'id' => $this->obj->get('users')
            ),
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
            array(
                'hostID' => $this->obj->get('id'),
                'date' => $dte,
                'action' => array('', 0, 1)
            )
        );
        $UserLogins = json_decode(
            Route::getData()
        );
        $UserLogins = $UserLogins->usertrackings;
        $Data = array();
        foreach ((array)$UserLogins as &$UserLogin) {
            $time = self::niceDate(
                $UserLogin->datetime
            )->format('U');
            if (!isset($Data[$UserLogin->username])) {
                $Data[$UserLogin->username] = array();
            }
            if (array_key_exists('login', $Data[$UserLogin->username])) {
                if ($UserLogin->action > 0) {
                    $this->data[] = array(
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
                    );
                    $Data[$UserLogin->username] = array();
                }
            }
            if ($UserLogin->action > 0) {
                $Data[$UserLogin->username]['login'] = true;
                $this->data[] = array(
                    'action' => _('Login'),
                    'user_name' => $UserLogin->username,
                    'user_time' => (
                        self::niceDate()
                        ->setTimestamp($time)
                        ->format('Y-m-d H:i:s')
                    ),
                    'user_desc' => $UserLogin->description
                );
            } elseif ($UserLogin->action < 1) {
                $this->data[] = array(
                    'action' => _('Logout'),
                    'user_name' => $UserLogin->username,
                    'user_time' => (
                        self::niceDate()
                        ->setTimestamp($time)
                        ->format('Y-m-d H:i:s')
                    ),
                    'user_desc' => $UserLogin->description
                );
                $Data[$UserLogin->username] = array();
            }
            unset($UserLogin);
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
            . '&tab=host-login-history">';
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
        Route::listem(
            'imaginglog',
            'name',
            false,
            array('hostID' => $this->obj->get('id'))
        );
        $Logs = json_decode(
            Route::getData()
        );
        $Logs = $Logs->imaginglogs;
        $imgTypes = array(
            'up' => _('Capture'),
            'down' => _('Deploy'),
        );
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
                array(
                    'checkInTime' => $Log->start,
                    'hostID' => $this->obj->get('id')
                )
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
            if (in_array($typeName, array('up', 'down'))) {
                $typeName = $imgTypes[$typeName];
            }
            $stateName = $Task->state->name;
            unset($Task);
            $createdBy = (
                $Log->createdBy ?:
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
            $this->data[] = array(
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
            );
            unset($Image, $Log);
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
        $this->headerData = array(
            _('Snapin Name'),
            _('Start Time'),
            _('Complete'),
            _('Duration'),
            _('Return Code')
        );
        $this->templates = array(
            '${snapin_name}',
            '${snapin_start}',
            '${snapin_end}',
            '${snapin_duration}',
            '${snapin_return}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
            array()
        );
        $SnapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            array(
                'hostID' => $this->obj->get('id')
            )
        );
        $doneStates = array(
            self::getCompleteState(),
            self::getCancelledState()
        );
        Route::listem(
            'snapintask',
            'name',
            false,
            array('jobID' => $SnapinJobIDs)
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
            $this->data[] = array(
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
            );
            unset($SnapinTask);
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
        $approve = filter_input(INPUT_GET, 'approveHost');
        if ($approve) {
            $this
                ->obj
                ->set(
                    'pending',
                    0
                );
            if ($this->obj->save()) {
                self::setMessage(_('Host approved'));
            } else {
                self::setMessage(_('Host approval failed.'));
            }
            self::redirect(
                '?node='
                . $this->node
                . '&sub=edit&id='
                . $this->obj->get('id')
            );
        }
        if ($this->obj->get('pending')) {
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading">';
            echo '<h4 class="title">';
            echo _('Approve Host');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            echo '<a href="'
                . $this->formAction
                . '&approveHost=1">'
                . _('Approve this host?')
                . '</a>';
            echo '</div>';
            echo '</div>';
        }
        $confirmMac = filter_input(
            INPUT_GET,
            'confirmMAC'
        );
        $approveAll = filter_input(
            INPUT_GET,
            'approveAll'
        );
        if ($confirmMac) {
            try {
                $this->obj->addPendtoAdd($confirmMac);
                if ($this->obj->save()) {
                    $msg = _('MAC')
                        . ': '
                        . $confirmMac
                        . ' '
                        . _('Approved')
                        . '!';
                    self::setMessage($msg);
                    unset($msg);
                }
            } catch (Exception $e) {
                self::setMessage($e->getMessage());
            }
            self::redirect(
                '?node='
                . $this->node
                . '&sub=edit&id='
                . $this->obj->get('id')
            );
        } elseif ($approveAll) {
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
                    (int)$_POST['id']
                )
            );
        }
        echo '<div class="col-xs-9 tab-content">';
        $this->hostGeneral();
        if (!$this->obj->get('pending')) {
            $this->basictasksOptions();
        }
        $this->adFieldsToDisplay(
            $this->obj->get('useAD'),
            $this->obj->get('ADDomain'),
            $this->obj->get('ADOU'),
            $this->obj->get('ADUser'),
            '',
            $this->obj->get('ADPassLegacy'),
            $this->obj->get('enforce')
        );
        $this->hostPrinters();
        $this->hostSnapins();
        $this->hostService();
        $this->hostPMDisplay();
        $this->hostInventory();
        $this->hostVirus();
        $this->hostLoginHistory();
        $this->hostImageHistory();
        $this->hostSnapinHistory();
        self::$HookManager->processEvent(
            'HOST_EDIT_EXTRA',
            array(
                'Host' => &$this->obj,
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
                'formAction' => &$this->formAction,
                'render' => &$this
            )
        );
        echo '</div>';
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
        $passlegacy = trim(
            filter_input(
                INPUT_POST,
                'domainpasswordlegacy'
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
            $passlegacy,
            $productKey,
            $enforce
        );
    }
    /**
     * Host power management post.
     *
     * @return void
     */
    public function hostPMPost()
    {
        $onDemand = (int)isset($_POST['onDemand']);
        $items = array();
        $flags = array('flags' => FILTER_REQUIRE_ARRAY);
        if (isset($_POST['pmupdate'])) {
            $items = filter_input_array(
                INPUT_POST,
                array(
                    'scheduleCronMin' => $flags,
                    'scheduleCronHour' => $flags,
                    'scheduleCronDOM' => $flags,
                    'scheduleCronMonth' => $flags,
                    'scheduleCronDOW' => $flags,
                    'pmid' => $flags,
                    'action' => $flags
                )
            );
            extract($items);
            if (!$action) {
                throw new Exception(
                    _('You must select an action to perform')
                );
            }
            $items = array();
            foreach ((array)$pmid as $index => &$pm) {
                $onDemandItem = array_search(
                    $pm,
                    $onDemand
                );
                $items[] = array(
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
        if (isset($_POST['pmsubmit'])) {
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
                array(
                    'rempowermanagements' => $flags
                )
            );
            $pmid = $pmid['rempowermanagements'];
            self::getClass('PowerManagementManager')
                ->destroy(
                    array(
                        'id' => $pmid
                    )
                );
        }
    }
    /**
     * Host printer post.
     *
     * @return void
     */
    public function hostPrinterPost()
    {
        if (isset($_POST['levelup'])) {
            $this
                ->obj
                ->set(
                    'printerLevel',
                    filter_input(
                        INPUT_POST,
                        'level'
                    )
                );
        }
        if (isset($_POST['updateprinters'])) {
            $printers = filter_input_array(
                INPUT_POST,
                array(
                    'printer' => array(
                        'flags' => FILTER_REQUIRE_ARRAY
                    )
                )
            );
            $printers = $printers['printer'];
            if (count($printers) > 0) {
                $this
                    ->obj
                    ->addPrinter(
                        $printers
                    );
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
                array(
                    'printerRemove' => array(
                        'flags' => FILTER_REQUIRE_ARRAY
                    )
                )
            );
            $printers = $printers['printerRemove'];
            if (count($printers) > 0) {
                $this
                    ->obj
                    ->removePrinter(
                        $printers
                    );
            }
        }
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
                array(
                    'snapin' => array(
                        'flags' => FILTER_REQUIRE_ARRAY
                    )
                )
            );
            $snapins = $snapins['snapin'];
            if (count($snapins) > 0) {
                $this
                    ->obj
                    ->addSnapin($snapins);
            }
        }
        if (isset($_POST['snapdel'])) {
            $snapins = filter_input_array(
                INPUT_POST,
                array(
                    'snapinRemove' => array(
                        'flags' => FILTER_REQUIRE_ARRAY
                    )
                )
            );
            $snapins = $snapins['snapinRemove'];
            if (count($snapins) > 0) {
                $this
                    ->obj
                    ->removeSnapin(
                        $snapins
                    );
            }
        }
    }
    /**
     * Update the actual thing.
     *
     * @return void
     */
    public function hostServicePost()
    {
        $x = filter_input(INPUT_POST, 'x');
        $y = filter_input(INPUT_POST, 'y');
        $r = filter_input(INPUT_POST, 'r');
        $tme = filter_input(INPUT_POST, 'tme');
        $modOn = filter_input_array(
            INPUT_POST,
            array(
                'modules' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $modOn = $modOn['modules'];
        if (isset($_POST['modulesend'])) {
            $this->obj->set('modules', $modOn);
        }
        if (isset($_POST['dispupdate'])) {
            $this->obj->setDisp($x, $y, $r);
        }
        if (isset($_POST['alosend'])) {
            $this->obj->setAlo($tme);
        }
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
                $this->hostGeneralPost();
                break;
            case 'host-active-directory':
                $this->hostADPost();
                break;
            case 'host-powermanagement':
                $this->hostPMPost();
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
            case 'host-hardware-inventory':
                $pu = filter_input(INPUT_POST, 'pu');
                $other1 = filter_input(INPUT_POST, 'other1');
                $other2 = filter_input(INPUT_POST, 'other2');
                if (isset($_POST['update'])) {
                    $this->obj
                        ->get('inventory')
                        ->set('primaryUser', $pu)
                        ->set('other1', $other1)
                        ->set('other2', $other2)
                        ->set('hostID', $this->obj->get('id'))
                        ->save();
                }
                break;
            case 'host-login-history':
                $dte = filter_input(INPUT_POST, 'dte');
                self::redirect(
                    '?node='
                    . $this->node
                    . '&sub=edit&id='
                    . $this->obj->get('id')
                    . '&dte='
                    . $dte
                    . '#'
                    . $tab
                );
                break;
            case 'host-virus-history':
                $delvid = filter_input(INPUT_POST, 'delvid');
                $delvidarr = filter_input_array(
                    INPUT_POST,
                    array(
                        'delvidarr' => array(
                            'flags' => FILTER_REQUIRE_ARRAY
                        )
                    )
                );
                $delvidarr = $delvidarr['delvidarr'];
                if ($delvid == 'all') {
                    $this->obj->clearAVRecordsForHost();
                } else {
                    self::getClass('VirusManager')
                        ->destroy(
                            array(
                                'id' => $delvidarr
                            )
                        );
                }
                $msg = json_encode(
                    array('msg' => _('Virus items removed!'))
                );
                echo $msg;
                exit;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Host Update Failed'));
            }
            $this->obj->setAD();
            if ($tab == 'host-general') {
                $igstuff = filter_input_array(
                    INPUT_POST,
                    array(
                        'igimage' => array(
                            'flags' => FILTER_REQUIRE_ARRAY
                        ),
                        'igclient' => array(
                            'flags' => FILTER_REQUIRE_ARRAY
                        )
                    )
                );
                $igimage = $igstuff['igimage'];
                $igclient = $igstuff['igclient'];
                $this->obj->ignore($igimage, $igclient);
            }
            $hook = 'HOST_EDIT_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Host updated!'),
                    'title' => _('Host Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'HOST_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Host Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Host' => &$this->obj)
            );
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
                throw new Exception(_('Failed to create new Group'));
            }
            $msg = json_encode(
                array(
                    'msg' => _('Successfully added selected hosts to the group!'),
                    'title' => _('Host Add to Group Success')
                )
            );
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Host Add to Group Fail')
                )
            );
        }
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
        Route::listem(
            'UserTracking',
            'name',
            'ASC',
            array(
                'hostID' => $this->obj->get('id'),
                'action' => array('', 0, 1)
            )
        );
        $UserTracks = json_decode(
            Route::getData()
        );
        $UserTracks = $UserTracks->usertrackings;
        $data = null;
        $Data = array();
        foreach ((array)$UserTracks as &$Login) {
            $ldate = self::niceDate($Login->date)
                ->format('Y-m-d');
            if ($Login->hostID != $this->obj->get('id')
                || $ldate != $date
                || !in_array($Login->action, array('', 0, 1))
            ) {
                continue;
            }
            $time = self::niceDate($Login->datetime);
            $Data[$Login->username] = array(
                'user' => $Login->username,
                'min' => $MainDate,
                'max' => $MainDate_1
            );
            if (array_key_exists('login', $Data[$Login->username])) {
                if ($Login->action > 0) {
                    $Data[$Login->username]['logout'] = (int)$time - 1;
                    $data[] = $Data[$Login->username];
                } elseif ($Login->action < 1) {
                    $Data[$Login->username]['logout'] = (int)$time;
                    $data[] = $Data[$Login->username];
                }
                $Data[$Login->username] = array(
                    'user' => $Login->username,
                    'min' => $MainDate,
                    'max' => $MainDate_1
                );
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
}
