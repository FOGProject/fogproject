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
class LDAPManagement extends FOGPage
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
        parent::__construct($name);
        $this->headerData = [
            _('LDAP Connection  Name'),
            _('LDAP Server'),
            _('Port'),
            _('Admin Group'),
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
    }
    /**
     * Create new ldap
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New LDAP Server');

        $ldap = filter_input(INPUT_POST, 'ldap');
        $description = filter_input(INPUT_POST, 'description');
        $address = filter_input(INPUT_POST, 'address');
        $port = filter_input(INPUT_POST, 'port');
        $searchDN = filter_input(INPUT_POST, 'searchDN');
        $grpSearchDN = filter_input(INPUT_POST, 'grpSearchDN');
        $adminGroup = filter_input(INPUT_POST, 'adminGroup');
        $userGroup = filter_input(INPUT_POST, 'userGroup');
        $userNameAttr = filter_input(INPUT_POST, 'userNameAttr');
        $grpMemberAttr = filter_input(INPUT_POST, 'grpMemberAttr');
        $searchScope = filter_input(INPUT_POST, 'searchScope');
        $bindDN = filter_input(INPUT_POST, 'bindDN');
        $bindPwd = filter_input(INPUT_POST, 'bindPwd');
        $template = filter_input(INPUT_POST, 'template');
        $searchScopes = [
            _('Base Only'),
            _('Subtree Only'),
            _('Subtree and Below')
        ];
        $searchSel = self::selectForm(
            'searchScope',
            $searchScopes,
            $searchScope,
            true
        );
        $templates = [
            _('Microsoft AD'),
            _('OpenLDAP'),
            _('Generic LDAP'),
            _('FreeIPA')
        ];
        $initialSel = self::selectForm(
            'template',
            $templates,
            $template,
            true
        );
        $ports = self::getSetting('FOG_PLUGIN_LDAP_PORTS');
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
        $displayNameEnabled = (
            isset($_POST['displayNameOn']) ?: $this->obj->get('displayNameOn')
        );
        $displayNameOn = (
            $displayNameEnabled ?
            'checked' :
            ''
        );
        $displayNameAttr = (
            filter_input(INPUT_POST, 'displayNameAttr') ?:
            $this->obj->get('displayNameAttr')
        );

        $isLDAPs = (
            isset($_POST['isLDAPs']) ?: $this->obj->get('isLdaps')
        );

        $isLDAPsOn = (
            $isLDAPs ? 'checked' : ''
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ldap',
                _('LDAP Server Name')
            ) => self::makeInput(
                'form-control ldapname-input',
                'ldap',
                _('LDAP Server Name'),
                'text',
                'ldap',
                $ldap,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('LDAP Server Description')
            ) => self::makeTextarea(
                'form-control ldapdescription-input',
                'description',
                _('LDAP Server Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'address',
                _('LDAP Server Address')
            ) => self::makeInput(
                'form-control ldapaddress-input',
                'address',
                'ldapserver.local',
                'text',
                'address',
                $address,
                true
            ),
            self::makeLabel(
                $labelClass,
                'isLDAPs',
                _('Use LDAP SSL')
            ) => self::makeInput(
                '',
                'isLDAPs',
                '',
                'checkbox',
                'isLDAPs',
                '',
                false,
                false,
                -1,
                -1,
                $isLDAPsOn
            ),
            self::makeLabel(
                $labelClass,
                'port',
                _('LDAP Server Port')
            ) => $portssel,
            self::makeLabel(
                $labelClass,
                'groupmatch',
                _('Group Matching')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'useGroupMatch',
                '',
                'checkbox',
                'groupmatch',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'searchDN',
                _('Search Base DN')
            ) => self::makeInput(
                'form-control ldapsearchdn-input',
                'searchDN',
                'DC=ldapserver,DC=local',
                'text',
                'searchDN',
                $searchDN,
                true
            ),
            self::makeLabel(
                $labelClass,
                'grpSearchDN',
                _('Group Search DN')
            ) => self::makeInput(
                'form-control ldapgrpsearchdn-input',
                'grpSearchDN',
                'OU=Groups,DC=ldapserver,DC=local',
                'text',
                'grpSearchDN',
                $grpSearchDN
            ),
            self::makeLabel(
                $labelClass,
                'adminGroup',
                _('Administrator Group')
            ) => self::makeInput(
                'form-control ldapadmingroup-input',
                'adminGroup',
                _('Domain Admins'),
                'text',
                'adminGroup',
                $adminGroup
            ),
            self::makeLabel(
                $labelClass,
                'userGroup',
                _('Non-Administrator Group')
            ) => self::makeInput(
                'form-control ldapusergroup-input',
                'userGroup',
                _('Users'),
                'text',
                'userGroup',
                $userGroup
            ),
            self::makeLabel(
                $labelClass,
                'template',
                _('Initial Template')
            ) => $initialSel,
            self::makeLabel(
                $labelClass,
                'userNameAttr',
                _('User Name Attribute')
            ) => self::makeInput(
                'form-control ldapusernameattr-input',
                'userNameAttr',
                'samAccountName',
                'text',
                'userNameAttr',
                $userNameAttr,
                true
            ),
            self::makeLabel(
                $labelClass,
                'groupNameAttr',
                _('Group Name Attribute')
            ) => self::makeInput(
                'form-control ldapgroupnameattr-input',
                'groupNameAttr',
                'name',
                'text',
                'groupNameAttr',
                $groupNameAttr,
                true
            ),
            self::makeLabel(
                $labelClass,
                'grpMemberAttr',
                _('Group Member Attribute')
            ) => self::makeInput(
                'form-control ldapgroupmemberattr-input',
                'grpMemberAttr',
                'memberof',
                'text',
                'grpMemberAttr',
                $grpMemberAttr
            ),
            self::makeLabel(
                $labelClass,
                'searchScope',
                _('Search Scope')
            ) => $searchSel,
            self::makeLabel(
                $labelClass,
                'bindDN',
                _('Bind DN')
            ) => self::makeInput(
                'form-control ldapbinddn-input',
                'bindDN',
                'CN=Users,DC=ldapserver,DC=local',
                'text',
                'bindDN',
                $bindDN
            ),
            self::makeLabel(
                $labelClass,
                'bindPwd',
                _('Bind Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control ldapbindpwd-input',
                'bindPwd',
                '',
                'password',
                'bindPwd',
                $bindPwd
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'displayNameOn',
                _('Use Display Name from Directory')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'displayNameOn',
                '',
                'checkbox',
                'displayNameOn',
                '',
                false,
                false,
                -1,
                -1,
                $displayNameOn
            ),
            self::makeLabel(
                $labelClass,
                'dislayNameAttr',
                _('Display Name Attribute')
            ) => self::makeInput(
                'form-control ldapdisplaynameattr-input',
                'displayNameAttr',
                'displayName',
                'text',
                'displayNameAttr',
                $displayNameAttr,
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'LDAP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'LDAP' => self::getClass('LDAP')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ldap-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="ldap-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New LDAP Server');
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
     * Create new ldap
     *
     * @return void
     */
    public function addModal()
    {
        $ldap = filter_input(INPUT_POST, 'ldap');
        $description = filter_input(INPUT_POST, 'description');
        $address = filter_input(INPUT_POST, 'address');
        $port = filter_input(INPUT_POST, 'port');
        $searchDN = filter_input(INPUT_POST, 'searchDN');
        $grpSearchDN = filter_input(INPUT_POST, 'grpSearchDN');
        $adminGroup = filter_input(INPUT_POST, 'adminGroup');
        $userGroup = filter_input(INPUT_POST, 'userGroup');
        $userNameAttr = filter_input(INPUT_POST, 'userNameAttr');
        $groupNameAttr = filter_input(INPUT_POST, 'groupNameAttr');
        $grpMemberAttr = filter_input(INPUT_POST, 'grpMemberAttr');
        $searchScope = filter_input(INPUT_POST, 'searchScope');
        $bindDN = filter_input(INPUT_POST, 'bindDN');
        $bindPwd = filter_input(INPUT_POST, 'bindPwd');
        $template = filter_input(INPUT_POST, 'template');
        $searchScopes = [
            _('Base Only'),
            _('Subtree Only'),
            _('Subtree and Below')
        ];
        $searchSel = self::selectForm(
            'searchScope',
            $searchScopes,
            $searchScope,
            true
        );
        $templates = [
            _('Microsoft AD'),
            _('OpenLDAP'),
            _('Generic LDAP'),
            _('FreeIPA')
        ];
        $initialSel = self::selectForm(
            'template',
            $templates,
            $template,
            true
        );
        $ports = self::getSetting('FOG_PLUGIN_LDAP_PORTS');
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
        $displayNameEnabled = (
            isset($_POST['displayNameOn']) ?: $this->obj->get('displayNameOn')
        );
        $displayNameOn = (
            $displayNameEnabled ?
            'checked' :
            ''
        );
        $displayNameAttr = (
            filter_input(INPUT_POST, 'displayNameAttr') ?:
            $this->obj->get('displayNameAttr')
        );

        $isLDAPs = (
            isset($_POST['isLDAPs']) ?: $this->obj->get('isLdaps')
        );

        $isLDAPsOn = (
            $isLDAPs ? 'checked' : ''
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ldap',
                _('LDAP Server Name')
            ) => self::makeInput(
                'form-control ldapname-input',
                'ldap',
                _('LDAP Server Name'),
                'text',
                'ldap',
                $ldap,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('LDAP Server Description')
            ) => self::makeTextarea(
                'form-control ldapdescription-input',
                'description',
                _('LDAP Server Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'address',
                _('LDAP Server Address')
            ) => self::makeInput(
                'form-control ldapaddress-input',
                'address',
                'ldapserver.local',
                'text',
                'address',
                $address,
                true
            ),
            self::makeLabel(
                $labelClass,
                'isLDAPs',
                _('Use LDAP SSL')
            ) => self::makeInput(
                '',
                'isLDAPs',
                '',
                'checkbox',
                'isLDAPs',
                '',
                false,
                false,
                -1,
                -1,
                $isLDAPsOn
            ),
            self::makeLabel(
                $labelClass,
                'port',
                _('LDAP Server Port')
            ) => $portssel,
            self::makeLabel(
                $labelClass,
                'groupmatch',
                _('Group Matching')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'useGroupMatch',
                '',
                'checkbox',
                'groupmatch',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
            ),
            self::makeLabel(
                $labelClass,
                'searchDN',
                _('Search Base DN')
            ) => self::makeInput(
                'form-control ldapsearchdn-input',
                'searchDN',
                'DC=ldapserver,DC=local',
                'text',
                'searchDN',
                $searchDN,
                true
            ),
            self::makeLabel(
                $labelClass,
                'grpSearchDN',
                _('Group Search DN')
            ) => self::makeInput(
                'form-control ldapgrpsearchdn-input',
                'grpSearchDN',
                'OU=Groups,DC=ldapserver,DC=local',
                'text',
                'grpSearchDN',
                $grpSearchDN
            ),
            self::makeLabel(
                $labelClass,
                'adminGroup',
                _('Administrator Group')
            ) => self::makeInput(
                'form-control ldapadmingroup-input',
                'adminGroup',
                _('Domain Admins'),
                'text',
                'adminGroup',
                $adminGroup
            ),
            self::makeLabel(
                $labelClass,
                'userGroup',
                _('Non-Administrator Group')
            ) => self::makeInput(
                'form-control ldapusergroup-input',
                'userGroup',
                _('Users'),
                'text',
                'userGroup',
                $userGroup
            ),
            self::makeLabel(
                $labelClass,
                'template',
                _('Initial Template')
            ) => $initialSel,
            self::makeLabel(
                $labelClass,
                'userNameAttr',
                _('User Name Attribute')
            ) => self::makeInput(
                'form-control ldapusernameattr-input',
                'userNameAttr',
                'samAccountName',
                'text',
                'userNameAttr',
                $userNameAttr,
                true
            ),
            self::makeLabel(
                $labelClass,
                'template',
                _('Initial Template')
            ) => $initialSel,
            self::makeLabel(
                $labelClass,
                'groupNameAttr',
                _('Group Name Attribute')
            ) => self::makeInput(
                'form-control ldapgroupnameattr-input',
                'groupNameAttr',
                'name',
                'text',
                'groupNameAttr',
                $groupNameAttr,
                true
            ),
            self::makeLabel(
                $labelClass,
                'grpMemberAttr',
                _('Group Member Attribute')
            ) => self::makeInput(
                'form-control ldapgroupmemberattr-input',
                'grpMemberAttr',
                'memberof',
                'text',
                'grpMemberAttr',
                $grpMemberAttr
            ),
            self::makeLabel(
                $labelClass,
                'searchScope',
                _('Search Scope')
            ) => $searchSel,
            self::makeLabel(
                $labelClass,
                'bindDN',
                _('Bind DN')
            ) => self::makeInput(
                'form-control ldapbinddn-input',
                'bindDN',
                'CN=Users,DC=ldapserver,DC=local',
                'text',
                'bindDN',
                $bindDN
            ),
            self::makeLabel(
                $labelClass,
                'bindPwd',
                _('Bind Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control ldapbindpwd-input',
                'bindPwd',
                '',
                'password',
                'bindPwd',
                $bindPwd
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'displayNameOn',
                _('Use Display Name from Directory')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'displayNameOn',
                '',
                'checkbox',
                'displayNameOn',
                '',
                false,
                false,
                -1,
                -1,
                $displayNameOn
            ),
            self::makeLabel(
                $labelClass,
                'dislayNameAttr',
                _('Display Name Attribute')
            ) => self::makeInput(
                'form-control ldapdisplaynameattr-input',
                'displayNameAttr',
                'displayName',
                'text',
                'displayNameAttr',
                $displayNameAttr,
                true
            )
        ];

        self::$HookManager->processEvent(
            'LDAP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'LDAP' => self::getClass('LDAP')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=ldap&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Create the new item
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: appication/json');
        self::$HookManager->processEvent('LDAP_ADD_POST');
        $ldap = trim(
            filter_input(INPUT_POST, 'ldap')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $address = trim(
            filter_input(INPUT_POST, 'address')
        );
        $port = trim(
            filter_input(INPUT_POST, 'port')
        );
        $searchDN = trim(
            filter_input(INPUT_POST, 'searchDN')
        );
        $grpSearchDN = trim(
            filter_input(INPUT_POST, 'grpSearchDN')
        );
        $adminGroup = trim(
            filter_input(INPUT_POST, 'adminGroup')
        );
        $userGroup = trim(
            filter_input(INPUT_POST, 'userGroup')
        );
        $userNameAttr = trim(
            filter_input(INPUT_POST, 'userNameAttr')
        );
        $groupNameAttr = trim(
            filter_input(INPUT_POST, 'groupNameAttr')
        );
        $grpMemberAttr = trim(
            filter_input(INPUT_POST, 'grpMemberAttr')
        );
        $searchScope = trim(
            filter_input(INPUT_POST, 'searchScope')
        );
        $bindDN = trim(
            filter_input(INPUT_POST, 'bindDN')
        );
        $bindPwd = trim(
            filter_input(INPUT_POST, 'bindPwd')
        );
        $useGroupMatch = (int)isset($_POST['useGroupMatch']);

        $displayNameOn = (int)isset($_POST['displayNameOn']);

        $displayNameAttr = trim(
            filter_input(INPUT_POST, 'displayNameAttr')
        );

        $isLDAPs = (int)isset($_POST['isLDAPs']);

        $serverFault = false;
        try {
            if (!is_numeric($searchScope)) {
                $searchScope = 0;
            }
            $ports = self::getSetting('FOG_PLUGIN_LDAP_PORTS');
            $ports = preg_replace('#\s+#', '', $ports);
            $ports = explode(',', $ports);
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
            $exists = self::getClass('LDAPManager')
                ->exists($ldap);
            if ($exists) {
                throw new Exception(
                    _('An LDAP server already exists with this name!')
                );
            }
            $LDAP = self::getClass('LDAP')
                ->set('name', $ldap)
                ->set('description', $description)
                ->set('address', $address)
                ->set('searchDN', $searchDN)
                ->set('isLdaps', $isLDAPs)
                ->set('port', $port)
                ->set('userNamAttr', $userNameAttr)
                ->set('grpNamAttr', $groupNameAttr)
                ->set('grpMemberAttr', $grpMemberAttr)
                ->set('adminGroup', $adminGroup)
                ->set('userGroup', $userGroup)
                ->set('searchScope', $searchScope)
                ->set('bindDN', $bindDN)
                ->set('bindPwd', $bindPwd)
                ->set('useGroupMatch', $useGroupMatch)
                ->set('grpSearchDN', $grpSearchDN)
                ->set('displayNameOn', $displayNameOn)
                ->set('displayNameAttr', $displayNameAttr);
            if (!$LDAP->save()) {
                $serverFault = true;
                throw new Exception(_('Add LDAP server failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'LDAP_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('LDAP Server added!'),
                    'title' => _('LDAP Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'LDAP_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('LDAP Create Fail')
                ]
            );
        }
        //header(
        //    'Location: ../management/index.php?node=ldap&sub=edit&id='
        //    . $LDAP->get('id')
        //);
        self::$HookManager->processEvent(
            $hook,
            [
                'LDAP' => &$LDAP,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
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
        $ldap = (
            filter_input(INPUT_POST, 'ldap') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $address = (
            filter_input(INPUT_POST, 'address') ?:
            $this->obj->get('address')
        );
        $port = (
            filter_input(INPUT_POST, 'port') ?:
            $this->obj->get('port')
        );
        $searchDN = (
            filter_input(INPUT_POST, 'searchDN') ?:
            $this->obj->get('searchDN')
        );
        $grpSearchDN = (
            filter_input(INPUT_POST, 'grpSearchDN') ?:
            $this->obj->get('grpSearchDN')
        );
        $adminGroup = (
            filter_input(INPUT_POST, 'adminGroup') ?:
            $this->obj->get('adminGroup')
        );
        $userGroup = (
            filter_input(INPUT_POST, 'userGroup') ?:
            $this->obj->get('userGroup')
        );
        $userNameAttr = (
            filter_input(INPUT_POST, 'userNameAttr') ?:
            $this->obj->get('userNamAttr')
        );
        $groupNameAttr = (
            filter_input(INPUT_POST, 'groupNameAttr') ?:
            $this->obj->get('grpNamAttr')
        );
        $grpMemberAttr = (
            filter_input(INPUT_POST, 'grpMemberAttr') ?:
            $this->obj->get('grpMemberAttr')
        );
        $searchScope = (
            filter_input(INPUT_POST, 'searchScope') ?:
            $this->obj->get('searchScope')
        );
        $bindDN = (
            filter_input(INPUT_POST, 'bindDN') ?:
            $this->obj->get('bindDN')
        );
        $bindPwd = (
            filter_input(INPUT_POST, 'bindPwd') ?:
            $this->obj->get('bindPwd')
        );
        $template = filter_input(INPUT_POST, 'template');
        $searchScopes = [
            _('Base Only'),
            _('Subtree Only'),
            _('Subtree and Below')
        ];
        $searchSel = self::selectForm(
            'searchScope',
            $searchScopes,
            $searchScope,
            true
        );
        $templates = [
            _('Microsoft AD'),
            _('OpenLDAP'),
            _('Generic LDAP'),
            _('FreeIPA')
        ];
        $initialSel = self::selectForm(
            'template',
            $templates,
            $template,
            true
        );
        $ports = self::getSetting('FOG_PLUGIN_LDAP_PORTS');
        $ports = preg_replace('#\s+#', '', $ports);
        $ports = explode(',', $ports);
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
            'checked' :
            ''
        );
        $displayNameEnabled = (
            isset($_POST['displayNameOn']) ?: $this->obj->get('displayNameOn')
        );
        $displayNameOn = (
            $displayNameEnabled ?
            'checked' :
            ''
        );
        $displayNameAttr = (
            filter_input(INPUT_POST, 'displayNameAttr') ?:
            $this->obj->get('displayNameAttr')
        );

        $isLDAPs = (
            isset($_POST['isLDAPs']) ?: $this->obj->get('isLdaps')
        );

        $isLDAPsOn = (
            $isLDAPs ? 'checked' : ''
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ldap',
                _('LDAP Server Name')
            ) => self::makeInput(
                'form-control ldapname-input',
                'ldap',
                _('LDAP Server Name'),
                'text',
                'ldap',
                $ldap,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('LDAP Server Description')
            ) => self::makeTextarea(
                'form-control ldapdescription-input',
                'description',
                _('LDAP Server Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'address',
                _('LDAP Server Address')
            ) => self::makeInput(
                'form-control ldapaddress-input',
                'address',
                'ldapserver.local',
                'text',
                'address',
                $address,
                true
            ),
            self::makeLabel(
                $labelClass,
                'isLDAPs',
                _('Use LDAP SSL')
            ) => self::makeInput(
                '',
                'isLDAPs',
                '',
                'checkbox',
                'isLDAPs',
                '',
                false,
                false,
                -1,
                -1,
                $isLDAPsOn
            ),
            self::makeLabel(
                $labelClass,
                'port',
                _('LDAP Server Port')
            ) => $portssel,
            self::makeLabel(
                $labelClass,
                'groupmatch',
                _('Group Matching')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'useGroupMatch',
                '',
                'checkbox',
                'groupmatch',
                '',
                false,
                false,
                -1,
                -1,
                $useMatch
            ),
            self::makeLabel(
                $labelClass,
                'searchDN',
                _('Search Base DN')
            ) => self::makeInput(
                'form-control ldapsearchdn-input',
                'searchDN',
                'DC=ldapserver,DC=local',
                'text',
                'searchDN',
                $searchDN,
                true
            ),
            self::makeLabel(
                $labelClass,
                'grpSearchDN',
                _('Group Search DN')
            ) => self::makeInput(
                'form-control ldapgrpsearchdn-input',
                'grpSearchDN',
                'OU=Groups,DC=ldapserver,DC=local',
                'text',
                'grpSearchDN',
                $grpSearchDN
            ),
            self::makeLabel(
                $labelClass,
                'adminGroup',
                _('Administrator Group')
            ) => self::makeInput(
                'form-control ldapadmingroup-input',
                'adminGroup',
                _('Domain Admins'),
                'text',
                'adminGroup',
                $adminGroup
            ),
            self::makeLabel(
                $labelClass,
                'userGroup',
                _('Non-Administrator Group')
            ) => self::makeInput(
                'form-control ldapusergroup-input',
                'userGroup',
                _('Users'),
                'text',
                'userGroup',
                $userGroup
            ),
            self::makeLabel(
                $labelClass,
                'template',
                _('Initial Template')
            ) => $initialSel,
            self::makeLabel(
                $labelClass,
                'userNameAttr',
                _('User Name Attribute')
            ) => self::makeInput(
                'form-control ldapusernameattr-input',
                'userNameAttr',
                'samAccountName',
                'text',
                'userNameAttr',
                $userNameAttr,
                true
            ),
            self::makeLabel(
                $labelClass,
                'groupNameAttr',
                _('Group Name Attribute')
            ) => self::makeInput(
                'form-control ldapusernameattr-input',
                'groupNameAttr',
                'name',
                'text',
                'groupNameAttr',
                $groupNameAttr,
                true
            ),
            self::makeLabel(
                $labelClass,
                'grpMemberAttr',
                _('Group Member Attribute')
            ) => self::makeInput(
                'form-control ldapgroupmemberattr-input',
                'grpMemberAttr',
                'memberof',
                'text',
                'grpMemberAttr',
                $grpMemberAttr
            ),
            self::makeLabel(
                $labelClass,
                'searchScope',
                _('Search Scope')
            ) => $searchSel,
            self::makeLabel(
                $labelClass,
                'bindDN',
                _('Bind DN')
            ) => self::makeInput(
                'form-control ldapbinddn-input',
                'bindDN',
                'CN=Users,DC=ldapserver,DC=local',
                'text',
                'bindDN',
                $bindDN
            ),
            self::makeLabel(
                $labelClass,
                'bindPwd',
                _('Bind Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control ldapbindpwd-input',
                'bindPwd',
                '',
                'password',
                'bindPwd',
                $bindPwd
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'displayNameOn',
                _('Use Display Name from Directory')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'displayNameOn',
                '',
                'checkbox',
                'displayNameOn',
                '',
                false,
                false,
                -1,
                -1,
                $displayNameOn
            ),
            self::makeLabel(
                $labelClass,
                'dislayNameAttr',
                _('Display Name Attribute')
            ) => self::makeInput(
                'form-control ldapdisplaynameattr-input',
                'displayNameAttr',
                'displayName',
                'text',
                'displayNameAttr',
                $displayNameAttr,
                true
            )
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
            'LDAP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'LDAP' => self::getClass('LDAP')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ldap-general-form',
            self::makeTabUpdateURL(
                'ldap-general',
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
     * Update the ldap general items.
     *
     * @throws Exception
     *
     * @return void
     */
    public function ldapGeneralPost()
    {
        $ldap = trim(
            filter_input(INPUT_POST, 'ldap')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $address = trim(
            filter_input(INPUT_POST, 'address')
        );
        $port = trim(
            filter_input(INPUT_POST, 'port')
        );
        $searchDN = trim(
            filter_input(INPUT_POST, 'searchDN')
        );
        $grpSearchDN = trim(
            filter_input(INPUT_POST, 'grpSearchDN')
        );
        $adminGroup = trim(
            filter_input(INPUT_POST, 'adminGroup')
        );
        $userGroup = trim(
            filter_input(INPUT_POST, 'userGroup')
        );
        $userNameAttr = trim(
            filter_input(INPUT_POST, 'userNameAttr')
        );
        $groupNameAttr = trim(
            filter_input(INPUT_POST, 'groupNameAttr')
        );
        $grpMemberAttr = trim(
            filter_input(INPUT_POST, 'grpMemberAttr')
        );
        $searchScope = trim(
            filter_input(INPUT_POST, 'searchScope')
        );
        $bindDN = trim(
            filter_input(INPUT_POST, 'bindDN')
        );
        $bindPwd = trim(
            filter_input(INPUT_POST, 'bindPwd')
        );
        $useGroupMatch = (int)isset($_POST['useGroupMatch']);

        $displayNameOn = (int)isset($_POST['displayNameOn']);

        $displayNameAttr = trim(
            filter_input(INPUT_POST, 'displayNameAttr')
        );

        $isLDAPs = (int)isset($_POST['isLDAPs']);

        if (!is_numeric($searchScope)) {
            $searchScope = 0;
        }
        $ports = self::getSetting('FOG_PLUGIN_LDAP_PORTS');
        $ports = preg_replace('#\s+#', '', $ports);
        $ports = explode(',', $ports);
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
        $exists = self::getClass('LDAPManager')
            ->exists($ldap);
        if ($ldap != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A LDAP setup already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $ldap)
            ->set('description', $description)
            ->set('address', $address)
            ->set('searchDN', $searchDN)
            ->set('isLdaps', $isLDAPs)
            ->set('port', $port)
            ->set('userNamAttr', $userNameAttr)
            ->set('grpNamAttr', $groupNameAttr)
            ->set('grpMemberAttr', $grpMemberAttr)
            ->set('adminGroup', $adminGroup)
            ->set('userGroup', $userGroup)
            ->set('searchScope', $searchScope)
            ->set('bindDN', $bindDN)
            ->set('bindPwd', $bindPwd)
            ->set('useGroupMatch', $useGroupMatch)
            ->set('grpSearchDN', $grpSearchDN)
            ->set('displayNameOn', $displayNameOn)
            ->set('displayNameAttr', $displayNameAttr);
    }
    /**
     * Presents the user with fields to edit
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
            'id' => 'ldap-general',
            'generator' => function () {
                $this->ldapGeneral();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * The ldap global settings options.
     *
     * @return void
     */
    public function globalsettings()
    {
        $this->title = _('Editing Global LDAP Settings');
        $find = [
            'name' => [
                'FOG_PLUGIN_LDAP_PORTS',
                'FOG_PLUGIN_LDAP_USER_FILTER'
            ]
        ];
        Route::ids(
            'setting',
            $find,
            'value'
        );
        $settings = json_decode(
            Route::getData(),
            true
        );
        list(
            $ports,
            $filters
        ) = $settings;

        $port = (
            filter_input(INPUT_POST, 'port') ?:
            $ports
        );

        $filter = (
            filter_input(INPUT_POST, 'filter') ?:
            $filters
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'filter',
                _('LDAP User Filter')
            ) => self::makeInput(
                'form-control ldapuserfilter-input',
                'filter',
                '990,991',
                'text',
                'filter',
                $filter,
                true
            ),
            self::makeLabel(
                $labelClass,
                'port',
                _('LDAP Ports')
            ) => self::makeInput(
                'form-control ldapport-input',
                'port',
                '389,636',
                'text',
                'port',
                $port,
                true
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'LDAP_GLOBAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ldap-global-form',
            self::makeTabUpdateURL(
                'ldap-global',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="ldap-global">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
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
     * LDAP Global Settings Post.
     *
     * @return void
     */
    public function globalsettingsPost()
    {
        header('Content-type: application/json');
        $filter = trim(
            filter_input(INPUT_POST, 'filter')
        );
        $port = trim(
            filter_input(INPUT_POST, 'port')
        );

        $serverFault = false;
        try {
            if (!$filter) {
                throw new Exception(_('A filter must be specified'));
            }
            $filter = preg_replace('#\s+#', '', $filter);
            $filters = explode(',', $filter);
            foreach ($filters as &$filter) {
                $filter = intval($filter);
                if (!is_int($filter) || $filter < 2) {
                    throw new Exception(_('All filters must be numeric and greater than 1'));
                }
                unset($filter);
            }
            if (!$port) {
                throw new Exception(_('A port must be specified'));
            }
            $port = preg_replace('#\s+#', '', $port);
            $ports = explode(',', $port);
            foreach ($ports as &$port) {
                $port = intval($port);
                if (!is_int($port) || $port < 1 || $port > 65535) {
                    throw new Exception(_('All ports must be numeric, greater than 0, and less than 65536'));
                }
                unset($port);
            }
            if (!self::setSetting('FOG_PLUGIN_LDAP_USER_FILTER', implode(',', $filters))) {
                $serverFault = true;
                throw new Exception(_('Unable to set user filter.'));
            }
            if (!self::setSetting('FOG_PLUGIN_LDAP_PORTS', implode(',', $ports))) {
                $serverFault = true;
                throw new Exception(_('Unable to set ldap ports.'));
            }
            $hook = 'LDAP_GLOBAL_EDIT_SUCCESS';
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Global settings updated!'),
                    'title' => _('Global Settings Update Success')
                ]
            );
        } catch (Exception $e) {
            $hook = 'LDAP_GLOBAL_EDIT_FAIL';
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Global Settings Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
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
    /**
     * Updates the current item
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'LDAP_EDIT_POST',
            ['LDAP' => &$this->obj]
        );

        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
                case 'ldap-general':
                    $this->ldapGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = false;
                throw new Exception(_('LDAP Server update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'LDAP_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('LDAP Server updated!'),
                    'title' => _('LDAP Server Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'LDAP_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('LDAP Server Update Fail')
                ]
            );
        }
        
        self::$HookManager->processEvent(
            $hook,
            [
                'LDAP' => &$this->obj,
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
