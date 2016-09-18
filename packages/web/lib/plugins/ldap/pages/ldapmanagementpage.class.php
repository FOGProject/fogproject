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
        self::$foglang['ExportLDAP'] = _('Export LDAPs');
        self::$foglang['ImportLDAP'] = _('Import LDAPs');
        parent::__construct($name);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
                "$this->delformat" => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('LDAP Server Name') => $this->obj->get('name'),
                _('LDAP Server Address') => $this->obj->get('address'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('LDAP Server Name'),
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
                'adminGroup' => $LDAP->get('adminGroup'),
                'userGroup' => $LDAP->get('userGroup'),
                'searchScope' => $LDAP->get('searchScope'),
                'bindDN' => $LDAP->get('bindDN'),
                'bindPwd' => $LDAP->get('bindPwd'),
            );
            unset($LDAP);
        };
    }
    /**
     * The starting page
     *
     * @return void
     */
    public function index()
    {
        global $sub;
        $this->title = _('All LDAPs');
        $count = self::getClass('LDAPManager')
            ->count();
        if ($_SESSION['DataReturn'] > 0
            && $count > $_SESSION['DataReturn']
            && $sub != 'list'
        ) {
            $this->redirect(sprintf('?node=%s&sub=search', $this->node));
        }
        $this->data = array();
        $LDAPs = $this->getClass('LDAPManager')->find();
        array_walk($LDAPs, self::$returnData);
        self::$HookManager->processEvent(
            'LDAP_DATA',
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
     * Search filtered results
     *
     * @return void
     */
    public function searchPost()
    {
        $this->data = array();
        $LDAPs = self::getClass('LDAPManager')
            ->search('', true);
        array_walk($LDAPs, self::$returnData);
        self::$HookManager->processEvent(
            'LDAP_DATA',
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
                'name="name" value="%s"/>',
                $_REQUEST['name']
            ),
            _('LDAP Server Description') => '<textarea name="description">'
            . $_REQUEST['description'] . '</textarea>',
            _('LDAP Server Address') => '<input class="smaller" type="text" '
            . sprintf(
                'name="address" value="%s"/>',
                $_REQUEST['address']
            ),
            _('LDAP Server Port') => '<input class="smaller" type="text" '
            . sprintf(
                'name="port" value="%s"/>',
                $_REQUEST['port']
            ),
            _('Search Base DN') => '<input class="smaller" type="text" '
            . sprintf(
                'name="searchDN" value="%s"/>',
                $_REQUEST['searchDN']
            ),
            _('Admin Group') => '<input class="smaller" type="text" '
            . sprintf(
                'name="adminGroup" value="%s"/>',
                $_REQUEST['adminGroup']
            ),
            _('User Group') => '<input class="smaller" type="text" '
            . sprintf(
                'name="userGroup" value="%s"/>',
                $_REQUEST['userGroup']
            ),
            _('User Nam Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'name="userNamAttr" value="%s"/>',
                $_REQUEST['userNameAttr']
            ),
            _('Group Member Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'name="grpMemberAttr" value="%s"/>',
                $_REQUEST['grpMemberAttr']
            ),
            _('Search Scope') => '<input class="smaller" type="text" '
            . sprintf(
                'name="searchScope" value="%s"/>',
                $_REQUEST['searchScope']
            ),
            _('Bind DN') => '<input class="smaller" type="text" '
            . sprintf(
                'name="bindDN" value="%s"/>',
                $_REQUEST['bindDN']
            ),
            _('Bind Pwd') => '<input class="smaller" type="text" '
            . sprintf(
                'name="bindPwd" value="%s"/>',
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
                ->set('bindPwd', $bindPwd);
            if ($LDAP->save()) {
                $this->setMessage(_('LDAP Server Added, editing!'));
                $this->redirect(
                    sprintf(
                        '?node=ldap&sub=edit&id=%s',
                        $LDAP->get('id')
                    )
                );
            }
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
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
                'name="name" value="%s"/>',
                $this->obj->get('name')
            ),
            _('LDAP Server Description') => '<textarea name="description">'
            . $this->obj->get('description') . '</textarea>',
            _('LDAP Server Address') => '<input class="smaller" type="text" '
            . sprintf(
                'name="address" value="%s"/>',
                $this->obj->get('address')
            ),
            _('LDAP Server Port') => '<input class="smaller" type="text" '
            . sprintf(
                'name="port" value="%d"/>',
                $this->obj->get('port')
            ),
            _('Search Base DN') => '<input class="smaller" type="text" '
            . sprintf(
                'name="searchDN" value="%s"/>',
                $this->obj->get('searchDN')
            ),
            _('Admin Group') => '<input class="smaller" type="text" '
            . sprintf(
                'name="adminGroup" value="%s"/>',
                $this->obj->get('adminGroup')
            ),
            _('User Group') => '<input class="smaller" type="text" '
            . sprintf(
                'name="userGroup" value="%s"/>',
                $this->obj->get('userGroup')
            ),
            _('User Nam Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'name="userNamAttr" value="%s"/>',
                $this->obj->get('userNamAttr')
            ),
            _('Group Member Attribute') => '<input class="smaller" type="text" '
            . sprintf(
                'name="grpMemberAttr" value="%s"/>',
                $this->obj->get('grpMemberAttr')
            ),
            _('Search Scope') => '<input class="smaller" type="text" '
            . sprintf(
                'name="searchScope" value="%s"/>',
                $this->obj->get('searchScope')
            ),
            _('Bind DN') => '<input class="smaller" type="text" '
            . sprintf(
                'name="bindDN" value="%s"/>',
                $this->obj->get('bindDN')
            ),
            _('Bind Pwd') => '<input class="smaller" type="text" '
            . sprintf(
                'name="bindPwd" value="%s"/>',
                $this->obj->get('bindPwd')
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
            $bindDN = trim($_REQUEST['bindDN']);
            $bindPwd = trim($_REQUEST['bindPwd']);
            if (empty($name)) {
                throw new Exception(_('Please enter a name for this LDAP server.'));
            }
            if (empty($address)) {
                throw new Exception(_('Please enter a LDAP server address'));
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
                ->set('bindPwd', $bindPwd);
            if (!$LDAP->save()) {
                throw new Exception(_('Database update failed'));
            }
            self::$HookManager->processEvent(
                'LDAP_EDIT_SUCCESS',
                array('LDAP' => &$this->obj)
            );
            $this->setMessage(_('LDAP information updated!'));
        } catch (Exception $e) {
            self::$HookManager->processEvent(
                'LDAP_EDIT_FAIL',
                array('LDAP' => &$this->obj)
            );
            $this->setMessage($e->getMessage());
        }
        $this->redirect($this->formAction);
    }
}
