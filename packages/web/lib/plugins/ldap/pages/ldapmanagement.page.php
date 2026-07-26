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
        ];
        $this->attributes = [
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
        $ldap = filter_input(INPUT_POST, 'ldap');
        $description = filter_input(INPUT_POST, 'description');
        $address = filter_input(INPUT_POST, 'address');
        $port = filter_input(INPUT_POST, 'port');
        $searchDN = filter_input(INPUT_POST, 'searchDN');
        $grpSearchDN = filter_input(INPUT_POST, 'grpSearchDN');
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

        $allowAPI = (
            isset($_POST['allowapi']) ?: $this->obj->get('allowapi')
        );

        $isAPI = ($allowAPI ? 'checked' : '');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
                'allowapi',
                _('Allow API')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'allowapi',
                '',
                'checkbox',
                'allowapi',
                '',
                false,
                false,
                -1,
                -1,
                'checked'
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
                'displayNameAttr',
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
    }
    /**
     * Create new ldap
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'ldap',
            _('Create New LDAP Server'),
            'LDAP_ADD_FIELDS',
            'LDAP'
        );
    }
    /**
     * Create new ldap
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'ldap',
            'LDAP_ADD_FIELDS',
            'LDAP'
        );
    }
    /**
     * Create the new item
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'LDAP',
            'LDAP_ADD',
            _('LDAP Server added!'),
            _('LDAP Create Success'),
            _('LDAP Create Fail'),
            function (&$serverFault) {
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
                $isAPI = (int)isset($_POST['allowapi']);
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
                    ->set('searchScope', $searchScope)
                    ->set('bindDN', $bindDN)
                    ->set('bindPwd', $bindPwd)
                    ->set('useGroupMatch', $useGroupMatch)
                    ->set('grpSearchDN', $grpSearchDN)
                    ->set('displayNameOn', $displayNameOn)
                    ->set('allowapi', $isAPI)
                    ->set('displayNameAttr', $displayNameAttr);
                if (!$LDAP->save()) {
                    $serverFault = true;
                    throw new Exception(_('Add LDAP server failed!'));
                }
                return $LDAP;
            }
        );
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
        // Deliberately NOT seeded from the stored value. type="password"
        // hides it on screen but the value attribute is still in the page
        // source, so rendering it handed the directory service account
        // credential to anyone who could open the edit page. Blank means
        // "unchanged" on save; see ldapGeneralPost().
        $bindPwd = (string)filter_input(INPUT_POST, 'bindPwd');
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

        $allowAPI = (
            isset($_POST['allowapi']) ?: $this->obj->get('allowapi')
        );

        $isAPI = ($allowAPI ? 'checked' : '');

        $labelClass = 'col-sm-3 col-form-label';

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
                'allowapi',
                _('Allow API')
                . '<br/>('
                . _('recommended')
                . ')'
            ) => self::makeInput(
                '',
                'allowapi',
                '',
                'checkbox',
                'allowapi',
                '',
                false,
                false,
                -1,
                -1,
                $isAPI
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
                _('Leave blank to keep the current password'),
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
                'displayNameAttr',
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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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
            '',
            'ldap-general-form',
            self::makeTabUpdateURL(
                'ldap-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
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
        self::checkAuthAndCSRF();
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
        $isAPI = (int)isset($_POST['allowapi']);

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
            ->set('searchScope', $searchScope)
            ->set('bindDN', $bindDN)
            ->set('useGroupMatch', $useGroupMatch)
            ->set('grpSearchDN', $grpSearchDN)
            ->set('displayNameOn', $displayNameOn)
            ->set('allowapi', $isAPI)
            ->set('displayNameAttr', $displayNameAttr);
        // The edit form no longer renders the stored password back into the
        // field, so an empty submission means "leave it as it is" rather
        // than "clear it". Without this an admin editing any other setting
        // on the page would silently wipe the bind credential.
        if ('' !== $bindPwd) {
            $this->obj->set('bindPwd', $bindPwd);
        }
    }
    /**
     * Presents the user with fields to edit
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'ldap-general',
            'generator' => function () {
                $this->ldapGeneral();
            }
        ];

        // Group Mappings
        $tabData[] = [
            'name' => _('Group Mappings'),
            'id' => 'ldap-groupmap',
            'generator' => function () {
                $this->ldapGroupMap();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * The ldap global settings options.
     *
     * @return void
     */
    public function globalsettings()
    {
        $this->title = _('Editing Global LDAP Settings');
        $port = (
            filter_input(INPUT_POST, 'port') ?:
            self::getSetting('FOG_PLUGIN_LDAP_PORTS')
        );

        // Role mapping. Read one setting at a time rather than batching them
        // into a single Route::getIds('setting', ...) call: that form returns
        // values positionally, so a setting an admin has blanked out would
        // shift every later value by one.
        $adminRole = (
            filter_input(INPUT_POST, 'adminrole') ?:
            self::getSetting('FOG_PLUGIN_LDAP_ADMIN_ROLE')
        );
        $userRole = (
            filter_input(INPUT_POST, 'userrole') ?:
            self::getSetting('FOG_PLUGIN_LDAP_USER_ROLE')
        );
        $nomatchRole = (
            filter_input(INPUT_POST, 'nomatchrole') ?:
            self::getSetting('FOG_PLUGIN_LDAP_NOMATCH_ROLE')
        );

        // Route::names() would emit a JSON content-type header into what is
        // an HTML fragment, so build the id => name map from ids(). Both
        // calls order by name, so the two lists line up.
        $roleIds = Route::getIds('role', [], 'id');
        $roleNames = Route::getIds('role', [], 'name');
        $roles = (
            count($roleIds) === count($roleNames) ?
            array_combine($roleIds, $roleNames) :
            []
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
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
            ),
            self::makeLabel(
                $labelClass,
                'adminrole',
                _('Role for LDAP admin group')
            ) => self::selectForm(
                'adminrole',
                $roles,
                $adminRole,
                true
            ),
            self::makeLabel(
                $labelClass,
                'userrole',
                _('Role for LDAP user group')
            ) => self::selectForm(
                'userrole',
                $roles,
                $userRole,
                true
            ),
            self::makeLabel(
                $labelClass,
                'nomatchrole',
                _('Role when group matching is off')
            ) => self::selectForm(
                'nomatchrole',
                $roles,
                $nomatchRole,
                true
            )
            // Spelled out rather than left implicit: on a server with group
            // matching off this role is granted to everyone who can bind,
            // which is the whole directory. An admin choosing it should see
            // that, not have to infer it.
            . '<small class="form-text text-muted">'
            . _(
                'Applies to LDAP servers that have group matching '
                . 'disabled. On those servers FOG cannot tell an '
                . 'administrator from any other account, so every user who '
                . 'can authenticate against the directory receives this '
                . 'role.'
            )
            . '</small>'
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
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
            '',
            'ldap-global-form',
            self::makeTabUpdateURL(
                'ldap-global',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card" id="ldap-global">';
        echo '<div class="card-body">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-footer">';
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
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        $port = trim(
            filter_input(INPUT_POST, 'port')
        );
        $roleFields = [
            'adminrole' => 'FOG_PLUGIN_LDAP_ADMIN_ROLE',
            'userrole' => 'FOG_PLUGIN_LDAP_USER_ROLE',
            'nomatchrole' => 'FOG_PLUGIN_LDAP_NOMATCH_ROLE'
        ];

        $serverFault = false;
        try {
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
            if (!self::setSetting('FOG_PLUGIN_LDAP_PORTS', implode(',', $ports))) {
                $serverFault = true;
                throw new Exception(_('Unable to set ldap ports.'));
            }
            // Blank is a legitimate choice meaning "grant nothing", so only
            // a non-blank value is checked -- and it is checked against the
            // roles that actually exist, because a setting naming a deleted
            // role would otherwise fail silently at login.
            $validRoles = array_map(
                'strval',
                (array)Route::getIds('role', [], 'id')
            );
            foreach ($roleFields as $field => $settingKey) {
                $roleId = trim((string)filter_input(INPUT_POST, $field));
                if ('' !== $roleId && !in_array($roleId, $validRoles, true)) {
                    throw new Exception(
                        _('A selected role no longer exists')
                    );
                }
                if (!self::setSetting($settingKey, $roleId)) {
                    $serverFault = true;
                    throw new Exception(_('Unable to set LDAP role mapping.'));
                }
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
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * The directory groups defined for the server being edited.
     *
     * Read-only: what a group grants is edited on the group's own page,
     * where roles and user groups are ordinary association tabs. This tab
     * exists so an admin looking at a server can see and reach its groups
     * without having to know they live under a separate node.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @return void
     */
    public function ldapGroupMap()
    {
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo '<p>';
        echo _(
            'A user signing in through this server receives every role and '
            . 'user group granted by a directory group they belong to. '
            . 'Select a group to change what it grants.'
        );
        echo '</p>';
        /**
         * Group matching is what makes these groups readable at all, so say
         * so here rather than leaving an admin to wonder why a group they
         * just added does nothing.
         */
        if (!$this->obj->get('useGroupMatch')) {
            echo '<div class="alert alert-warning">';
            echo Initiator::e(
                _(
                    'Group Matching is off for this server, so directory '
                    . 'groups cannot be read and these mappings are not '
                    . 'used. Users who can bind receive the fallback role '
                    . 'from the global LDAP settings instead.'
                )
            );
            echo '</div>';
        }
        // A real DataTable rather than a hand-built one. It is read-only, but
        // the point of the shared table plumbing is that every grid in FOG
        // sorts, searches and pages the same way -- and this one grows with
        // the directory, so paging stops being optional at some size.
        // Rows are fed by getGroupMapList().
        echo '<table id="ldap-groupmap-table" '
            . 'class="display table table-bordered table-striped">';
        echo '<thead><tr class="header">';
        echo '<th data-column="0" scope="col">'
            . _('Directory Group')
            . '</th>';
        echo '<th data-column="1" scope="col">'
            . _('Grants')
            . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        // Constructive actions sit right, destructive left, matching every
        // other actionbox in FOG: the easy-to-reach side is for the safe
        // action, so destroying something takes deliberate travel.
        // text-end rather than float-end: .btn-actionbox has no clearfix, and
        // this button is the last thing in the card body, so a float would
        // collapse the wrapper and hang the button past the card's padding.
        echo '<div class="btn-actionbox text-end">';
        printf(
            '<a class="btn btn-primary" href="?node=ldapgroup&sub=add">%s</a>',
            Initiator::e(_('Create New LDAP Group'))
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Feeds the Group Mappings datatable.
     *
     * Server-side so the grid pages rather than rendering every directory
     * group at once, and scoped to the server being edited -- the groups are
     * a property of this server, not a global list. The where goes in as a
     * bound-safe integer because it is the object's own id.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @return void
     */
    public function getGroupMapList()
    {
        header('Content-type: application/json');
        $pass_vars = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $manager = self::getClass('LDAPGroupManager');
        $columns = [
            [
                'db' => 'lgName',
                'dt' => 'mainLink',
                'formatter' => function ($d, $row) {
                    return sprintf(
                        '<a href="?node=ldapgroup&sub=edit&id=%1$s">'
                        . '(%1$s) - %2$s</a>',
                        Initiator::e($row['lgID']),
                        Initiator::e($d)
                    );
                }
            ],
            [
                'db' => 'lgID',
                'dt' => 'grants',
                'formatter' => function ($d, $row) {
                    return self::_grantLinks((int)$d);
                }
            ],
        ];

        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $manager->getTable(),
                'lgID',
                $columns,
                $manager->getQueryStr(),
                $manager->getFilterStr(),
                $manager->getTotalStr(),
                null,
                sprintf(
                    '`LDAPGroups`.`lgServerID` = %d',
                    (int)$this->obj->get('id')
                )
            )
        ));
    }
    /**
     * What one directory group grants, as links to the things it grants.
     *
     * Linked rather than plain text for the same reason the group name is:
     * the answer to "what does this grant?" is almost always followed by
     * "let me go look at that", and every other reference in FOG is
     * clickable.
     *
     * @param int $groupId the LDAPGroups row id
     *
     * @return string
     */
    private static function _grantLinks($groupId)
    {
        $group = self::getClass('LDAPGroup', $groupId);
        $links = [];
        $targets = [
            ['roles', 'Role', 'role', _('Role')],
            ['usergroups', 'UserGroup', 'usergroup', _('User Group')],
        ];
        foreach ($targets as $target) {
            list($getter, $class, $node, $label) = $target;
            foreach ((array)$group->get($getter) as $id) {
                $obj = self::getClass($class, (int)$id);
                if (!$obj->isValid()) {
                    continue;
                }
                $links[] = sprintf(
                    '%s - <a href="?node=%s&sub=edit&id=%s">%s</a>',
                    Initiator::e($label),
                    $node,
                    Initiator::e((int)$id),
                    Initiator::e($obj->get('name'))
                );
            }
        }
        if (empty($links)) {
            return Initiator::e(_('Nothing yet'));
        }
        return implode(', ', $links);
    }
    /**
     * Updates the current item
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'LDAP',
            'LDAP_EDIT',
            _('LDAP Server updated!'),
            _('LDAP Server Update Success'),
            _('LDAP Server Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'ldap-general':
                        $this->ldapGeneralPost();
                        break;
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('LDAP Server update failed!'));
                }
            }
        );
    }
}
