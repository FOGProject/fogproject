<?php
/**
 * Task type edit page.
 *
 * PHP Version 5
 *
 * @category TasktypeeditManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task type edit page.
 *
 * @category TasktypeeditManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TasktypeeditManagementPage extends FOGPage
{
    /**
     * The node to work from.
     *
     * @var string
     */
    public $node = 'tasktypeedit';
    /**
     * The initializor for the class.
     *
     * @param string $name What to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Task Type Management';
        self::$foglang['ExportTasktypeedit'] = _('Export Task Types');
        self::$foglang['ImportTasktypeedit'] = _('Import Task Types');
        parent::__construct($this->name);
        $this->menu['list'] = sprintf(self::$foglang['ListAll'], _('Task Types'));
        $this->menu['add'] = sprintf(self::$foglang['CreateNew'], _('Task Typee'));
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Name') => $this->obj->get('name'),
                _('Icon') => sprintf(
                    '<i class="fa fa-%s"></i>',
                    $this->obj->get('icon')
                ),
                _('Type') => $this->obj->get('type'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction"/>',
            _('Name'),
            _('Access'),
            _('Kernel Args'),
        );
        $this->templates = array(
            '<input type="checkbox" name="tasktypeedit[]" value='
            . '"${id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${id}" title='
                . '"Edit"><i class="fa fa-${icon} fa-1x"> ${name}</i></a>',
                $this->node
            ),
            '${access}',
            '${args}',
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $TaskType the object to use
         *
         * @return void
         */
        self::$returnData = function (&$TaskType) {
            $this->data[] = array(
                'icon' => $TaskType->icon,
                'id' => $TaskType->id,
                'name' => $TaskType->name,
                'access' => $TaskType->access,
                'args' => $TaskType->kernelArgs,
            );
            unset($TaskType);
        };
    }
    /**
     * Create new task type.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Task Type');
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $accessTypes = array('both','host','group');
        ob_start();
        foreach ($accessTypes as $i => &$type) {
            printf(
                '<option value="%s"%s>%s</option>',
                $type,
                (
                    $_REQUEST['access'] == $type ?
                    ' selected' :
                    ''
                ),
                ucfirst($type)
            );
            unset($type);
        }
        unset($accessTypes);
        $initrd = (
            filter_input(INPUT_POST, 'initrd') ?: ''
        );
        $access_opt = ob_get_clean();
        $fields = array(
            _('Name') => sprintf(
                '<input type="text" name="name" class="smaller" value="%s"/>',
                $_REQUEST['name']
            ),
            _('Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            _('Icon') => self::getClass('TaskType')->iconlist($_REQUEST['icon']),
            _('Kernel') => sprintf(
                '<input type="text" name="kernel" class="smaller" value="%s"/>',
                $_REQUEST['kernel']
            ),
            _('Kernel Arguments') => sprintf(
                '<input type="text" name="kernelargs" class="smaller" value="%s"/>',
                $_REQUEST['kernelargs']
            ),
            '<label for="initrd">'
            . _('Init')
            . '</label>' => '<textarea name="initrd" class='
            . '"form-control" id="initrd">'
            . $initrd
            . '</textarea>',
            _('Type') => sprintf(
                '<input type="text" name="type" class="smaller" value="%s"/>',
                $_REQUEST['type']
            ),
            _('Is Advanced') => sprintf(
                '<input type="checkbox" name="advanced"%s>',
                (
                    isset($_REQUEST['advanced']) ?
                    ' checked' :
                    ''
                )
            ),
            _('Accessed By') => sprintf(
                '<select name="access">%s</select>',
                $access_opt
            ),
            '&nbsp;'=> sprintf(
                '<input class="smaller" type="submit" value="%s"/>',
                _('Add')
            )
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
                'TASKTYPE_ADD',
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
     * Create the new type.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $name = $_REQUEST['name'];
            $description = $_REQUEST['description'];
            $icon = $_REQUEST['icon'];
            $kernel = $_REQUEST['kernel'];
            $kernelargs = $_REQUEST['kernelargs'];
            $initrd = filter_input(INPUT_POST, 'initrd');
            $type = (string)$_REQUEST['type'];
            $advanced = (string)intval(isset($_REQUEST['advanced']));
            $access = $_REQUEST['access'];
            $initrd = filter_input(INPUT_POST, 'initrd');
            if (!$name) {
                throw new Exception(_('You must enter a name'));
            }
            if (self::getClass('TaskTypeManager')->exists($name)) {
                throw new Exception(
                    _('Task type already exists, please try again.')
                );
            }
            $TaskType = self::getClass('TaskType')
                ->set('name', $name)
                ->set('description', $description)
                ->set('icon', $icon)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelargs)
                ->set('initrd', $initrd)
                ->set('type', $type)
                ->set('isAdvanced', $advanced)
                ->set('access', $access);
            if (!$TaskType->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Task Type added, editing'));
            self::redirect(
                sprintf(
                    '?node=%s&sub=edit&id=%s',
                    $this->node,
                    $TaskType->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Edit the current type.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf('%s: %s', _('Edit'), $this->obj->get('name'));
        unset($this->headerData);
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $accessTypes = array('both','host','group');
        foreach ($accessTypes as $i => &$type) {
            printf(
                '<option value="%s"%s>%s</option>',
                $type,
                (
                    $this->obj->get('access') == $type ?
                    ' selected' :
                    ''
                ),
                ucfirst($type)
            );
            unset($type);
        }
        unset($accessTypes);
        $access_opt = ob_get_clean();
        $initrd = (
            filter_input(INPUT_POST, 'initrd') ?: $this->obj->get('initrd')
        );
        $fields = array(
            _('Name') => sprintf(
                '<input type="text" name="name" class="smaller" value="%s"/>',
                $this->obj->get('name')
            ),
            _('Description') => sprintf(
                '<textarea name="description" rows="8" cols="40">%s</textarea>',
                $this->obj->get('description')
            ),
            _('Icon') => sprintf(
                '<input type="text" name="icon" class="smaller" value="%s"/>',
                $this->obj->get('icon')
            ),
            _('Icon') => self::getClass('TaskType')
                ->iconlist($this->obj->get('icon')),
            _('Kernel') => sprintf(
                '<input type="text" name="kernel" class="smaller" value="%s"/>',
                $this->obj->get('kernel')
            ),
            _('Kernel Arguments') => sprintf(
                '<input type="text" name="kernelargs" class="smaller" value="%s"/>',
                $this->obj->get('kernelArgs')
            ),
            '<label for="initrd">'
            . _('Init')
            . '</label>' => '<textarea name="initrd" class='
            . '"form-control" id="initrd">'
            . $initrd
            . '</textarea>',
            _('Type') => sprintf(
                '<input type="text" name="type" class="smaller" value="%s"/>',
                $this->obj->get('type')
            ),
            _('Is Advanced') => sprintf(
                '<input type="checkbox" name="advanced"%s/>',
                (
                    $this->obj->get('isAdvanced') ?
                    ' checked' :
                    ''
                )
            ),
            _('Accessed By') => sprintf(
                '<select name="access">%s</select>',
                $access_opt
            ),
            '&nbsp;' => sprintf(
                '<input class="smaller" type="submit" value="%s"/>',
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
                'TASKTYPE_EDIT',
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
     * Update the item.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'TASKTYPE_EDIT_POST',
                array('TaskType' => &$this->obj)
            );
        try {
            $name = $_REQUEST['name'];
            $description = $_REQUEST['description'];
            $icon = $_REQUEST['icon'];
            $kernel = $_REQUEST['kernel'];
            $kernelargs = $_REQUEST['kernelargs'];
            $type = $_REQUEST['type'];
            $advanced = (string)intval(isset($_REQUEST['advanced']));
            $access = $_REQUEST['access'];
            $initrd = filter_input(INPUT_POST, 'initrd');
            if (!$name) {
                throw new Exception(_('You must enter a name'));
            }
            if ($this->obj->get('name') != $name
                && self::getClass('TaskTypeManager')->exists($name)
            ) {
                throw new Exception(
                    _('Task type already exists, please try again.')
                );
            }
            $this->obj
                ->set('name', $name)
                ->set('description', $description)
                ->set('icon', $icon)
                ->set('kernel', $kernel)
                ->set('kernelArgs', $kernelargs)
                ->set('initrd', $initrd)
                ->set('type', $type)
                ->set('isAdvanced', $advanced)
                ->set('access', $access);
            if (!$this->obj->save()) {
                throw new Exception(_('Failed to update'));
            }
            self::setMessage('TaskType Updated');
            self::redirect($this->formAction);
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
}
