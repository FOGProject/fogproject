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
                . '"${id}" class="toggle-action" checked/>',
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
        $this->indexDivDisplay(true);
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
            . '</label>' => 'div class="input-group">'
            . '<input class="form-control ruleparent-input" type='
            . '"text" name="parent" id="parent" required value="'
            . $parent
            . '"/>'
            . '</div>',
            '<label for="nodeParent">'
            . _('Node Parent')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control rulenodeparent-input" '
            . 'type="text" name="nodeParent" id="nodeParent" required value="'
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
                ->set('node', trim($_REQUEST['nodeParent']));
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
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
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
                '<input class="smaller" type="text" name="nodeParent" value="%s"/>',
                (
                    $_REQUEST['nodeParent'] ?
                    $_REQUEST['nodeParent'] :
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
            if (isset($_REQUEST['update'])) {
                $value = $_REQUEST['value'];
                $this->obj
                    ->set('type', $_REQUEST['type'])
                    ->set('parent', $_REQUEST['parent'])
                    ->set('node', $_REQUEST['nodeParent'])
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
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8'),
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
        $this->data = array();
        echo '<!-- Rule Membership -->';
        printf(
            '<div id="%s-membership">',
            $this->node
        );
        $this->headerData = array(
            sprintf(
                '<input type="checkbox" name="toggle-checkboxrule"'
                . 'class="toggle-checkboxrule"/>',
                $this->node
            ),
            _('Rule name'),
            _('Value'),
            _('Parent'),
            _('Node'),
        );
        $this->templates = array(
            '<input type="checkbox" name="rule[]" value="${rule_id}" '
            . 'class="toggle-rule"/>',
            sprintf(
                '<a href="?node=%s&sub=editRule&id=${rule_id}" '
                . 'title="%s: ${rule_name}">${rule_name}</a>',
                $this->node,
                _('Edit')
            ),
            '${value}',
            '${parent}',
            '${node}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false',
            ),
            array(),
            array(),
            array(),
            array()
        );
        foreach ((array)self::getClass('AccessControlRuleManager')
            ->find(
                array(
                    'id' => $this->obj->get('accesscontrolrulesnotinme'),
                )
            ) as &$Rule
        ) {
            $this->data[] = array(
                'rule_id' => $Rule->get('id'),
                'rule_name' => $Rule->get('name'),
                'value' => $Rule->get('value'),
                'parent' => $Rule->get('parent'),
                'node' => $Rule->get('node'),
            );
            unset($Rule);
        }
        if (count($this->data) > 0) {
            self::$HookManager->processEvent(
                'OBJ_RULES_NOT_IN_ME',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
            printf(
                '<form method="post" action="%s"><label for="ruleMeShow">'
                . '<p class="c">%s %s&nbsp;&nbsp;<input '
                . 'type="checkbox" name="ruleMeShow" id="ruleMeShow"/>'
                . '</p></label><div id="ruleNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                _('Check here to see rules not within this'),
                $this->node,
                _('Modify Rule Membership for'),
                $this->obj->get('name')
            );
            $this->render();
            printf(
                '</div><br/><p class="c"><input type="submit" '
                . 'value="%s %s(s) %s %s" name="addRules"/></p><br/>',
                _('Add'),
                _('rule'),
                _('to'),
                $this->node
            );
        }
        $this->data = array();
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Rule Name'),
            _('Value'),
            _('Parent'),
            _('Node'),
        );
        $this->templates = array(
            '<input type="checkbox" name="ruledel[]" '
            . 'value="${rule_id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=editRule&id=${rule_id}" '
                . 'title="%s: ${rule_name}">${rule_name}</a>',
                $this->node,
                _('Edit')
            ),
            '${value}',
            '${parent}',
            '${node}',
        );
        foreach ((array)self::getClass('AccessControlRuleManager')
            ->find(
                array(
                    'id' => $this->obj->get('accesscontrolrules'),
                )
            ) as &$Rule
        ) {
            $this->data[] = array(
                'rule_id' => $Rule->get('id'),
                'rule_name' => $Rule->get('name'),
                'value' => $Rule->get('value'),
                'parent' => $Rule->get('parent'),
                'node' => $Rule->get('node'),
            );
            unset($Rule);
        }
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
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        if (count($this->data)) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s %ss %s %s" name="remrules"/></p>',
                _('Delete Selected'),
                _('rule'),
                _('from'),
                $this->node
            );
        }
        $this->data = array();
    }
    /**
     * Post assoc rule adjustments.
     *
     * @return void
     */
    public function assocRulePost()
    {
        $this->membershipPost();
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
            if (!$_REQUEST['accesscontrolruleIDArray']) {
                throw new Exception(_('Not rule selected'));
            }
            $reqID = explode(',', $_REQUEST['accesscontrolruleIDArray']);
            $reqID = array_unique($reqID);
            $reqID = array_filter($reqID);
            $Role = new AccessControl($_REQUEST['accesscontrol']);
            foreach ((array)$reqID as $ruleID) {
                $Rule = new AccessControlRule($ruleID);
                $AccessControlRuleAssociation
                    = self::getClass('AccessControlRuleAssociation')
                    ->set('accesscontrolID', $_REQUEST['accesscontrol'])
                    ->set('name', $Role->get('name'). "-" . $Rule->get('name'))
                    ->set('accesscontrolruleID', $ruleID);
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
     * Custom membership method.
     *
     * @return void
     */
    public function membership()
    {
        $this->data = array();
        echo '<!-- Membership -->';
        printf(
            '<div id="%s-membership">',
            $this->node
        );
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxuser" '
            . 'class="toggle-checkboxuser"/>',
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            sprintf(
                '<input type="checkbox" name="user[]" value="${user_id}" '
                . 'class="toggle-%s"/>',
                'user'
            ),
            sprintf(
                '<a href="?node=%s&sub=edit&id=${user_id}" '
                . 'title="Edit: ${user_name}">${user_name}</a>',
                'user'
            ),
            '${friendly}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
            ),
            array()
        );
        foreach ((array)self::getClass('UserManager')
            ->find(
                array(
                    'id' => $this->obj->get('usersnotinme'),
                )
            ) as &$User
        ) {
            $this->data[] = array(
                'user_id' => $User->get('id'),
                'user_name' => $User->get('name'),
                'friendly' => $User->get('display')
            );
            unset($User);
        }
        if (count($this->data) > 0) {
            self::$HookManager->processEvent(
                'OBJ_USERS_NOT_IN_ME',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
            printf(
                '<form method="post" action="%s"><label for="userMeShow">'
                . '<p class="c">%s %s&nbsp;&nbsp;<input '
                . 'type="checkbox" name="userMeShow" id="userMeShow"/>'
                . '</p></label><div id="userNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                _('Check here to see users not within this'),
                $this->node,
                _('Modify Membership for'),
                $this->obj->get('name')
            );
            $this->render();
            printf(
                '</div><br/><p class="c"><input type="submit" '
                . 'value="%s %s(s) %s %s" name="addUsers"/></p><br/>',
                _('Add'),
                _('user'),
                _('to'),
                $this->node
            );
        }
        $this->data = array();
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<input type="checkbox" name="userdel[]" '
            . 'value="${user_id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${user_id}" '
                . 'title="%s: ${user_name}">${user_name}</a>',
                $this->node,
                _('Edit')
            ),
            '${friendly}'
        );
        foreach ((array)self::getClass('UserManager')
            ->find(
                array(
                    'id' => $this->obj->get('users'),
                )
            ) as &$User
        ) {
            $this->data[] = array(
                'user_id' => $User->get('id'),
                'user_name' => $User->get('name'),
                'friendly' => $User->get('display')
            );
            unset($User);
        }
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
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        if (count($this->data)) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s %ss %s %s" name="remusers"/></p>',
                _('Delete Selected'),
                _('user'),
                _('from'),
                $this->node
            );
        }
        $this->data = array();
    }
    /**
     * Customize membership actions
     *
     * @return void
     */
    public function membershipPost()
    {
        if (isset($_REQUEST['addUsers'])) {
            $this->obj->addUser($_REQUEST['user']);
        }
        if (isset($_REQUEST['remusers'])) {
            $this->obj->removeUser($_REQUEST['userdel']);
        }
        if (isset($_REQUEST['addRules'])) {
            $this->obj->addRule($_REQUEST['rule']);
        }
        if (isset($_REQUEST['remrules'])) {
            $this->obj->removeRule($_REQUEST['ruledel']);
        }
        if ($this->obj->save()) {
            self::setMessage(
                sprintf(
                    '%s %s',
                    $this->obj->get('name'),
                    _('saved successfully')
                )
            );
        }
        self::redirect($this->formAction);
    }
}
