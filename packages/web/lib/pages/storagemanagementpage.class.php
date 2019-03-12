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
        $this->menu = array(
            'list' => self::$foglang['AllSN'],
            'addStorageNode' => self::$foglang['AddSN'],
            'storageGroup' => self::$foglang['AllSG'],
            'addStorageGroup' => self::$foglang['AddSG'],
        );
        global $node;
        global $sub;
        global $id;
        switch ($sub) {
        case 'edit':
        case 'delete':
        case 'deleteStorageNode':
            if ($id) {
                if (!$this->obj->isValid() && false === strpos($sub, 'add')) {
                    unset($this->obj);
                    self::setMessage(
                        sprintf(
                            _('%s ID %s is not valid'),
                            _('Storage Node'),
                            $id
                        )
                    );
                    self::redirect(sprintf('?node=%s', $this->node));
                }
                $this->subMenu = array(
                    sprintf(
                        '?node=%s&sub=%s&id=%s#node-general',
                        $this->node,
                        $sub,
                        $id
                    ) => self::$foglang['General'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'deleteStorageNode',
                        $id
                    ) => self::$foglang['Delete']
                );
                $this->notes = array(
                    sprintf(
                        '%s %s',
                        self::$foglang['Storage'],
                        self::$foglang['Node']
                    ) => $this->obj->get('name'),
                    self::$foglang['ImagePath'] => $this->obj->get('path'),
                    self::$foglang['FTPPath'] => $this->obj->get('ftppath'),
                );
            }
            break;
        case 'editStorageGroup':
        case 'deleteStorageGroup':
            if ($id) {
                if (!$this->obj->isValid() && false === strpos($sub, 'add')) {
                    unset($this->obj);
                    self::setMessage(
                        sprintf(
                            _('%s ID %s is not valid'),
                            $this->childClass,
                            $id
                        )
                    );
                    self::redirect(
                        sprintf(
                            '?node=%s',
                            $this->node
                        )
                    );
                }
                $this->subMenu = array(
                    sprintf(
                        '?node=%s&sub=%s&id=%s#group-general',
                        $this->node,
                        $sub,
                        $id
                    ) => self::$foglang['General'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'deleteStorageGroup',
                        $id
                    ) => self::$foglang['Delete']
                );
                $this->notes = array(
                    sprintf(
                        '%s %s',
                        self::$foglang['Storage'],
                        self::$foglang['Group']
                    ) => $this->obj->get('name')
                );
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = self::$foglang['AllSN'];
        Route::listem('storagenode');
        $StorageNodes = json_decode(
            Route::getData()
        );
        $StorageNodes = $StorageNodes->storagenodes;
        foreach ((array)$StorageNodes as &$StorageNode) {
            $StorageGroup = $StorageNode->storagegroup;
            $this->data[] = array(
                'name' => $StorageNode->name,
                'id' => $StorageNode->id,
                'isMasterText' => (
                    $StorageNode->isMaster ?
                    _('Yes') :
                    _('No')
                ),
                'isEnabledText' => (
                    $StorageNode->isEnabled ?
                    _('Yes') :
                    _('No')
                ),
                'storage_group' => $StorageGroup->name,
                'max_clients' => $StorageNode->maxClients,
            );
            unset($StorageGroup, $StorageNode);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler"/>'
            . '<label for="toggler"></label>',
            self::$foglang['SN'],
            self::$foglang['SG'],
            self::$foglang['Enabled'],
            self::$foglang['MasterNode'],
            _('Max Clients'),
        );
        $this->templates = array(
            '<input type="checkbox" name="node[]" value='
            . '"${id}" class="toggle-action" id="node-${id}"/>'
            . '<label for="node-${id}"></label>',
            sprintf(
                '<a href="?node=%s&sub=edit&%s=${id}" title="%s">${name}</a>',
                $this->node,
                $this->id,
                self::$foglang['Edit']
            ),
            '${storage_group}',
            '${isEnabledText}',
            '${isMasterText}',
            '${max_clients}',
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 22
            ),
            array(),
            array(
            ),
            array(),
            array(
            ),
            array(),
        );
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_DATA',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9">';
        $this->indexDivDisplay(true, 'node');
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
     * Display createing a new storage node.
     *
     * @return void
     */
    public function addStorageNode()
    {
        $this->title = self::$foglang['AddSN'];
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $ip = filter_input(INPUT_POST, 'ip');
        $webroot = filter_input(INPUT_POST, 'webroot') ?: '/fog';
        $maxClients = (int)filter_input(INPUT_POST, 'maxClients');
        $ismaster = isset($_POST['isMaster']) ? ' checked' : '';
        $bandwidth = filter_input(INPUT_POST, 'bandwidth');
        $storagegroupID = (int)filter_input(INPUT_POST, 'storagegroupID');
        if (!$storagegroupID) {
            $storagegroupID = @min(
                self::getSubObjectIDs('StorageGroup')
            );
        }
        $path = filter_input(INPUT_POST, 'path') ?: '/images/';
        $ftppath = filter_input(INPUT_POST, 'ftppath') ?: '/images/';
        $snapinpath = filter_input(INPUT_POST, 'snapinpath') ?: '/opt/fog/snapins/';
        $sslpath = filter_input(INPUT_POST, 'sslpath') ?: '/opt/fog/snapins/ssl/';
        $bitrate = filter_input(INPUT_POST, 'bitrate');
        $helloInterval = filter_input(INPUT_POST, 'helloInterval');
        $interface = filter_input(INPUT_POST, 'interface') ?: 'eth0';
        $user = filter_input(INPUT_POST, 'user');
        $pass = filter_input(INPUT_POST, 'pass');
        $fields = array(
            '<label for="name">'
            . self::$foglang['SNName']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="desc">'
            . self::$foglang['SNDesc']
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desc" autocomplete="off" '
            . 'class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="ip">'
            . self::$foglang['IPAdr']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ip" id="ip" value="'
            . $ip
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="webroot">'
            . _('Web root')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="webroot" id="webroot" value="'
            . $webroot
            . '" class="form-control" autocomplete="off"/>'
            . '</div>',
            '<label for="maxClients">'
            . self::$foglang['MaxClients']
            . '</div>' => '<div class="input-group">'
            . '<input type="number" name="maxClients" id="maxClients" value="'
            . $maxClients
            . '" class="form-control" autocomplete="off" required/>'
            . '</div>',
            '<label for="ismaster">'
            . self::$foglang['IsMasterNode']
            . '</label>' => '<div class="col-xs-1">'
            . '<input type="checkbox" name="isMaster" '
            . 'id="ismaster"'
            . $ismaster
            . '/>'
            . '</div>'
            . '<div class="col-xs-1">'
            . '<i class="icon fa fa-question hand" title="'
            . self::$foglang['CautionPhrase']
            . '" data-toggle="tooltip" data-placement="right"></i>'
            . '</div>',
            '<label for="bandwidth">'
            . self::$foglang['BandwidthReplication']
            . ' (Kbps)'
            . '</label>' => '<div class="input-group">'
            . '<i class="input-group-addon icon fa fa-question hand" title="'
            . self::$foglang['BandwidthRepHelp']
            . '" data-toggle="tooltip" data-placement="left"></i>'
            . '<input type="number" name="bandwidth" id="bandwidth" '
            . 'value="'
            . $bandwidth
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="storagegroupID">'
            . self::$foglang['SG']
            . '</label>' => self::getClass('StorageGroupManager')->buildSelectBox(
                $storagegroupID,
                'storagegroupID'
            ),
            '<label for="path">'
            . self::$foglang['ImagePath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="path" id="path" value="'
            . $path
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="ftppath">'
            . self::$foglang['FTPPath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ftppath" id="ftppath" value="'
            . $ftppath
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="snapinpath">'
            . self::$foglang['SnapinPath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="snapinpath" id="snapinpath" value="'
            . $snapinpath
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="sslpath">'
            . self::$foglang['SSLPath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="sslpath" id="sslpath" value="'
            . $sslpath
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="bitrate">'
            . _('Bitrate')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="bitrate" id="bitrate" value="'
            . $bitrate
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="helloInterval">'
            . _('Rexmit Hello Interval')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="helloInterval" id="helloInterval" value="'
            . $helloInterval
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="interface">'
            . self::$foglang['Interface']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="interface" id="interface" value="'
            . $interface
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="isen">'
            . self::$foglang['IsEnabled']
            . '</label>' => '<input type="checkbox" name="isEnabled" id="isen" '
            . 'checked/>',
            '<label for="isgren">'
            . self::$foglang['IsGraphEnabled']
            . '<br/>'
            . '('
            . self::$foglang['OnDash']
            . ')'
            . '</label>' => '<input type="checkbox" name="isGraphEnabled" '
            . 'id="isgren" checked/>',
            '<label for="user">'
            . self::$foglang['ManUser']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="user" id="user" value="'
            . $user
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="pass">'
            . self::$foglang['ManPass']
            . '</label>' => '<div class="input-group">'
            . '<input type="password" name="pass" id="pass" value="'
            . $pass
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="add">'
            . _('Create Storage Node')
            . '</label>' => '<button name="add" id="add" type="submit" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_ADD',
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
        echo _('New Storage Node');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        echo '<input type="text" name="fakeusernameremembered" class="fakes"/>';
        echo '<input type="text" name="fakepasswordremembered" class="fakes"/>';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
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
        $helloInterval = filter_input(INPUT_POST, 'helloInterval');
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
                ->set('helloInterval', $helloInterval)
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
     * Edit existing nodes.
     *
     * @return void
     */
    public function editStorageNode()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $name = filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name');
        $desc = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
        $ip = filter_input(INPUT_POST, 'ip') ?:
            $this->obj->get('ip');
        $webroot = filter_input(INPUT_POST, 'webroot') ?:
            $this->obj->get('webroot');
        $maxClients = (int)filter_input(INPUT_POST, 'maxClients') ?:
            $this->obj->get('maxClients');
        $bandwidth = filter_input(INPUT_POST, 'bandwidth') ?:
            $this->obj->get('bandwidth');
        $storagegroupID = (int)filter_input(INPUT_POST, 'storagegroupID') ?:
            $this->obj->get('storagegroupID');
        $path = filter_input(INPUT_POST, 'path') ?:
            $this->obj->get('path');
        $ftppath = filter_input(INPUT_POST, 'ftppath') ?:
            $this->obj->get('ftppath');
        $snapinpath = filter_input(INPUT_POST, 'snapinpath') ?:
            $this->obj->get('snapinpath');
        $sslpath = filter_input(INPUT_POST, 'sslpath') ?:
            $this->obj->get('sslpath');
        $bitrate = filter_input(INPUT_POST, 'bitrate') ?:
            $this->obj->get('bitrate');
        $helloInterval = filter_input(INPUT_POST, 'helloInterval') ?:
            $this->obj->get('helloInterval');
        $interface = filter_input(INPUT_POST, 'interface') ?:
            $this->obj->get('interface');
        $user = filter_input(INPUT_POST, 'user') ?:
            $this->obj->get('user');
        $pass = filter_input(INPUT_POST, 'pass') ?:
            $this->obj->get('pass');
        $isgren = isset($_POST['isGraphEnabled']) ?:
            $this->obj->get('isGraphEnabled');
        $isen = isset($_POST['isEnabled']) ?:
            $this->obj->get('isEnabled');
        $ismaster = isset($_POST['isMaster']) ?:
            $this->obj->get('isMaster');
        if ($isgren) {
            $isgren = ' checked';
        }
        if ($isen) {
            $isen = ' checked';
        }
        if ($ismaster) {
            $ismaster = ' checked';
        }
        $this->title = _('Storage Node General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<label for="name">'
            . self::$foglang['SNName']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="desc">'
            . self::$foglang['SNDesc']
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="desc" autocomplete="off" '
            . 'class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="ip">'
            . self::$foglang['IPAdr']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ip" id="ip" value="'
            . $ip
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="webroot">'
            . _('Web root')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="webroot" id="webroot" value="'
            . $webroot
            . '" class="form-control" autocomplete="off"/>'
            . '</div>',
            '<label for="maxClients">'
            . self::$foglang['MaxClients']
            . '</div>' => '<div class="input-group">'
            . '<input type="number" name="maxClients" id="maxClients" value="'
            . $maxClients
            . '" class="form-control" autocomplete="off" required/>'
            . '</div>',
            '<label for="ismaster">'
            . self::$foglang['IsMasterNode']
            . '</label>' => '<div class="col-xs-1">'
            . '<input type="checkbox" name="isMaster" '
            . 'id="ismaster"'
            . $ismaster
            . '/>'
            . '</div>'
            . '<div class="col-xs-1">'
            . '<i class="icon fa fa-question hand" title="'
            . self::$foglang['CautionPhrase']
            . '" data-toggle="tooltip" data-placement="right"></i>'
            . '</div>',
            '<label for="bandwidth">'
            . self::$foglang['BandwidthReplication']
            . ' (Kbps)'
            . '</label>' => '<div class="input-group">'
            . '<i class="input-group-addon icon fa fa-question hand" title="'
            . self::$foglang['BandwidthRepHelp']
            . '" data-toggle="tooltip" data-placement="left"></i>'
            . '<input type="number" name="bandwidth" id="bandwidth" '
            . 'value="'
            . $bandwidth
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="storagegroupID">'
            . self::$foglang['SG']
            . '</label>' => self::getClass('StorageGroupManager')->buildSelectBox(
                $storagegroupID,
                'storagegroupID'
            ),
            '<label for="path">'
            . self::$foglang['ImagePath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="path" id="path" value="'
            . $path
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="ftppath">'
            . self::$foglang['FTPPath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ftppath" id="ftppath" value="'
            . $ftppath
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="snapinpath">'
            . self::$foglang['SnapinPath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="snapinpath" id="snapinpath" value="'
            . $snapinpath
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="sslpath">'
            . self::$foglang['SSLPath']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="sslpath" id="sslpath" value="'
            . $sslpath
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="bitrate">'
            . _('Bitrate')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="bitrate" id="bitrate" value="'
            . $bitrate
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="helloInterval">'
            . _('Remit Hello Interval')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="helloInterval" id="helloInterval" value="'
            . $helloInterval
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="interface">'
            . self::$foglang['Interface']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="interface" id="interface" value="'
            . $interface
            . '" autocomplete="off" class="form-control"/>'
            . '</div>',
            '<label for="isen">'
            . self::$foglang['IsEnabled']
            . '</label>' => '<input type="checkbox" name="isEnabled" id="isen" '
            . $isen
            . '/>',
            '<label for="isgren">'
            . self::$foglang['IsGraphEnabled']
            . '<br/>'
            . '('
            . self::$foglang['OnDash']
            . ')'
            . '</label>' => '<input type="checkbox" name="isGraphEnabled" '
            . 'id="isgren"'
            . $isgren
            . '/>',
            '<label for="user">'
            . self::$foglang['ManUser']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="user" id="user" value="'
            . $user
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="pass">'
            . self::$foglang['ManPass']
            . '</label>' => '<div class="input-group">'
            . '<input type="password" name="pass" id="pass" value="'
            . $pass
            . '" autocomplete="off" class="form-control" required/>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button name="update" id="update" type="submit" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<div class="col-xs-9 tab-content">';
        echo '<div class="tab-pane fade in active" id="node-general">';
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
        echo '<input type="text" name="fakeusernameremembered" class="fakes"/>';
        echo '<input type="text" name="fakepasswordremembered" class="fakes"/>';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually store the edits.
     *
     * @return void
     */
    public function editStorageNodePost()
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
        $helloInterval = filter_input(INPUT_POST, 'helloInterval');
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_EDIT_POST',
                array('StorageNode' => &$this->obj)
            );
        try {
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
                ->set('helloInterval', $helloInterval)
                ->set('interface', $interface)
                ->set('isGraphEnabled', $isgren)
                ->set('isEnabled', $isen)
                ->set('user', $user)
                ->set('pass', $pass)
                ->set('bandwidth', $bandwidth);
            if (!$this->obj->save()) {
                throw new Exception(_('Storage Node update failed!'));
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
                                (array) $this->obj->get('id'),
                                (array) $masternodes
                            )
                        ),
                        '',
                        array('isMaster' => 0)
                    );
            }
            $hook = 'STORAGE_NODE_EDIT_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Storage Node updated!'),
                    'title' => _('Storage Node Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_NODE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Storage Node Update Fail')
                )
            );
        }
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
        array_walk($fields, $this->fieldsToData);
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
     * Storage group display page.
     *
     * @return void
     */
    public function storageGroup()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = self::$foglang['AllSG'];
        Route::listem('storagegroup');
        $StorageGroups = json_decode(
            Route::getData()
        );
        $StorageGroups = $StorageGroups->storagegroups;
        foreach ((array)$StorageGroups as &$StorageGroup) {
            $this->data[] = array(
                'name' => $StorageGroup->name,
                'id' => $StorageGroup->id,
                'max_clients' => $StorageGroup->totalsupportedclients,
            );
            unset($StorageGroup);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler2"/>'
            . '<label for="toggler2"></label>',
            self::$foglang['SG'],
            _('Max'),
        );
        $this->templates = array(
            '<input type="checkbox" name="storage[]" value='
            . '"${id}" class="toggle-action" id="group-${id}"/>'
            . '<label for="group-${id}"></label>',
            sprintf(
                '<a href="?node=%s&sub=editStorageGroup&%s=${id}" title='
                . '"%s">${name}</a>',
                $this->node,
                $this->id,
                self::$foglang['Edit']
            ),
            '${max_clients}',
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 22
            ),
            array(),
            array(
                'width' => 20
            ),
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
        echo '<div class="col-xs-9">';
        $this->indexDivDisplay(true, 'group');
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
     * Create a new storage group.
     *
     * @return void
     */
    public function addStorageGroup()
    {
        $this->title = self::$foglang['AddSG'];
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        $fields = array(
            '<label for="name">'
            . self::$foglang['SGName']
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" value="'
            . $name
            . '" class="form-control" required/>'
            . '</div>',
            '<label for="description">'
            . self::$foglang['SGDesc']
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" id="description" class="form-control">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="add">'
            . _('Create Storage Group')
            . '</label>' => '<button name="add" id="add" type="submit" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'STORAGE_GROUP_ADD',
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
        echo _('New Storage Group');
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
     * Actually create the new group.
     *
     * @return void
     */
    public function addStorageGroupPost()
    {
        self::$HookManager->processEvent('STORAGE_GROUP_ADD_POST');
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
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
                throw new Exception(self::$foglang['DBupfailed']);
            }
            $hook = 'STORAGE_GROUP_ADD_POST_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => self::$foglang['SGCreated'],
                    'title' => _('Storage Group Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_GROUP_ADD_POST_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Storage Group Create Fail')
                )
            );
        }
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
     * Edit a storage group.
     *
     * @return void
     */
    public function editStorageGroup()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('Storage Group General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $name = filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name');
        $desc = filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description');
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
        array_walk($fields, $this->fieldsToData);
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
        self::$HookManager
            ->processEvent(
                'STORAGE_GROUP_EDIT_POST',
                array(
                    'StorageGroup' => &$this->obj
                )
            );
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        try {
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
            if (!$this->obj->save()) {
                throw new Exception(_('Storage Group update failed!'));
            }
            $hook = 'STORAGE_GROUP_EDIT_POST_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Storage Group updated!'),
                    'title' => _('Storage Group Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_GROUP_EDIT_POST_FAIL';
            $msg = array(
                'error' => $e->getMessage(),
                'title' => _('Storage Group Update Fail')
            );
        }
        self::$HookManager
            ->processEvent(
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
        array_walk($fields, $this->fieldsToData);
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
