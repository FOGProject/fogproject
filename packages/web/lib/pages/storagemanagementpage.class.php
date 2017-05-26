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
    // Base variables
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
                        '?node=%s&sub=%s&id=%s',
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
                        '?node=%s&sub=%s&id=%s',
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
        $this->title = self::$foglang['AllSN'];
        foreach ((array)self::getClass('StorageNodeManager')
            ->find() as &$StorageNode
        ) {
            $StorageGroup = $StorageNode->getStorageGroup();
            $this->data[] = array(
                'name' => $StorageNode->get('name'),
                'id' => $StorageNode->get('id'),
                'isMasterText' => (
                    $StorageNode->get('isMaster') ?
                    _('Yes') :
                    _('No')
                ),
                'isEnabledText' => (
                    $StorageNode->get('isEnabled') ?
                    _('Yes') :
                    _('No')
                ),
                'storage_group' => $StorageGroup->get('name'),
                'max_clients' => $StorageNode->get('maxClients'),
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
                'class' => 'l filter-false',
                'width' => 22
            ),
            array(),
            array(
                'class' => 'c',
                'width' => 90
            ),
            array(
                'class' => 'c',
                'width' => 90),
            array(
                'class' => 'c',
                'width' => 90
            ),
            array('class' => 'c'),
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

        $this->render();
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
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<input type="text" name="fakeusernameremembered"/>' =>
            '<input type="text" name="fakepasswordremembered"/>',
            self::$foglang['SNName'] => sprintf(
                '<input type="text" name="name" value="%s" autocomplete="off"/>*',
                $_REQUEST['name']
            ),
            self::$foglang['SNDesc'] => sprintf(
                '<textarea name="description" rows="8" cols='
                . '"40" autocomplete="off">%s</textarea>',
                $_REQUEST['description']
            ),
            self::$foglang['IPAdr'] => sprintf(
                '<input type="text" name="ip" value="%s" autocomplete="off"/>*',
                $_REQUEST['ip']
            ),
            _('Web root')  => sprintf(
                '<input type="text" name="webroot" value="%s" autocomplete="off"/>*',
                (
                    isset($_REQUEST['webroot']) ?
                    $_REQUEST['webroot'] :
                    '/fog'
                )
            ),
            self::$foglang['MaxClients'] => sprintf(
                '<input type="text" name="maxClients" value='
                . '"%s" autocomplete="off"/>*',
                $_REQUEST['maxClients']
            ),
            self::$foglang['IsMasterNode'] => sprintf(
                '<input type="checkbox" name="isMaster" id="'
                . 'ismaster"%s/><label for="ismaster"></label>'
                . '&nbsp;&nbsp;%s',
                (
                    isset($_REQUEST['isMaster']) ?
                    ' checked' :
                    ''
                ),
                sprintf(
                    '<i class="icon fa fa-question hand" title="%s"></i>',
                    self::$foglang['CautionPhrase']
                )
            ),
            sprintf(
                '%s (Kbps)',
                self::$foglang['BandwidthReplication']
            ) => sprintf(
                '<input type="text" name="bandwidth" value="%s" autocomplete='
                . '"off"/>&nbsp;&nbsp;%s',
                $_REQUEST['bandwidth'],
                sprintf(
                    '<i class="icon fa fa-question hand" title="%s"></i>',
                    self::$foglang['BandwidthRepHelp']
                )
            ),
            self::$foglang['SG'] => self::getClass(
                'StorageGroupManager'
            )->buildSelectBox(
                (
                    isset($_REQUEST['storagegroupID'])
                    && is_numeric($_REQUEST['storagegroupID'])
                    && $_REQUEST['storagegroupID'] > 0 ?
                    $_REQUEST['storagegroupID'] :
                    1
                ),
                'storagegroupID'
            ),
            self::$foglang['ImagePath'] => sprintf(
                '<input type="text" name="path" value="%s" autocomplete="off"/>',
                (
                    isset($_REQUEST['path'])
                    && $_REQUEST['path'] ?
                    $_REQUEST['path'] :
                    '/images/'
                )
            ),
            self::$foglang['FTPPath'] => sprintf(
                '<input type="text" name="ftppath" value="%s" autocomplete="off"/>',
                (
                    isset($_REQUEST['ftppath'])
                    && $_REQUEST['ftppath'] ?
                    $_REQUEST['ftppath'] :
                    '/images/'
                )
            ),
            self::$foglang['SnapinPath'] => sprintf(
                '<input type="text" name="snapinpath" value='
                . '"%s" autocomplete="off"/>',
                (
                    isset($_REQUEST['snapinpath'])
                    && $_REQUEST['snapinpath'] ?
                    $_REQUEST['snapinpath'] :
                    '/opt/fog/snapins/'
                )
            ),
            self::$foglang['SSLPath'] => sprintf(
                '<input type="text" name="sslpath" value='
                . '"%s" autocomplete="off"/>',
                (
                    isset($_REQUEST['sslpath'])
                    && $_REQUEST['sslpath'] ?
                    $_REQUEST['sslpath'] :
                    '/opt/fog/snapins/ssl/'
                )
            ),
            _('Bitrate') => sprintf(
                '<input type="text" name="bitrate" value="%s" autocomplete="off"/>',
                $_REQUEST['bitrate']
            ),
            self::$foglang['Interface'] => sprintf(
                '<input type="text" name="interface" value='
                . '"%s" autocomplete="off"/>',
                (
                    isset($_REQUEST['interface'])
                    && $_REQUEST['interface'] ?
                    $_REQUEST['interface'] :
                    'eth0'
                )
            ),
            self::$foglang['IsEnabled'] => '<input type="checkbox" name='
            . '"isEnabled" id="isen" checked/><label for="isen"></label>',
            sprintf(
                '%s<br/><small>(%s)</small>',
                self::$foglang['IsGraphEnabled'],
                self::$foglang['OnDash']
            ) => '<input type="checkbox" name="isGraphEnabled" id="isgren"'
           . ' checked/><label for="isgren"></label>',
            self::$foglang['ManUser'] => sprintf(
                '<input type="text" name="user" value="%s" autocomplete="off"/>*',
                $_REQUST['user']
            ),
            self::$foglang['ManPass'] => sprintf(
                '<input type="password" name="pass" value='
                . '"%s" autocomplete="off"/>*',
                $_REQUEST['pass']
            ),
            '&nbsp;' => sprintf(
                '<input name="add" type="submit" value="%s"/>',
                self::$foglang['Add']
            )
        );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($field, $input);
        }
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
        $this->render();
        echo '</form>';
    }
    /**
     * Actually save the new node.
     *
     * @return void
     */
    public function addStorageNodePost()
    {
        self::$HookManager->processEvent('STORAGE_NODE_ADD_POST');
        try {
            if (empty($_REQUEST['name'])) {
                throw new Exception(self::$foglang['StorageNameRequired']);
            }
            if (self::getClass('StorageNodeManager')->exists($_REQUEST['name'])) {
                throw new Exception(self::$foglang['StorageNameExists']);
            }
            if (empty($_REQUEST['ip'])) {
                throw new Exception(self::$foglang['StorageIPRequired']);
            }
            if (empty($_REQUEST['maxClients'])) {
                throw new Exception(self::$foglang['StorageClientsRequired']);
            }
            if (empty($_REQUEST['interface'])) {
                throw new Exception(self::$foglang['StorageIntRequired']);
            }
            if (empty($_REQUEST['user'])) {
                throw new Exception(self::$foglang['StorageUserRequired']);
            }
            if (empty($_REQUEST['pass'])) {
                throw new Exception(self::$foglang['StoragePassRequired']);
            }
            if ($_REQUEST['bandwidth']
                && !(is_numeric($_REQUEST['bandwidth'])
                && $_REQUEST['bandwidth'] > 0)
            ) {
                throw new Exception(
                    _('Bandwidth should be numeric and greater than 0')
                );
            }
            $StorageNode = self::getClass('StorageNode')
                ->set('name', $_REQUEST['name'])
                ->set('description', $_REQUEST['description'])
                ->set('ip', $_REQUEST['ip'])
                ->set('webroot', $_REQUEST['webroot'])
                ->set('maxClients', $_REQUEST['maxClients'])
                ->set('isMaster', isset($_REQUEST['isMaster']))
                ->set('storagegroupID', $_REQUEST['storagegroupID'])
                ->set('path', $_REQUEST['path'])
                ->set('ftppath', $_REQUEST['ftppath'])
                ->set('snapinpath', $_REQUEST['snapinpath'])
                ->set('sslpath', $_REQUEST['sslpath'])
                ->set('bitrate', $_REQUEST['bitrate'])
                ->set('interface', $_REQUEST['interface'])
                ->set('isGraphEnabled', isset($_REQUEST['isGraphEnabled']))
                ->set('isEnabled', isset($_REQUEST['isEnabled']))
                ->set('user', $_REQUEST['user'])
                ->set('pass', $_REQUEST['pass'])
                ->set('bandwidth', $_REQUEST['bandwidth']);
            if (!$StorageNode->save()) {
                throw new Exception(self::$foglang['DBupfailed']);
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
            $msg = self::$foglang['SNCreated'];
            $url = sprintf(
                '?node=%s&sub=edit&%s=%s',
                $this->node,
                $this->id,
                $StorageNode->get('id')
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_NODE_ADD_FAIL';
            $msg = $e->getMessage();
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageNode' => &$StorageNode)
            );
        self::setMessage($msg);
        self::redirect($url);
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
            self::$foglang['Edit'],
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            '<input type="text" name="fakeusernameremembered"/>' =>
            '<input type="text" name="fakepasswordremembered"/>',
            self::$foglang['SNName'] => sprintf(
                '<input type="text" name="name" value="%s" autocomplete="off"/>*',
                $this->obj->get('name')
            ),
            self::$foglang['SNDesc'] => sprintf(
                '<textarea name="description" rows="8" cols='
                . '"40" autocomplete="off">%s</textarea>',
                $this->obj->get('description')
            ),
            self::$foglang['IPAdr'] => sprintf(
                '<input type="text" name="ip" value="%s" autocomplete="off"/>*',
                $this->obj->get('ip')
            ),
            _('Web root')  => sprintf(
                '<input type="text" name="webroot" value="%s" autocomplete="off"/>*',
                $this->obj->get('webroot')
            ),
            self::$foglang['MaxClients'] => sprintf(
                '<input type="text" name="maxClients" value='
                . '"%s" autocomplete="off"/>*',
                $this->obj->get('maxClients')
            ),
            self::$foglang['IsMasterNode'] => sprintf(
                '<input type="checkbox" name="isMaster" id="ismaster"%s/>'
                . '<label for="ismaster"></label>'
                . '&nbsp;&nbsp;%s',
                (
                    $this->obj->get('isMaster') > 0 ?
                    ' checked' :
                    ''
                ),
                sprintf(
                    '<i class="icon fa fa-question hand" title="%s"></i>',
                    self::$foglang['CautionPhrase']
                )
            ),
            sprintf(
                '%s (Kbps)',
                self::$foglang['BandwidthReplication']
            ) => sprintf(
                '<input type="text" name="bandwidth" value="%s" autocomplete='
                . '"off"/>&nbsp;&nbsp;%s',
                $this->obj->get('bandwidth'),
                sprintf(
                    '<i class="icon fa fa-question hand" title="%s"></i>',
                    self::$foglang['BandwidthRepHelp']
                )
            ),
            self::$foglang['SG'] => self::getClass(
                'StorageGroupManager'
            )->buildSelectBox(
                $this->obj->get('storagegroupID'),
                'storagegroupID'
            ),
            self::$foglang['ImagePath'] => sprintf(
                '<input type="text" name="path" value="%s" autocomplete="off"/>',
                $this->obj->get('path')
            ),
            self::$foglang['FTPPath'] => sprintf(
                '<input type="text" name="ftppath" value="%s" autocomplete="off"/>',
                $this->obj->get('ftppath')
            ),
            self::$foglang['SnapinPath'] => sprintf(
                '<input type="text" name="snapinpath" value='
                . '"%s" autocomplete="off"/>',
                $this->obj->get('snapinpath')
            ),
            self::$foglang['SSLPath'] => sprintf(
                '<input type="text" name="sslpath" value='
                . '"%s" autocomplete="off"/>',
                $this->obj->get('sslpath')
            ),
            _('Bitrate') => sprintf(
                '<input type="text" name="bitrate" value="%s" autocomplete="off"/>',
                $this->obj->get('bitrate')
            ),
            self::$foglang['Interface'] => sprintf(
                '<input type="text" name="interface" value='
                . '"%s" autocomplete="off"/>',
                $this->obj->get('interface')
            ),
            self::$foglang['IsEnabled'] => sprintf(
                '<input type="checkbox" name="isEnabled" id="isen"%s/>'
                . '<label for="isen"></label>',
                (
                    $this->obj->get('isEnabled') > 0 ?
                    ' checked' :
                    ''
                )
            ),
            sprintf(
                '%s<br/><small>(%s)</small>',
                self::$foglang['IsGraphEnabled'],
                self::$foglang['OnDash']
            ) => sprintf(
                '<input type="checkbox" name="isGraphEnabled" id="isgren"%s/>'
                . '<label for="isgren"></label>',
                (
                    $this->obj->get('isGraphEnabled') > 0 ?
                    ' checked' :
                    ''
                )
            ),
            self::$foglang['ManUser'] => sprintf(
                '<input type="text" name="user" value="%s" autocomplete="off"/>*',
                $this->obj->get('user')
            ),
            self::$foglang['ManPass'] => sprintf(
                '<input type="password" name="pass" value='
                . '"%s" autocomplete="off"/>*',
                $this->obj->get('pass')
            ),
            '&nbsp;' => sprintf(
                '<input name="update" type="submit" value="%s"/>',
                self::$foglang['Update']
            )
        );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input, $field);
        }
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
        $this->render();
        echo "</form>";
    }
    /**
     * Actually store the edits.
     *
     * @return void
     */
    public function editStorageNodePost()
    {
        self::$HookManager
            ->processEvent(
                'STORAGE_NODE_EDIT_POST',
                array('StorageNode' => &$this->obj)
            );
        try {
            $name = trim($_REQUEST['name']);
            $ip = trim($_REQUEST['ip']);
            $maxClients = (int)trim($_REQUEST['maxClients']);
            $interface = trim($_REQUEST['interface']);
            $user = trim($_REQUEST['user']);
            $pass = trim($_REQUEST['pass']);
            $bandwidth = trim($_REQUEST['bandwidth']);
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
            if (is_numeric($bandwidth)
                && $bandwidth < 0
            ) {
                throw new Exception(_('Bandwidth should be greater than 0'));
            }
            $this->obj
                ->set('name', $name)
                ->set('description', $_REQUEST['description'])
                ->set('ip', $ip)
                ->set('webroot', $_REQUEST['webroot'])
                ->set('maxClients', $maxClients)
                ->set('isMaster', isset($_REQUEST['isMaster']))
                ->set('storagegroupID', $_REQUEST['storagegroupID'])
                ->set('path', $_REQUEST['path'])
                ->set('ftppath', $_REQUEST['ftppath'])
                ->set('snapinpath', $_REQUEST['snapinpath'])
                ->set('sslpath', $_REQUEST['sslpath'])
                ->set('bitrate', $_REQUEST['bitrate'])
                ->set('interface', $interface)
                ->set('isGraphEnabled', isset($_REQUEST['isGraphEnabled']))
                ->set('isEnabled', isset($_REQUEST['isEnabled']))
                ->set('user', $user)
                ->set('pass', $pass)
                ->set('bandwidth', $bandwidth);
            if (!$this->obj->save()) {
                throw new Exception(self::$foglang['DBupfailed']);
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
            $msg = self::$foglang['SNUpdated'];
        } catch (Exception $e) {
            $hook = 'STORAGE_NODE_EDIT_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageNode' => &$this->obj)
            );
        self::setMessage($msg);
        self::redirect($this->formAction);
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
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            sprintf(
                '%s <b>%s</b>',
                self::$foglang['ConfirmDel'],
                $this->obj->get('name')
            ) => sprintf(
                '<input type="submit" value="%s"/>',
                $this->title
            )
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($field, $input);
        }
        unset($input);
        printf(
            '<form method="post" action="%s" class="c">',
            $this->formAction
        );
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
        $this->render();
        echo '</form>';
    }
    /**
     * Actually delete the node.
     *
     * @return void
     */
    public function deleteStorageNodePost()
    {
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
        $this->title = self::$foglang['AllSG'];
        foreach ((array)self::getClass('StorageGroupManager')
            ->find() as &$StorageGroup
        ) {
            $this->data[] = array(
                'name' => $StorageGroup->get('name'),
                'id' => $StorageGroup->get('id'),
                'max_clients' => $StorageGroup->getTotalSupportedClients(),
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
                'class' => 'l filter-false',
                'width' => 22
            ),
            array(),
            array(
                'class' => 'c',
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
        $this->render();
        $this->data = array();
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
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            self::$foglang['SGName'] => sprintf(
                '<input type="text" name="name" value="%s"/>',
                $_REQUEST['name']
            ),
            self::$foglang['SGDesc'] => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            '&nbsp;' => sprintf(
                '<input name="add" type="submit" value="%s"/>',
                self::$foglang['Add']
            )
        );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input
            );
            unset($field, $input);
        }
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
        $this->render();
        echo '</form>';
    }
    /**
     * Actually create the new group.
     *
     * @return void
     */
    public function addStorageGroupPost()
    {
        self::$HookManager->processEvent('STORAGE_GROUP_ADD_POST');
        try {
            if (empty($_REQUEST['name'])) {
                throw new Exception(self::$foglang['SGNameReq']);
            }
            if (self::getClass('StorageGroupManager')->exists($_REQUEST['name'])) {
                throw new Exception(self::$foglang['SGExist']);
            }
            $StorageGroup = self::getClass('StorageGroup')
                ->set('name', $_REQUEST['name'])
                ->set('description', $_REQUEST['description']);
            if (!$StorageGroup->save()) {
                throw new Exception(self::$foglang['DBupfailed']);
            }
            $hook = 'STORAGE_GROUP_ADD_POST_SUCCESS';
            $msg = self::$foglang['SGCreated'];
            $url = sprintf(
                '?node=%s&sub=editStorageGroup&%s=%s',
                $this->node,
                $this->id,
                $StorageGroup->get('id')
            );
        } catch (Exception $e) {
            $hook = 'STORAGE_GROUP_ADD_POST_FAIL';
            $msg = $e->getMessage();
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageGroup' => &$StorageGroup)
            );
        self::setMessage($msg);
        self::redirect($url);
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
            self::$foglang['Edit'],
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            self::$foglang['SGName'] => sprintf(
                '<input type="text" name="name" value="%s"/>',
                $this->obj->get('name')
            ),
            self::$foglang['SGDesc'] => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" value="%s"/>',
                self::$foglang['Update']
            )
        );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($field, $input);
        }
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
        $this->render();
        echo '</form>';
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
        try {
            $name = trim($_REQUEST['name']);
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
                ->set('description', $_REQUEST['description']);
            if (!$this->obj->save()) {
                throw new Exception(self::$foglang['DBupfailed']);
            }
            $hook = 'STORAGE_GROUP_EDIT_POST_SUCCESS';
            $msg = self::$foglang['SGUpdated'];
        } catch (Exception $e) {
            $hook = 'STORAGE_GROUP_EDIT_POST_FAIL';
            $msg = $e->getMessage();
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('StorageGroup' => &$this->obj)
            );
        self::setMessage($msg);
        self::redirect($this->formAction);
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
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            sprintf(
                '%s <b>%s</b>',
                self::$foglang['ConfirmDel'],
                $this->obj->get('name')
            ) => sprintf(
                '<input name="delete" type="submit" value="%s"/>',
                $this->title
            )
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($field, $input);
        }
        printf(
            '<form method="post" action="%s" class="c">',
            $this->formAction
        );
        echo '<div id="deleteDiv"></div>';
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
        $this->render();
        echo '</form>';
    }
    /**
     * Actually Delete the group.
     *
     * @return void
     */
    public function deleteStorageGroupPost()
    {
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
