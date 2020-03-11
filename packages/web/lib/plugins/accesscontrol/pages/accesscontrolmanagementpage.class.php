<?php
/**
 * Access Control plugin
 *
 * PHP version 7
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
        /**
         * The name to give.
         */
        $this->name = _('Access Control Management');
        /**
         * Add this page to the PAGES_WITH_OBJECTS hook event.
         */
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );
        /**
         * Get our $_GET['node'], $_GET['sub'], and $_GET['id']
         * in a nicer to use format.
         */
        global $node;
        global $sub;
        global $id;
        self::$foglang['ExportAccesscontrol'] = _('Export Accesscontrols');
        self::$foglang['ImportAccesscontrol'] = _('Import Accesscontrols');
        /**
         * Customize our settings as needed.
         */
        switch ($sub) {
        case 'edit':
        case 'delete':
            parent::__construct($this->name);
            if ($id) {
                $this->subMenu = array(
                    "$this->linkformat#role-general" => self::$foglang['General'],
                    $this->membership => self::$foglang['Members'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'assocRule',
                        $id
                    ) => _('Rule Association'),
                        "$this->delformat" => self::$foglang['Delete'],
                    );
                $this->notes = array(
                    _('Role Name') => $this->obj->get('name'),
                    _('Description') => $this->obj->get('description'),
                );
            }
            break;
        case 'deletemulti':
            parent::__construct($this->name);
            /**
             * Setup our matching elements.
             */
            /**
             * Association Rules
             */
            $assocrule = preg_match(
                '#items=assocRule#i',
                self::$querystring
            );
            /**
             * User associations
             */
            $user = preg_match(
                '#items=user#i',
                self::$querystring
            );
            /**
             * Roles
             */
            $role = preg_match(
                '#items=role#i',
                self::$querystring
            );
            /**
             * Rules
             */
            $rule = preg_match(
                '#items=rule#i',
                self::$querystring
            );
            /**
             * Set our child class based on
             * what is found above.
             */
            if ($assocrule) {
                $this->childClass = 'AccessControlRuleAssociation';
            } elseif ($user) {
                $this->childClass = 'AccessControlAssociation';
            } elseif ($role) {
                $this->childClass = 'AccessControl';
            } elseif ($rule) {
                $this->childClass = 'AccessControlRule';
            } else {
                $this->childClass = 'AccessControl';
            }
            break;
        case 'membership':
        case 'assocRule':
            parent::__construct($this->name);
            if ($id) {
                $this->subMenu = array(
                    "$this->linkformat#role-general" => self::$foglang['General'],
                    $this->membership => self::$foglang['Members'],
                    sprintf(
                        '?node=%s&sub=%s&id=%s',
                        $this->node,
                        'assocRule',
                        $id
                    ) => _('Rule Association'),
                        "$this->delformat" => self::$foglang['Delete'],
                    );
                $this->notes = array(
                    _('Role Name') => $this->obj->get('name'),
                    _('Description') => $this->obj->get('description'),
                );
            }
            break;
        case 'editRule':
        case 'deleteRule':
            $this->childClass = 'AccessControlRule';
            if ($id) {
                $this->obj = new $this->childClass($id);
                $link = sprintf(
                    '?node=%s&sub=%s&%s=%d',
                    $this->node,
                    '%s',
                    $this->id,
                    $id
                );
                $this->linkformat = sprintf(
                    $link,
                    'editRule'
                );
                $this->subMenu = array(
                    "$this->linkformat" => self::$foglang['General'],
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
        case 'addRule':
        case 'ruleList':
        case 'addRuleGroup':
            parent::__construct($this->name);
            $this->childClass = 'AccessControlRule';
            break;
        default:
            parent::__construct($this->name);
        }
        /**
         * Set title to our initiator name.
         */
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
        $this->menu = array(
            'list' => sprintf(_('List all roles')),
            'addRole' => sprintf(_('Add new role')),
            'ruleList' => sprintf(_('List all rules')),
            'addRule' => sprintf(_('Add new rule')),
            'export' => $this->menu['export'],
            'import' => $this->menu['import']
        );
        switch (strtolower($this->childClass)) {
        case 'accesscontrol':
            $this->headerData = array(
                '<input type="checkbox" name="toggle-checkbox" class='
                . '"toggle-checkboxAction"/>',
                _('Role Name'),
                _('Role Description'),
            );
            $this->templates = array(
                '<input type="checkbox" name="accesscontrol[]" value='
                . '"${id}" class="toggle-action"/>',
                '<a href="?node=accesscontrol&sub=edit'
                . '&id=${id}" title="Edit">${name}</a>',
                '${description}',
            );
            $this->attributes = array(
                array(
                    'class' => 'filter-false',
                    'width' => 16
                ),
                array(),
                array()
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
            self::$returnData = function (&$AccessControl) {
                $this->data[] = array(
                    'id' => $AccessControl->id,
                    'name' => $AccessControl->name,
                    'description' => $AccessControl->description,
                    'createdBy' => $AccessControl->createdBy,
                    'createdTime' => $AccessControl->createdTime
                );
                unset($AccessControl);
            };
            break;
        case 'accesscontrolrule':
            self::$returnData = function (&$AccessControlRule) {
                $this->data[] = array(
                    'type' => $AccessControlRule->type,
                    'id' => $AccessControlRule->id,
                    'value' => $AccessControlRule->value,
                    'parent' => $AccessControlRule->parent,
                    'node' => $AccessControlRule->node
                );
                unset($AccessControlRule);
            };
            break;
        }
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
     * Add.
     *
     * @return void
     */
    public function add()
    {
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('New Role');
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
            . _('Role Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" class="form-control" value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="desc">'
            . _('Role Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control" name="description" '
            . 'id="desc">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="add">'
            . _('Create New Access Control Role')
            . '</label>' => '<button type="submit" name="add" id="add" '
            . 'class="btn btn-info btn-block">'
            . _('Add')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
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
    /**
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $desc = filter_input(
            INPUT_POST,
            'description'
        );
        try {
            if (!$name) {
                throw new Exception(
                    _('A name is required!')
                );
            }
            $exists = self::getClass('AccessControlManager')
                ->exists($name);
            if ($exists) {
                throw new Exception(
                    _('A role already exists with this name!')
                );
            }
            $AccessControl = self::getClass('AccessControl')
                ->set('name', $name)
                ->set('description', $desc);
            if (!$AccessControl->save()) {
                throw new Exception(_('Add role failed!'));
            }
            $hook = 'ROLE_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Role added!'),
                    'title' => _('Role Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'ROLE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Role Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('AccessControl' => &$AccessControl)
            );
        unset($AccessControl);
        echo $msg;
        exit;
    }
    /**
     * Edit.
     *
     * @return void
     */
    public function edit()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );
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
            . _('Role Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" id="name" class="form-control" value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="desc">'
            . _('Role Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control" name="description" '
            . 'id="desc">'
            . $desc
            . '</textarea>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
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
        echo '<div class="col-xs-9 tab-content">';
        echo '<div class="tab-pane fade in active" id="role-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Access Control Role General');
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
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
        $name = filter_input(INPUT_POST, 'name');
        $desc = filter_input(INPUT_POST, 'description');
        try {
            if ($name != $this->obj->get('name')
                && $this->obj->getManager()->exists($name)
            ) {
                throw new Exception(_('A role already exists with this name!'));
            }
            if (isset($_POST['update'])) {
                $this->obj
                    ->set('name', $name)
                    ->set('description', $desc);
                if (!$this->obj->save()) {
                    throw new Exception(_('Role update failed!'));
                }
                $hook = 'ROLE_EDIT_SUCCESS';
                $msg = json_encode(
                    array(
                        'msg' => _('Role updated!'),
                        'title' => _('Role Update Success')
                    )
                );
            }
        } catch (Exception $e) {
            $hook = 'ROLE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Role Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('AccessControl' => &$this->obj)
            );
        echo $msg;
        exit;
    }
    /**
     * Rule list.
     *
     * @return void
     */
    public function ruleList()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->title = _('Access Control Rules');
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
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array(),
            array()
        );
        Route::listem('accesscontrolrule');
        $AccessControlRules = json_decode(
            Route::getData()
        );
        $AccessControlRules = $AccessControlRules->accesscontrolrules;
        array_walk($AccessControlRules, static::$returnData);
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
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 clas="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        echo '<div class="action-boxes del hiddeninitially">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Delete Selected');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $formAction = $this->formAction;
        $components = parse_url($formAction);
        parse_str($components['query'], $vars);
        $vars['sub'] = 'deletemultiRule';
        $formAction = '?'.http_build_query($vars);
        echo '<form class="form-horizontal" method="post" action="'
            . $formAction
            . '">';
        echo '<div class="form-group">';
        echo '<label class="control-label col-xs-4" for="del-'
            . $this->node
            . 'rule">';
        echo _('Delete Selected');
        echo ' ';
        echo $this->node . 'rules';
        echo '</label>';
        echo '<div class="col-xs-8">';
        echo '<input type="hidden" name="'
            . $this->node
            . 'ruleIDArray"/>';
        echo '<button type="submit" class='
            . '"btn btn-danger btn-block" id="'
            . 'del-'
            . $this->node
            . 'rule">';
        echo _('Delete');
        echo '</button>';
        echo '</div>';
        echo '</div>';
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
    /**
     * Presents the en-mass delete elements.
     *
     * @return void
     */
    public function deletemultiRule()
    {
        global $sub;
        global $node;
        $this->title = sprintf(
            "%s's to remove",
            _('Access Control Rule')
        );
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $reqID = $node
            . 'ruleIDArray';
        $items = filter_input(
            INPUT_POST,
            $reqID
        );
        $reqID = array_values(
            array_filter(
                array_unique(
                    explode(',', $items)
                )
            )
        );
        Route::listem('accesscontrolrule');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->accesscontrolrules;
        foreach ((array)$items as &$object) {
            if (!in_array($object->id, $reqID)) {
                continue;
            }
            $this->data[] = array(
                'field' => '<input type="hidden" value="'
                . $object->id
                . '" name="remitems[]"/>',
                'input' => '<a href="?node='
                . $node
                . '&sub=editRule&id='
                . $object->id
                . '">'
                . $object->name
                . '</a>'
            );
            unset($object);
        }
        if (count($this->data) < 1) {
            self::redirect('?node=' . $node . '&sub=ruleList');
        }
        $this->data[] = array(
            'field' => '<label for="delete">'
            . _('Remove these items?')
            . '</label>',
            'input' => '<button class="btn btn-danger btn-block" type="submit" '
            . 'name="delete" id="delete">'
            . _('Delete')
            . '</button>'
        );
        echo '<!-- Delete Items -->';
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div id="deleteDiv"></div>';
        echo '<form class="form-horizontal" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '<input type="hidden" name="storagegroup" value="0"/>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually performs the deletion actions.
     *
     * @return void
     */
    public function deletemultiRuleAjax()
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
            if (!$validate) {
                echo json_encode(
                    array(
                        'error' => self::$foglang['InvalidLogin'],
                        'title' => _('Unable to Authenticate')
                    )
                );
                exit;
            }
        }
        $remitems = filter_input_array(
            INPUT_POST,
            array(
                'remitems' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $remitems = $remitems['remitems'];
        self::$HookManager->processEvent(
            'MULTI_REMOVE',
            array('removing' => &$remitems)
        );
        self::getClass('AccessControlRule')
            ->getManager()
            ->destroy(
                array('id' => $remitems)
            );
        echo json_encode(
            array(
                'msg' => _('Successfully deleted'),
                'title' => _('Delete Success')
            )
        );
        exit;
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
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $type = filter_input(
            INPUT_POST,
            'type'
        );
        $parent = filter_input(
            INPUT_POST,
            'parent'
        );
        $node = filter_input(
            INPUT_POST,
            'nodeParent'
        );
        $value = filter_input(
            INPUT_POST,
            'value'
        );
        $fields = array(
            '<label for="type">'
            . _('Rule Type')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control ruletype-input" type='
            . '"text" name="type" id="type" required value="'
            . $type
            . '"/>'
            . '</div>',
            '<label for="parent">'
            . _('Parent')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control ruleparent-input" type='
            . '"text" name="parent" id="parent" required value="'
            . $parent
            . '"/>'
            . '</div>',
            '<label for="nodeParent">'
            . _('Node Parent')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control rulenodeparent-input" '
            . 'type="text" name="nodeParent" id="nodeParent" value="'
            . $node
            . '"/>'
            . '</div>',
            '<label for="value">'
            . _('Rule Value')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control rulevalue-input" '
            . 'type="text" name="value" id="value" required value="'
            . $value
            . '"/>'
            . '</div>',
            '<label for="add">'
            . _('Create Rule?')
            . '</label>' => '<button class="btn btn-info btn-blcok" name="'
            . 'add" id="add" type="submit">'
            . _('Create')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
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
    }
    /**
     * Add rule post.
     *
     * @return void
     */
    public function addRulePost()
    {
        self::$HookManager
            ->processEvent(
                'ADD_RULE_POST'
            );
        $value = trim(
            filter_input(
                INPUT_POST,
                'value'
            )
        );
        $type = trim(
            filter_input(
                INPUT_POST,
                'type'
            )
        );
        $name = $type
            . '-'
            . $value;
        $parent = trim(
            filter_input(
                INPUT_POST,
                'parent'
            )
        );
        $node = trim(
            filter_input(
                INPUT_POST,
                'nodeParent'
            )
        );
        try {
            $exists = self::getClass('AccessControlRuleManager')
                ->exists($value);
            if ($exists) {
                throw new Exception(_('A rule already exists with this name.'));
            }
            $AccessControlRule = self::getClass('AccessControlRule')
                ->set('type', $type)
                ->set('value', $value)
                ->set('name', $name)
                ->set('parent', $parent)
                ->set('node', $node);
            if (!$AccessControlRule->save()) {
                throw new Exception(_('Add rule failed!'));
            }
            $hook = 'RULE_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Rule added!'),
                    'title' => _('Rule Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'RULE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Rule Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('AccessControlRule' => &$AccessControlRule)
            );
        unset($AccessControlRule);
        echo $msg;
        exit;
    }
    /**
     * Edit rule.
     *
     * @return void
     */
    public function editRule()
    {
        $this->title = _('Edit')
            . ': '
            . $this->obj->get('name');
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $type = filter_input(
            INPUT_POST,
            'type'
        ) ?: $this->obj->get('type');
        $parent = filter_input(
            INPUT_POST,
            'parent'
        ) ?: $this->obj->get('parent');
        $node = filter_input(
            INPUT_POST,
            'nodeParent'
        ) ?: $this->obj->get('node');
        $value = filter_input(
            INPUT_POST,
            'value'
        ) ?: $this->obj->get('value');
        $fields = array(
            '<label for="type">'
            . _('Rule Type')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control ruletype-input" type='
            . '"text" name="type" id="type" required value="'
            . $type
            . '"/>'
            . '</div>',
            '<label for="parent">'
            . _('Parent')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control ruleparent-input" type='
            . '"text" name="parent" id="parent" required value="'
            . $parent
            . '"/>'
            . '</div>',
            '<label for="nodeParent">'
            . _('Node Parent')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control rulenodeparent-input" '
            . 'type="text" name="nodeParent" id="nodeParent" value="'
            . $node
            . '"/>'
            . '</div>',
            '<label for="value">'
            . _('Rule Value')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control rulevalue-input" '
            . 'type="text" name="value" id="value" required value="'
            . $value
            . '"/>'
            . '</div>',
            '<label for="updaterule">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" name="'
            . 'updaterule" id="updaterule" type="submit">'
            . _('Update')
            . '</button>'
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
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
        echo '<div class="col-xs-9 tab-content">';
        echo '<div class="tab-pane fade in active" id="rule-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Access Control Rule General');
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
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
        $value = trim(
            filter_input(
                INPUT_POST,
                'value'
            )
        );
        $parent = trim(
            filter_input(
                INPUT_POST,
                'parent'
            )
        );
        $node = trim(
            filter_input(
                INPUT_POST,
                'nodeParent'
            )
        );
        $type = trim(
            filter_input(
                INPUT_POST,
                'type'
            )
        );
        try {
            if (isset($_POST['updaterule'])) {
                $this->obj
                    ->set('type', $type)
                    ->set('parent', $parent)
                    ->set('node', $node)
                    ->set('value', $value);
                if (!$this->obj->save()) {
                    throw new Exception(_('Failed to update'));
                }
                $hook = 'ROLE_EDIT_SUCCESS';
                $msg = json_encode(
                    array(
                        'msg' => _('Rule updated!'),
                        'title' => _('Rule Update Success')
                    )
                );
            }
        } catch (Exception $e) {
            $hook = 'RULE_EDIT_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Rule Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('AccessControlRule' => &$this->obj)
            );
        echo $msg;
        exit;
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
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
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
        self::$HookManager
            ->processEvent(
                'RULE_DEL_FIELDS',
                array(
                    'fields' => &$fields,
                    'AccessControlRule' => &$this->obj
                )
            );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
        self::$HookManager->processEvent(
            'RULE_DEL',
            array(
                'data' => &$this->data,
                'headerData' => &$this->headerData,
                'attributes' => &$this->attributes,
                'templates' => &$this->templates,
                'AccessControlRule' => &$this->obj
            )
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div id="deleteDiv"></div>';
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
     * Delete rule post.
     *
     * @return void
     */
    public function deleteRulePost()
    {
        if (self::getSetting('FOG_REAUTH_ON_DELETE')) {
            $validate = self::getClass('User')
                ->passwordValidate(
                    $_POST['fogguiuser'],
                    $_POST['fogguipass'],
                    true
                );
            if (!$validate) {
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
                'ACCESSCONTROL_RULE_DELETE_POST',
                array('AccessControlRule' => &$this->obj)
            );
        try {
            if (!$this->obj->destroy()) {
                throw new Exception(
                    _('Fail to destroy')
                );
            }
            $hook = 'ACCESSCONTROL_RULE_DELETE_POST_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Rule deleted successfully!'),
                    'title' => _('Rule Delete Success')
                )
            );
            $url = sprintf(
                '?node=%s&sub=ruleList',
                $this->node
            );
        } catch (Exception $e) {
            $hook = 'ACCESSCONTROL_RULE_DELETE_POST_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Rule Delete Fail')
                )
            );
            $url = $this->formAction;
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('AccessControlRule'=>&$this->obj)
            );
        self::resetRequest();
        echo $msg;
        self::redirect($url);
    }
    /**
     * Assoc rule.
     *
     * @return void
     */
    public function assocRule()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler2>'
            . '<input type="checkbox" name="toggle-checkbox'
            . $this->node
            . '" class="toggle-checkboxrule" id="toggler2"/>'
            . '</label>',
            _('Rule Name'),
            _('Value'),
            _('Parent'),
            _('Node')
        );
        $this->templates = array(
            '<label for="rule-${rule_id}">'
            . '<input type="checkbox" name="rule[]" class="toggle-'
            . 'rule" id="rule-${rule_id}" '
            . 'value="${rule_id}"/>'
            . '</label>',
            '<a href="?node=%s&sub=editRule&id=${rule_id}">'
            . '${rule_name}</a>',
            '${value}',
            '${parent}',
            '${node}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${rule_name}'
            ),
            array(),
            array(),
            array()
        );
        Route::listem('accesscontrolrule');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->accesscontrolrules;
        $getter = 'accesscontrolrulesnotinme';
        $returnData = function (&$item) use (&$getter) {
            $this->obj->get($getter);
            if (!in_array($item->id, (array)$this->obj->get($getter))) {
                return;
            }
            $this->data[] = array(
                'rule_id' => $item->id,
                'rule_name' => $item->name,
                'value' => $item->value,
                'parent' => $item->parent,
                'node' => $item->node,
            );
            unset($item);
        };
        array_walk($items, $returnData);
        echo '<!-- Rule Membership -->';
        echo '<div class="col-xs-9">';
        echo '<div class="tab-pane fade in active" id="'
            . $this->node
            . '-membership">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->childClass
            . ' '
            . _('Rule Membership');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        if (count($this->data) > 0) {
            $notInMe = $meShow = 'accesscontrolrule';
            $meShow .= 'MeShow';
            $notInMe .= 'NotInMe';
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="'
                . $meShow
                . '">';
            echo '<input type="checkbox" name="'
                . $meShow
                . '" id="'
                . $meShow
                . '"/>';
            echo _('Check here to see what rules can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially panel panel-info" id="'
                . $notInMe
                . '">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Rules');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updaterules" class="control-label col-xs-4">';
            echo _('Add selected rules');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="addRules" '
                . 'id="updaterules" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates
        );
        $this->headerData = array(
            '<label for="toggler3">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxrulerm" id="toggler3"/></label>',
            _('Rule Name'),
            _('Value'),
            _('Parent'),
            _('Node')
        );
        $this->templates = array(
            '<label for="rulerm-${rule_id}">'
            . '<input type="checkbox" name="ruledel[]" class="toggle-'
            . 'rulerm" id="rulerm-${rule_id}" '
            . 'value="${rule_id}"/>'
            . '</label>',
            '<a href="?node=%s&sub=editRule&id=${rule_id}">'
            . '${rule_name}</a>',
            '${value}',
            '${parent}',
            '${node}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${rule_name}'
            ),
            array(),
            array(),
            array()
        );
        $getter = 'accesscontrolrules';
        array_walk($items, $returnData);
        if (count($this->data) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Remove Accesscontrol Rules');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="remrules" class="control-label col-xs-4">';
            echo _('Remove selected rules');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="remrules" class='
                . '"btn btn-danger btn-block" id="remrules">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Post assoc rule adjustments.
     *
     * @return void
     */
    public function assocRulePost()
    {
        $flags = array(
            'flags' => FILTER_REQUIRE_ARRAY
        );
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'rule' => $flags,
                'ruledel' => $flags
            )
        );
        $rules = $reqitems['rule'];
        $rulesdel = $reqitems['ruledel'];
        if (isset($_POST['addRules'])) {
            $this->obj->addRule($rules);
        }
        if (isset($_POST['remrules'])) {
            $this->obj->removeRule($rulesdel);
        }
        if ($this->obj->save()) {
            self::redirect($this->formAction);
        }
    }
    /**
     * Add rule group.
     *
     * @return void
     */
    public function addRuleGroup()
    {
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'accesscontrol',
                'accesscontrolIDArray'
            )
        );
        $accesscontrol = $reqitems['accesscontrol'];
        $accesscontrolrules = array_unique(
            array_filter(
                explode(',', $reqitems['accesscontrolIDArray'])
            )
        );
        try {
            if (!$accesscontrol) {
                throw new Exception(_('No role selected'));
            }
            if (count($accesstrolrules) < 1) {
                throw new Exception(_('No rule selected'));
            }
            $Role = new AccessControl($accesscontrol);
            foreach ((array)$accesscontrolrules as $ruleID) {
                $Rule = new AccessControlRule($ruleID);
                $name = $Role->get('name')
                    . '-'
                    . $Rule->get('name');
                $AccessControlRuleAssociation
                    = self::getClass('AccessControlRuleAssociation')
                    ->set('accesscontrolID', $accesscontrol)
                    ->set('name', $name)
                    ->set('accesscontrolruleID', $ruleID);
                if (!$AccessControlRuleAssociation->save()) {
                    throw new Exception(_('Associate rule failed!'));
                }
                unset($AccessControlRuleAssociation);
                unset($Rule);
            }
            unset($ruleID);
            $hook = 'RULEASSOC_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Rule associate success!'),
                    'title' => _('Rule Associate Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'RULEASSOC_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Rule Associate Fail')
                )
            );
        }
        self::$HookManager->processEvent(
            $hook
        );
        echo $msg;
        exit;
    }
    /**
     * Custom membership method.
     *
     * @return void
     */
    public function membership()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox'
            . $this->node
            . '" class="toggle-checkboxuser" id="toggler"/>'
            . '</label>',
            _('User name'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="user-${user_id}">'
            . '<input type="checkbox" name="user[]" class="toggle-'
            . 'user" id="user-${user_id}" '
            . 'value="${user_id}"/>'
            . '</label>',
            '<a href="?node=user&sub=edit&id=${user_id}">'
            . '${user_name}</a>',
            '${friendly}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${user_name}'
            ),
            array()
        );
        Route::listem('user');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->users;
        $getter = 'usersnotinme';
        $returnData = function (&$item) use (&$getter) {
            $this->obj->get($getter);
            if (!in_array($item->id, (array)$this->obj->get($getter))) {
                return;
            }
            $this->data[] = array(
                'user_id' => $item->id,
                'user_name' => $item->name,
                'friendly' => $item->display
            );
        };
        array_walk($items, $returnData);
        echo '<!-- Membership -->';
        echo '<div class="col-xs-9">';
        echo '<div class="tab-pane fade in active" id="'
            . $this->node
            . '-membership">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->childClass
            . ' '
            . _('Membership');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        if (count($this->data) > 0) {
            $notInMe = $meShow = 'user';
            $meShow .= 'MeShow';
            $notInMe .= 'NotInMe';
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="'
                . $meShow
                . '">';
            echo '<input type="checkbox" name="'
                . $meShow
                . '" id="'
                . $meShow
                . '"/>';
            echo _('Check here to see what users can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially panel panel-info" id="'
                . $notInMe
                . '"/>';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Users');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updateusers" class="control-label col-xs-4">';
            echo _('Add selected users');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="addUsers" '
                . 'id="updateusers" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates
        );
        $this->headerData = array(
            '<label for="toggler1">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxuserrm" id="toggler1"/></label>',
            _('User Name'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="userrm-${user_id}">'
            . '<input type="checkbox" name="userdel[]" '
            . 'value="${user_id}" class="toggle-userrm" id="'
            . 'userrm-${user_id}"/>'
            . '</label>',
            '<a href="?node=user&sub=edit&id=${user_id}">'
            . '${user_name}</a>',
            '${friendly}'
        );
        $getter = 'users';
        array_walk($items, $returnData);
        if (count($this->data) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Remove Users');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="remusers" class="control-label col-xs-4">';
            echo _('Remove selected users');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="remusers" class='
                . '"btn btn-danger btn-block" id="remusers">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Customize membership actions
     *
     * @return void
     */
    public function membershipPost()
    {
        $flags = array(
            'flags' => FILTER_REQUIRE_ARRAY
        );
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'user' => $flags,
                'userdel' => $flags
            )
        );
        $users = $reqitems['user'];
        $usersdel = $reqitems['userdel'];
        if (isset($_POST['addUsers'])) {
            $this->obj->addUser($users);
        }
        if (isset($_POST['remusers'])) {
            $this->obj->removeUser($usersdel);
        }
        if ($this->obj->save()) {
            self::redirect($this->formAction);
        }
    }
}
