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
        self::$foglang['ExportSubnetgroup'] = _('Export Subnet Groups');
        self::$foglang['ImportSubnetgroup'] = _('Import Subnet Groups');
        parent::__construct($this->name);
        $this->headerData = [
            _('Subnet Group Name'),
            _('Subnet Grouped Group Name')
        ];
        $this->attributes = [
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
        echo '<div class="box-footer">';
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
}
