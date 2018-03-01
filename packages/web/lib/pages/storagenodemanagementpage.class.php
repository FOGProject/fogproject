<?php
/**
 * Displays the storage node information.
 *
 * PHP version 5
 *
 * @category StorageNodeManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays the storage node information.
 *
 * @category StorageNodeManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageNodeManagementPage extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'storagenode';
    /**
     * Initializes the storage node class.
     *
     * @param string $name The name to load this as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Storage Node Management');
        parent::__construct($this->name);
        $this->headerData = [
            self::$foglang['SN'],
            self::$foglang['SG'],
            self::$foglang['Enabled'],
            self::$foglang['MasterNode'],
            _('Max Clients')
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
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Page to enable creating a new storage node.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Storage Node');
        $storagenode = filter_input(
            INPUT_POST,
            'storagenode'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $ip = filter_input(
            INPUT_POST,
            'ip'
        );
        $webroot = filter_input(
            INPUT_POST,
            'webroot'
        ) ?: '/fog';
        $maxClients = (int)filter_input(
            INPUT_POST,
            'maxClients'
        ) ?: 10;
        $isMaster = isset($_POST['isMaster']) ? ' checked' : '';
        $bandwidth = filter_input(
            INPUT_POST,
            'bandwidth'
        );
        $storagegroupID = (int)filter_input(
            INPUT_POST,
            'storagegroupID'
        );
        if (!$storagegroupID) {
            $storagegroupID = @min(
                self::getSubObjectIDs('StorageGroup')
            );
        }
        $path = filter_input(
            INPUT_POST,
            'path'
        ) ?: '/images/';
        $ftppath = filter_input(
            INPUT_POST,
            'ftppath'
        ) ?: '/images/';
        $snapinpath = filter_input(
            INPUT_POST,
            'snapinppath'
        ) ?: '/opt/fog/snapins/';
        $sslpath = filter_input(
            INPUT_POST,
            'sslpath'
        ) ?: '/opt/fog/snapins/ssl/';
        $bitrate = filter_input(
            INPUT_POST,
            'bitrate'
        );
        $interface = filter_input(
            INPUT_POST,
            'interface'
        );
        $user = filter_input(
            INPUT_POST,
            'user'
        );
        $pass = filter_input(
            INPUT_POST,
            'pass'
        );
        $labelClass = 'col-sm-2 control-label';
        $fields = [
            // Basic information
            self::makeLabel(
                $labelClass,
                'storagenode',
                _('Storage Node Name')
            ) => self::makeInput(
                'form-control storagenodename-input',
                'storagenode',
                _('Storage Node Name'),
                'text',
                'storagenode',
                $storagenode,
                true,
                false
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Storage Node Description')
            ) => self::makeTextarea(
                'form-control storeagenodedescription-input',
                'description',
                _('Storage Node Description'),
                'description',
                $description,
                false,
                false
            ),
            // Node information
            self::makeLabel(
                $labelClass,
                'storagegroupID',
                _('Storage Group')
            ) => self::getClass('StorageGroupManager')
            ->buildSelectBox(
                $storagegroupID,
                'storagegroupID'
            ),
            self::makeLabel(
                $labelClass,
                'ip',
                _('Storage Node IP')
            ) => self::makeInput(
                'form-control storagenodeip-input',
                'ip',
                '127.0.0.1',
                'text',
                'ip',
                $ip,
                true,
                false,
                -1,
                -1,
                'data-inputmask="\'alias\': \'ip\'"'
            ),
            self::makeLabel(
                $labelClass,
                'webroot',
                _('Storage Node Web Root')
            ) => self::makeInput(
                'form-control storagenodewebroot-input',
                'webroot',
                _('Storage Node Web Root'),
                'text',
                'webroot',
                $webroot,
                true
            ),
            self::makeLabel(
                $labelClass,
                'maxClients',
                _('Storage Node Max Clients')
            ) => self::makeInput(
                'form-control storagenodemaxclients-input',
                'maxClients',
                '',
                'number',
                'maxClients',
                $maxClients
            ),
            // Node Checkboxes
            self::makeLabel(
                $labelClass,
                'isMaster',
                _('Storage Node Master')
            ) => self::makeInput(
                'storagenodeismaster-input',
                'isMaster',
                '',
                'checkbox',
                'isMaster',
                '',
                false,
                false,
                -1,
                -1,
                $isMaster
            ),
            self::makeLabel(
                $labelClass,
                'isEnabled',
                _('Storage Node Enabled')
            ) => self::makeInput(
                'storagenodeisenabled-input',
                'isEnabled',
                '',
                'checkbox',
                'isEnabled',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'isGraphEnabled',
                _('Graph Enabled')
                . '<br/>('
                . _('On Dashboard')
                . ')'
            ) => self::makeInput(
                'storagenodeisgraphenabled-input',
                'isGraphEnabled',
                '',
                'checkbox',
                'isGraphEnabled',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            // Bandwidth/Network Limiting
            self::makeLabel(
                $labelClass,
                'interface',
                _('Network Interface')
            ) => self::makeInput(
                'form-control storagenodeinterface-input',
                'interface',
                'eth0',
                'text',
                'interface',
                $interface
            ),
            self::makeLabel(
                $labelClass,
                'bandwidth',
                self::$foglang['BandwidthReplication']
                . '<br/>('
                . _('Kbps')
                . ')'
            ) => self::makeInput(
                'form-control storagenodebandwidth-input',
                'bandwidth',
                '0',
                'number',
                'bandwidth',
                $bandwidth
            ),
            self::makeLabel(
                $labelClass,
                'bitrate',
                _('Multicast Bitrate')
            ) => self::makeInput(
                'form-control storagenodebitrate-input',
                'bitrate',
                '100m',
                'text',
                'bitrate',
                $bitrate
            ),
            // Node Path Locations
            '<label class="col-sm-2 control-label" for="path">'
            . _('Image Path')
            . '</label>' => '<input type="text" name="path" '
            . 'value="'
            . $path
            . '" class="storagenodepath-input form-control" '
            . 'id="path" required/>',
            '<label class="col-sm-2 control-label" for="ftppath">'
            . _('FTP Path')
            . '</label>' => '<input type="text" name="ftppath" '
            . 'value="'
            . $ftppath
            . '" class="storagenodeftppath-input form-control" '
            . 'id="ftppath" required/>',
            '<label class="col-sm-2 control-label" for="snapinpath">'
            . _('Snapin Path')
            . '</label>' => '<input type="text" name="snapinpath" '
            . 'value="'
            . $snapinpath
            . '" class="storagenodesnapinpath-input form-control" '
            . 'id="snapinpath" required/>',
            '<label class="col-sm-2 control-label" for="sslpath">'
            . self::$foglang['SSLPath']
            . '</label>' => '<input type="text" name="sslpath" '
            . 'value="'
            . $sslpath
            . '" class="storagenodesslpath-input form-control" '
            . 'id="sslpath" required/>',
            // Node FTP User/Password
            self::makeLabel(
                $labelClass,
                'user',
                _('Storage Node FTP User')
            ) => self::makeInput(
                'form-control storagenodeuser-input',
                'user',
                'fog',
                'text',
                'user',
                $user,
                true
            ),
            self::makeLabel(
                $labelClass,
                'pass',
                _('Storage Node FTP Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control storagenodepass-input',
                'pass',
                _('Password'),
                'password',
                'pass',
                $pass,
                true
            )
            . '</div>',
        ];
        self::$HookManager
            ->processEvent(
                'STORAGENODE_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'StorageNode' => self::getClass('StorageNode')
                ]
            );
        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="storagenode-create">';
        echo self::makeFormTag(
            'form-horizontal',
            'storagenode-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box-body">';
        echo '<!-- Storage Node -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Storage Node');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Actually save the new node.
     *
     * @return void
     */
    public function addPost()
    {
        // Setup and filter our vars.
        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $ip = filter_input(INPUT_POST, 'ip');
        $maxClients = filter_input(INPUT_POST, 'maxClients');
        $interface = filter_input(INPUT_POST, 'interface');
        $user = filter_input(INPUT_POST, 'user');
        $pass = filter_input(INPUT_POST, 'pass');
        $bandwidth = filter_input(INPUT_POST, 'bandwidth');
        $desc = filter_input(INPUT_POST, 'description');
        $webroot = filter_input(INPUT_POST, 'webroot');
        $isen = (int)isset($_POST['isEnabled']);
        $isgren = (int)isset($_POST['isGraphEnabled']);
        $isMaster = (int)isset($_POST['isMaster']);
        $storagegroupID = filter_input(INPUT_POST, 'storagegroupID');
        $path = filter_input(INPUT_POST, 'path');
        $ftppath = filter_input(INPUT_POST, 'ftppath');
        $snapinpath = filter_input(INPUT_POST, 'snapinpath');
        $sslpath = filter_input(INPUT_POST, 'sslpath');
        $bitrate = filter_input(INPUT_POST, 'bitrate');
        self::$HookManager->processEvent('STORAGE_NODE_ADD_POST');
        try {
            if (empty($name)) {
                throw new Exception(self::$foglang['StorageNameRequired']);
            }
            if (self::getClass('StorageNodeManager')->exists($name)) {
                throw new Exception(self::$foglang['StorageNameExists']);
            }
            if (empty($ip)) {
                throw new Exception(self::$foglang['StorageIPRequired']);
            }
            if (empty($maxClients)) {
                throw new Exception(self::$foglang['StorageClientsRequired']);
            }
            if (empty($interface)) {
                throw new Exception(self::$foglang['StorageIntRequired']);
            }
            if (empty($user)) {
                throw new Exception(self::$foglang['StorageUserRequired']);
            }
            if (empty($pass)) {
                throw new Exception(self::$foglang['StoragePassRequired']);
            }
            if (is_numeric($bandwidth)) {
                if ($bandwidth < 0) {
                    throw new Exception(
                        _('Bandwidth should be numeric and greater than 0')
                    );
                }
            } else {
                $bandwidth = '';
            }
            $StorageNode = self::getClass('StorageNode')
                ->set('name', $name)
                ->set('description', $desc)
                ->set('ip', $ip)
                ->set('webroot', $webroot)
                ->set('maxClients', $maxClients)
                ->set('isMaster', $isMaster)
                ->set('storagegroupID', $storagegroupID)
                ->set('path', $path)
                ->set('ftppath', $ftppath)
                ->set('snapinpath', $snapinpath)
                ->set('sslpath', $sslpath)
                ->set('bitrate', $bitrate)
                ->set('interface', $interface)
                ->set('isGraphEnabled', $isgren)
                ->set('isEnabled', $isen)
                ->set('user', $user)
                ->set('pass', $pass)
                ->set('bandwidth', $bandwidth);
            if (!$StorageNode->save()) {
                throw new Exception(_('Add storage node failed!'));
            }
            if ($StorageNode->get('isMaster')) {
                $masternodes = self::getSubObjectIDs(
                    'StorageNode',
                    [
                        'isMaster' => 1,
                        'storagegroupID' => $StorageNode->get('storagegroupID')
                    ]
                );
                self::getClass('StorageNodeManager')
                    ->update(
                        [
                            'id' => array_diff(
                                (array)$StorageNode->get('id'),
                                (array)$masternodes
                            )
                        ],
                        '',
                        ['isMaster' => 0]
                    );
            }
            $hook = 'STORAGE_NODE_ADD_SCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Node added!'),
                    'title' => _('Storage Node Create Success')
                ]
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_NODE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Create Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'StorageNode' => &$StorageNode,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        unset($StorageNode);
        echo $msg;
        exit;
    }
    /**
     * Storage Node General
     *
     * @return void
     */
    public function storagenodeGeneral()
    {
        // Post Fields
        $name = (
            filter_input(
                INPUT_POST,
                'name'
            ) ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(
                INPUT_POST,
                'description'
            ) ?:
            $this->obj->get('description')
        );
        $ip = (
            filter_input(
                INPUT_POST,
                'ip'
            ) ?:
            $this->obj->get('ip')
        );
        $webroot = (
            filter_input(
                INPUT_POST,
                'webroot'
            ) ?:
            $this->obj->get('webroot')
        );
        $maxClients = (
            (int)filter_input(
                INPUT_POST,
                'maxClients'
            ) ?:
            $this->obj->get('maxClients')
        );
        $bandwidth = (
            filter_input(
                INPUT_POST,
                'bandwidth'
            ) ?:
            $this->obj->get('bandwidth')
        );
        $storagegroupID = (
            (int)filter_input(
                INPUT_POST,
                'storagegroupID'
            ) ?:
            $this->obj->get('storagegroupID')
        );
        $path = (
            filter_input(
                INPUT_POST,
                'path'
            ) ?:
            $this->obj->get('path')
        );
        $ftppath = (
            filter_input(
                INPUT_POST,
                'ftppath'
            ) ?:
            $this->obj->get('ftppath')
        );
        $snapinpath = (
            filter_input(
                INPUT_POST,
                'snapinpath'
            ) ?:
            $this->obj->get('snapinpath')
        );
        $sslpath = (
            filter_input(
                INPUT_POST,
                'sslpath'
            ) ?:
            $this->obj->get('sslpath')
        );
        $bitrate = (
            filter_input(
                INPUT_POST,
                'bitrate'
            ) ?:
            $this->obj->get('bitrate')
        );
        $interface = (
            filter_input(
                INPUT_POST,
                'interface'
            ) ?:
            $this->obj->get('interface')
        );
        $user = (
            filter_input(
                INPUT_POST,
                'user'
            ) ?:
            $this->obj->get('user')
        );
        $pass = (
            filter_input(
                INPUT_POST,
                'pass'
            ) ?:
            $this->obj->get('pass')
        );
        $isgren = isset($_POST['isGraphEnabled']) ?:
            $this->obj->get('isGraphEnabled');
        $isen = isset($_POST['isEnabled']) ?:
            $this->obj->get('isEnabled');
        $ismaster = isset($_POST['isMaster']) ?:
            $this->obj->get('isMaster');
        if ($isgren) {
            $isgren = ' checked';
        } else {
            $isgren = '';
        }
        if ($isen) {
            $isen = ' checked';
        } else {
            $isen = '';
        }
        if ($ismaster) {
            $ismaster = ' checked';
        } else {
            $ismaster = '';
        }
        $fields = [
            '<label class="col-sm-2 control-label" for="name">'
            . _('Storage Node Name')
            . '</label>' => '<input type="text" name="name" '
            . 'value="'
            . $name
            . '" class="storagenodename-input form-control" '
            . 'id="name" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Storage Node Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>',
            '<label class="col-sm-2 control-label" for="ip">'
            . self::$foglang['IPAdr']
            . '</label>' => '<input type="text" name="ip" '
            . 'value="'
            . $ip
            . '" class="storagenodeip-input form-control" '
            . 'id="ip" required/>',
            '<label class="col-sm-2 control-label" for="webroot">'
            . _('Web Root')
            . '</label>' => '<input type="text" name="webroot" '
            . 'value="'
            . $webroot
            . '" class="storagenodewebroot-input form-control" '
            . 'id="webroot" required/>',
            '<label class="col-sm-2 control-label" for="maxClients">'
            . _('Max Clients')
            . '</label>' => '<input type="number" name="maxClients" '
            . 'value="'
            . $maxClients
            . '" class="storagenodemaxclients-input form-control" '
            . 'id="maxClients"/>',
            '<label class="col-sm-2 control-label" for="isMaster">'
            . _('Is Master Node')
            . '</label>' => '<input type="checkbox" name="isMaster" '
            . 'id="isMaster"'
            . $ismaster
            . '/>',
            '<label class="col-sm-2 control-label" for="bandwidth">'
            . self::$foglang['BandwidthReplication']
            . ' (Kbps)'
            . '</label>' => '<input type="number" name="bandwidth" '
            . 'value="'
            . $bandwidth
            . '" class="storagenodebandwidth-input form-control" '
            . 'id="bandwidth"/>',
            '<label class="col-sm-2 control-label" for="storagegroupID">'
            . _('Storage Group')
            . '</label>' => self::getClass('StorageGroupManager')->buildSelectBox(
                $storagegroupID,
                'storagegroupID'
            ),
            '<label class="col-sm-2 control-label" for="path">'
            . _('Image Path')
            . '</label>' => '<input type="text" name="path" '
            . 'value="'
            . $path
            . '" class="storagenodepath-input form-control" '
            . 'id="path" required/>',
            '<label class="col-sm-2 control-label" for="ftppath">'
            . _('FTP Path')
            . '</label>' => '<input type="text" name="ftppath" '
            . 'value="'
            . $ftppath
            . '" class="storagenodeftppath-input form-control" '
            . 'id="ftppath" required/>',
            '<label class="col-sm-2 control-label" for="snapinpath">'
            . _('Snapin Path')
            . '</label>' => '<input type="text" name="snapinpath" '
            . 'value="'
            . $snapinpath
            . '" class="storagenodesnapinpath-input form-control" '
            . 'id="snapinpath" required/>',
            '<label class="col-sm-2 control-label" for="sslpath">'
            . self::$foglang['SSLPath']
            . '</label>' => '<input type="text" name="sslpath" '
            . 'value="'
            . $sslpath
            . '" class="storagenodesslpath-input form-control" '
            . 'id="sslpath" required/>',
            '<label class="col-sm-2 control-label" for="bitrate">'
            . _('Bitrate')
            . '</label>' => '<input type="text" name="bitrate" '
            . 'value="'
            . $bitrate
            . '" class="storagenodebitrate-input form-control" '
            . 'id="bitrate"/>',
            '<label class="col-sm-2 control-label" for="interface">'
            . self::$foglang['Interface']
            . '</label>' => '<input type="text" name="interface" '
            . 'value="'
            . $interface
            . '" class="storagenodeinterface-input form-control" '
            . 'id="interface"/>',
            '<label class="col-sm-2 control-label" for="isen">'
            . self::$foglang['IsEnabled']
            . '</label>' => '<input type="checkbox" name="isEnabled" id="isen"'
            . $isen
            . '/>',
            '<label class="col-sm-2 control-label" for="isgren">'
            . self::$foglang['IsGraphEnabled']
            . '<br/>'
            . '('
            . self::$foglang['OnDash']
            . ')'
            . '</label>' => '<input type="checkbox" name="isGraphEnabled" '
            . 'id="isgren"'
            . $isgren
            . '/>',
            '<label class="col-sm-2 control-label" for="user">'
            . self::$foglang['ManUser']
            . '</label>' => '<input type="text" name="user" '
            . 'value="'
            . $user
            . '" class="storagenodeuser-input form-control" '
            . 'id="user" required/>',
            '<label class="col-sm-2 control-label" for="pass">'
            . self::$foglang['ManPass']
            . '</label>' => '<div class="input-group">'
            . '<input type="password" name="pass" '
            . 'value="'
            . $pass
            . '" class="storagenodepass-input form-control" '
            . 'id="pass" required/>'
            . '</div>'
        ];
        self::$HookManager
            ->processEvent(
                'STORAGENODE_GENERAL_FIELDS',
                [
                    'fields' => &$fields,
                    'StorageNode' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="storagenode-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('storagenode-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Storage node general post update.
     *
     * @return void
     */
    public function storagenodeGeneralPost()
    {
        // Setup and filter our vars.
        $name = filter_input(INPUT_POST, 'name');
        $ip = filter_input(INPUT_POST, 'ip');
        $maxClients = filter_input(INPUT_POST, 'maxClients');
        $interface = filter_input(INPUT_POST, 'interface');
        $user = filter_input(INPUT_POST, 'user');
        $pass = filter_input(INPUT_POST, 'pass');
        $bandwidth = filter_input(INPUT_POST, 'bandwidth');
        $desc = filter_input(INPUT_POST, 'description');
        $webroot = filter_input(INPUT_POST, 'webroot');
        $isen = (int)isset($_POST['isEnabled']);
        $isgren = (int)isset($_POST['isGraphEnabled']);
        $isMaster = (int)isset($_POST['isMaster']);
        $storagegroupID = filter_input(INPUT_POST, 'storagegroupID');
        $path = filter_input(INPUT_POST, 'path');
        $ftppath = filter_input(INPUT_POST, 'ftppath');
        $snapinpath = filter_input(INPUT_POST, 'snapinpath');
        $sslpath = filter_input(INPUT_POST, 'sslpath');
        $bitrate = filter_input(INPUT_POST, 'bitrate');
        if (!$name) {
            throw new Exception(self::$foglang['StorageNameRequired']);
        }
        $exists = self::getClass('StorageNodeManager')
            ->exists($name, $this->obj->get('id'));
        if ($this->obj->get('name') != $name
            && $exists
        ) {
            throw new Exception(self::$foglang['StorageNameExists']);
        }
        if (!$ip) {
            throw new Exception(self::$foglang['StorageIPRequired']);
        }
        if ($maxClients < 0) {
            throw new Exception(self::$foglang['StorageClientRequired']);
        }
        if (!$interface) {
            throw new Exception(self::$foglang['StorageIntRequired']);
        }
        if (!$user) {
            throw new Exception(self::$foglang['StorageUserRequired']);
        }
        if (!$pass) {
            throw new Exception(self::$foglang['StoragePassRequired']);
        }
        if (is_numeric($bandwidth)) {
            if ($bandwidth < 0) {
                throw new Exception(
                    _('Bandwidth should be numeric and greater than 0')
                );
            }
        } else {
            $bandwidth = '';
        }
        $this->obj
            ->set('name', $name)
            ->set('description', $desc)
            ->set('ip', $ip)
            ->set('webroot', $webroot)
            ->set('maxClients', $maxClients)
            ->set('isMaster', $isMaster)
            ->set('storagegroupID', $storagegroupID)
            ->set('path', $path)
            ->set('ftppath', $ftppath)
            ->set('snapinpath', $snapinpath)
            ->set('sslpath', $sslpath)
            ->set('bitrate', $bitrate)
            ->set('interface', $interface)
            ->set('isGraphEnabled', $isgren)
            ->set('isEnabled', $isen)
            ->set('user', $user)
            ->set('pass', $pass)
            ->set('bandwidth', $bandwidth);
    }
    /**
     * Edit existing nodes.
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
            'id' => 'storagenode-general',
            'generator' => function() {
                $this->storagenodeGeneral();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually store the edits.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager
            ->processEvent(
                'STORAGENODE_EDIT_POST',
                ['StorageNode' => &$this->obj]
            );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'storagenode-general':
                $this->storagenodeGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Storage Node Update Failed'));
            }
            if ($this->obj->get('isMaster')) {
                $masternodes = self::getSubObjectIDs(
                    'StorageNode',
                    [
                        'isMaster' => 1,
                        'storagegroupID' => $this->obj->get('storagegroupID')
                    ]
                );
                self::getClass('StorageNodeManager')
                    ->update(
                        [
                            'id' => array_diff(
                                (array)$this->obj->get('id'),
                                (array)$masternodes
                            )
                        ],
                        '',
                        ['isMaster' => 0]
                    );
            }
            $code = 201;
            $hook = 'STORAGENODE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Node updated!'),
                    'title' => _('Storage Node Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'STORAGENODE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'StorageNode' => &$this->obj,
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
