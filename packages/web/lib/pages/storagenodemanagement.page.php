<?php
/**
 * Displays the storage node information.
 *
 * PHP version 7.4+
 *
 * @category StorageNodeManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Base\FOGPage;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
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
            $storagegroupID = @min(Route::getIds('storagegroup', false));
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

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
            // Names both roles on purpose. This field drives the dashboard
            // bandwidth graph (status/bandwidth.php?dev=), and #908 made it
            // the multicast interface fallback when the routing table
            // cannot answer. Until then nothing read it, so it drifted --
            // the reference server had a NIC recorded that no longer
            // existed. A field whose purpose is stated is a field someone
            // maintains.
            self::makeLabel(
                $labelClass,
                'interface',
                _('Network Interface')
                . '<br/>('
                . _(
                    'bandwidth graph, and multicast when the interface '
                    . 'cannot be derived from the routing table'
                )
                . ')'
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
            // The system account FOG signs in as ON the node. Named
            // "FTP User"/"FTP Password" until now, which described only half
            // of what it does: the replicators use it for FTP transfers, and
            // this page's own save-time reachability check uses it for an SSH
            // login. It is a service account on the node, not a FOG web user,
            // so it is named for what it is rather than for one protocol.
            self::makeLabel(
                $labelClass,
                'user',
                _('Node Service Account')
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
                _('Node Service Account Password')
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
    }
    /**
     * Page to enable creating a new storage node.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'storagenode',
            _('Create New Storage Node'),
            'STORAGENODE_ADD_FIELDS',
            'StorageNode'
        );
    }
    /**
     * Page to enable creating a new storage node.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'storagenode',
            'STORAGENODE_ADD_FIELDS',
            'StorageNode'
        );
    }
    /**
     * Actually save the new node.
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
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
        if (!$storagegroupID) {
            $storagegroupID = @min(Route::getIds('storagegroup', false));
        }
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
                throw new \Exception(
                    _('A storage node already exists with this name!')
                );
            }
            if (is_numeric($bandwidth)) {
                if ($bandwidth < 0) {
                    throw new \Exception(
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
                throw new \Exception(_('Add storage node failed!'));
            }
            if ($StorageNode->get('isMaster')) {
                $find = [
                    'isMaster' => 1,
                    'storagegroupID' => $StorageNode->get('storagegroupID')
                ];
                $masternodes = Route::getIds(
                    'storagenode',
                    $find
                );
                self::getClass('StorageNodeManager')
                    ->update(
                        [
                            'id' => array_diff(
                                (array)$masternodes,
                                (array)$StorageNode->get('id')
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
        } catch (\Exception $e) {
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
        $this->jsonHookResponse(
            [
                'StorageNode' => &$StorageNode,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Storage Node General
     *
     * @return void
     */
    public function storagenodeGeneral()
    {
        // Post Fields
        self::checkAuthAndCSRF();
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
        $apikey = (
            filter_input(INPUT_POST, 'apikey') ?:
            $this->obj->get('key')
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

        $labelClass = 'col-sm-3 col-form-label';

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
            // Names both roles on purpose. This field drives the dashboard
            // bandwidth graph (status/bandwidth.php?dev=), and #908 made it
            // the multicast interface fallback when the routing table
            // cannot answer. Until then nothing read it, so it drifted --
            // the reference server had a NIC recorded that no longer
            // existed. A field whose purpose is stated is a field someone
            // maintains.
            self::makeLabel(
                $labelClass,
                'interface',
                _('Network Interface')
                . '<br/>('
                . _(
                    'bandwidth graph, and multicast when the interface '
                    . 'cannot be derived from the routing table'
                )
                . ')'
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
            // The system account FOG signs in as ON the node. Named
            // "FTP User"/"FTP Password" until now, which described only half
            // of what it does: the replicators use it for FTP transfers, and
            // this page's own save-time reachability check uses it for an SSH
            // login. It is a service account on the node, not a FOG web user,
            // so it is named for what it is rather than for one protocol.
            self::makeLabel(
                $labelClass,
                'user',
                _('Node Service Account')
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
                _('Node Service Account Password')
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
            . '</div>'
            . '<div class="form-text">'
            . _(
                'The account on the node itself that FOG signs in as, over '
                . 'SSH to check the node is reachable and over FTP to move '
                . 'images and snapins. Created by the installer, normally '
                . '"fogproject". This is not a FOG web user.'
            )
            . '</div>',
            // Only needed when the peer is a full FOG server with its own
            // database. A true storage node reads the master's
            // globalSettings, so it already shares FOG_NODE_API_KEY and
            // this stays empty.
            //
            // A text input rather than a password one, deliberately: the
            // whole point of the field is that the administrator has to
            // read the value and set it as the peer's own
            // FOG_NODE_API_KEY. A masked field cannot be transcribed. It
            // is never emitted by the API -- 'key' is already in
            // Route::$sensitiveAlwaysFields -- and this page is admin only.
            self::makeLabel(
                $labelClass,
                'apikey',
                _('Node API Signing Key')
            ) => self::makeInput(
                'form-control storagenodeapikey-input',
                'apikey',
                _('Leave empty unless this node is its own FOG server'),
                'text',
                'apikey',
                $apikey,
                false
            )
            . '<div class="form-text">'
            . _(
                'Only for a peer running its own FOG database. On that '
                . 'peer run bin/fog-node-key.php --set with this same '
                . 'value, or it cannot verify requests this server signs.'
            )
            . '</div>',
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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

        $this->renderGeneralForm('storagenode', $rendered, $buttons);
    }
    /**
     * Storage node general post update.
     *
     * @return void
     */
    public function storagenodeGeneralPost()
    {
        self::checkAuthAndCSRF();
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
        // Three unrelated things can fail here -- the node can be off the
        // network, its SSH service can refuse the handshake, or the account
        // can be rejected -- and this used to report all three as
        // "Unable to connect using ip, user, and/or password provided!".
        // That names the password for a failure the password was never
        // involved in: a handshake that never completed presented no
        // credentials at all. Reported by Tom after an ssh2_connect -43
        // ("failed getting banner") on a node whose password was correct
        // and merely contained a '>', which made it look like an escaping
        // bug. Say which stage actually failed instead.
        $warning = '';
        $testavail = self::$FOGURLRequests->isAvailable($ip);
        if (!array_shift($testavail)) {
            $warning = sprintf(
                '%s: %s',
                _('The node did not answer on the network'),
                $ip
            );
        } else {
            self::$FOGSSH->username = $user;
            self::$FOGSSH->password = $pass;
            self::$FOGSSH->host = $ip;
            if (!self::$FOGSSH->connect()) {
                if (self::$FOGSSH->lastFailure() === 'login') {
                    $warning = sprintf(
                        '%s: %s@%s',
                        _('The node refused the SSH login for'),
                        $user,
                        $ip
                    );
                } else {
                    $warning = sprintf(
                        '%s: %s. %s',
                        _('Could not open an SSH connection to'),
                        $ip,
                        _(
                            'This is a transport failure -- no credentials '
                            . 'were sent -- so check sshd on the node.'
                        )
                    );
                }
            } else {
                self::$FOGSSH->disconnect();
            }
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
        if (!$storagegroupID) {
            $storagegroupID = @min(Route::getIds('storagegroup', false));
        }
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
            throw new \Exception(self::$foglang['StorageNameRequired']);
        }
        $exists = self::getClass('StorageNodeManager')
            ->exists($storagenode, $this->obj->get('id'));
        if ($storagenode != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A storage node already exists with this name!')
            );
        }
        if (is_numeric($bandwidth)) {
            if ($bandwidth < 0) {
                throw new \Exception(
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
            ->set('key', trim((string)filter_input(INPUT_POST, 'apikey')))
            ->set('bandwidth', $bandwidth)
            ->set('graphcolor', $graphcolor);
        if ($this->obj->get('isMaster')) {
            $find = [
                'isMaster' => 1,
                'storagegroupID' => $this->obj->get('storagegroupID')
            ];
            $masternodes = Route::getIds(
                'storagenode',
                $find
            );
            self::getClass('StorageNodeManager')
                ->update(
                    [
                        'id' => array_diff(
                            (array)$masternodes,
                            (array)$this->obj->get('id')
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
        echo '<div class="card">';
        echo '<div class="card-body">';
        if (!$this->obj->get('online')) {
            echo $this->obj->get('name');
            echo ' ';
            echo _('is not currently online');
        } else {
            $url = filter_var(
                sprintf(
                    '%s://%s/%s/status/kernelvers.php',
                    self::$httpproto,
                    $this->obj->get('ip'),
                    self::webrootPath($this->obj->get('webroot'))
                ),
                FILTER_SANITIZE_URL
            );
            $data = ['ko' => 1];
            $res = self::$FOGURLRequests->process(
                $url,
                'POST',
                $data,
                false,
                false,
                false,
                false,
                false
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
        $this->notes = [
            _('Storage Node') => $this->obj->get('name'),
            _('Storage Group') => $this->obj->getStorageGroup()->get('name'),
            _('Role') => (
                $this->obj->get('isMaster') ?
                _('Master') :
                _('Member')
            ),
            _('Image Path') => $this->obj->get('path'),
            _('Max Clients') => (string)$this->obj->get('maxClients')
        ];
        // Every note here mirrors a General-tab control, so the card can track
        // the form instead of going stale until the next page load. Keys must
        // match $notes exactly.
        $this->noteSources = [
            _('Storage Node') => '#storagenode',
            _('Storage Group') => '#storagegroupID',
            _('Role') => [
                'sel' => '#isMaster',
                'on' => _('Master'),
                'off' => _('Member')
            ],
            _('Image Path') => '#path',
            _('Max Clients') => '#maxClients'
        ];
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
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually store the edits.
     *
     * @return void
     */
    public function editPost()
    {
        self::checkAuthAndCSRF();
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
                throw new \Exception(_('Storage Node Update Failed'));
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
        } catch (\Exception $e) {
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

        $this->jsonHookResponse(
            [
                'StorageNode' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\StorageNodeManagement', 'StorageNodeManagement');
