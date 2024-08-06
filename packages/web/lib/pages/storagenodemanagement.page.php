<?php
/**
 * Displays the storage node information.
 *
 * PHP version 5
 *
 * @category StorageNodeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays the storage node information.
 *
 * @category StorageNodeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageNodeManagement extends FOGPage
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

        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $description = filter_input(INPUT_POST, 'description');
        $ip = filter_input(INPUT_POST, 'ip');
        $webroot = filter_input(INPUT_POST, 'webroot') ?:
            '/fog';
        $maxClients = (int)filter_input(INPUT_POST, 'maxClients') ?:
            10;
        $isMaster = isset($_POST['isMaster']) ? ' checked' : '';
        $bandwidth = filter_input(INPUT_POST, 'bandwidth');
        $storagegroupID = (int)filter_input(INPUT_POST, 'storagegroupID');
        if (!$storagegroupID) {
            Route::ids('storagegroup', false);
            $storagegroupID = @min(json_decode(Route::getData(), true));
        }
        $path = filter_input(INPUT_POST, 'path') ?:
            '/images/';
        $ftppath = filter_input(INPUT_POST, 'ftppath') ?:
            '/images/';
        $snapinpath = filter_input(INPUT_POST, 'snapinppath') ?:
            '/opt/fog/snapins/';
        $sslpath = filter_input(INPUT_POST, 'sslpath') ?:
            '/opt/fog/snapins/ssl/';
        $bitrate = filter_input(INPUT_POST, 'bitrate');
        $helloInterval = (int)filter_input(INPUT_POST, 'helloInterval');
        $interface = filter_input(INPUT_POST, 'interface');
        $user = filter_input(INPUT_POST, 'user');
        $pass = filter_input(INPUT_POST, 'pass');
        $graphcolor = filter_input(INPUT_POST, 'graphcolor');

        $labelClass = 'col-sm-3 control-label';

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
                true
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
                true
            ),
            self::makeLabel(
                $labelClass,
                'webroot',
                _('Storage Node Web Root')
            ) => self::makeInput(
                'form-control storagenodewebroot-input',
                'webroot',
                '/fog',
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
            self::makeLabel(
                $labelClass,
                'graphcolor',
                _('Graph Color')
                . '<br/>('
                . _('On Dashboard')
                . ')'
            ) => self::makeInput(
                'jscolor {required:false} {refine: false} '
                    . 'form-control storagenodecolor-input',
                'graphcolor',
                'FFFFFF',
                'text',
                'graphcolor',
                $graphcolor
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
            self::makeLabel(
                $labelClass,
                'helloInterval',
                _('Re-Transmit Hello Interval')
            ) => self::makeInput(
                'form-control storagenodehellointerval-input',
                'helloInterval',
                '300',
                'number',
                'helloInterval',
                $helloInterval
            ),
            // Node Path Locations
            self::makeLabel(
                $labelClass,
                'path',
                _('Storage Node Image Path')
            ) => self::makeInput(
                'form-control storagenodeimagepath-input',
                'path',
                '/images/',
                'text',
                'path',
                $path,
                true
            ),
            self::makeLabel(
                $labelClass,
                'ftppath',
                _('Storage Node FTP Path')
            ) => self::makeInput(
                'form-control storagenodeftppath-input',
                'ftppath',
                '/images/',
                'text',
                'ftppath',
                $ftppath,
                true
            ),
            self::makeLabel(
                $labelClass,
                'snapinpath',
                _('Storage Node Snapin Path')
            ) => self::makeInput(
                'form-control storagenodeftppath-input',
                'snapinpath',
                '/opt/fog/snapins/',
                'text',
                'snapinpath',
                $snapinpath,
                true
            ),
            self::makeLabel(
                $labelClass,
                'sslpath',
                _('Storage Node SSL Path')
            ) => self::makeInput(
                'form-control storagenodesslpath-input',
                'sslpath',
                '/opt/fog/snapins/ssl/',
                'text',
                'sslpath',
                $sslpath,
                true
            ),
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

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'STORAGENODE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'StorageNode' => self::getClass('StorageNode')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'storagenode-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="storagenode-create">';
        echo '<div class="box-body">';
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
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Page to enable creating a new storage node.
     *
     * @return void
     */
    public function addModal()
    {
        $storagenode = filter_input(INPUT_POST, 'storagenode');
        $description = filter_input(INPUT_POST, 'description');
        $ip = filter_input(INPUT_POST, 'ip');
        $webroot = filter_input(INPUT_POST, 'webroot') ?:
            '/fog';
        $maxClients = (int)filter_input(INPUT_POST, 'maxClients') ?:
            10;
        $isMaster = isset($_POST['isMaster']) ? ' checked' : '';
        $bandwidth = filter_input(INPUT_POST, 'bandwidth');
        $storagegroupID = (int)filter_input(INPUT_POST, 'storagegroupID');
        if (!$storagegroupID) {
            Route::ids('storagegroup', false);
            $storagegroupID = @min(json_decode(Route::getData(), true));
        }
        $path = filter_input(INPUT_POST, 'path') ?:
            '/images/';
        $ftppath = filter_input(INPUT_POST, 'ftppath') ?:
            '/images/';
        $snapinpath = filter_input(INPUT_POST, 'snapinppath') ?:
            '/opt/fog/snapins/';
        $sslpath = filter_input(INPUT_POST, 'sslpath') ?:
            '/opt/fog/snapins/ssl/';
        $bitrate = filter_input(INPUT_POST, 'bitrate');
        $helloInterval = (int)filter_input(INPUT_POST, 'helloInterval');
        $interface = filter_input(INPUT_POST, 'interface');
        $user = filter_input(INPUT_POST, 'user');
        $pass = filter_input(INPUT_POST, 'pass');
        $graphcolor = filter_input(INPUT_POST, 'graphcolor');

        $labelClass = 'col-sm-3 control-label';

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
                true
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
                true
            ),
            self::makeLabel(
                $labelClass,
                'webroot',
                _('Storage Node Web Root')
            ) => self::makeInput(
                'form-control storagenodewebroot-input',
                'webroot',
                '/fog',
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
            self::makeLabel(
                $labelClass,
                'graphcolor',
                _('Graph Color')
                . '<br/>('
                . _('On Dashboard')
                . ')'
            ) => self::makeInput(
                'jscolor {required:false} {refine: false} '
                    . 'form-control storagenodecolor-input',
                'graphcolor',
                'FFFFFF',
                'text',
                'graphcolor',
                $graphcolor
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
            self::makeLabel(
                $labelClass,
                'helloInterval',
                _('Re-Transmit Hello Interval')
            ) => self::makeInput(
                'form-control storagenodehellointerval-input',
                'helloInterval',
                '300',
                'number',
                'helloInterval',
                $helloInterval
            ),
            // Node Path Locations
            self::makeLabel(
                $labelClass,
                'path',
                _('Storage Node Image Path')
            ) => self::makeInput(
                'form-control storagenodeimagepath-input',
                'path',
                '/images/',
                'text',
                'path',
                $path,
                true
            ),
            self::makeLabel(
                $labelClass,
                'ftppath',
                _('Storage Node FTP Path')
            ) => self::makeInput(
                'form-control storagenodeftppath-input',
                'ftppath',
                '/images/',
                'text',
                'ftppath',
                $ftppath,
                true
            ),
            self::makeLabel(
                $labelClass,
                'snapinpath',
                _('Storage Node Snapin Path')
            ) => self::makeInput(
                'form-control storagenodeftppath-input',
                'snapinpath',
                '/opt/fog/snapins/',
                'text',
                'snapinpath',
                $snapinpath,
                true
            ),
            self::makeLabel(
                $labelClass,
                'sslpath',
                _('Storage Node SSL Path')
            ) => self::makeInput(
                'form-control storagenodesslpath-input',
                'sslpath',
                '/opt/fog/snapins/ssl/',
                'text',
                'sslpath',
                $sslpath,
                true
            ),
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

        self::$HookManager->processEvent(
            'STORAGENODE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'StorageNode' => self::getClass('StorageNode')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=storagenode&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually save the new node.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('STORAGENODE_ADD_POST');
        // Setup and filter our vars.
        $storagenode = trim(
            filter_input(INPUT_POST, 'storagenode')
        );
        $ip = trim(
            filter_input(INPUT_POST, 'ip')
        );
        $maxClients = (int)trim(
            filter_input(INPUT_POST, 'maxClients')
        );
        $interface = trim(
            filter_input(INPUT_POST, 'interface')
        );
        $user = trim(
            filter_input(INPUT_POST, 'user')
        );
        $pass = trim(
            filter_input(INPUT_POST, 'pass')
        );
        $bandwidth = trim(
            filter_input(INPUT_POST, 'bandwidth')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $webroot = trim(
            filter_input(INPUT_POST, 'webroot')
        );
        $isen = (int)isset($_POST['isEnabled']);
        $isgren = (int)isset($_POST['isGraphEnabled']);
        $isMaster = (int)isset($_POST['isMaster']);
        $storagegroupID = (int)trim(
            filter_input(INPUT_POST, 'storagegroupID')
        );
        $graphcolor = trim(
            filter_input(INPUT_POST, 'graphcolor')
        );
        $path = trim(
            filter_input(INPUT_POST, 'path')
        );
        $ftppath = trim(
            filter_input(INPUT_POST, 'ftppath')
        );
        $snapinpath = trim(
            filter_input(INPUT_POST, 'snapinpath')
        );
        $sslpath = trim(
            filter_input(INPUT_POST, 'sslpath')
        );
        $bitrate = trim(
            filter_input(INPUT_POST, 'bitrate')
        );
        $helloInterval = (int)trim(
            filter_input(INPUT_POST, 'helloInterval')
        );

        $serverFault = false;
        try {
            $testavail = self::$FOGURLRequests->isAvailable($ip);
            $warning = !array_shift($testavail);
            if (!$warning) {
                self::$FOGSSH->username = $user;
                self::$FOGSSH->password = $pass;
                self::$FOGSSH->host = $ip;
                $warning = !self::$FOGSSH->connect();
            }
            $exists = self::getClass('StorageNodeManager')
                ->exists($storagenode);
            if ($exists) {
                throw new Exception(
                    _('A storage node already exists with this name!')
                );
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
                ->set('name', $storagenode)
                ->set('description', $description)
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
                ->set('helloInterval', $helloInterval)
                ->set('interface', $interface)
                ->set('isGraphEnabled', $isgren)
                ->set('isEnabled', $isen)
                ->set('user', $user)
                ->set('pass', $pass)
                ->set('bandwidth', $bandwidth)
                ->set('graphcolor', $graphcolor);
            if (!$StorageNode->save()) {
                $serverFault = true;
                throw new Exception(_('Add storage node failed!'));
            }
            if ($StorageNode->get('isMaster')) {
                $find = [
                    'isMaster' => 1,
                    'storagegroupID' => $StorageNode->get('storagegroupID')
                ];
                Route::ids(
                    'storagenode',
                    $find
                );
                $masternodes = json_decode(
                    Route::getData(),
                    true
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
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'STORAGENODE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Node added!'),
                    'title' => _('Storage Node Create Success')
                ]
            );
            if ($warning) {
                $warn = _(
                    'Unable to connect using ip, user, and/or password provided!'
                );
                $warn .= '<br/><br/>';
                $warn .= _('Storage Node created successfully');
                $title = _('Storage Node Create Warning');
                $msg = json_encode(
                    [
                        'warning' => $warn,
                        'title' => $title
                    ]
                );
            } else {
                self::$FOGSSH->disconnect();
            }
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'STORAGENODE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=storagenode&sub=edit&sub='
        //    . $StorageNode->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'StorageNode' => &$StorageNode,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
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
        $storagenode = (
            filter_input(INPUT_POST, 'storagenode') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $ip = (
            filter_input(INPUT_POST, 'ip') ?:
            $this->obj->get('ip')
        );
        $webroot = (
            filter_input(INPUT_POST, 'webroot') ?:
            $this->obj->get('webroot')
        );
        $maxClients = (
            (int)filter_input(INPUT_POST, 'maxClients') ?:
            $this->obj->get('maxClients')
        );
        $bandwidth = (
            filter_input(INPUT_POST, 'bandwidth') ?:
            $this->obj->get('bandwidth')
        );
        $storagegroupID = (
            (int)filter_input(INPUT_POST, 'storagegroupID') ?:
            $this->obj->get('storagegroupID')
        );
        $path = (
            filter_input(INPUT_POST, 'path') ?:
            $this->obj->get('path')
        );
        $ftppath = (
            filter_input(INPUT_POST, 'ftppath') ?:
            $this->obj->get('ftppath')
        );
        $snapinpath = (
            filter_input(INPUT_POST, 'snapinpath') ?:
            $this->obj->get('snapinpath')
        );
        $sslpath = (
            filter_input(INPUT_POST, 'sslpath') ?:
            $this->obj->get('sslpath')
        );
        $bitrate = (
            filter_input(INPUT_POST, 'bitrate') ?:
            $this->obj->get('bitrate')
        );
        $helloInterval = (int)(
            filter_input(INPUT_POST, 'helloInterval') ?:
            $this->obj->get('helloInterval')
        );
        $interface = (
            filter_input(INPUT_POST, 'interface') ?:
            $this->obj->get('interface')
        );
        $user = (
            filter_input(INPUT_POST, 'user') ?:
            $this->obj->get('user')
        );
        $pass = (
            filter_input(INPUT_POST, 'pass') ?:
            $this->obj->get('pass')
        );
        $isgren = isset($_POST['isGraphEnabled']) ?:
            $this->obj->get('isGraphEnabled');
        $isen = isset($_POST['isEnabled']) ?:
            $this->obj->get('isEnabled');
        $isMaster = isset($_POST['isMaster']) ?:
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
        if ($isMaster) {
            $isMaster = ' checked';
        } else {
            $isMaster = '';
        }
        $graphcolor = (
            filter_input(INPUT_POST, 'graphcolor') ?:
            $this->obj->get('graphcolor')
        );

        $labelClass = 'col-sm-3 control-label';

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
                true
            ),
            self::makeLabel(
                $labelClass,
                'webroot',
                _('Storage Node Web Root')
            ) => self::makeInput(
                'form-control storagenodewebroot-input',
                'webroot',
                '/fog',
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
                $isen
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
                $isgren
            ),
            self::makeLabel(
                $labelClass,
                'graphcolor',
                _('Graph Color')
                . '<br/>('
                . _('On Dashboard')
                . ')'
            ) => self::makeInput(
                'jscolor {required:false} {refine: false} '
                    . 'form-control storagenodecolor-input',
                'graphcolor',
                'FFFFFF',
                'text',
                'graphcolor',
                $graphcolor
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
            self::makeLabel(
                $labelClass,
                'helloInterval',
                _('Re-Transmit Hello Interval')
            ) => self::makeInput(
                'form-control storagenodehellointerval-input',
                'helloInterval',
                '300',
                'number',
                'helloInterval',
                $helloInterval
            ),
            // Node Path Locations
            self::makeLabel(
                $labelClass,
                'path',
                _('Storage Node Image Path')
            ) => self::makeInput(
                'form-control storagenodeimagepath-input',
                'path',
                '/images/',
                'text',
                'path',
                $path,
                true
            ),
            self::makeLabel(
                $labelClass,
                'ftppath',
                _('Storage Node FTP Path')
            ) => self::makeInput(
                'form-control storagenodeftppath-input',
                'ftppath',
                '/images/',
                'text',
                'ftppath',
                $ftppath,
                true
            ),
            self::makeLabel(
                $labelClass,
                'snapinpath',
                _('Storage Node Snapin Path')
            ) => self::makeInput(
                'form-control storagenodeftppath-input',
                'snapinpath',
                '/opt/fog/snapins/',
                'text',
                'snapinpath',
                $snapinpath,
                true
            ),
            self::makeLabel(
                $labelClass,
                'sslpath',
                _('Storage Node SSL Path')
            ) => self::makeInput(
                'form-control storagenodesslpath-input',
                'sslpath',
                '/opt/fog/snapins/ssl/',
                'text',
                'sslpath',
                $sslpath,
                true
            ),
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

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'STORAGENODE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'StorageNode' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'storagenode-general-form',
            self::makeTabUpdateURL(
                'storagenode-general',
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
        echo $this->deleteModal();
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
        $storagenode = trim(
            filter_input(INPUT_POST, 'storagenode')
        );
        $ip = trim(
            filter_input(INPUT_POST, 'ip')
        );
        $maxClients = (int)trim(
            filter_input(INPUT_POST, 'maxClients')
        );
        $interface = trim(
            filter_input(INPUT_POST, 'interface')
        );
        $user = trim(
            filter_input(INPUT_POST, 'user')
        );
        $pass = trim(
            filter_input(INPUT_POST, 'pass')
        );
        $testavail = self::$FOGURLRequests->isAvailable($ip);
        $warning = !array_shift($testavail);
        if (!$warning) {
            self::$FOGSSH->username = $user;
            self::$FOGSSH->password = $pass;
            self::$FOGSSH->host = $ip;
            $warning = !self::$FOGSSH->connect();
        }
        if ($warning) {
            $warning = _(
                'Unable to connect using ip, user, and/or password provided!'
            );
        } else {
            self::$FOGSSH->disconnect();
        }
        $bandwidth = trim(
            filter_input(INPUT_POST, 'bandwidth')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $webroot = trim(
            filter_input(INPUT_POST, 'webroot')
        );
        $isen = (int)isset($_POST['isEnabled']);
        $isgren = (int)isset($_POST['isGraphEnabled']);
        $isMaster = (int)isset($_POST['isMaster']);
        $graphcolor = trim(
            filter_input(INPUT_POST, 'graphcolor')
        );
        $storagegroupID = (int)trim(
            filter_input(INPUT_POST, 'storagegroupID')
        );
        $path = trim(
            filter_input(INPUT_POST, 'path')
        );
        $ftppath = trim(
            filter_input(INPUT_POST, 'ftppath')
        );
        $snapinpath = trim(
            filter_input(INPUT_POST, 'snapinpath')
        );
        $sslpath = trim(
            filter_input(INPUT_POST, 'sslpath')
        );
        $bitrate = trim(
            filter_input(INPUT_POST, 'bitrate')
        );
        $helloInterval = (int)trim(
            filter_input(INPUT_POST, 'helloInterval')
        );
        if (!$storagenode) {
            throw new Exception(self::$foglang['StorageNameRequired']);
        }
        $exists = self::getClass('StorageNodeManager')
            ->exists($storagenode, $this->obj->get('id'));
        if ($storagenode != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A storage node already exists with this name!')
            );
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
            ->set('name', $storagenode)
            ->set('description', $description)
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
            ->set('helloInterval', $helloInterval)
            ->set('interface', $interface)
            ->set('isGraphEnabled', $isgren)
            ->set('isEnabled', $isen)
            ->set('user', $user)
            ->set('pass', $pass)
            ->set('bandwidth', $bandwidth)
            ->set('graphcolor', $graphcolor);
        if ($this->obj->get('isMaster')) {
            $find = [
                'isMaster' => 1,
                'storagegroupID' => $this->obj->get('storagegroupID')
            ];
            Route::ids(
                'storagenode',
                $find
            );
            $masternodes = json_decode(
                Route::getData(),
                true
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
        return $warning;
    }
    /**
     * Viewing the Storage Node's Version information.
     *
     * @return void
     */
    public function storagenodeVersion()
    {
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        if (!$this->obj->get('online')) {
            echo $this->obj->get('name');
            echo ' ';
            echo _('is not currently online');
        } else {
            $url = filter_var(
                sprintf(
                    '%s://%s/fog/status/kernelvers.php',
                    self::$httpproto,
                    $this->obj->get('ip')
                ),
                FILTER_SANITIZE_URL
            );
            $data = ['ko' => 1];
            $res = self::$FOGURLRequests->process(
                $url,
                'POST',
                $data
            );
            $res = array_shift($res);
            echo $res;
        }
        echo '</div>';
        echo '</div>';
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
            'generator' => function () {
                $this->storagenodeGeneral();
            }
        ];

        // Info
        $tabData[] = [
            'name' => _('Information'),
            'id' => 'storagenode-info',
            'generator' => function () {
                self::getClass('ServerInfo')->index();
            }
        ];

        // Versions
        $tabData[] = [
            'name' => _('Versions'),
            'id' => 'storagenode-version',
            'generator' => function () {
                $this->storagenodeVersion();
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
                    $warning = $this->storagenodeGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Storage Node Update Failed'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'STORAGENODE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Node updated!'),
                    'title' => _('Storage Node Update Success')
                ]
            );
            if ($warning) {
                $warning .= '<br/><br/>';
                $warning .= _('Storage Node updated successfully');
                $title = _('Storage Node Update Warning');
                $msg = json_encode(
                    [
                        'warning' => $warning,
                        'title' => $title
                    ]
                );
            }
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'STORAGENODE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Update Fail')
                ]
            );
        }

        self::$HookManager->processEvent(
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
