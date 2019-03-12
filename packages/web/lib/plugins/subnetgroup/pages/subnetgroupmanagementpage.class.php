<?php
/**
 * The subnetgroup page.
 *
 * PHP version 5
 *
 * @category SubnetGroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The wol broadcast page.
 *
 * @category SubnetGroupManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetgroupManagementPage extends FOGPage
{
    /**
     * The node this page displays with.
     *
     * @var string
     */
    public $node = 'subnetgroup';
    /**
     * Initializes the Subnetgroup Page.
     *
     * @param string $name The name to pass with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Subnetgroup Management';

        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );

        self::$foglang['ExportSubnetgroup'] = _('Export Subnetgroups');
        self::$foglang['ImportSubnetgroup'] = _('Import Subnetgroups');

        parent::__construct($this->name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#subnetgroup-general" => self::$foglang['General'],
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Name') => $this->obj->get('name'),
                _('Group') => $this->obj->get('groupID'),
                _('Subnets') => $this->obj->get('subnets'),
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" checked/>',
            _('Name'),
            _('Subnets'),
            _('Group'),
        );
        $this->templates = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="subnetgroup[]" value='
            . '"${id}" class="toggle-action" checked/>'
            . '</label>',
            '<a href="?node=subnetgroup&sub=edit&id=${id}" title="'
            . _('Edit')
            . '">${name}</a>',
            '${subnets}',
            '<a href="?node=group&sub=edit&id=${groupID}" title="'
            . _('Group')
            . '">${groupName}</a>',
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => '16'
            ),
            array(),
            array()
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Subnetgroup the object to use
         *
         * @return void
         */
        self::$returnData = function (&$Subnetgroup) {
            Route::listem(
                'group',
                'name',
                false,
                array('id' => $Subnetgroup->groupID)
            );

            $Group = json_decode(
                Route::getData()
            );

            $this->data[] = array(
                'id' => $Subnetgroup->id,
                'groupName' => isset($Group->groups[0]) ? $Group->groups[0]->name : '',
                'groupID'   => $Subnetgroup->groupID,
                'subnets' => $Subnetgroup->subnets,
                'name' => $Subnetgroup->name,
            );
            unset($Subnetgroup);
        };
    }

    /**
     * Present page to create new Subnetgroup entry.
     *
     * @return void
     */
    public function add()
    {
        $subnets = filter_input(
            INPUT_POST,
            'subnets'
        );
        $group = filter_input(
            INPUT_POST,
            'group'
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );

        $grbuild = self::getClass('GroupManager')->buildSelectBox(
            $group
        );

        $this->title = _('New Subnetgroup');
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
          '<label for="name">'
            . _('Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control sgsubnet-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name" required'
            . '</div>',
          '<label for="subnets">'
            . _('Subnets')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control sgsubnet-input" name='
            . '"subnets" value="'
            . $subnets
            . '" autocomplete="off" id="subnets" required'
            . ' placeholder="192.168.1.0/24, 10.1.0.0/16"/>'
            . '</div>',
          '<label for="group">'
            . _('Group')
            . '</label>' => $grbuild,
          '<label for="add">'
            . _('Create New SubnetGroup?')
            . '</label>' => '<button class="btn btn-info btn-block" name="'
            . 'add" id="add" type="submit">'
            . _('Create')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
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
     * Actually create the items.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('SUBNETGROUP_ADD_POST');

        $subnets = filter_input(
            INPUT_POST,
            'subnets'
        );
        $group = filter_input(
            INPUT_POST,
            'group'
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );

        try {
            if (!$name) {
                throw new Exception(
                    _('A name is required!')
                );
            }
            if (self::getClass('SubnetgroupManager')->exists($name)) {
                throw new Exception(
                    _('A subnetgroup already exists with this name!')
                );
            }
            if (!$group) {
                throw new Exception(
                    _('A group is required!')
                );
            }
            $gexists = self::getClass('SubnetGroupManager')
                ->exists($group, '', 'groupID');
            if ($gexists) {
                throw new Exception(
                    _('A subnet group is already using this group.')
                );
            }

            $subnetsMatch = "/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))"
                . "(( )*,( )*([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))+)"
                . "*$/";
            if (!preg_match($subnetsMatch, $subnets)) {
                throw new Exception(
                    _('Please enter a valid CIDR subnets comma separated list')
                );
            }

            $subnets = str_replace(' ', '', $subnets);
            $subnets = str_replace(',', ', ', $subnets);
            $gr = new Group($group);
            $SubnetGroup = self::getClass('Subnetgroup')
                ->set('name', $name)
                ->set('subnets', $subnets)
                ->set('groupID', $gr->get('id'));

            if (!$SubnetGroup->save()) {
                throw new Exception(_('Add Subnetgroup failed!'));
            }
            $hook = 'SUBNETGROUP_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Subnetgroup added!'),
                    'title' => _('Subnetgroup Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SUBNETGROUP_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Subnetgroup Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('SubnetGroup' => &$SubnetGroup)
            );
        unset($SubnetGroup);
        echo $msg;
        exit;
    }
    /**
     * SubnetGroup General tab.
     *
     * @return void
     */
    public function subnetgroupGeneral()
    {
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->attributes,
            $this->templates
        );

        $subnets = filter_input(
            INPUT_POST,
            'subnets'
        ) ? : $this->obj->get('subnets');

        $group = filter_input(
            INPUT_POST,
            'group'
        ) ? : $this->obj->get('groupID');
        $grbuild = self::getClass('GroupManager')->buildSelectBox(
            $group
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        ) ? : $this->obj->get('name');


        $this->title = _('SubnetGroup General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );

        $fields = array(
          '<label for="subnets">'
            . _('Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control sgsubnet-input" name='
            . '"name" value="'
            . $name
            . '" autocomplete="off" id="name"/>'
            . '</div>',
          '<label for="subnets">'
            . _('Subnets')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" class="'
            . 'form-control sgsubnet-input" name='
            . '"subnets" value="'
            . $subnets
            . '" autocomplete="off" id="subnets"/>'
            . '</div>',
          '<label for="group">'
            . _('Group')
            . '</label>' => $grbuild,
          '<label for="updategen">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" name="'
            . 'updategen" id="updategen" type="submit">'
            . _('Update')
            . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'SUBNETGROUP_EDIT',
                array(
                    'data' => &$this->data,
                    'headerData' => &$this->headerData,
                    'attributes' => &$this->attributes,
                    'templates' => &$this->templates
                )
            );
        unset($fields);
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="subnetgroup-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=subnetgroup-general">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->form,
            $this->data,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Edit the current item.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->subnetgroupGeneral();
        echo '</div>';
    }
    /**
     * SubnetGroup General Post()
     *
     * @return void
     */
    public function subnetgroupGeneralPost()
    {
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $group = trim(
            filter_input(INPUT_POST, 'group')
        );
        $subnets = trim(
            filter_input(INPUT_POST, 'subnets')
        );

        $subnetsMatch = "/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))"
            . "(( )*,( )*([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))+)"
            . "*$/";
        $exists = self::getClass('SubnetGroupManager')
            ->exists($name);
        if ($name != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('A subnet group already exists with this name!')
            );
        }
        if (!$group) {
            throw new Exception(
                _('A group must be selected.')
            );
        }
        $gexists = self::getClass('SubnetGroupManager')
            ->exists($group, '', 'groupID');
        if ($group != $this->obj->get('groupID')
            && $gexists
        ) {
            throw new Exception(
                _('A subnet group is already using this group.')
            );
        }
        if (!preg_match($subnetsMatch, $subnets)) {
            throw new Exception(
                _('Please enter a valid CIDR subnet.')
                . ' '
                . _('Can be a comma seperated list.')
            );
        }
        $subnets = preg_replace('/\s+/', '', $subnets);
        $subnets = str_replace(',', ', ', $subnets);
        $this->obj
            ->set('name', $name)
            ->set('groupID', $group)
            ->set('subnets', $subnets);
    }
    /**
     * Submit the edits.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'SUBNETGROUP_EDIT_POST',
                array('Subnetgroup'=> &$this->obj)
            );
        global $tab;
        try {
            switch ($tab) {
            case 'subnetgroup-general':
                $this->subnetgroupGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Subnetgroup update failed!'));
            }
            $hook = 'SUBNETGROUP_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Subnetgroup updated!'),
                    'title' => _('Subnetgroup Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SUBNETGROUP_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Subnetgroup Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Subnetgroup' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
