<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlManagementPage
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlManagementPage
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlManagementPage extends FOGPage
{
    public $node = 'accesscontrol';
    /**
     * Constructor
     *
     * @param string $name The name for the page.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Access Control Management';

        if (self::$ajax) {
            session_write_close();
            ignore_user_abort(true);
            set_time_limit(0);
        }
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );
        global $node;
        global $sub;
        global $id;
        if ($node !== 'service'
            && preg_match('#edit#i', $sub)
            && (!isset($id)
            || !is_numeric($id)
            || $id < 1)
        ) {
            self::setMessage(
                _('ID Must be set to edit')
            );
            self::redirect(
                "?node=$node"
            );
            exit;
        }
        $subs = array(
            'configure',
            'authorize',
            'requestClientInfo',
        );
        if (in_array($sub, $subs)) {
            return $this->{$sub}();
        }
        switch($_REQUEST['sub']){
        case 'deletemulti':
            $assocrule = preg_match(
                '#items=assocRule*#i',
                self::$querystring
            );
            if ($assocrule) {
                $this->childClass = 'AccessControlRuleAssociation';
            }
            $user = preg_match(
                '#items=user*#i',
                self::$querystring
            );
            if ($user) {
                $this->childClass = 'AccessControlAssociation';
            }
            $role = preg_match(
                '#items=role*#i',
                self::$querystring
            );
            if ($role) {
                $this->childClass = 'AccessControl';
            }
            $rule = preg_match(
                '#items=rule*#i',
                self::$querystring
            );
            if ($rule) {
                $this->childClass = 'AccessControlRule';
            }
            break;
        case 'membership':
            $this->childClass = 'AccessControlAssociation';
            break;
        case 'assocRule':
            $this->childClass = 'AccessControlRuleAssociation';
            break;
        case 'editRule':
        case 'deleteRule':
        case 'addRule':
        case 'ruleList':
        case 'addRuleGroup':
            $this->childClass = 'AccessControlRule';
            break;
        default:
            $this->childClass = 'AccessControl';
            break;
        }
        $this->title = $this->name;
        if (in_array($this->node, $this->PagesWithObjects)) {
            $classVars = self::getClass(
                $this->childClass,
                '',
                true
            );
            $this->databaseTable
                = $classVars['databaseTable'];
            $this->databaseFields
                = $classVars['databaseFields'];
            $this->databaseFieldsRequired
                = $classVars['databaseFieldsRequired'];
            $this->databaseFieldClassRelationships
                = $classVars['databaseFieldClassRelationships'];
            $this->additionalFields
                = $classVars['additionalFields'];
            unset($classVars);
            $this->obj = self::getClass(
                $this->childClass,
                $id
            );
            if (isset($id)) {
                $link = sprintf(
                    '?node=%s&sub=%s&%s=%d',
                    $this->node,
                    '%s',
                    $this->id,
                    $id
                );
                $this->delformat = sprintf(
                    $link,
                    'delete'
                );
                $this->linkformat = sprintf(
                    $link,
                    'edit'
                );
                $this->membership = sprintf(
                    $link,
                    'membership'
                );
                if ($id === 0 || !is_numeric($id)) {
                    unset($this->obj);
                    self::setMessage(
                        sprintf(
                            _('%s ID %d is not valid'),
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
            }
        }
        $this->reportString = '<h2><div id="exportDiv"></div><a id="csvsub" '
            . 'href="../management/export.php?filename=%s&type=csv" alt="%s" '
            . 'title="%s" target="_blank">%s</a> <a id="pdfsub" '
            . 'href="../management/export.php?filename=%s&type=pdf" alt="%s" '
            . 'title="%s" target="_blank">%s</a></h2>';
        self::$pdffile = '<i class="fa fa-file-pdf-o fa-2x"></i>';
        self::$csvfile = '<i class="fa fa-file-excel-o fa-2x"></i>';
        $this->menu = array(
            'search' => self::$foglang['NewSearch'],
            'list' => sprintf(
                self::$foglang['ListAll'],
                _(
                    sprintf(
                        '%ss',
                        $this->childClass
                    )
                )
            ),
            'add' => sprintf(
                self::$foglang['CreateNew'],
                _($this->childClass)
            ),
            'export' => sprintf(
                self::$foglang[
                    sprintf(
                        'Export%s',
                        $this->childClass
                    )
                ]
            ),
            'import' => sprintf(
                self::$foglang[
                    sprintf(
                        'Import%s',
                        $this->childClass
                    )
                ]
            ),
        );
        $this->fieldsToData = function (&$input, &$field) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            if (is_array($this->span) && count($this->span) === 2) {
                $this->data[count($this->data)-1][$this->span[0]] = $this->span[1];
            }
            unset($input);
        };
        $this->formAction = preg_replace(
            '#\&tab=#i',
            '#',
            filter_var(
                sprintf(
                    '%s?%s',
                    self::$scriptname,
                    self::$querystring
                ),
                FILTER_SANITIZE_URL
            )
        );
        self::$HookManager->processEvent(
            'SEARCH_PAGES',
            array('searchPages' => &self::$searchPages)
        );
        self::$HookManager->processEvent(
            'SUB_MENULINK_DATA',
            array(
                'menu' => &$this->menu,
                'submenu' => &$this->subMenu,
                'id' => &$this->id,
                'notes' => &$this->notes
            )
        );

        $this->menu = array(
            'list' => sprintf(_('List all roles')),
            'addRole' => sprintf(_('Add new role')),
            'ruleList' => sprintf(_('List all rules')),
            'addRule' => sprintf(_('Add new rule')),
        );

        switch ($_REQUEST['sub'])
        {
        case 'edit':
        case 'delete':
            if ($id) {
                $this->subMenu = array(
                    "$this->linkformat" => self::$foglang['General'],
                    $this->membership => self::$foglang['Members'],
                    "$this->delformat" => self::$foglang['Delete'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'assocRule',
                        $id
                    ) => _('Rule Association'),
                    );
                $this->notes = array(
                    _('Role Name') => $this->obj->get('name'),
                    _('Description') => $this->obj->get('description'),
                );

            }
            break;
        case 'assocRule':
        case 'membership':
            if ($id) {
                $AccessControl = new AccessControl($id);
                $this->subMenu = array(
                    "$this->linkformat" => self::$foglang['General'],
                    $this->membership => self::$foglang['Members'],
                    "$this->delformat" => self::$foglang['Delete'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'assocRule',
                        $id
                    ) => _('Rule Association'),
                );
                $this->notes = array(
                    _('Role Name') => $AccessControl->get('name'),
                    _('Description') => $AccessControl->get('description'),
                );

            }
            break;
        case 'editRule':
        case 'deleteRule':
            if ($id) {
                $this->subMenu = array(
                    "$this->linkformat" => self::$foglang['General'],
                    //$this->membership => self::$foglang['Members'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'deleteRule',
                        $id
                    ) => self::$foglang['Delete'],
                );
                $this->notes = array(
                    _('Rule type') => $this->obj->get('type'),
                    _('Rule value') => $this->obj->get('value'),
                    _('Parent Node') => $this->obj->get('parent'),
                );

            }
            break;
        }
    }
    /**
     * Search
     *
     * @return void
     */
    public function search()
    {
        $this->index();
    }
    /**
     * Add role.
     *
     * @return void
     */
    public function addRole()
    {
        $this->add();
    }
    /**
     * Add role post.
     *
     * @return void
     */
    public function addRolePost()
    {
        $this->addPost();
    }
    /**
     * Index.
     *
     * @return void
     */
    public function index()
    {

        $this->title = _('All Roles');
        foreach ((array)self::getClass('AccessControlManager')
            ->find() as &$AccessControl
        ) {
            $this->data[] = array(
                'id' => $AccessControl->get('id'),
                'name' => $AccessControl->get('name'),
                'description' => $AccessControl->get('description'),
                'createdBy' => $AccessControl->get('createdBy'),
                'createdTime' => $AccessControl->get('createdTime'),

            );
            unset($AccessControl);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Role Name'),
            _('Role Description'),
        );
        $this->templates = array(
            '<input type="checkbox" name="accesscontrol[]" value='
            . '"${id}" class="toggle-action" checked/>',
            '<a href="?node=accesscontrol&sub=edit'
            . '&id=${id}" title="Edit">${name}</a>',
            '${description}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l'),
            array('class' => 'l'),
        );
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_DATA',
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
     * Add.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Role');
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
            _('Role Name') => '<input class="smaller" type="text" name="name"/>',
            _('Role Description') => sprintf(
                '<textarea name="description">%s</textarea>',
                $_REQUEST['description']
            ),
            '&nbsp;' => sprintf(
                '<input name="add" class="smaller" type="submit" value="%s"/>',
                _('Add')
            ),
        );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_ADD',
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
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $name = trim($_REQUEST['name']);
            $exists = self::getClass('AccessControlManager')
                ->exists(trim($name));
            if ($exists) {
                throw new Exception(_('Role already Exists, please try again.'));
            }
            if (!$name) {
                throw new Exception(_('Please enter a name for this role.'));
            }

            $description = $_REQUEST['description'];
            $AccessControl = self::getClass('AccessControl')
                ->set('name', $name)
                ->set('description', $description);
            if (!$AccessControl->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Role Added, editing!'));
            self::redirect(
                sprintf(
                    '?node=accesscontrol&sub=edit&id=%s',
                    $AccessControl->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Edit.
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
            _('Role Name') => sprintf(
                '<input class="smaller" type="text" name="name" value="%s"/>',
                (
                    $_REQUEST['name'] ?
                    $_REQUEST['name'] :
                    $this->obj->get('name')
                )
            ),
            _('Role Description') => '<textarea name="description">'
            . (
                $_REQUEST['description'] ?
                $_REQUEST['description'] :
                $this->obj->get('description')
            )
            . '</textarea>',
            '&nbsp;' => sprintf(
                '<input name="update" class="smaller" type="submit" value="%s"/>',
                _('Update')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);

        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );

        printf(
            '<form method="post" action="%s&id=%d">',
            $this->formAction,
            $this->obj->get('id')
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Edit post.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_EDIT_POST',
                array(
                    'AccessControl' => &$this->obj
                )
            );
        try {
            if ($_REQUEST['name'] != $this->obj->get('name')
                && $this->obj->getManager()->exists($_REQUEST['name'])
            ) {
                throw new Exception(_('A role with that name already exists.'));
            }
            if (isset($_REQUEST['update'])) {
                $description = $_REQUEST['description'];
                $this->obj
                    ->set('name', $_REQUEST['name'])
                    ->set('description', $_REQUEST['description']);
                if (!$this->obj->save()) {
                    throw new Exception(_('Failed to update'));
                }
                self::setMessage(_('Role Updated'));
                self::redirect(
                    sprintf(
                        '?node=accesscontrol&sub=edit&id=%d',
                        $this->obj->get('id')
                    )
                );
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Rule list.
     *
     * @return void
     */
    public function ruleList()
    {
        $this->title = _('Rules');
        foreach ((array)self::getClass('AccessControlRuleManager')
            ->find() as &$AccessControlRule
        ) {
            $this->data[] = array(
                'type' => $AccessControlRule->get('type'),
                'id' => $AccessControlRule->get('id'),
                'value' => $AccessControlRule->get('value'),
                'parent' => $AccessControlRule->get('parent'),
                'node' => $AccessControlRule->get('node'),
            );
            unset($AccessControlRule);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Type'),
            _('Value'),
            _('Parent'),
            _('Node')
        );
        $this->templates = array(
            '<input type="checkbox" name="rule[]" value='
            . '"${id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=editRule&%s=${id}" title='
                . '"%s">${type}</a>',
                $this->node,
                $this->id,
                _('Edit')
            ),
            '${value}',
            '${parent}',
            '${node}'
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l')
        );
        self::$HookManager
            ->processEvent(
                'RULE_DATA',
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
     * Add rule.
     *
     * @return void
     */
    public function addRule()
    {
        $this->title = _('New Rule');
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
            _('Rule Type') => '<input class="smaller" type="text" name="type"/>',
            _('Parent') => '<input class="smaller" type="text" name="parent"/>',
            _('Node') => '<input class="smaller" type="text" name="node"/>',
            _('Rule Value') => '<input class="smaller" type="text" name="value"/>',
            '&nbsp;' => sprintf(
                '<input name="add" class="smaller" type="submit" value="%s"/>',
                _('Add Rule')
            ),
        );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_RULE_ADD',
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
     * Add rule post.
     *
     * @return void
     */
    public function addRulePost()
    {
        try {
            $value = trim($_REQUEST['value']);
            $exists = self::getClass('AccessControlRuleManager')
                ->exists(trim($value));
            if ($exists) {
                throw new Exception(_('Rule already Exists, please try again.'));
            }
            if (!$value) {
                throw new Exception(_('Please enter a value for this rule.'));
            }

            $type = $_REQUEST['type'];
            $AccessControlRule = self::getClass('AccessControlRule')
                ->set('type', $type)
                ->set('value', $value)
                ->set('name', $type. '-' . $value)
                ->set('parent', trim($_REQUEST['parent']))
                ->set('node', trim($_REQUEST['node']));
            if (!$AccessControlRule->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Rule Added, editing!'));
            self::redirect(
                sprintf(
                    '?node=accesscontrol&sub=editRule&id=%s',
                    $AccessControlRule->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Edit rule.
     *
     * @return void
     */
    public function editRule()
    {
        $this->obj = new AccessControlRule($_REQUEST['id']);

        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('value')
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
            _('Rule Type') => sprintf(
                '<input class="smaller" type="text" name="type" value="%s"/>',
                (
                    $_REQUEST['type'] ?
                    $_REQUEST['type'] :
                    $this->obj->get('type')
                )
            ),
            _('Parent') => sprintf(
                '<input class="smaller" type="text" name="parent" value="%s"/>',
                (
                    $_REQUEST['parent'] ?
                    $_REQUEST['parent'] :
                    $this->obj->get('parent')
                )
            ),
            _('Node') => sprintf(
                '<input class="smaller" type="text" name="parent" value="%s"/>',
                (
                    $_REQUEST['node'] ?
                    $_REQUEST['node'] :
                    $this->obj->get('node')
                )
            ),
            _('Rule Value') => sprintf(
                '<input class="smaller" type="text" name="value" value="%s"/>',
                (
                    $_REQUEST['value'] ?
                    $_REQUEST['value'] :
                    $this->obj->get('value')
                )
            ),
            '&nbsp;' => sprintf(
                '<input name="update" class="smaller" type="submit" value="%s"/>',
                _('Update')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);

        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_RULE_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );

        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Edit rule post.
     *
     * @return void
     */
    public function editRulePost()
    {
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_RULE_EDIT_POST',
                array(
                    'AccessControlRule' => &$this->obj
                )
            );
        try {
            if ($_REQUEST['value'] != $this->obj->get('value')
                && $this->obj->getManager()->exists($_REQUEST['value'])
            ) {
                throw new Exception(_('A rule with that value already exists.'));
            }
            if (isset($_REQUEST['update'])) {
                $value = $_REQUEST['value'];
                $this->obj
                    ->set('type', $_REQUEST['type'])
                    ->set('parent', $_REQUEST['parent'])
                    ->set('node', $_REQUEST['node'])
                    ->set('value', $_REQUEST['value']);
                if (!$this->obj->save()) {
                    throw new Exception(_('Failed to update'));
                }
                self::setMessage(_('Rule Updated'));
                self::redirect(
                    sprintf(
                        '?node=accesscontrol&sub=editRule&id=%d',
                        $this->obj->get('id')
                    )
                );
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Delete rule.
     *
     * @return void
     */
    public function deleteRule()
    {

        $this->title = sprintf(
            '%s: %s',
            self::$foglang['Remove'],
            $this->obj->get('value')
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
                $this->obj->get('value')
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
                'ACCESSCONTROL_RULE_DELETE',
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
     * Delete rule post.
     *
     * @return void
     */
    public function deleteRulePost()
    {
        self::$HookManager
            ->processEvent(
                'ACCESS_CONTROL_RULE_DELETE_POST',
                array('AccessControlRule' => &$this->obj)
            );
        try {

            if (!$this->obj->destroy()) {
                throw new Exception(_('Fail to delete rule'));
            }
            $hook = 'ACCESSCONTROL_RULE_DELETE_POST_SUCCESS';
            $msg = sprintf(
                '%s',
                _('Rule Delete Success')
            );
            $url = sprintf(
                '?node=%s&sub=accesscontrol',
                $this->node
            );
        } catch (Exception $e) {
            $hook = 'ACCESSCONTROL_RULE_DELETE_POST_FAIL';
            $msg = $e->getMessage();
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('AccessControlRule'=>&$this->obj)
            );
        self::setMessage($msg);
        self::redirect($url);
    }
    /**
     * Assoc rule.
     *
     * @return void
     */
    public function assocRule()
    {
        $this->title = _('Rule Association');

        $ruleID = self::getSubObjectIDs(
            'AccessControlRuleAssociation',
            array('roleID' => $_REQUEST['id']),
            'ruleID'
        );
        foreach ((array)self::getClass('AccessControlRuleManager')
            ->find(array('id' => $ruleID)) as &$AccessControlRule
        ) {
            $this->data[] = array(
                'type' => $AccessControlRule->get('type'),
                'id' => $AccessControlRule->get('id'),
                'value' => $AccessControlRule->get('value'),
                'parent' => $AccessControlRule->get('parent'),
            );
            unset($AccessControlRule);
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Type'),
            _('Value'),
            _('Parent Node'),
        );
        $this->templates = array(
            '<input type="checkbox" name="rule[]" value='
            . '"${id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=editRule&%s=${id}" title='
                . '"%s">${type}</a>',
                $this->node,
                $this->id,
                _('Edit')
            ),
            '${value}',
            '${parent}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'l'),
        );
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_ASSOCRULE_DATA',
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
     * Add rule group.
     *
     * @return void
     */
    public function addRuleGroup()
    {
        try {
            if (!$_REQUEST['accesscontrol']) {
                throw new Exception(_('Not role selected'));
            }
            if (!$_REQUEST['ruleIDArray']) {
                throw new Exception(_('Not rule selected'));
            }
            $reqID = explode(',', $_REQUEST['ruleIDArray']);
            $reqID = array_unique($reqID);
            $reqID = array_filter($reqID);
            $Role = new AccessControl($_REQUEST['accesscontrol']);
            foreach ((array)$reqID as $ruleID) {
                $Rule = new AccessControlRule($ruleID);
                $AccessControlRuleAssociation
                    = self::getClass('AccessControlRuleAssociation')
                    ->set('roleID', $_REQUEST['accesscontrol'])
                    ->set('name', $Role->get('name'). "-" . $Rule->get('name'))
                    ->set('ruleID', $ruleID);
                if (!$AccessControlRuleAssociation->save()) {
                    throw new Exception(_('Failed to create'));
                }
                unset($AccessControlRuleAssociation);
                unset($Rule);

            }
            unset($ruleID);

            self::setMessage(_('Rule Added, editing!'));
            self::redirect('?node=accesscontrol&sub=ruleList');

        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Custom render
     *
     * @return void
     */
    public function render()
    {
        echo $this->process();
    }
    /**
     * Custom process
     *
     * @return void
     */
    public function process()
    {
        try {
            unset($actionbox);
            //global $sub;
            global $node;
            $defaultScreen = strtolower($_SESSION['FOG_VIEW_DEFAULT_SCREEN']);
            $defaultScreens = array(
                'search',
                'list',
                'membership',
                'ruleList',
                'assocRule'
            );
            $sub = $_REQUEST['sub'];
            if (!$sub
                || in_array($sub, $defaultScreens)
            ) {
                switch ($sub){
                case '':
                    $sub = 'list';
                case 'list':
                    $item = 'role';
                    $actionbox .= sprintf(
                        '<form method="post" class="c" id="action-boxdel" '
                        . 'action="%s"><p>%s</p><input type="hidden" '
                        . 'name="IDArray" value="" autocomplete="off"/>'
                        . '<input type="submit" value="%s?"/></form>',
                        sprintf(
                            '?node=%s&sub=deletemulti&items=%s',
                            $node,
                            $item
                        ),
                        _('Delete all selected items'),
                        sprintf(
                            _('Delete all selected %ss'),
                            $item
                        )
                    );
                    break;
                case 'assocRule':
                    $item = 'assocRule';
                    $actionbox .= sprintf(
                        '<form method="post" class="c" id="action-boxdel" '
                        . 'action="%s"><p>%s</p><input type="hidden" '
                        . 'name="IDArray" value="" autocomplete="off"/>'
                        . '<input type="submit" value="%s?"/></form>',
                        sprintf(
                            '?node=%s&sub=deletemulti&items=%s&roleID=%s',
                            $node,
                            $item,
                            $_REQUEST['id']
                        ),
                        _('Delete all selected items'),
                        sprintf(
                            _('Delete all selected %ss'),
                            $item
                        )
                    );
                    break;
                case 'membership':
                    $item = 'user';
                    $actionbox .= sprintf(
                        '<form method="post" class="c" id="action-boxdel" '
                        . 'action="%s"><p>%s</p><input type="hidden" '
                        . 'name="IDArray" value="" autocomplete="off"/>'
                        . '<input type="submit" value="%s?"/></form>',
                        sprintf(
                            '?node=%s&sub=deletemulti&items=%s&roleID=%s',
                            $node,
                            $item,
                            $_REQUEST['id']
                        ),
                        _('Delete all selected items'),
                        sprintf(
                            _('Delete all selected %ss'),
                            $item
                        )
                    );
                    break;
                case 'ruleList':
                    $item = 'rule';
                    $actionbox = sprintf(
                        '<form method="post" action="%s" id="action-box">'
                        . '<input type="hidden" name="ruleIDArray" value="" '
                        . 'autocomplete="off"/><p><label for="rule">%s</label>%s</p>'
                        . '<p class="c"><input type="submit" id="processrule" '
                        . 'value="%s"/></p></form>',
                        sprintf(
                            '?node=%s&sub=addRuleGroup',
                            $node
                        ),
                        _('Add to role'),
                        self::getClass('AccessControlManager')->buildSelectBox(),
                        _('Process Rule Changes')
                    );
                    $actionbox .= sprintf(
                        '<form method="post" class="c" id="action-boxdel" '
                        . 'action="%s"><p>%s</p><input type="hidden" '
                        . 'name="IDArray" value="" autocomplete="off"/>'
                        . '<input type="submit" value="%s?"/></form>',
                        sprintf(
                            '?node=%s&sub=deletemulti&items=%s',
                            $node,
                            $item
                        ),
                        _('Delete all selected items'),
                        sprintf(
                            _('Delete all selected %ss'),
                            $item
                        )
                    );
                    break;
                }
            }
            self::$HookManager->processEvent(
                'ACTIONBOX',
                array('actionbox' => &$actionbox)
            );
            if (self::$ajax) {
                echo json_encode(
                    array(
                        'data' => $this->data,
                        'templates' => $this->templates,
                        'headerData' => $this->headerData,
                        'title' => $this->title,
                        'attributes' => $this->attributes,
                        'form' => $this->form,
                        'searchFormURL' => $this->searchFormURL,
                        'actionbox' => (
                            count($this->data) > 0 ?
                            $actionbox :
                            ''
                        ),
                    )
                );
            }
            if (!count($this->templates)) {
                throw new Exception(
                    _('Requires templates to process')
                );
            }
            ob_start();
            $contentField = 'active-tasks';
            if ($this->searchFormURL) {
                printf(
                    '<form method="post" action="%s" id="search-wrapper">'
                    . '<input id="%s-search" class="search-input placeholder" '
                    . 'type="text" value="" placeholder="%s" autocomplete="off" %s/>'
                    . '<%s id="%s-search-submit" class="search-submit" type="%s" '
                    . 'value="%s"></form>%s',
                    $this->searchFormURL,
                    (
                        substr($this->node, -1) == 's' ?
                        substr($this->node, 0, -1) :
                        $this->node
                    ),
                    sprintf(
                        '%s %s',
                        ucwords(
                            (
                                substr($this->node, -1) == 's' ?
                                substr($this->node, 0, -1) :
                                $this->node
                            )
                        ),
                        self::$foglang['Search']
                    ),
                    (
                        self::$isMobile ?
                        'name="host-search"' :
                        ''
                    ),
                    (
                        self::$isMobile ?
                        'input' :
                        'button'
                    ),
                    (
                        substr($this->node, -1) == 's' ?
                        substr($this->node, 0, -1) :
                        $this->node
                    ),
                    (
                        self::$isMobile ?
                        'submit' :
                        'button'
                    ),
                    (
                        self::$isMobile ?
                        self::$foglang['Search'] :
                        ''
                    ),
                    (
                        self::$isMobile ?
                        '</input>' :
                        '</button>'
                    )
                );
                $contentField = 'search-content';
            }
            if (isset($this->form)) {
                printf($this->form);
            }
            printf(
                '<table width="%s" cellpadding="0" cellspacing="0" '
                . 'border="0" id="%s">%s<tbody>',
                '100%',
                $contentField,
                $this->buildHeaderRow()
            );
            $node = $_REQUEST['node'];
            $sub = $_REQUEST['sub'];
            if (in_array($this->node, array('task'))
                && (!$sub || $sub == 'list')
            ) {
                self::redirect(
                    sprintf(
                        '?node=%s&sub=active',
                        $this->node
                    )
                );
            }
            if (!count($this->data)) {
                $contentField = 'no-active-tasks';
                printf(
                    '<tr><td colspan="%s" class="%s">%s</td></tr></tbody></table>',
                    count($this->templates),
                    $contentField,
                    (
                        $this->data['error'] ?
                        (
                            is_array($this->data['error']) ?
                            sprintf(
                                '<p>%s</p>',
                                implode(
                                    '</p><p>',
                                    $this->data['error']
                                )
                            ) :
                            $this->data['error']
                        ) :
                        (
                            $this->node != 'task' ?
                            (
                                !self::$isMobile ?
                                self::$foglang['NoResults'] :
                                ''
                            ) :
                            self::$foglang['NoResults']
                        )
                    )
                );
            } else {
                if ((!$sub
                    && $defaultScreen == 'list')
                    || (in_array($sub, $defaultScreens)
                    && in_array($node, self::$searchPages))
                ) {
                    if (!in_array($this->node, array('home', 'hwinfo'))) {
                        self::setMessage(
                            sprintf(
                                '%s %s%s found',
                                count($this->data),
                                $this->childClass,
                                (
                                    count($this->data) != 1 ?
                                    's' :
                                    ''
                                )
                            )
                        );
                    }
                }
                $id_field = "{$node}_id";
                foreach ((array)$this->data as &$rowData) {
                    printf(
                        '<tr id="%s-%s">%s</tr>',
                        strtolower($this->childClass),
                        (
                            isset($rowData['id']) ?
                            $rowData['id'] :
                            (
                                isset($rowData[$id_field]) ?
                                $rowData[$id_field] :
                                ''
                            )
                        ),
                        $this->buildRow($rowData)
                    );
                    unset($rowData);
                }
            }
            echo '</tbody></table>';
            $text = ob_get_clean();
            $text .= $actionbox;
            return $text;
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $result;
    }
    /**
     * Custom membership method.
     *
     * @return void
     */
    public function membership()
    {

        $userIDs = self::getSubObjectIDs(
            'AccessControlAssociation',
            array('roleID' => $_REQUEST['id']),
            'userID'
        );
        foreach ($userIDs as &$identificator) {
            $Users = new User($identificator);
            $this->data[] = array(
                'id' => $Users->get('id'),
                'name' => $Users->get('name'),
            );
            unset($Users);
        }
        unset($identificator);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Username'),
        );
        $this->templates = array(
            '<input type="checkbox" name="user[]" value='
            . '"${id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=user&sub=edit&%s=${id}" title='
                . '"%s">${name}</a>',
                $this->id,
                _('Edit')
            ),
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l'),
        );
        self::$HookManager
            ->processEvent(
                'ACCESSCONTROL_USER_DATA',
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
     * Custom delete multi.
     *
     * @return void
     */
    public function deletemulti()
    {
        $sub = $_REQUEST['sub'];
        $item = $_REQUEST['items'];
        $roleID = $_REQUEST['roleID'];
        $userID = $_REQUEST['userID'];
        global $node;
        $this->additional = array();
        $subs = array(
            'assocRule',
            'user'
        );


        $this->title = sprintf(
            "%s's to remove",
            $item
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
        );
        $this->templates = array(
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}">${name}</a>',
                $this->node
            ),
            '<input type="hidden" value="${id}" name="remitems[]"/>',
        );

        $reqID = 'IDArray';
        $reqID = explode(',', $_REQUEST[$reqID]);
        $reqID = array_unique($reqID);
        $reqID = array_filter($reqID);

        if (in_array($item, $subs)) {
            switch ($item){
            case 'assocRule':
                $findWhere = array(
                    'ruleID' => $reqID,
                    'roleID' => $roleID

                );
                break;
            case 'user':
                $findWhere = array(
                    'userID' => $reqID,
                    'roleID' => $roleID

                );
                break;
            }
        } else {
            $findWhere = array(
                'id' => $reqID,
            );
        }

        foreach ((array)self::getClass($this->childClass)
            ->getManager()
            ->find(
                $findWhere
            ) as &$Object
        ) {

            if ($Object->get('protected')) {
                continue;
            }
            $this->data[] = array(
                'id' => $Object->get('id'),
                'name' => $Object->get('name'),
            );
            array_push(
                $this->additional,
                sprintf(
                    '<p>%s</p>',
                    $Object->get('name')
                )
            );
            unset($Object);
        }
        if (count($this->data)) {
            printf(
                '<div class="confirm-message"><p>%s:</p>'
                . '<div id="deleteDiv"></div>',
                $this->title
            );
            $this->render();
            printf(
                '<p class="c"><input type="submit" name="delete" '
                . 'value="%s?"/></p>',
                _('Are you sure you wish to remove these items')
            );
        } else {
            self::setMessage(
                sprintf(
                    '%s<br/>%s',
                    _('No items to delete'),
                    _('None selected or item is protected')
                )
            );
            self::redirect(
                sprintf(
                    '?node=%s',
                    $this->node
                )
            );
        }
    }
    /**
     * Delete multi ajax customized.
     *
     * @return void
     */
    public function deletemultiAjax()
    {
        self::$HookManager->processEvent(
            'MULTI_REMOVE',
            array('removing' => &$_REQUEST['remitems'])
        );
        self::getClass($this->childClass)
        ->getManager()
        ->destroy(
            array('id' => $_REQUEST['remitems'])
        );
        self::setMessage(
            _('All selected items have been deleted')
        );
        self::redirect(
            sprintf(
                '?node=%s',
                $this->node
            )
        );
    }
}
