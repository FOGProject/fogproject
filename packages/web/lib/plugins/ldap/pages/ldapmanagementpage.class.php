<?php
/**
 * The ldap management page
 *
 * PHP version 5
 *
 * @category LDAPPluginHook
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The ldap management page
 *
 * PHP version 5
 *
 * @category LDAPPluginHook
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPManagementPage extends FOGPage
{
    /**
     * The node that uses this page
     *
     * @var string
     */
    public $node = 'ldap';
    /**
     * Initialize our page
     *
     * @param string $name the name to use
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('LDAP Management');
        self::$foglang['ExportLdap'] = _('Export LDAPs');
        self::$foglang['ImportLdap'] = _('Import LDAPs');
        parent::__construct($name);
        global $id;
        global $sub;
        $this->menu['PluginConfiguration'] = _('Plugin Configuration');
        switch ($sub) {
        case 'PluginConfiguration':
            parent::__construct($this->name);
            break;
        default:
    }
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#ldap-gen" => self::$foglang['General'],
                "$this->delformat" => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('LDAP Connection Name') => $this->obj->get('name'),
                _('LDAP Server Address') => $this->obj->get('address'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('LDAP Connection  Name'),
            _('LDAP Server Description'),
            _('LDAP Server'),
            _('Port'),
            _('Admin Group'),
        );
        $this->templates = array(
            '<input type="checkbox" name="ldap[]" value="${id}" '
            . 'class="toggle-action"/>',
            '<a href="?node=ldap&sub=edit&id=${id}" data-toggle="tooltip" '
            . 'data-placement="right" title="'
            . _('Edit')
            . ' '
            . '${name}">${name}</a>',
            '${description}',
            '${address}',
            '${port}',
            '${adminGroup}',
        );
        $this->attributes = array(
            array(
                'class' => 'parser-false filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array(),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $LDAP the object to use
         *
         * @return void
         */
        self::$returnData = function (&$LDAP) {
            $this->data[] = array(
                'id' => $LDAP->id,
                'name' => $LDAP->name,
                'description' => $LDAP->description,
                'address' => $LDAP->address,
                'searchDN' => $LDAP->DN,
                'port' => $LDAP->port,
                'userNamAttr' => $LDAP->userNamAttr,
                'grpMemberAttr' => $LDAP->grpMemberAttr,
                'grpSearchDN' => $LDAP->grpSearchDN,
                'adminGroup' => $LDAP->adminGroup,
                'userGroup' => $LDAP->userGroup,
                'searchScope' => $LDAP->searchScope,
                'bindDN' => $LDAP->bindDN,
                'bindPwd' => $LDAP->bindPwd,
                'useGroupMatch' => $LDAP->useGroupMatch,
            );
            unset($LDAP);
        };
    }
    /**
     * Create new ldap
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('New LDAP Server');
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $address = filter_input(
            INPUT_POST,
            'address'
        );
        $port = filter_input(
            INPUT_POST,
            'port'
        );
        $searchDN = filter_input(
            INPUT_POST,
            'searchDN'
        );
        $grpSearchDN = filter_input(
            INPUT_POST,
            'grpSearchDN'
        );
        $adminGroup = filter_input(
            INPUT_POST,
            'adminGroup'
        );
        $userGroup = filter_input(
            INPUT_POST,
            'userGroup'
        );
        $userNamAttr = filter_input(
            INPUT_POST,
            'userNamAttr'
        );
        $grpMemberAttr = filter_input(
            INPUT_POST,
            'grpMemberAttr'
        );
        $searchScope = filter_input(
            INPUT_POST,
            'searchScope'
        );
        $bindDN = filter_input(
            INPUT_POST,
            'bindDN'
        );
        $bindPwd = filter_input(
            INPUT_POST,
            'bindPwd'
        );
        $searchScopes = array(
            _('Base Only'),
            _('Subtree Only'),
            _('Subree and Below')
        );
        $searchSel = self::selectForm(
            'searchScope',
            $searchScopes,
            $searchScope,
            true
        );
        $ports = self::getSetting('LDAP_PORTS');
        $ports = preg_replace('#\s+#', '', $ports);
        $ports = explode(',', $ports);
        $portssel = self::selectForm(
            'port',
            $ports,
            $port
        );
        $useGroupMatch = isset($_POST['useGroupMatch']);
        $useMatch = (
            $useGroupMatch ?
            ' checked' :
            ''
        );
        $fields = array(
            '<label for="name">'
            . _('LDAP Connection Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="name" name="name" '
            . 'value="'
            . $name
            . '" required/>'
            . '</div>',
            '<label for="desc">'
            . _('LDAP Server Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control" id="desc">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="address">'
            . _('LDAP Server Address')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="address" name="address" '
            . 'value="'
            . $address
            . '" required/>'
            . '</div>',
            '<label for="port">'
            . _('LDAP Server Port')
            . '</label>' => $portssel,
            '<label for="groupmatch">'
            . _('Use Group Matching (recommended)')
            . '</label>' => '<input type="checkbox" '
            . 'name="useGroupMatch" id="groupmatch" checked/>',
            '<label for="searchDN">'
            . _('Search Base DN')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="searchDN" name='
            . '"searchDN" value="'
            . $searchDN
            . '" required/>'
            . '</div>',
            '<label for="grpSearchDN">'
            . _('Group Search DN')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="grpSearchDN" name='
            . '"grpSearchDN" value="'
            . $grpSearchDN
            . '"/>'
            . '</div>',
            '<label for="adminGroup">'
            . _('Admin Group')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="adminGroup" name='
            . '"adminGroup" value="'
            . $adminGroup
            . '"/>'
            . '</div>',
            '<label for="userGroup">'
            . _('Mobile Group')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="userGroup" name='
            . '"userGroup" value="'
            . $userGroup
            . '"/>'
            . '</div>',
            '<label for="inittemplate">'
            . _('Initial Template')
            . '</label>' => '<select class="smaller" id="inittemplate">'
            . '<option value="pick" selected>'
            . _('Pick a template')
            . '</option>'
            . '<option value="msad">'
            . _('Microsoft AD')
            . '</option>'
            . '<option value="open">'
            . _('OpenLDAP')
            . '</option>'
            . '<option value="edir">'
            . _('Generic LDAP')
            . '</option>'
            . '</select>',
            '<label for="userNamAttr">'
            . _('User Name Attribute')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="userNamAttr" name='
            . '"userNamAttr" value="'
            . $userNamAttr
            . '" required/>'
            . '</div>',
            '<label for="grpMemberAttr">'
            . _('Group Member Attribute')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="grpMemberAttr" name='
            . '"grpMemberAttr" value="'
            . $grpMemberAttr
            . '"/>'
            . '</div>',
            '<label for="searchScope">'
            . _('Search Scope')
            . '</label>' => $searchSel,
            '<label for="bindDN">'
            . _('Bind DN')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="bindDN" name="bindDN" '
            . 'value="'
            . $bindDN
            . '"/>'
            . '</div>',
            '<label for="bindPwd">'
            . _('Bind Password')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="password" id="bindPwd" name='
            . '"bindPwd" value="'
            . $bindPwd
            . '"/>'
            . '</div>',
            '<label for="add">'
            . _('Create New LDAP')
            . '</label>' => '<button type="submit" name="add" id="add" '
            . 'class="btn btn-info btn-block">'
            . _('Create')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'LDAP_FIELDS',
                array(
                    'fields' => &$fields,
                    'LDAP' => self::getClass('LDAP')
                )
            );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'LDAP_ADD',
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
     * Create the new item
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('LDAP_ADD');
        $ports = array_map(
            'trim',
            explode(
                ',',
                self::getSetting('LDAP_PORTS')
            )
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $address = filter_input(
            INPUT_POST,
            'address'
        );
        $port = filter_input(
            INPUT_POST,
            'port'
        );
        $searchDN = filter_input(
            INPUT_POST,
            'searchDN'
        );
        $grpSearchDN = filter_input(
            INPUT_POST,
            'grpSearchDN'
        );
        $adminGroup = filter_input(
            INPUT_POST,
            'adminGroup'
        );
        $userGroup = filter_input(
            INPUT_POST,
            'userGroup'
        );
        $userNamAttr = filter_input(
            INPUT_POST,
            'userNamAttr'
        );
        $grpMemberAttr = filter_input(
            INPUT_POST,
            'grpMemberAttr'
        );
        $searchScope = filter_input(
            INPUT_POST,
            'searchScope'
        );
        $bindDN = filter_input(
            INPUT_POST,
            'bindDN'
        );
        $bindPwd = filter_input(
            INPUT_POST,
            'bindPwd'
        );
        $useGroupMatch = (int)isset($_POST['useGroupMatch']);
        try {
            if (!isset($_POST['add'])) {
                throw new Exception(_('Not able to add'));
            }
            if (!is_numeric($searchScope)) {
                $searchScope = 0;
            }
            if (empty($name)) {
                throw new Exception(
                    _('Please enter a name for this LDAP server.')
                );
            }
            if (empty($address)) {
                throw new Exception(
                    _('Please enter a LDAP server address')
                );
            }
            if (empty($searchDN)) {
                throw new Exception(
                    _('Please enter a Search Base DN')
                );
            }
            if (empty($port)) {
                throw new Exception(
                    _('Please select an LDAP port to use')
                );
            }
            if (!in_array($port, $ports)) {
                throw new Exception(
                    _('Please select a valid ldap port')
                );
            }
            if (empty($adminGroup) && empty($userGroup)) {
                throw new Exception(
                    _('Please Enter an admin or mobile lookup name')
                );
            }
            if (empty($userNamAttr)) {
                throw new Exception(
                    _('Please enter a User Name Attribute')
                );
            }
            if (empty($grpMemberAttr)) {
                throw new Exception(
                    _('Please enter a Group Member Attribute')
                );
            }
            if (self::getClass('LDAPManager')->exists($name)) {
                throw new Exception(
                    _('A LDAP setup already exists with this name!')
                );
            }
            $LDAP = self::getClass('LDAP')
                ->set('name', $name)
                ->set('description', $description)
                ->set('address', $address)
                ->set('searchDN', $searchDN)
                ->set('port', $port)
                ->set('userNamAttr', $userNamAttr)
                ->set('grpMemberAttr', $grpMemberAttr)
                ->set('adminGroup', $adminGroup)
                ->set('userGroup', $userGroup)
                ->set('searchScope', $searchScope)
                ->set('bindDN', $bindDN)
                ->set('bindPwd', $bindPwd)
                ->set('useGroupMatch', $useGroupMatch)
                ->set('grpSearchDN', $grpSearchDN);
            if (!$LDAP->save()) {
                throw new Exception(_('Add LDAP server failed!'));
            }
            $hook = 'LDAP_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('LDAP Server added!'),
                    'title' => _('LDAP Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'LDAP_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('LDAP Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('LDAP' => &$LDAP)
            );
        unset($LDAP);
        echo $msg;
        exit;
    }
    /**
     * Display ldap general information.
     *
     * @return void
     */
    public function ldapGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $this->title = _('LDAP General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $name = (
            filter_input(
                INPUT_POST,
                'name'
            ) ?: $this->obj->get('name')
        );
        $description = (
            filter_input(
                INPUT_POST,
                'description'
            ) ?: $this->obj->get('description')
        );
        $address = (
            filter_input(
                INPUT_POST,
                'address'
            ) ?: $this->obj->get('address')
        );
        $port = (
            filter_input(
                INPUT_POST,
                'port'
            ) ?: $this->obj->get('port')
        );
        $searchDN = (
            filter_input(
                INPUT_POST,
                'searchDN'
            ) ?: $this->obj->get('searchDN')
        );
        $grpSearchDN = (
            filter_input(
                INPUT_POST,
                'grpSearchDN'
            ) ?: $this->obj->get('grpSearchDN')
        );
        $adminGroup = (
            filter_input(
                INPUT_POST,
                'adminGroup'
            ) ?: $this->obj->get('adminGroup')
        );
        $userGroup = (
            filter_input(
                INPUT_POST,
                'userGroup'
            ) ?: $this->obj->get('userGroup')
        );
        $userNamAttr = (
            filter_input(
                INPUT_POST,
                'userNamAttr'
            ) ?: $this->obj->get('userNamAttr')
        );
        $grpMemberAttr = (
            filter_input(
                INPUT_POST,
                'grpMemberAttr'
            ) ?: $this->obj->get('grpMemberAttr')
        );
        $searchScope = (
            filter_input(
                INPUT_POST,
                'searchScope'
            ) ?: $this->obj->get('searchScope')
        );
        $bindDN = (
            filter_input(
                INPUT_POST,
                'bindDN'
            ) ?: $this->obj->get('bindDN')
        );
        $bindPwd = (
            filter_input(
                INPUT_POST,
                'bindPwd'
            ) ?: $this->obj->get('bindPwd')
        );
        $searchScopes = array(
            _('Base Only'),
            _('Subtree Only'),
            _('Subree and Below')
        );
        $searchSel = self::selectForm(
            'searchScope',
            $searchScopes,
            $searchScope,
            true
        );
        $ports = array_map(
            'trim',
            explode(
                ',',
                self::getSetting('LDAP_PORTS')
            )
        );
        $portssel = self::selectForm(
            'port',
            $ports,
            $port
        );
        $useGroupMatch = (
            isset($_POST['useGroupMatch']) ?: $this->obj->get('useGroupMatch')
        );
        $useMatch = (
            $useGroupMatch ?
            ' checked' :
            ''
        );
        $fields = array(
            '<label for="name">'
            . _('LDAP Connection Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="name" name="name" '
            . 'value="'
            . $name
            . '" required/>'
            . '</div>',
            '<label for="desc">'
            . _('LDAP Server Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control" id="desc">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="address">'
            . _('LDAP Server Address')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="address" name="address" '
            . 'value="'
            . $address
            . '" required/>'
            . '</div>',
            '<label for="port">'
            . _('LDAP Server Port')
            . '</label>' => $portssel,
            '<label for="groupmatch">'
            . _('Use Group Matching (recommended)')
            . '</label>' => '<input type="checkbox" '
            . 'name="useGroupMatch" id="groupmatch"'
            . $useMatch
            . '/>',
            '<label for="searchDN">'
            . _('Search Base DN')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="searchDN" name='
            . '"searchDN" value="'
            . $searchDN
            . '" required/>'
            . '</div>',
            '<label for="grpSearchDN">'
            . _('Group Search DN')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="grpSearchDN" name='
            . '"grpSearchDN" value="'
            . $grpSearchDN
            . '"/>'
            . '</div>',
            '<label for="adminGroup">'
            . _('Admin Group')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="adminGroup" name='
            . '"adminGroup" value="'
            . $adminGroup
            . '"/>'
            . '</div>',
            '<label for="userGroup">'
            . _('Mobile Group')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="userGroup" name='
            . '"userGroup" value="'
            . $userGroup
            . '"/>'
            . '</div>',
            '<label for="inittemplate">'
            . _('Initial Template')
            . '</label>' => '<select class="smaller" id="inittemplate">'
            . '<option value="pick" selected>'
            . _('Pick a template')
            . '</option>'
            . '<option value="msad">'
            . _('Microsoft AD')
            . '</option>'
            . '<option value="open">'
            . _('OpenLDAP')
            . '</option>'
            . '<option value="edir">'
            . _('Generic LDAP')
            . '</option>'
            . '</select>',
            '<label for="userNamAttr">'
            . _('User Name Attribute')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="userNamAttr" name='
            . '"userNamAttr" value="'
            . $userNamAttr
            . '" required/>'
            . '</div>',
            '<label for="grpMemberAttr">'
            . _('Group Member Attribute')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="grpMemberAttr" name='
            . '"grpMemberAttr" value="'
            . $grpMemberAttr
            . '"/>'
            . '</div>',
            '<label for="searchScope">'
            . _('Search Scope')
            . '</label>' => $searchSel,
            '<label for="bindDN">'
            . _('Bind DN')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="bindDN" name="bindDN" '
            . 'value="'
            . $bindDN
            . '"/>'
            . '</div>',
            '<label for="bindPwd">'
            . _('Bind Password')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="password" id="bindPwd" name='
            . '"bindPwd" value="'
            . $bindPwd
            . '"/>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'LDAP_FIELDS',
                array(
                    'fields' => &$fields,
                    'LDAP' => self::getClass('LDAP')
                )
            );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'LDAP_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'headerData' => &$this->headerData,
                    'attributes' => &$this->attributes
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="ldap-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=ldap-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Presents the user with fields to edit
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->ldapGeneral();
        echo '</div>';
    }
    /**
     * Updates the current item
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'LDAP_EDIT_POST',
                array('LDAP'=> &$this->obj)
            );
        $ports = array_map(
            'trim',
            explode(
                ',',
                self::getSetting('LDAP_PORTS')
            )
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $address = filter_input(
            INPUT_POST,
            'address'
        );
        $port = filter_input(
            INPUT_POST,
            'port'
        );
        $searchDN = filter_input(
            INPUT_POST,
            'searchDN'
        );
        $grpSearchDN = filter_input(
            INPUT_POST,
            'grpSearchDN'
        );
        $adminGroup = filter_input(
            INPUT_POST,
            'adminGroup'
        );
        $userGroup = filter_input(
            INPUT_POST,
            'userGroup'
        );
        $userNamAttr = filter_input(
            INPUT_POST,
            'userNamAttr'
        );
        $grpMemberAttr = filter_input(
            INPUT_POST,
            'grpMemberAttr'
        );
        $searchScope = filter_input(
            INPUT_POST,
            'searchScope'
        );
        $bindDN = filter_input(
            INPUT_POST,
            'bindDN'
        );
        $bindPwd = filter_input(
            INPUT_POST,
            'bindPwd'
        );
        $useGroupMatch = (int)isset($_POST['useGroupMatch']);
        try {
            if (!is_numeric($searchScope)) {
                $searchScope = 0;
            }
            if (empty($name)) {
                throw new Exception(
                    _('Please enter a name for this LDAP server.')
                );
            }
            if (empty($address)) {
                throw new Exception(
                    _('Please enter a LDAP server address')
                );
            }
            if (empty($searchDN)) {
                throw new Exception(
                    _('Please enter a Search Base DN')
                );
            }
            if (empty($port)) {
                throw new Exception(
                    _('Please select an LDAP port to use')
                );
            }
            if (!in_array($port, $ports)) {
                throw new Exception(
                    _('Please select a valid ldap port')
                );
            }
            if (empty($adminGroup) && empty($userGroup)) {
                throw new Exception(
                    _('Please Enter an admin or mobile lookup name')
                );
            }
            if (empty($userNamAttr)) {
                throw new Exception(
                    _('Please enter a User Name Attribute')
                );
            }
            if (empty($grpMemberAttr)) {
                throw new Exception(
                    _('Please enter a Group Member Attribute')
                );
            }
            if ($this->obj->get('name') != $name
                && self::getClass('LDAPManager')->exists($name)
            ) {
                throw new Exception(
                    _('A LDAP setup already exists with this name!')
                );
            }
            $LDAP = $this->obj
                ->set('name', $name)
                ->set('description', $description)
                ->set('address', $address)
                ->set('searchDN', $searchDN)
                ->set('port', $port)
                ->set('userNamAttr', $userNamAttr)
                ->set('grpMemberAttr', $grpMemberAttr)
                ->set('adminGroup', $adminGroup)
                ->set('userGroup', $userGroup)
                ->set('searchScope', $searchScope)
                ->set('bindDN', $bindDN)
                ->set('bindPwd', $bindPwd)
                ->set('useGroupMatch', $useGroupMatch)
                ->set('grpSearchDN', $grpSearchDN);
            if (!$LDAP->save()) {
                throw new Exception(_('Update LDAP server failed!'));
            }
            $hook = 'LDAP_EDIT_POST_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('LDAP Server updated!'),
                    'title' => _('LDAP Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'LDAP_EDIT_POST_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('LDAP Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('LDAP' => &$this->obj)
            );
        echo $msg;
        exit;
    }

    public function PluginConfiguration()
    {
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('Plugin Configuration');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $filter = self::getSetting('FOG_USER_FILTER');
        $filter = preg_replace('#\s+#', '', $filter);
        $ports = self::getSetting('LDAP_PORTS');
        $ports = preg_replace('#\s+#', '', $ports);
        $fields = array(
            '<label for="filter">'
            . _('User Filter')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="filter" id="filter" class="form-control" value="'
            . $filter
            . '"/>'
            . '</div>',
            '<label for="ports">'
            . _('LDAP Ports')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="ports" id="ports" class="form-control" value="'
            . $ports
            . '"/>'
            . '</div>',
            '<label for="update">'
            . _('Update')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'LDAP_CONFIG',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        unset($fields);
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
    }

    public function PluginConfigurationPost()
    {
        $filter = filter_input(
            INPUT_POST,
            'filter'
        );
        $ports = filter_input(
            INPUT_POST,
            'ports'
        );
        try {
            if (in_array(false, array_map(function ($v) {
                return is_numeric($v);
            }, explode(',', $filter)))||
                        in_array(false, array_map(function ($v) {
                            return is_numeric($v);
                        }, explode(',', $ports)))) {
                $msg = json_encode(
                    array(
                                        'error' => _('Not all elements in filter or ports setting are integer'),
                                        'title' => _('Settings Update Fail')
                                )
                );
            } else {
                self::setSetting('LDAP_PORTS', $ports);
                self::setSetting('FOG_USER_FILTER', $filter);
                $msg = json_encode(
                    array(
                                        'msg' => _('Settings successfully stored!'),
                                        'title' => _('Settings Update Success')
                                )
                );
            }
        } catch (Exception $e) {
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Settings Update Fail')
                )
            );
        }
        echo $msg;
        exit;
    }
}
