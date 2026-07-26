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
     * The nested-group resolution strategies, keyed by stored value.
     *
     * One list, used by both form renderers and by both post handlers'
     * validation, so the set of legal values cannot drift between what the
     * form offers and what a save accepts.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/884
     *
     * @return array
     */
    private static function _nestedStrategies()
    {
        return [
            'off' => _('Off - direct membership only'),
            'expand' => _('Expand - walk the chain (any directory)'),
            'chain' => _('Chain - LDAP_MATCHING_RULE_IN_CHAIN (AD only)')
        ];
    }
    /**
     * The LDAPS certificate verification levels, keyed by stored value.
     *
     * One list, used by both form renderers and by _tlsFromPost(), for the
     * same reason _nestedStrategies() is: the set of legal values cannot
     * drift between what the form offers and what a save accepts.
     *
     * 'inherit' is first and is the column default. The plugin set no TLS
     * option at all before this, so ldap.conf governed, and asserting 'hard'
     * on upgrade would break every install that had relaxed TLS_REQCERT to
     * reach an internal CA.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/893
     *
     * @return array
     */
    private static function _tlsVerifyLevels()
    {
        return [
            'inherit' => _('Inherit - use the system ldap.conf setting'),
            'hard' => _('Hard - require a valid certificate'),
            'never' => _('Never - do not verify (insecure)')
        ];
    }
    /**
     * Validates the LDAPS certificate fields out of a POST.
     *
     * Shared by addPost() and ldapGeneralPost() for the same reason
     * _nestedFromPost() is: the two drifting apart is how a field ends up
     * settable on edit but not on create.
     *
     * Deliberately does NOT reject an unreadable CA path. php-fpm may run as
     * a different user than the one that placed the file (see GH-849), and
     * admins routinely configure a server before its certificate is in
     * place -- the same reasoning that lets an unverifiable chain strategy
     * save. LDAP::_applyTlsOptions() logs the unreadable case at connect
     * time, which is where it actually bites.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/893
     *
     * @throws Exception
     * @return array verify and caCert, ready to set()
     */
    private static function _tlsFromPost()
    {
        $verify = trim((string)filter_input(INPUT_POST, 'tlsVerify'));
        // Blank is the empty "- Please select an option -" entry every
        // selectForm() emits, and it means the admin left it alone.
        if ('' === $verify) {
            $verify = 'inherit';
        }
        if (!array_key_exists($verify, self::_tlsVerifyLevels())) {
            throw new Exception(
                _('Please select a valid certificate verification level')
            );
        }
        $caCert = trim((string)filter_input(INPUT_POST, 'tlsCaCert'));
        if ('' !== $caCert) {
            /**
             * Absolute only. This path is handed to the OpenLDAP client
             * inside the web server process, so a relative path resolves
             * against php-fpm's working directory -- something an admin
             * cannot see or reason about, and which differs between the
             * Apache and nginx deployments. Better to refuse it here than
             * to store a path that silently resolves somewhere else.
             */
            if ('/' !== $caCert[0]) {
                throw new Exception(
                    _('The CA certificate path must be absolute')
                );
            }
            // The column is VARCHAR(255); refuse rather than let the store
            // truncate the path into one that points at nothing.
            if (strlen($caCert) > 255) {
                throw new Exception(
                    _('The CA certificate path is too long (255 characters max)')
                );
            }
        }
        return ['verify' => $verify, 'caCert' => $caCert];
    }
    /**
     * Placeholder for the per-server depth override, naming the global it
     * inherits so an admin can see what leaving it blank actually means.
     *
     * @return string
     */
    private static function _nestedDepthPlaceholder()
    {
        return sprintf(
            /* translators: %s is the inherited default depth */
            _('Inherit global (%s)'),
            (int)self::getSetting('FOG_PLUGIN_LDAP_NESTED_DEPTH')
        );
    }
    /**
     * Validates the nested-group fields out of a POST.
     *
     * Shared by addPost() and ldapGeneralPost() so the two cannot disagree
     * about what a legal value is -- the pair of them drifting apart is how
     * a field ends up settable on edit but not on create.
     *
     * @param array $tls the validated _tlsFromPost() pair, needed because the
     *                   chain probe below has to trust the certificate on the
     *                   same terms the sign-in will
     *
     * @throws Exception
     * @return array strategy and depth, ready to set()
     */
    private static function _nestedFromPost(array $tls)
    {
        $strategy = trim((string)filter_input(INPUT_POST, 'nestedGroups'));
        // Blank is the empty "- Please select an option -" entry every
        // selectForm() emits, and it means the admin left it alone.
        if ('' === $strategy) {
            $strategy = 'off';
        }
        if (!array_key_exists($strategy, self::_nestedStrategies())) {
            throw new Exception(
                _('Please select a valid nested group strategy')
            );
        }
        $depth = trim((string)filter_input(INPUT_POST, 'nestedDepth'));
        // Blank and 0 both mean "inherit the global", which is what the
        // column's default already is.
        if ('' === $depth) {
            $depth = 0;
        }
        if (!is_numeric($depth)) {
            throw new Exception(_('Nested depth must be a number'));
        }
        $depth = (int)$depth;
        // Upper bound is a typo guard, not a policy: each level is one more
        // query on every sign-in, and a fat-fingered 1000 would hang logins
        // against a slow directory rather than fail visibly.
        if ($depth < 0 || $depth > 100) {
            throw new Exception(
                _('Nested depth must be between 0 and 100, or blank to inherit')
            );
        }
        /**
         * Chain only works on a directory that implements the matching rule,
         * so ask the directory before storing it. LDAP::save() refuses this
         * too and is the authority -- every writer including the REST API
         * goes through it -- but a throw here is what puts a readable
         * message on the form the admin is looking at, next to the field
         * they just changed.
         *
         * Address and port come from the POST rather than the stored object
         * because the admin may be pointing an existing server at a new
         * directory in the same submission. The TLS pair comes from the same
         * POST for the same reason, and because without it an LDAPS directory
         * whose certificate only validates under this server's own settings
         * answers "undeterminable" and its chain setting goes in unverified
         * (#893).
         */
        if ('chain' === $strategy) {
            $supported = LDAP::supportsChain(
                trim((string)filter_input(INPUT_POST, 'address')),
                trim((string)filter_input(INPUT_POST, 'port')),
                isset($_POST['isLDAPs']),
                $tls['verify'],
                $tls['caCert']
            );
            if (false === $supported) {
                throw new Exception(
                    _(
                        'This directory does not advertise support for '
                        . 'nested group chaining, so the chain strategy '
                        . 'would match nothing. Use the expand strategy '
                        . 'instead.'
                    )
                );
            }
        }
        return ['strategy' => $strategy, 'depth' => $depth];
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
        $nestedGroups = filter_input(INPUT_POST, 'nestedGroups');
        $nestedSel = self::selectForm(
            'nestedGroups',
            self::_nestedStrategies(),
            $nestedGroups ?: 'off',
            true
        );
        $nestedDepth = filter_input(INPUT_POST, 'nestedDepth');
        $tlsVerify = filter_input(INPUT_POST, 'tlsVerify');
        $tlsSel = self::selectForm(
            'tlsVerify',
            self::_tlsVerifyLevels(),
            $tlsVerify ?: 'inherit',
            true
        );
        $tlsCaCert = filter_input(INPUT_POST, 'tlsCaCert');
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
            // Grouped with the SSL toggle and the port rather than filed with
            // the search settings: these three are the one cluster an admin
            // touches when moving a server onto LDAPS (#893).
            self::makeLabel(
                $labelClass,
                'tlsVerify',
                _('Certificate Verification')
            ) => $tlsSel,
            self::makeLabel(
                $labelClass,
                'tlsCaCert',
                _('CA Certificate Path')
            ) => self::makeInput(
                'form-control ldaptlscacert-input',
                'tlsCaCert',
                '/etc/ssl/certs/my-ca.pem',
                'text',
                'tlsCaCert',
                $tlsCaCert
            ),
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
                'nestedGroups',
                _('Nested Groups')
            ) => $nestedSel,
            self::makeLabel(
                $labelClass,
                'nestedDepth',
                _('Nested Depth')
            ) => self::makeInput(
                'form-control ldapnesteddepth-input',
                'nestedDepth',
                self::_nestedDepthPlaceholder(),
                'number',
                'nestedDepth',
                $nestedDepth
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
                // TLS first: the chain probe inside _nestedFromPost() needs
                // these to reach an LDAPS directory on this server's terms.
                $tls = self::_tlsFromPost();
                $nested = self::_nestedFromPost($tls);
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
                    ->set('nestedGroups', $nested['strategy'])
                    ->set('nestedDepth', $nested['depth'])
                    ->set('tlsVerify', $tls['verify'])
                    ->set('tlsCaCert', $tls['caCert'])
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
        $nestedGroups = (
            filter_input(INPUT_POST, 'nestedGroups') ?:
            $this->obj->get('nestedGroups')
        );
        $nestedSel = self::selectForm(
            'nestedGroups',
            self::_nestedStrategies(),
            $nestedGroups ?: 'off',
            true
        );
        // 0 is the stored "inherit" sentinel, and it must render as blank so
        // the placeholder can say what it inherits -- printing a literal 0
        // would read as a depth of zero.
        $nestedDepth = (
            filter_input(INPUT_POST, 'nestedDepth')
            ?? (int)$this->obj->get('nestedDepth')
        );
        $nestedDepth = ((int)$nestedDepth > 0 ? (int)$nestedDepth : '');
        $tlsVerify = (
            filter_input(INPUT_POST, 'tlsVerify') ?:
            $this->obj->get('tlsVerify')
        );
        $tlsSel = self::selectForm(
            'tlsVerify',
            self::_tlsVerifyLevels(),
            $tlsVerify ?: 'inherit',
            true
        );
        // ?? rather than ?: because this field is clearable: '' is a POST that
        // blanked it out and must render blank, where ?: would hand the stored
        // path straight back and make the field impossible to empty.
        $tlsCaCert = (
            filter_input(INPUT_POST, 'tlsCaCert')
            ?? $this->obj->get('tlsCaCert')
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
            // Same cluster as on the create form, in the same position, so an
            // admin who learned the one finds the other where they expect.
            self::makeLabel(
                $labelClass,
                'tlsVerify',
                _('Certificate Verification')
            ) => $tlsSel,
            self::makeLabel(
                $labelClass,
                'tlsCaCert',
                _('CA Certificate Path')
            ) => self::makeInput(
                'form-control ldaptlscacert-input',
                'tlsCaCert',
                '/etc/ssl/certs/my-ca.pem',
                'text',
                'tlsCaCert',
                $tlsCaCert
            ),
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
                'nestedGroups',
                _('Nested Groups')
            ) => $nestedSel,
            self::makeLabel(
                $labelClass,
                'nestedDepth',
                _('Nested Depth')
            ) => self::makeInput(
                'form-control ldapnesteddepth-input',
                'nestedDepth',
                self::_nestedDepthPlaceholder(),
                'number',
                'nestedDepth',
                $nestedDepth
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
        /**
         * Sits immediately left of Update, so it must be emitted *after* it:
         * bare float-end siblings stack right-to-left in emission order.
         * Secondary rather than primary because two blues touching read as one
         * wide button -- the supporting action has to be visibly the lesser
         * one. An <a> rather than makeButton() because it navigates; a bare
         * <button> inside this form would default to submit.
         */
        $buttons .= sprintf(
            '<a class="btn btn-secondary float-end" '
            . 'href="?node=ldapgroup&sub=add">%s</a>',
            Initiator::e(_('Create New LDAP Group'))
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
        // TLS first: the chain probe inside _nestedFromPost() needs these to
        // reach an LDAPS directory on this server's terms.
        $tls = self::_tlsFromPost();
        $nested = self::_nestedFromPost($tls);
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
            ->set('nestedGroups', $nested['strategy'])
            ->set('nestedDepth', $nested['depth'])
            ->set('tlsVerify', $tls['verify'])
            ->set('tlsCaCert', $tls['caCert'])
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

        // Grants
        $tabData[] = [
            'name' => _('Grants'),
            'id' => 'ldap-grants',
            'generator' => function () {
                $this->ldapGrants();
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
        $nestedDepth = (
            filter_input(INPUT_POST, 'nesteddepth') ?:
            self::getSetting('FOG_PLUGIN_LDAP_NESTED_DEPTH')
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
                'nesteddepth',
                _('Default nested group depth')
            ) => self::makeInput(
                'form-control ldapnesteddepth-input',
                'nesteddepth',
                '10',
                'number',
                'nesteddepth',
                $nestedDepth,
                true
            )
            // The cost is per sign-in and per server, and on the API's basic
            // auth path a sign-in happens on every request -- so say what
            // raising this actually buys the directory, rather than leaving
            // it as a bare number.
            . '<small class="form-text text-muted">'
            . _(
                'Applies to LDAP servers using the "expand" strategy, which '
                . 'walks one query per level of nesting on every sign-in. A '
                . 'server can override this on its own settings. Directories '
                . 'rarely nest more than three or four deep.'
            )
            . '</small>',
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
            // The global floor for the `expand` strategy. Unlike the
            // per-server override, 0 is not "inherit" here -- there is
            // nothing above this to inherit from -- so it has to be a real
            // depth, and a depth of 0 would silently disable nesting on
            // every server that did not set its own.
            $nestedDepth = trim(
                (string)filter_input(INPUT_POST, 'nesteddepth')
            );
            if (!is_numeric($nestedDepth)
                || (int)$nestedDepth < 1
                || (int)$nestedDepth > 100
            ) {
                throw new Exception(
                    _('Nested group depth must be between 1 and 100')
                );
            }
            if (!self::setSetting(
                'FOG_PLUGIN_LDAP_NESTED_DEPTH',
                (int)$nestedDepth
            )) {
                $serverFault = true;
                throw new Exception(_('Unable to set nested group depth.'));
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
     * What the server's directory groups grant, one card per kind of grant.
     *
     * Read-only: what a group grants is edited on the group's own page,
     * where roles and user groups are ordinary association tabs. This tab
     * exists so an admin looking at a server can see and reach its groups
     * without having to know they live under a separate node.
     *
     * Two stacked cards rather than one grid. The single grid this replaces
     * comma-joined both kinds of grant into one cell, which forced a
     * "Role - " / "User Group - " prefix on every entry to stay readable and
     * left the cell unsortable and unsearchable. A card heading carries that
     * meaning for free, and separate feeds mean each card sorts and searches
     * on the granted thing's own name.
     *
     * One row per mapping, not per group: a group granting two roles is two
     * rows, and a group granting nothing of a kind is absent from that card
     * rather than filling it with "Nothing yet". So each card reads as the
     * list of grants it actually is.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @return void
     */
    public function ldapGrants()
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
        $this->_grantCard(
            'ldap-grants-roles-table',
            _('Roles'),
            _('Role')
        );
        $this->_grantCard(
            'ldap-grants-usergroups-table',
            _('User Groups'),
            _('User Group')
        );
        // No create button here: it lives in the General tab's footer next to
        // Update, where every other page keeps its commit-row actions. This
        // tab is a read-only view, so an action button inside it was the odd
        // one out.
        echo '</div>';
        echo '</div>';
    }
    /**
     * One titled sub-card holding a read-only grants grid.
     *
     * card-primary card-outline nested in the tab's own card is the shape
     * renderCreateForm() already uses for its titled sections, so the two
     * cards read as sections of one tab rather than two unrelated panels.
     *
     * Real DataTables rather than hand-built ones: the point of the shared
     * table plumbing is that every grid in FOG sorts, searches and pages the
     * same way, and these grow with the directory so paging stops being
     * optional at some size. Rows come from _grantList() via the two subs.
     *
     * @param string $tableId     dom id, matched in fog.ldap.edit.js
     * @param string $title       the card heading
     * @param string $grantHeader the second column's heading
     *
     * @return void
     */
    private function _grantCard($tableId, $title, $grantHeader)
    {
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">' . Initiator::e($title) . '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        printf(
            '<table id="%s" class="display table table-bordered '
            . 'table-striped">',
            Initiator::e($tableId)
        );
        echo '<thead><tr class="header">';
        echo '<th data-column="0" scope="col">'
            . Initiator::e(_('Directory Group'))
            . '</th>';
        echo '<th data-column="1" scope="col">'
            . Initiator::e($grantHeader)
            . '</th>';
        echo '</tr></thead><tbody></tbody></table>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Feeds the Roles card.
     *
     * @return void
     */
    public function getGrantRoleList()
    {
        $this->_grantList('role');
    }
    /**
     * Feeds the User Groups card.
     *
     * @return void
     */
    public function getGrantUserGroupList()
    {
        $this->_grantList('usergroup');
    }
    /**
     * Feeds one of the two grants grids.
     *
     * Server-side so each grid pages rather than rendering every mapping at
     * once, and scoped to the server being edited -- the groups are a
     * property of this server, not a global list. The scope goes in as a
     * bound-safe integer because it is the object's own id, and it goes in
     * as the "all" condition so the count the grid reports is this server's
     * rather than every server's.
     *
     * One body for both kinds, keyed off $kind, so the two feeds cannot
     * drift apart: the only differences between them are table and column
     * names.
     *
     * The joins live here rather than on the two association manager
     * classes. complex() takes its query templates as arguments precisely so
     * a caller can widen them, nothing else queries these tables this way,
     * and a class-level override would silently reshape every other use of
     * the manager.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @param string $kind either 'role' or 'usergroup'
     *
     * @return void
     */
    private function _grantList($kind)
    {
        // Per kind: the association table and its primary key, the two
        // foreign keys on it, then the target's table, key, name column and
        // the node its edit page lives at.
        $kinds = [
            'role' => [
                'assoc' => 'ldapGroupRoleAssoc',
                'assocId' => 'lgraID',
                'groupKey' => 'lgraGroupID',
                'targetKey' => 'lgraRoleID',
                'targetTable' => 'roles',
                'targetId' => 'rID',
                'targetName' => 'rName',
                'node' => 'role'
            ],
            'usergroup' => [
                'assoc' => 'ldapGroupUserGroupAssoc',
                'assocId' => 'lgugID',
                'groupKey' => 'lgugGroupID',
                'targetKey' => 'lgugUserGroupID',
                'targetTable' => 'userGroups',
                'targetId' => 'ugID',
                'targetName' => 'ugName',
                'node' => 'usergroup'
            ]
        ];
        $map = $kinds[$kind];

        header('Content-type: application/json');
        $pass_vars = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        // LEFT OUTER rather than INNER on the target: #885 clears a deleted
        // role's or user group's mappings, but a database restore or a
        // hand-edited row can still leave one behind, and a stale mapping is
        // live -- better shown in the grid than filtered silently out of it.
        // The group join stays LEFT OUTER for symmetry; the server scope
        // below excludes a mapping whose group is gone either way.
        $joins = sprintf(
            'LEFT OUTER JOIN `LDAPGroups`
            ON `%1$s`.`%2$s` = `LDAPGroups`.`lgID`
            LEFT OUTER JOIN `%3$s`
            ON `%1$s`.`%4$s` = `%3$s`.`%5$s`',
            $map['assoc'],
            $map['groupKey'],
            $map['targetTable'],
            $map['targetKey'],
            $map['targetId']
        );
        // complex() sprintf's these, so the slot counts have to match what it
        // passes: columns/table/where/order/limit, then key/table/where, then
        // key/table with the "all" condition appended by complex() itself.
        $sqlStr = "SELECT `%s`
            FROM `%s`
            $joins
            %s
            %s
            %s";
        $filterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            $joins
            %s";
        $totalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            $joins";

        $columns = [
            [
                'db' => 'lgName',
                'dt' => 'groupLink',
                'formatter' => function ($d, $row) use ($map) {
                    return self::entityLink(
                        'ldapgroup',
                        $row[$map['groupKey']],
                        $d
                    );
                }
            ],
            [
                'db' => $map['targetName'],
                'dt' => 'grantLink',
                'formatter' => function ($d, $row) use ($map) {
                    // Orphan mapping: no target row, so nothing to link to.
                    // Keep the canonical shape so the id is still readable.
                    if (null === $d) {
                        return sprintf(
                            '%s - (%d)',
                            Initiator::e(_('Unknown')),
                            (int)$row[$map['targetKey']]
                        );
                    }
                    return self::entityLink(
                        $map['node'],
                        $row[$map['targetKey']],
                        $d
                    );
                }
            ],
            // Not rendered. The two formatters above need these ids, and
            // dataOutput() only ever sees columns that are in the SELECT
            // list -- the client declares just the two visible ones, and
            // filter()/order() match on the 'dt' name rather than position,
            // so the extra pair costs nothing but the ids.
            [
                'db' => $map['groupKey'],
                'dt' => 'groupID'
            ],
            [
                'db' => $map['targetKey'],
                'dt' => 'grantID'
            ],
        ];

        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                $map['assoc'],
                $map['assocId'],
                $columns,
                $sqlStr,
                $filterStr,
                $totalStr,
                null,
                sprintf(
                    '`LDAPGroups`.`lgServerID` = %d',
                    (int)$this->obj->get('id')
                ),
                'groupLink'
            )
        ));
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
