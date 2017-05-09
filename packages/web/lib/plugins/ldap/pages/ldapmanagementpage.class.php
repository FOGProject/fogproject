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
        $this->name = 'LDAP Management';
        self::$foglang['ExportLdap'] = _('Export LDAPs');
        self::$foglang['ImportLdap'] = _('Import LDAPs');
        parent::__construct($name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
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
            '<a href="?node=ldap&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${description}',
            '${address}',
            '${port}',
            '${adminGroup}',
        );
        $this->attributes = array(
            array('class' => 'l filter-false','width' => 16),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
        );
        self::$returnData = function (&$LDAP) {
            if (!$LDAP->isValid()) {
                return;
            }
            $this->data[] = array(
                'id' => $LDAP->get('id'),
                'name' => $LDAP->get('name'),
                'description' => $LDAP->get('description'),
                'address' => $LDAP->get('address'),
                'searchDN' => $LDAP->get('DN'),
                'port' => $LDAP->get('port'),
                'userNamAttr' => $LDAP->get('userNamAttr'),
                'grpMemberAttr' => $LDAP->get('grpMemberAttr'),
                'grpSearchDN' => $LDAP->get('grpSearchDN'),
                'adminGroup' => $LDAP->get('adminGroup'),
                'userGroup' => $LDAP->get('userGroup'),
                'searchScope' => $LDAP->get('searchScope'),
                'bindDN' => $LDAP->get('bindDN'),
                'bindPwd' => $LDAP->get('bindPwd'),
                'useGroupMatch' => $LDAP->get('useGroupMatch'),
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
        $this->title = _('New LDAP Server');
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
            _('LDAP Connection Name') => '<input class="smaller" type="text" '
            . sprintf(
                'id="name" name="name" value="%s"/>',
                $_REQUEST['name']
            ),
            _('LDAP Server Description') => '<textarea name="description">'
            . $_REQUEST['description'] . '</textarea>',
            _('LDAP Server Address') => '<input class="smaller" type="text" '
            . sprintf(
                'id="address" name="address" value="%s"/>',
                $_REQUEST['address']
            ),
            _('LDAP Server Port') => '<select id="port" name="port">'
            . sprintf(
                '<option value="">- %s -</option>',
                self::$foglang['PleaseSelect']
            )
            . sprintf(
                '<option value="389"%s>389</option>',
                $_REQUEST['port'] == 389 ? ' selected' : ''
            )
            . sprintf(
                '<option value="636"%s>636</option>',
                $_REQUEST['port'] == 636 ? ' selected' : ''
            )
            . '</select>',
            _('Use Group Matching (recommended)') => '<select id="useGroupMatch" '
            . 'name="useGroupMatch">'
            . sprintf(
                '<option value="0"%s>%s</option>',
                isset($_REQUEST['useGroupMatch'])
                && $_REQUEST['useGroupMatch'] < 1 ? ' selected' : '',
                _('No')
            )
            . sprintf(
                '<option value="1"%s>%s</option>',
                !isset($_REQUEST['useGroupMatch'])
                || $_REQUEST['useGroupMatch'] > 0 ? ' selected' : '',
                _('Yes')
            )
            . '</select>',
            _('Search Base DN') => '<input class="smaller" type="text" '
            . sprintf(
                'id="searchDN" name="searchDN" value="%s"/>',
                $_REQUEST['searchDN']
            ),
            _('Group Search DN') => '<input class="smaller" type="text" '
            . sprintf(
                'id="grpSearchDN" name="grpSearchDN" value="%s"/>',
                $_REQUEST['grpSearchDN']
            ),
            _('Admin Group') => '<input class="smaller" type="text" '
            . sprintf(
                'id="adminGroup" name="adminGroup" value="%s"/>',
                $_REQUEST['adminGroup']
            ),
            _('Mobile Group') => '<input class="smaller" type="text" '
            . sprintf(
                'id="userGroup" name="userGroup" value="%s"/>',
                $_REQUEST['userGroup']
            ),
            _('Initial Template') => '<select class="smaller" '
            . 'id="inittemplate">'
            . '<option value="pick" selected >Pick a template</option>'
            . '<option value="msad">Microsoft AD</option>'
            . '<option value="open">OpenLDAP</option>'
            . '<option value="edir">Generic LDAP</option>'
            .  '</select>',
            _('User Name Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'id="userNamAttr" name="userNamAttr" value="%s"/>',
                $_REQUEST['userNameAttr']
            ),
            _('Group Member Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'id="grpMemberAttr" name="grpMemberAttr" value="%s"/>',
                $_REQUEST['grpMemberAttr']
            ),
            _('Search Scope') => '<select id="searchScope" name="searchScope">'
            . sprintf(
                '<option value="">- %s -</option>',
                self::$foglang['PleaseSelect']
            )
            . sprintf(
                '<option value="0"%s>%s</option>',
                isset($_REQUEST['searchScope'])
                && $_REQUEST['searchScope'] == 0 ? ' selected' : '',
                _('Base only')
            )
            . sprintf(
                '<option value="1"%s>%s</option>',
                $_REQUEST['searchScope'] == 1 ? ' selected' : '',
                _('Subtree Only')
            )
            . sprintf(
                '<option value="1"%s>%s</option>',
                $_REQUEST['searchScope'] == 2 ? ' selected' : '',
                _('Subtree and below')
            )
            . '</select>',
            _('Bind DN') => '<input class="smaller" type="text" '
            . sprintf(
                'id="bindDN" name="bindDN" value="%s"/>',
                $_REQUEST['bindDN']
            ),
            _('Bind Password') => '<input class="smaller" type="password" '
            . sprintf(
                'id="bindPwd" name="bindPwd" value="%s"/>',
                $_REQUEST['bindPwd']
            ),
            '&nbsp;' => sprintf(
                '<input class="smaller" name="add" type="submit" value="%s"/>',
                _('Add')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent(
            'LDAP_ADD',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        printf('<form method="post" action="%s">', $this->formAction);
        $this->render();
        echo '</form>';
    }
    /**
     * Create the new item
     *
     * @return void
     */
    public function addPost()
    {
        try {
            if (!isset($_REQUEST['add'])) {
                throw new Exception(_('Not able to add'));
            }
            $name = trim($_REQUEST['name']);
            $address = trim($_REQUEST['address']);
            $description = trim($_REQUEST['description']);
            $searchDN = trim($_REQUEST['searchDN']);
            $port = trim($_REQUEST['port']);
            $userNamAttr = trim($_REQUEST['userNamAttr']);
            $grpMemberAttr = trim($_REQUEST['grpMemberAttr']);
            $adminGroup = trim($_REQUEST['adminGroup']);
            $userGroup = trim($_REQUEST['userGroup']);
            $searchScope = trim($_REQUEST['searchScope']);
            if (!is_numeric($searchScope)) {
                $searchScope = 0;
            }
            $bindDN = trim($_REQUEST['bindDN']);
            $bindPwd = trim($_REQUEST['bindPwd']);
            $grpSearchDN = trim($_REQUEST['grpSearchDN']);
            $useGroupMatch = trim($_REQUEST['useGroupMatch']);
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
            if (!in_array($port, array(389, 636))) {
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
                ->set('bindPwd', self::encryptpw($bindPwd))
                ->set('useGroupMatch', $useGroupMatch)
                ->set('grpSearchDN', $grpSearchDN);
            if ($LDAP->save()) {
                self::setMessage(_('LDAP Server Added, editing!'));
                self::redirect(
                    sprintf(
                        '?node=ldap&sub=edit&id=%s',
                        $LDAP->get('id')
                    )
                );
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Presents the user with fields to edit
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
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
            _('LDAP Connection Name') => '<input class="smaller" type="text" '
            . sprintf(
                'id="name" name="name" value="%s"/>',
                (
                    $_REQUEST['name'] ?
                    $_REQUEST['name'] :
                    $this->obj->get('name')
                )
            ),
            _('LDAP Server Description') => '<textarea name="description">'
            . (
                $_REQUEST['description'] ?
                $_REQUEST['description'] :
                $this->obj->get('description')
            )
            . '</textarea>',
            _('LDAP Server Address') => '<input class="smaller" type="text" '
            . sprintf(
                'id="address" name="address" value="%s"/>',
                (
                    $_REQUEST['address'] ?
                    $_REQUEST['address'] :
                    $this->obj->get('address')
                )
            ),
            _('LDAP Server Port') => '<select id="port" name="port">'
            . sprintf(
                '<option value="">- %s -</option>',
                self::$foglang['PleaseSelect']
            )
            . sprintf(
                '<option value="389"%s>389</option>',
                (
                    $_REQUEST['port'] == 389 ?
                    ' selected' :
                    (
                        $this->obj->get('port') == 389 ?
                        ' selected' :
                        ''
                    )
                )
            )
            . sprintf(
                '<option value="636"%s>636</option>',
                (
                    $_REQUEST['port'] == 636 ?
                    ' selected' :
                    (
                        $this->obj->get('port') == 636 ?
                        ' selected' :
                        ''
                    )
                )
            )
            . '</select>',
            _('Use Group Matching (recommended)') => '<select id="useGroupMatch" '
            . 'name="useGroupMatch">'
            . sprintf(
                '<option value="0"%s>%s</option>',
                (
                    $_REQUEST['useGroupMatch'] < 1 ?
                    ' selected' :
                    (
                        $this->obj->get('useGroupMatch') < 1 ?
                        ' selected' :
                        ''
                    )
                ),
                _('No')
            )
            . sprintf(
                '<option value="1"%s>%s</option>',
                (
                    $_REQUEST['useGroupMatch'] > 0 ?
                    ' selected' :
                    (
                        $this->obj->get('useGroupMatch') > 0 ?
                        ' selected' :
                        ''
                    )
                ),
                _('Yes')
            )
            . '</select>',
            _('Search Base DN') => '<input class="smaller" type="text" '
            . sprintf(
                'id="searchDN" name="searchDN" value="%s"/>',
                (
                    $_REQUEST['searchDN'] ?
                    $_REQUEST['searchDN'] :
                    $this->obj->get('searchDN')
                )
            ),
            _('Group Search DN') => '<input class="smaller" type="text" '
            . sprintf(
                'id="grpSearchDN" name="grpSearchDN" value="%s"/>',
                (
                    $_REQUEST['grpSearchDN'] ?
                    $_REQUEST['grpSearchDN'] :
                    $this->obj->get('grpSearchDN')
                )
            ),
            _('Admin Group') => '<input class="smaller" type="text" '
            . sprintf(
                'id="adminGroup" name="adminGroup" value="%s"/>',
                (
                    $_REQUEST['adminGroup'] ?
                    $_REQUEST['adminGroup'] :
                    $this->obj->get('adminGroup')
                )
            ),
            _('Mobile Group') => '<input class="smaller" type="text" '
            . sprintf(
                'id="userGroup" name="userGroup" value="%s"/>',
                (
                    $_REQUEST['userGroup'] ?
                    $_REQUEST['userGroup'] :
                    $this->obj->get('userGroup')
                )
            ),
            _('Initial Template') => '<select class="smaller" '
            . 'id="inittemplate">'
            . '<option value="pick" selected >Pick a template</option>'
            . '<option value="msad">Microsoft AD</option>'
            . '<option value="open">OpenLDAP</option>'
            . '<option value="edir">Generic LDAP</option>'
            . '</select>',
            _('User Name Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'id="userNamAttr" name="userNamAttr" value="%s"/>',
                (
                    $_REQUEST['userNamAttr'] ?
                    $_REQUEST['userNamAttr'] :
                    $this->obj->get('userNamAttr')
                )
            ),
            _('Group Member Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'id="grpMemberAttr" name="grpMemberAttr" value="%s"/>',
                (
                    $_REQUEST['grpMemberAttr'] ?
                    $_REQUEST['grpMemberAttr'] :
                    $this->obj->get('grpMemberAttr')
                )
            ),
            _('Search Scope') => '<select id="searchScope" name="searchScope">'
            . sprintf(
                '<option value="">- %s -</option>',
                self::$foglang['PleaseSelect']
            )
            . sprintf(
                '<option value="0"%s>%s</option>',
                (
                    isset($_REQUEST['searchScope'])
                    && $_REQUEST['searchScope'] == 0 ?
                    ' selected' :
                    (
                        $this->obj->get('searchScope') == 0 ?
                        ' selected' :
                        ''
                    )
                ),
                _('Base only')
            )
            . sprintf(
                '<option value="1"%s>%s</option>',
                (
                    $_REQUEST['searchScope'] == 1 ?
                    ' selected' :
                    (
                        $this->obj->get('searchScope') == 1 ?
                        ' selected' :
                        ''
                    )
                ),
                _('Base and subtree')
            )
            . sprintf(
                '<option value="2"%s>%s</option>',
                (
                    $_REQUEST['searchScope'] == 2 ?
                    ' selected' :
                    (
                        $this->obj->get('searchScope') == 2 ?
                        ' selected' :
                        ''
                    )
                ),
                _('Subtree and below')
            )
            . '</select>',
            _('Bind DN') => '<input class="smaller" type="text" '
            . sprintf(
                'id="bindDN" name="bindDN" value="%s"/>',
                (
                    $_REQUEST['bindDN'] ?
                    $_REQUEST['bindDN'] :
                    $this->obj->get('bindDN')
                )
            ),
            _('Bind Password') => '<input class="smaller" type="password" '
            . sprintf(
                'id="bindPwd" name="bindPwd" value="%s"/>',
                (
                    $_REQUEST['bindPwd'] ?
                    $_REQUEST['bindPwd'] :
                    $this->obj->get('bindPwd')
                )
            ),
            '&nbsp;' => sprintf(
                '<input class="smaller" name="update" type="submit" value="%s"/>',
                _('Update')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager->processEvent(
            'LDAP_EDIT',
            array(
                'headerData' => &$this->headerData,
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes
            )
        );
        printf('<form method="post" action="%s">', $this->formAction);
        $this->render();
        echo '</form>';
    }
    /**
     * Updates the current item
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager->processEvent('LDAP_EDIT_POST', array('LDAP'=> &$LDAP));
        try {
            if (!isset($_REQUEST['update'])) {
                throw new Exception(_('Not able to update'));
            }
            $name = trim($_REQUEST['name']);
            $address = trim($_REQUEST['address']);
            $description = trim($_REQUEST['description']);
            $searchDN = trim($_REQUEST['searchDN']);
            $port = trim($_REQUEST['port']);
            $userNamAttr = trim($_REQUEST['userNamAttr']);
            $grpMemberAttr = trim($_REQUEST['grpMemberAttr']);
            $adminGroup = trim($_REQUEST['adminGroup']);
            $userGroup = trim($_REQUEST['userGroup']);
            $searchScope = trim($_REQUEST['searchScope']);
            $grpSearchDN = trim($_REQUEST['grpSearchDN']);
            $useGroupMatch = trim($_REQUEST['useGroupMatch']);
            if (!is_numeric($searchScope)) {
                $searchScope = 0;
            }
            $bindDN = trim($_REQUEST['bindDN']);
            $bindPwd = trim($_REQUEST['bindPwd']);
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
            if (!in_array($port, array(389, 636))) {
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
                ->set('bindPwd', self::encryptpw($bindPwd))
                ->set('useGroupMatch', $useGroupMatch)
                ->set('grpSearchDN', $grpSearchDN);
            if (!$LDAP->save()) {
                throw new Exception(_('Database update failed'));
            }
            self::$HookManager->processEvent(
                'LDAP_EDIT_SUCCESS',
                array('LDAP' => &$this->obj)
            );
            self::setMessage(_('LDAP information updated!'));
        } catch (Exception $e) {
            self::$HookManager->processEvent(
                'LDAP_EDIT_FAIL',
                array('LDAP' => &$this->obj)
            );
            self::setMessage($e->getMessage());
        }
        self::redirect($this->formAction);
    }
}
