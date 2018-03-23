<?php
/**
 * OU management page.
 *
 * PHP version 5
 *
 * @category OUManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * OU management page.
 *
 * @category OUManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OUManagement extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'ou';
    /**
     * Initializes the OU management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'OU Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('OU Name'),
            _('OU DN')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New OU');

        $ou = filter_input(INPUT_POST, 'ou');
        $description = filter_input(INPUT_POST, 'description');
        $oudn = filter_input(INPUT_POST, 'oudn');

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ou',
                _('OU Name')
            ) => self::makeInput(
                'form-control ouname-input',
                'ou',
                _('OU Name'),
                'text',
                'ou',
                $ou,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('OU Description')
            ) => self::makeTextarea(
                'form-control oudescription-input',
                'description',
                _('OU Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'oudn',
                _('OU DN')
            ) => self::makeInput(
                'form-control oudn-input',
                'oudn',
                'ou=computers,dc=example,dc=com',
                'text',
                'oudn',
                $oudn,
                true
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary'
        );

        self::$HookManager->processEvent(
            'OU_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'OU' => self::getClass('OU')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ou-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="ou-create">';
        echo '<div class="box-body">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Create New Site');
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
     * Actually create the ou.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('OU_ADD_POST');
        $ou = trim(
            filter_input(INPUT_POST, 'ou')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $oudn = trim(
            filter_input(INPUT_POST, 'oudn')
        );

        $serverFault = false;
        try {
            $exists = self::getClass('OUManager')
                ->exists($ou);
            if ($exists) {
                throw new Exception(
                    _('An ou already exists with this name!')
                );
            }
            $OU = self::getClass('OU')
                ->set('name', $ou)
                ->set('description', $description)
                ->set('ou', $oudn);
            if (!$OU->save()) {
                $serverFault = false;
                throw new Exception(_('Add ou failed!'));
            }
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'OU_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('OU added!'),
                    'title' => _('OU Create Succes')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'OU_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('OU Create Fail')
                ]
            );
        }
        // header(
        //     'Location: ../management/index.php?node=ou&sub=edit&id='
        //     . $OU->get('id')
        // );
        self::$HookManager->processEvent(
            $hook,
            [
                'OU' => &$OU,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ]
        );
        http_response_code($code);
        unset($OU);
        echo $msg;
        exit;
    }
    /**
     * Displays the ou general tab.
     *
     * @return void
     */
    public function ouGeneral()
    {
        $ou = (
            filter_input(INPUT_POST, 'ou') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $oudn = (
            filter_input(INPUT_POST, 'oudn') ?:
            $this->obj->get('ou')
        );

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'ou',
                _('OU Name')
            ) => self::makeInput(
                'form-control ouname-input',
                'ou',
                _('OU Name'),
                'text',
                'ou',
                $ou,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('OU Description')
            ) => self::makeTextarea(
                'form-control oudescription-input',
                'description',
                _('OU Description'),
                'description',
                $description
            ),
            self::makeLabel(
                $labelClass,
                'oudn',
                _('OU DN')
            ) => self::makeInput(
                'form-control oudn-input',
                'oudn',
                'ou=computers,dc=example,dc=com',
                'text',
                'oudn',
                $oudn,
                true
            )
        ];

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger pull-right'
        );

        self::$HookManager->processEvent(
            'OU_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'OU' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'ou-general-form',
            self::makeTabUpdateURL(
                'ou-general',
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
        echo '<div class="box-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Actually update the general information.
     *
     * @return void
     */
    public function ouGeneralPost()
    {
        $ou = trim(
            filter_input(INPUT_POST, 'ou')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $oudn = trim(
            filter_input(INPUT_POST, 'oudn')
        );

        $exists = self::getClass('OUManager')
            ->exists($ou);
        if ($ou != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(
                _('An OU already exists with this name!')
            );
        }
        $this->obj
            ->set('name', $ou)
            ->set('description', $description)
            ->set('ou', $oudn);
    }
    /**
     * Present the host membership tab.
     *
     * @return void
     */
    public function ouMembership()
    {
        global $id;
        $props = ' method="post" action="'
            . $this->formAction
            . '&tab=ou-membership" ';

        $buttons = self::makeButton(
            'membership-add',
            _('Add Selected'),
            'btn btn-primary',
            $props
        );
        $buttons .= self::makeButton(
            'membership-remove',
            _('Remove Selected'),
            'btn btn-danger',
            $props
        );

        $this->headerData = [
            _('Host Name'),
            _('Host Associated')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            []
        ];

        echo '<!-- Host Membership -->';
        echo '<div class="box-group" id="membership">';
        echo '<div class="box box-solid">';
        echo '<div class="updatemembership" class="">';
        echo '<div class="box-body">';
        $this->render(12, 'ou-membership-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Update host membership.
     *
     * @return void
     */
    public function ouMembershipPost()
    {
        if (isset($_POST['updatemembership'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membership' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membership'];
            $this->obj->addHost($membership);
        }
        if (isset($_POST['membershipdel'])) {
            $membership = filter_input_array(
                INPUT_POST,
                [
                    'membershipRemove' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $membership = $membership['membershipRemove'];
            self::getClass('OUAssociationManager')->destroy(
                [
                    'ouID' => $this->obj->get('id'),
                    'hostID' => $membership
                ]
            );
        }
    }
    /**
     * Present the ou to edit the page.
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
            'id' => 'ou-general',
            'generator' => function () {
                $this->ouGeneral();
            }
        ];

        // Hosts
        $tabData[] = [
            'name' => _('Host Association'),
            'id' => 'ou-membership',
            'generator' => function () {
                $this->ouMembership();
            }
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Actually update the ou.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager
            ->processEvent(
                'OU_EDIT_POST',
                ['OU' => &$this->obj]
            );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'ou-general':
                $this->ouGeneralPost();
                break;
            case 'ou-membership':
                $this->ouMembershipPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('OU update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'OU_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('OU updated!'),
                    'title' => _('OU Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'OU_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('OU Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'OU' => &$this->obj,
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
    /**
     * OU -> host membership list
     *
     * @return void
     */
    public function getHostsList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $hostsSqlStr = "SELECT `%s`,"
            . "IF(`oaOUID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') as `oaOUID`
            FROM `%s`
            LEFT OUTER JOIN `ouAssoc`
            ON `hosts`.`hostID` = `ouAssoc`.`oaHostID`
            %s
            %s
            %s";
        $hostsFilterStr = "SELECT COUNT(`%s`),"
            . "IF(`oaOUID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') as `oaOUID`
            FROM `%s`
            LEFT OUTER JOIN `ouAssoc`
            ON `hosts`.`hostID` = `ouAssoc`.`oaHostID`
            %s";
        $hostsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
        }
        $columns[] = [
            'db' => 'oaOUID',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'hosts',
                'hostID',
                $columns,
                $hostsSqlStr,
                $hostsFilterStr,
                $hostsTotalStr
            )
        );
        exit;
    }
    /**
     * Present the export information.
     *
     * @return void
     */
    public function export()
    {
        // The data to use for building our table.
        $this->headerData = [];
        $this->templates = [];
        $this->attributes = [];

        $obj = self::getClass('OUManager');

        foreach ($obj->getColumns() as $common => &$real) {
            if ('id' == $common) {
                continue;
            }
            $this->headerData[] = $common;
            $this->templates[] = '';
            $this->attributes[] = [];
            unset($real);
        }

        $this->title = _('Export OUs');

        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Export OUs');
        echo '</h4>';
        echo '<p class="help-block">';
        echo _('Use the selector to choose how many items you want exported.');
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<p class="help-block">';
        echo _(
            'When you click on the item you want to export, it can only select '
            . 'what is currently viewable on the screen. This includes searched '
            . 'and the current page. Please use the selector to choose the amount '
            . 'of items you would like to export.'
        );
        echo '</p>';
        $this->render(12, 'ou-export-table');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Present the export list.
     *
     * @return void
     */
    public function getExportList()
    {
        header('Content-type: application/json');
        $obj = self::getClass('OUManager');
        $table = $obj->getTable();
        $sqlstr = $obj->getQueryStr();
        $filterstr = $obj->getFilterStr();
        $totalstr = $obj->getTotalStr();
        $dbcolumns = $obj->getColumns();
        $pass_vars = $columns = [];
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );
        // Setup our columns for the CSVn.
        // Automatically removes the id column.
        foreach ($dbcolumns as $common => &$real) {
            if ('id' == $common) {
                $tableID = $real;
                continue;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        self::$HookManager->processEvent(
            'OU_EXPORT_ITEMS',
            [
                'table' => &$table,
                'sqlstr' => &$sqlstr,
                'filterstr' => &$filterstr,
                'totalstr' => &$totalstr,
                'columns' => &$columns
            ]
        );
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                $table,
                $tableID,
                $columns,
                $sqlstr,
                $filterstr,
                $totalstr
            )
        );
        exit;
    }
}
