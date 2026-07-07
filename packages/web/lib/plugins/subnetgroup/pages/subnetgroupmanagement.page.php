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
        $this->name = _('Subnet Group Management');
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
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $subnetgroup = filter_input(INPUT_POST, 'subnetgroup');
        $description = filter_input(INPUT_POST, 'description');
        $group = filter_input(INPUT_POST, 'group');
        $groupSelector = self::getClass('GroupManager')->buildSelectBox($group);
        $subnets = filter_input(INPUT_POST, 'subnets');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
    }
    /**
     * Create new subnet group entry.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'subnetgroup',
            _('Create New Subnet Group'),
            'SUBNETGROUP_ADD_FIELDS',
            'SubnetGroup'
        );
    }
    /**
     * Create new subnet group entry.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'subnetgroup',
            'SUBNETGROUP_ADD_FIELDS',
            'SubnetGroup'
        );
    }
    /**
     * Actually create the location.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'SubnetGroup',
            'SUBNETGROUP_ADD',
            _('Subnet Group added!'),
            _('Subnet Group Create Success'),
            _('Subnet Group Create Fail'),
            function (&$serverFault) {
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
                return $SubnetGroup;
            }
        );
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

        $labelClass = 'col-sm-3 col-form-label';

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
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
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
            '',
            'subnetgroup-general-form',
            self::makeTabUpdateURL(
                'subnetgroup-general',
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
     * Actually update the general information.
     *
     * @return void
     */
    public function subnetgroupGeneralPost()
    {
        self::checkAuthAndCSRF();
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
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'subnetgroup-general',
            'generator' => function () {
                $this->subnetgroupGeneral();
            }
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually update the subnetgroup
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'SubnetGroup',
            'SUBNETGROUP_EDIT',
            _('Subnet Group updated!'),
            _('Subnet Group Update Success'),
            _('Subnet Group Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'subnetgroup-general':
                        $this->subnetgroupGeneralPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('Subnet Group update failed!'));
                }
            }
        );
    }
}
