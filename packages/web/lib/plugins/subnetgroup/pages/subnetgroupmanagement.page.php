<?php
/**
 * Subnet group management page.
 *
 * PHP version 5
 *
 * @category SubnetGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Subnet group management page.
 *
 * @category SubnetGroupManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetGroupManagement extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'subnetgroup';
    /**
     * Initializes the Subnet Group management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Subnet Group Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Subnets'),
            _('Assigned Group Name')
        ];
        $this->attributes = [
            [],
            [],
            []
        ];
    }
    /**
     * Create new subnet group entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Subnet Group');

        $subnetgroup = filter_input(INPUT_POST, 'subnetgroup');
        $description = filter_input(INPUT_POST, 'description');
        $group = filter_input(INPUT_POST, 'group');
        $groupSelector = self::getClass('GroupManager')->buildSelectBox($group);
        $subnets = filter_input(INPUT_POST, 'subnets');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'subnetgroup',
                _('Subnet Group Name')
            ) => self::makeInput(
                'form-control subnetgroupname-input',
                'subnetgroup',
                _('Subnet Group Name'),
                'text',
                'subnetgroup',
                $subnetgroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Subnet Group Description')
            ) => self::makeTextarea(
                'form-control subnetgroupdescription-input',
                'description',
                _('Subnet Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'subnets',
                _('Subnets')
            ) => self::makeInput(
                'form-control subnetgroupsubnets-input',
                'subnets',
                _('192.168.1.0/24, 10.1.0.0/16'),
                'text',
                'subnets',
                $subnets,
                true
            ),
            self::makeLabel(
                $labelClass,
                'group',
                _('Subnet Group -> Group Relationship')
            ) => $groupSelector
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'SUBNETGROUP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'SubnetGroup' => self::getClass('SubnetGroup')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'subnetgroup-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="subnetgroup-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Subnet Group');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Create new subnet group entry.
     *
     * @return void
     */
    public function addModal()
    {
        $subnetgroup = filter_input(INPUT_POST, 'subnetgroup');
        $description = filter_input(INPUT_POST, 'description');
        $group = filter_input(INPUT_POST, 'group');
        $groupSelector = self::getClass('GroupManager')->buildSelectBox($group);
        $subnets = filter_input(INPUT_POST, 'subnets');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'subnetgroup',
                _('Subnet Group Name')
            ) => self::makeInput(
                'form-control subnetgroupname-input',
                'subnetgroup',
                _('Subnet Group Name'),
                'text',
                'subnetgroup',
                $subnetgroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Subnet Group Description')
            ) => self::makeTextarea(
                'form-control subnetgroupdescription-input',
                'description',
                _('Subnet Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'subnets',
                _('Subnets')
            ) => self::makeInput(
                'form-control subnetgroupsubnets-input',
                'subnets',
                _('192.168.1.0/24, 10.1.0.0/16'),
                'text',
                'subnets',
                $subnets,
                true
            ),
            self::makeLabel(
                $labelClass,
                'group',
                _('Subnet Group -> Group Relationship')
            ) => $groupSelector
        ];

        self::$HookManager->processEvent(
            'SUBNETGROUP_ADD_FIELDS',
            [
                'fields' => &$fields,
                'SubnetGroup' => self::getClass('SubnetGroup')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=subnetgroup&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
    }
    /**
     * Actually create the location.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('SUBNETGROUP_ADD_POST');
        $subnetgroup = trim(
            filter_input(INPUT_POST, 'subnetgroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $group = trim(
            filter_input(INPUT_POST, 'group')
        );
        $subnets = trim(
            filter_input(INPUT_POST, 'subnets')
        );

        $subnetsMatch = '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}"
            . "(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\/(?:3[0-2]|[12]?"
            . "[0-9]))\b/';
        preg_match_all(
            $subnetsMatch,
            $subnets,
            $subnetsFound
        );

        $serverFault = false;
        try {
            $exists = self::getClass('SubnetGroupManager')
                ->exists($subnetgroup);
            if ($exists) {
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
            if ($gexists) {
                throw new Exception(
                    _('A subnet group is already using this group.')
                );
            }
            if (!count($subnetsFound[0] ?: []) > 0) {
                throw new Exception(
                    _('Please enter a valid CIDR subnet.')
                    . ' '
                    . _('Can be a comma seperated list.')
                );
            }
            $subnets = implode(', ', $subnetsFound[0]);
            $SubnetGroup = self::getClass('SubnetGroup')
                ->set('name', $subnetgroup)
                ->set('description', $description)
                ->set('groupID', $group)
                ->set('subnets', $subnets);
            if (!$SubnetGroup->save()) {
                $serverFault = true;
                throw new Exception(_('Add subnet group failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'SUBNETGROUP_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Subnet Group added!'),
                    'title' => _('Subnet Group Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SUBNETGROUP_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Subnet Group Create Fail')
                ]
            );
        }
        // header(
        //     'Location: ../management/index.php?node=subnetgroup&sub=edit&id=
        //     . $SubnetGroup->get('id')'
        // );
        self::$HookManager->processEvent(
            $hook,
            [
                'SubnetGroup' => &$SubnetGroup,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($SubnetGroup);
        echo $msg;
        exit;
    }
    /**
     * Displays the subnet group general tab.
     *
     * @return void
     */
    public function subnetgroupGeneral()
    {
        $subnetgroup = (
            filter_input(INPUT_POST, 'subnetgroup') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $group = (
            filter_input(INPUT_POST, 'group') ?:
            $this->obj->get('groupID')
        );
        $groupSelector = self::getClass('GroupManager')->buildSelectBox($group);
        $subnets = (
            filter_input(INPUT_POST, 'subnets') ?:
            $this->obj->get('subnets')
        );

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'subnetgroup',
                _('Subnet Group Name')
            ) => self::makeInput(
                'form-control subnetgroupname-input',
                'subnetgroup',
                _('Subnet Group Name'),
                'text',
                'subnetgroup',
                $subnetgroup,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Subnet Group Description')
            ) => self::makeTextarea(
                'form-control subnetgroupdescription-input',
                'description',
                _('Subnet Group Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'subnets',
                _('Subnets')
            ) => self::makeInput(
                'form-control subnetgroupsubnets-input',
                'subnets',
                _('192.168.1.0/24, 10.1.0.0/16'),
                'text',
                'subnets',
                $subnets,
                true
            ),
            self::makeLabel(
                $labelClass,
                'group',
                _('Subnet Group -> Group Relationship')
            ) => $groupSelector
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-left'
        );

        self::$HookManager->processEvent(
            'SUBNETGROUP_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'SubnetGroup' => $this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'subnetgroup-general-form',
            self::makeTabUpdateURL(
                'subnetgroup-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo $this->deleteModal();
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually update the general information.
     *
     * @return void
     */
    public function subnetgroupGeneralPost()
    {
        $subnetgroup = trim(
            filter_input(INPUT_POST, 'subnetgroup')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
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
            ->exists($subnetgroup);
        if ($subnetgroup != $this->obj->get('name')
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
            ->set('name', $subnetgroup)
            ->set('description', $description)
            ->set('groupID', $group)
            ->set('subnets', $subnets);
    }
    /**
     * Present the subnet group to edit page.
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

        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'subnetgroup-general',
            'generator' => function () {
                $this->subnetgroupGeneral();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually update the subnetgroup
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'SUBNETGROUP_EDIT_POST',
            ['SubnetGroup' => &$this->obj]
        );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'subnetgroup-general':
                $this->subnetgroupGeneralPost();
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Subnet Group update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'SUBNETGROUP_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Subnet Group updated!'),
                    'title' => _('Subnet Group Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SUBNETGROUP_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Subnet Group Update Fail')
                ]
            );
        }
        self::$HookManager->processEvent(
            $hook,
            [
                'SubnetGroup' => &$this->obj,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        echo $msg;
        exit;
    }
}
