<?php
/**
 * The SubnetGroups page.
 *
 * PHP version 5
 *
 * @category SubnetGroupsManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The wol broadcast page.
 *
 * @category SubnetGroupsManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetgroupsManagementPage extends FOGPage
{
    /**
     * The node this page displays with.
     *
     * @var string
     */
    public $node = 'subnetgroups';
    /**
     * Initializes the Subnetgroups Page.
     *
     * @param string $name The name to pass with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Subnetgroups Management';

        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );

        self::$foglang['ExportSubnetgroups'] = _('Export Subnetgroups');
        self::$foglang['ImportSubnetgroups'] = _('Import Subnetgroups');
        self::$foglang['ListAll'] = _('List All Subnetgroups');

        parent::__construct($this->name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat#subnetgroups-general" => self::$foglang['General'],
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
            . '<input type="checkbox" name="subnetgroups[]" value='
            . '"${id}" class="toggle-action" checked/>'
            . '</label>',
            '<a href="?node=subnetgroups&sub=edit&id=${id}" title="'
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
         * @param object $Subnetgroups the object to use
         *
         * @return void
         */
        self::$returnData = function (&$Subnetgroups) {

            $groupName = self::getSubObjectIDs(
                'Group',
                array('id' => $Subnetgroups->groupID),
                'name'
            )[0];

            $this->data[] = array(
                'id' => $Subnetgroups->id,
                'groupName' => $groupName,
                'groupID'   => $Subnetgroups->groupID,
                'subnets' => $Subnetgroups->subnets,
                'name' => $Subnetgroups->name,
            );
            unset($Subnetgroups);
        };
    }

    /**
     * Present page to create new Subnetgroups entry.
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

        $this->title = _('New Subnetgroups');
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
            . _('Create New SubnetGroups?')
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
        self::$HookManager->processEvent('SUBNETGROUPS_ADD_POST');

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
            if (self::getClass('SubnetgroupsManager')->exists($name)) {
                throw new Exception(
                    _('A subnetgroups already exists with this name!')
                );
            }
            if (!$group) {
                throw new Exception(
                    _('A group is required!')
                );
            }
            $subnetsMatch = "/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))(( )*,( )*([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))+)*$/";
            if (!preg_match($subnetsMatch, $subnets)) {
                throw new Exception(
                    _('Please enter a valid CIDR subnets comma separated list')
                );
            }

            $subnets = str_replace(' ', '', $subnets);
            $subnets = str_replace(',', ', ', $subnets);
            $gr = new Group($group);
            $SubnetGroups = self::getClass('Subnetgroups')
                ->set('name', $name)
                ->set('subnets', $subnets)
                ->set('groupID', $gr->get('id'));

            if (!$SubnetGroups->save()) {
                throw new Exception(_('Add Subnetgroups failed!'));
            }
            $hook = 'SUBNETGROUPS_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Subnetgroups added!'),
                    'title' => _('Subnetgroups Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SUBNETGROUPS_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Subnetgroups Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('SubnetGroups' => &$SubnetGroups)
            );
        unset($SubnetGroups);
        echo $msg;
        exit;
    }
    /**
     * SubnetGroups General tab.
     *
     * @return void
     */
    public function subnetgroupsGeneral()
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


        $this->title = _('SubnetGroups General');
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
                'SUBNETGROUPS_EDIT',
                array(
                    'data' => &$this->data,
                    'headerData' => &$this->headerData,
                    'attributes' => &$this->attributes,
                    'templates' => &$this->templates
                )
            );
        unset($fields);
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="subnetgroups-general">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=subnetgroups-general">';
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
        $this->subnetgroupsGeneral();
        echo '</div>';
    }
    /**
     * SubnetGroups General Post()
     *
     * @return void
     */
    public function subnetgroupsGeneralPost()
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

        if ($this->obj->get('name') != $name
             && self::getClass('SubnetgroupsManager')->exists(
                 $name,
                 $this->obj->get('id')))
        {
            throw new Exception(
                _('A name is required!')
            );
        }
        if (!$group) {
            throw new Exception(
                _('A group is required!')
            );
        }

	$subnetsMatch = "/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))(( )*,( )*([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))+)*$/";
        if (!preg_match($subnetsMatch, $subnets)) {
            throw new Exception(
                _('Please enter a valid CIDR subnets comma separated list')
            );
        }

        $subnets = str_replace(' ', '', $subnets);
        $subnets = str_replace(',', ', ', $subnets);
        $this->obj
            ->set('name', $name)
            ->set('subnets', $subnets)
            ->set('groupID', $group);
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
                'SUBNETGROUPS_EDIT_POST',
                array('Subnetgroups'=> &$this->obj)
            );
        global $tab;
        try{
            switch ($tab) {
            case 'subnetgroups-general':
                $this->subnetgroupsGeneralPost();
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Subnetgroups update failed!'));
            }
            $hook = 'SUBNETGROUPS_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Subnetgroups updated!'),
                    'title' => _('Subnetgroups Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SUBNETGROUPS_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Subnetgroups Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Subnetgroups' => &$this->obj)
            );
        echo $msg;
        exit;
    }
}
