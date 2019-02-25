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
		$subnetsMatch = "/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))"
            . "(( )*,( )*([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))+)"
            . "*$/";

        $serverFault = false;
        try{
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
            if (!preg_match($subnetsMatch, $subnets)) {
                throw new Exception(
                    _('Please enter a valid CIDR subnet.')
                    . ' '
                    . _('Can be a comma seperated list.')
                );
            }
            $subnets = preg_replace('/\s+/','', $subnets);
            $subnets = str_replace(',', ', ', $subnets);
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
}
