<?php
/**
 * Displays the storage group.node information.
 *
 * PHP version 5
 *
 * @category StorageManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays the storage group.node information.
 *
 * @category StorageManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageManagementPage extends FOGPage
{
    /**
     * Node this class works from.
     *
     * @var string
     */
    public $node = 'storage';
    /**
     * Initializes the storage page.
     *
     * @param string $name Name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Storage Management';
        parent::__construct($this->name);
        global $sub;
        global $id;
        switch ($sub) {
        case 'edit':
        case 'delete':
        case 'deleteStorageNode':
            if ($id) {
                if (!$this->obj->isValid() && false === strpos($sub, 'add')) {
                    unset($this->obj);
                    header_response_code(400);
                    header('Location: ../management/index.php?node=storage');
                }
            }
            break;
        case 'editStorageGroup':
        case 'deleteStorageGroup':
            if ($id) {
                if (!$this->obj->isValid() && false === strpos($sub, 'add')) {
                    unset($this->obj);
                    header_response_code(400);
                    header('Location: ../management/index.php?node=storage');
                }
            }
            break;
        }
    }
    /**
     * If search is passed display index.
     *
     * @return void
     */
    public function search()
    {
        $this->index();
    }
    /**
     * If edit redirect to edit storage node.
     *
     * @return void
     */
    public function edit()
    {
        $this->editStorageNode();
    }
    /**
     * If edit post redirect to edit storage node post.
     *
     * @return void
     */
    public function editPost()
    {
        $this->editStorageNodePost();
    }
    /**
     * If delete redirect to delete storage node.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleteStorageNode();
    }
    /**
     * If delete post redirect to delete storage node post.
     *
     * @return void
     */
    public function deletePost()
    {
        $this->deleteStorageNodePost();
    }
    /**
     * Display the list of storage nodes.
     *
     * @return void
     */
    public function index()
    {
        global $node;
        global $sub;
        if (false === self::$showhtml) {
            return;
        }
        if (self::$ajax) {
            header('Content-Type: application/json');
            Route::listem($this->childClass);
            echo Route::getData();
            exit;
        }
        $this->title = self::$foglang['AllSN'];
        $this->headerData = array(
            self::$foglang['SN'],
            self::$foglang['SG'],
            self::$foglang['Enabled'],
            self::$foglang['MasterNode'],
            _('Max Clients')
        );
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
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->indexDivDisplay(true, 'node');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Display createing a new storage node.
     *
     * @return void
     */
    public function addStorageNode()
    {
        $this->title = _('Create New Storage Node');
        $name = filter_input(
            INPUT_POST,
            'name'
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
        );
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
            . $isMaster
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
            . '</label>' => '<input type="checkbox" name="isEnabled" id="isen" '
            . 'checked/>',
            '<label class="col-sm-2 control-label" for="isgren">'
            . self::$foglang['IsGraphEnabled']
            . '<br/>'
            . '('
            . self::$foglang['OnDash']
            . ')'
            . '</label>' => '<input type="checkbox" name="isGraphEnabled" '
            . 'id="isgren" checked/>',
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
                'STORAGENODE_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'StorageNode' => self::getClass('StorageNode')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="storagenode-create">';
        echo '<form id="storagenode-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Storage Node -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Storage Node');
        echo '</h3>';
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
     * Actually save the new node.
     *
     * @return void
     */
    public function addStorageNodePost()
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
                    array(
                        'isMaster' => 1,
                        'storagegroupID' => $StorageNode->get('storagegroupID')
                    )
                );
                self::getClass('StorageNodeManager')
                    ->update(
                        array(
                            'id' => array_diff(
                                (array) $StorageNode->get('id'),
                                (array) $masternodes
                            )
                        ),
                        '',
                        array('isMaster' => 0)
                    );
            }
            $hook = 'STORAGE_NODE_ADD_SCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Storage Node added!'),
                    'title' => _('Storage Node Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_NODE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageNode' => &$StorageNode)
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
                'STORAGENODE_EDIT_FIELDS',
                [
                    'fields' => &$fields,
                    'obj' => &$this->obj
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
     * Presents the Storage nodes list table.
     *
     * @return void
     */
    public function getStorageNodesList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $where = "`nfsGroups`.`ngID` = '"
            . $this->obj->get('id')
            . "'";

        $storagegroupsSqlStr = "SELECT `%s`,"
            . "`ngmGroupID` AS `origID`,IF(`ngmGroupID` = '"
            . $this->obj->get('id')
            . "','dissociated','associated') AS `ngmGroupID`
            FROM `%s`
            CROSS JOIN `nfsGroups`
            %s
            %s
            %s";
        $storagegroupsFilterStr = "SELECT COUNT(`%s`),"
            . "`ngmGroupID` AS `origID`,IF(`ngmGroupID` = '"
            . $this->obj->get('id')
            . "','dissociated','associated') AS `ngmGroupID`
            FROM `%s`
            CROSS JOIN `nfsGroups`
            %s";
        $storagegroupsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('StorageNodeManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'ngmGroupID',
            'dt' => 'association'
        ];
        $columns[] = [
            'db' => 'origID',
            'dt' => 'origID',
            'removeFromQuery' => true
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'nfsGroupMembers',
                'ngmID',
                $columns,
                $storagegroupsSqlStr,
                $storagegroupsFilterStr,
                $storagegroupsTotalStr,
                $where
            )
        );
        exit;
    }
    /**
     * Edit existing nodes.
     *
     * @return void
     */
    public function editStorageNode()
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

        echo self::tabFields($tabData);
    }
    /**
     * Actually store the edits.
     *
     * @return void
     */
    public function editStorageNodePost()
    {
        header('Content-type: application/json');
        self::$HookManager
            ->processEvent(
                'STORAGENODE_EDIT_POST',
                array('StorageNode' => &$this->obj)
            );
        $serverFault = false;
        try {
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
                    array(
                        'isMaster' => 1,
                        'storagegroupID' => $this->obj->get('storagegroupID')
                    )
                );
                self::getClass('StorageNodeManager')
                    ->update(
                        array(
                            'id' => array_diff(
                                (array)$this->obj->get('id'),
                                (array)$masternodes
                            )
                        ),
                        '',
                        array('isMaster' => 0)
                    );
            }
            $code = 201;
            $hook = 'STORAGENODE_EDIT_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Storage Node updated!'),
                    'title' => _('Storage Node Update Success')
                )
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'STORAGENODE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Update Fail')
                )
            );
        }
        http_response_code($code);
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageNode' => &$this->obj)
            );
        echo $msg;
        exit;
    }
    /**
     * Displays form for deleting node.
     *
     * @return void
     */
    public function deleteStorageNode()
    {
        $this->title = sprintf(
            '%s: %s',
            self::$foglang['Remove'],
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="delete">'
            . $this->title
            . '</label>' => '<input type="hidden" name="remitems[]" '
            . 'value="'
            . $this->obj->get('id')
            . '"/>'
            . '<button type="submit" name="delete" id="delete" '
            . 'class="btn btn-danger btn-block">'
            . _('Delete')
            . '</button>'
        );
        $rendered = self::formFields($fields);
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_DELETE',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually delete the node.
     *
     * @return void
     */
    public function deleteStorageNodePost()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $user = filter_input(INPUT_POST, 'fogguiuser');
            $pass = filter_input(INPUT_POST, 'fogguipass');
            $validate = self::getClass('User')
                ->passwordValidate(
                    $user,
                    $pass,
                    true
                );
            if ($validate) {
                echo json_encode(
                    array(
                        'error' => self::$foglang['InvalidLogin']
                    )
                );
                exit;
            }
        }
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_DELETE_POST',
                array(
                    'StorageNode' => &$this->obj
                )
            );
        try {
            if (!$this->obj->destroy()) {
                throw new Exception(self::$foglang['FailDelSN']);
            }
            $hook = 'STORAGE_NODE_DELETE_SUCCESS';
            $msg = sprintf(
                '%s: %s',
                self::$foglang['SNDelSuccess'],
                $this->obj->get('name')
            );
            $url = sprintf(
                '?node=%s',
                $this->node
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_NODE_DELETE_FAIL';
            $msg = $e->getMessage();
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array(
                    'StorageNode' => &$this->obj
                )
            );
        self::setMessage($msg);
        self::redirect($url);
    }
    /**
     * Display the list of storage nodes.
     *
     * @return void
     */
    public function storageGroup()
    {
        global $node;
        global $sub;
        if (false === self::$showhtml) {
            return;
        }
        if (self::$ajax) {
            header('Content-Type: application/json');
            Route::listem($this->childClass);
            echo Route::getData();
            exit;
        }
        $this->title = self::$foglang['AllSG'];
        $this->headerData = array(
            self::$foglang['SG'],
            _('Total Clients')
        );
        $this->templates = array(
            '',
            ''
        );
        $this->attributes = array(
            [],
            []
        );
        self::$HookManager
            ->processEvent(
                'STORAGE_GROUP_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->indexDivDisplay(true, 'group');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }
    /**
     * Create a new storage group.
     *
     * @return void
     */
    public function addStorageGroup()
    {
        $this->title = _('Create New Storage Group');
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $fields = [
            '<label class="col-sm-2 control-label" for="name">'
            . _('Storage Group Name')
            . '</label>' => '<input type="text" name="name" '
            . 'value="'
            . $name
            . '" class="storagegroupname-input form-control" '
            . 'id="name" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Storage Group Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'STORAGEGROUP_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'StorageGroup' => self::getClass('StorageGroup')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="storagegroup-create">';
        echo '<form id="storagegroup-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Storage Group -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h3 class="box-title">';
        echo _('Create New Storage Group');
        echo '</h3>';
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
     * Actually create the new group.
     *
     * @return void
     */
    public function addStorageGroupPost()
    {
        header('Content-Type: application/json');
        self::$HookManager->processEvent('STORAGEGROUP_ADD_POST');
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $serverFault = false;
        try {
            if (empty($name)) {
                throw new Exception(self::$foglang['SGNameReq']);
            }
            if (self::getClass('StorageGroupManager')->exists($name)) {
                throw new Exception(self::$foglang['SGExist']);
            }
            $StorageGroup = self::getClass('StorageGroup')
                ->set('name', $name)
                ->set('description', $desc);
            if (!$StorageGroup->save()) {
                $serverFault = true;
                throw new Exception(self::$foglang['DBupfailed']);
            }
            $code = 201;
            $hook = 'STORAGEGROUP_ADD_POST_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => self::$foglang['SGCreated'],
                    'title' => _('Storage Group Create Success')
                )
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'STORAGEGROUP_ADD_POST_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Create Fail')
                )
            );
        }
        http_response_code($code);
        //header('Location: ../management/index.php?node=storage&sub=editStorageGroup&id=' . $StorageGroup->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageGroup' => &$StorageGroup)
            );
        unset($StorageGroup);
        echo $msg;
        exit;
    }
    /**
     * Presents the storage group general.
     *
     * @return void
     */
    public function storagegroupGeneral()
    {
        $name = filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name');
        $description = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
        $fields = [
            '<label class="col-sm-2 control-label" for="name">'
            . _('Storage Group Name')
            . '</label>' => '<input type="text" name="name" '
            . 'value="'
            . $name
            . '" class="storagegroupname-input form-control" '
            . 'id="name" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Storage Group Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height:50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'STORAGEGROUP_GENERAL_FIELDS',
                [
                    'fields' => &$fields,
                    'obj' => &$this->obj
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<form id="storagegroup-general-form" class="form-horizontal" '
            . 'method="post" action="'
            . self::makeTabUpdateURL('storagegroup-general', $this->obj->get('id'))
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
     * Updates the storage group general elements.
     *
     * @return void
     */
    public function storagegroupGeneralPost()
    {
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $exists = self::getClass('StorageGroupManager')->exists(
            $name,
            $this->obj->get('id')
        );
        if (!$name) {
            throw new Exception(self::$foglang['SGName']);
        }
        if ($this->obj->get('name') != $name
            && $exists
        ) {
            throw new Exception(self::$foglang['SGExist']);
        }
        $this->obj
            ->set('name', $name)
            ->set('description', $desc);
    }
    /**
     * Presents the storage group membership.
     *
     * @return void
     */
    public function storagegroupMembership()
    {
        global $id;
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=storagegroup-membership" ';

        echo '<!-- Storage Nodes -->';
        echo '<div class="box-group" id="membership">';
        // =================================================================
        // Associated Storage Nodes
        $buttons = self::makeButton(
            'membership-master',
            _('Update Master Node'),
            'btn btn-primary master' . $id,
            $props
        );
        $buttons .= self::makeButton(
            'membership-add',
            _('Add selected'),
            'btn btn-success',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove selected'),
            'btn btn-danger',
            $props
        );

        $this->headerData = [
            _('Storage Node Name'),
            _('Storage Node Master'),
            _('Storage Node Associated')
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
        echo '<div id="updatestoragenodes" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'storagegroup-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates the storage group membership.
     *
     * @return void
     */
    public function storagegroupMembershipPost()
    {

        if (isset($_POST['updatemembership'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membership' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membership'];
            if (count($membership ?: []) > 0) {
                $this->obj->addNode($membership);
            }
        }
        if (isset($_POST['membershipdel'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membershipRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membershipRemove'];
            if (count($membership ?: []) > 0) {
                $this->obj->removeNode($membership);
            }
        }
        if (isset($_POST['mastersel'])) {
            $master = filter_input(
                INPUT_POST,
                'master'
            );
            self::getClass('StorageNodeManager')->update(
                [
                    'storagegroupID' => $this->obj->get('id'),
                    'isMaster' => '1'
                ],
                '',
                [
                    'isMaster' => '0'
                ]
            );
            if ($master) {
                self::getClass('StorageNodeManager')->update(
                    [
                        'storagegroupID' => $this->obj->get('id'),
                        'id' => $master
                    ],
                    '',
                    [
                        'isMaster' => '1'
                    ]
                );
            }
        }
    }
    /**
     * Edit a storage group.
     *
     * @return void
     */
    public function editStorageGroup()
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
            'id' => 'storagegroup-general',
            'generator' => function() {
                $this->storagegroupGeneral();
            }
        ];

        // Membership
        $tabData[] = [
            'name' => _('Membership'),
            'id' => 'storagegroup-membership',
            'generator' => function() {
                $this->storagegroupMembership();
            }
        ];

        echo self::tabFields($tabData);
        return;
        $fields = array(
            '<label for="name">'
            . self::$foglang['SGName']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" class="form-control" autocomplete="off" required/>'
            . '</div>',
            '<label for="description">'
            . self::$foglang['SGDesc']
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="description" class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . self::$foglang['Update']
            . '</button>'
        );
        $rendered = self::formFields($fields);
        self::$HookManager
            ->processEvent(
                'STORAGE_GROUP_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9 tab-content">';
        echo '<div class="tab-pane fade in active" id="group-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually submit the changes.
     *
     * @return void
     */
    public function editStorageGroupPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'STORAGEGROUP_EDIT_POST',
            ['StorageGroup' => &$this->obj]
        );
        $serverFault = false;
        try{
            global $tab;
            switch ($tab) {
            case 'storagegroup-general':
                $this->storagegroupGeneralPost();
                break;
            case 'storagegroup-membership':
                $this->storagegroupMembershipPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Storage Group Update Failed'));
            }
            $code = 201;
            $hook = 'STORAGEGROUP_EDIT_POST_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Storage Group updated!'),
                    'title' => _('Storage Group Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'STORAGEGROUP_EDIT_POST_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Update Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager->processEvent(
            $hook,
            array('StorageGroup' => &$this->obj)
        );
        echo $msg;
        exit;
    }
    /**
     * Delete storage group.
     *
     * @return void
     */
    public function deleteStorageGroup()
    {
        $this->title = sprintf(
            '%s: %s',
            self::$foglang['Remove'],
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="delete">'
            . $this->title
            . '</label>' => '<input type="hidden" name="remitems[]" '
            . 'value="'
            . $this->obj->get('id')
            . '"/>'
            . '<button type="submit" name="delete" id="delete" '
            . 'class="btn btn-danger btn-block">'
            . _('Delete')
            . '</button>'
        );
        $rendered = self::formFields($fields);
        self::$HookManager
            ->processEvent(
                'STORAGE_GROUP_DELETE',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<div id="deleteDiv"></div>';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually Delete the group.
     *
     * @return void
     */
    public function deleteStorageGroupPost()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $user = filter_input(INPUT_POST, 'fogguiuser');
            $pass = filter_input(INPUT_POST, 'fogguipass');
            $validate = self::getClass('User')
                ->passwordValidate(
                    $user,
                    $pass,
                    true
                );
            if ($validate) {
                echo json_encode(
                    array(
                        'error' => self::$foglang['InvalidLogin']
                    )
                );
                exit;
            }
        }
        self::$HookManager
            ->processEvent(
                'STORAGE_GROUP_DELETE_POST',
                array('StorageGroup' => &$this->obj)
            );
        try {
            if (self::getClass('StorageGroupManager')->count() == 1) {
                throw new Exception(self::$foglang['OneSG']);
            }
            if (!$this->obj->destroy()) {
                throw new Exception(self::$foglang['FailDelSG']);
            }
            $hook = 'STORAGE_GROUP_DELETE_POST_SUCCESS';
            $msg = sprintf(
                '%s: %s',
                self::$foglang['SGDelSuccess'],
                $this->obj->get('name')
            );
            $url = sprintf(
                '?node=%s&sub=storageGroup',
                $this->node
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_GROUP_DELETE_POST_FAIL';
            $msg = $e->getMessaage();
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageGroup'=>&$this->obj)
            );
        self::setMessage($msg);
        self::redirect($url);
    }
}
