<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteManagementPage
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteManagementPage
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteManagementPage extends FOGPage
{
    public $node = 'site';
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
        $this->name = 'Site Management';
        /**
         * Add this page to the PAGES_WITH_OBJECTS hook event.
         */
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            ['PagesWithObjects' => &$this->PagesWithObjects]
        );
        parent::__construct($this->name);
        self::$foglang['ExportSite'] = _('Export Sites');
        self::$foglang['ImportSite'] = _('Import Sites');
        $this->headerData = [
            _('Name'),
            _('Host Count'),
            _('User Count')
        ];
        $this->templates = [
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            ['width' => 5],
            ['width' => 5]
        ];
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('Create New Site');
        // Check all the post fields if they've already been set.
        $site = filter_input(INPUT_POST, 'site');
        $description = filter_input(INPUT_POST, 'description');

        // The fields to display
        $fields = [
            '<label class="col-sm-2 control-label" for="site">'
            . _('Site Name')
            . '</label>' => '<input type="text" name="site" '
            . 'value="'
            . $site
            . '" class="sitename-input form-control" '
            . 'id="site" required/>',
            '<label class="col-sm-2 control-label" for="description">'
            . _('Site Description')
            . '</label>' => '<textarea class="form-control" style="resize:vertical;'
            . 'min-height: 50px;" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>'
        ];
        self::$HookManager
            ->processEvent(
                'SITE_ADD_FIELDS',
                [
                    'fields' => &$fields,
                    'Site' => self::getClass('Site')
                ]
            );
        $rendered = self::formFields($fields);
        unset($fields);
        echo '<div class="box box-solid" id="site-create">';
        echo '<form id="site-create-form" class="form-horizontal" method="post" action="'
            . $this->formAction
            . '" novalidate>';
        echo '<div class="box-body">';
        echo '<!-- Site General -->';
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
        echo '<button class="btn btn-primary" id="send">'
            . _('Create')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('SITE_ADD_POST');
        $site = trim(
            filter_input(INPUT_POST, 'site')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        $serverFault = false;
        try{
            if (!$site) {
                throw new Exception(
                    _('A site name is required!')
                );
            }
            if (self::getClass('SiteManager')->exists($site)) {
                throw new Exception(
                    _('A site already exists with this name!')
                );
            }
            $Site = self::getClass('Site')
                ->set('name', $site)
                ->set('description', $description);
            if (!$Site->save()) {
                $serverFault = true;
                throw new Exception(_('Add site failed!'));
            }
            $code = 201;
            $hook = 'SITE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Site added!'),
                    'title' => _('Site Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500: 400);
            $hook = 'SITE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Site Create Fail')
                ]
            );
        }
        // header('Location: ../management/index.php?node=site&sub=edit&id=' . $Site->get('id'));
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'Site' => &$Site,
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        http_response_code($code);
        unset($Site);
        echo $msg;
        exit;
    }
    /**
     * Displays the site general tab.
     *
     * @return void
     */
    public function siteGeneral()
    {
        $site = (
            filter_input(INPUT_POST, 'site') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $fields = [
            '<label for="site" class="col-sm-2 control-label">'
            . _('Site Name')
            . '</label>' => '<input id="site" class="form-control" placeholder="'
            . _('Site Name')
            . '" type="text" value="'
            . $site
            . '" name="site" required/>',
            '<label for="description" class="col-sm-2 control-label">'
            . _('Site Description')
            . '</label>' => '<textarea style="resize:vertical;'
            . 'min-height:50px;" id="description" name="description" class="form-control">'
            . $description
            . '</textarea>'
        ];
        self::$HookManager->processEvent(
            'SITE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'Site' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<form id="site-general-form" class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('site-general', $this->obj->get('id'))
            . '" novalidate>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="general-send">'
            . _('Update')
            . '</button>';
        echo '<button class="btn btn-danger pull-right" id="general-delete">'
            . _('Delete')
            . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }
    /**
     * Site general post element
     *
     * @return void
     */
    public function siteGeneralPost()
    {
        $site = trim(
            filter_input(INPUT_POST, 'site')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        if ($site != $this->obj->get('name')) {
            if ($this->obj->getManager()->exists($site)) {
                throw new Exception(_('A site already exists with this name!'));
            }
        }
        $this->obj
            ->set('name', $site)
            ->set('description', $description);
    }
    /**
     * Presents the hosts list.
     *
     * @return void
     */
    public function siteHosts()
    {
        $this->headerData = [
            _('Host Name'),
            _('Associated')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $buttons = self::makeButton(
            'site-host-send',
            _('Add selected'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'site-host-remove',
            _('Remove selected'),
            'btn btn-danger'
        );
        echo self::makeFormTag(
            'form-horizontal',
            'site-host-form',
            self::makeTabUpdateURL(
                'site-host',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Site Host Associations');
        echo '</h4>';
        echo '<p class="help-block">';
        echo 'TODO: Make jQuery Functional';
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'site-host-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates site hosts.
     *
     * @return void
     */
    public function siteHostPost()
    {
        throw new Exception('TODO: Make Functional');
    }
    /**
     * Presents the users list.
     *
     * @return void
     */
    public function siteUsers()
    {
        $this->headerData = [
            _('User Name'),
            _('Associated')
        ];
        $this->templates = [
            '',
            ''
        ];
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $buttons = self::makeButton(
            'site-user-send',
            _('Add selected'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'site-user-remove',
            _('Remove selected'),
            'btn btn-danger'
        );
        echo self::makeFormTag(
            'form-horizontal',
            'site-user-form',
            self::makeTabUpdateURL(
                'site-user',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Site User Associations');
        echo '</h4>';
        echo '<p class="help-block">';
        echo 'TODO: Make jQuery Functional';
        echo '</p>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'site-user-table', $buttons);
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Updates site users.
     *
     * @return void
     */
    public function siteUserPost()
    {
        throw new Exception('TODO: Make Functional');
    }
    /**
     * Edit.
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
            'id' => 'site-general',
            'generator' => function() {
                $this->siteGeneral();
            }
        ];

        // Associations
        $tabData[] = [
            'tabs' => [
                'name' => _('Associations'),
                'tabData' => [
                    [
                        'name' => _('Host Association'),
                        'id' => 'site-host',
                        'generator' => function() {
                            $this->siteHosts();
                        }
                    ],
                    [
                        'name' => _('User Association'),
                        'id' => 'site-user',
                        'generator' => function() {
                            $this->siteUsers();
                        }
                    ]
                ]
            ]
        ];

        echo self::tabFields($tabData, $this->obj);
    }
    /**
     * Edit post.
     *
     * @return void
     */
    public function editPost()
    {
        header('Content-type: application/json');
        self::$HookManager
            ->processEvent(
                'SITE_EDIT_POST',
                ['Site' => &$this->obj]
            );
        $serverFault = false;
        try {
            global $tab;
            switch ($tab) {
            case 'site-general':
                $this->siteGeneralPost();
                break;
            case 'site-host':
                $this->siteHostPost();
                break;
            case 'site-user':
                $this->siteUserPost();
                break;
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Site update failed!'));
            }
            $code = 201;
            $hook = 'SITE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Site updated!'),
                    'title' => _('Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'SITE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Site Update Fail')
                ]
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'Site' => &$this->obj,
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
     * Gets the host list.
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
            . "IF(`shaSiteID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') as `shaSiteID`
            FROM `%s`
            CROSS JOIN `site`
            LEFT OUTER JOIN `siteHostAssoc`
            ON `hosts`.`hostID` = `siteHostAssoc`.`shaHostID`
            AND `site`.`sID` = `siteHostAssoc`.`shaSiteID`
            %s
            %s
            %s";
        $hostsFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `site`
            LEFT OUTER JOIN `siteHostAssoc`
            ON `hosts`.`hostID` = `siteHostAssoc`.`shaHostID`
            AND `site`.`sID` = `siteHostAssoc`.`shaSiteID`
            %s";
        $hostsTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            if ('id' == $common) {
                $tableID = $real;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'shaSiteID',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                'hosts',
                $tableID,
                $columns,
                $hostsSqlStr,
                $hostsFilterStr,
                $hostsTotalStr
            )
        );
        exit;
    }
    /**
     * Gets the user list.
     *
     * @return void
     */
    public function getUsersList()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $usersSqlStr = "SELECT `%s`,"
            . "IF(`suaSiteID` = '"
            . $this->obj->get('id')
            . "','associated','dissociated') as `suaSiteID`
            FROM `%s`
            CROSS JOIN `site`
            LEFT OUTER JOIN `siteUserAssoc`
            ON `users`.`uID` = `siteUserAssoc`.`suaUserID`
            AND `site`.`sID` = `siteUserAssoc`.`suaSiteID`
            %s
            %s
            %s";
        $usersFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `site`
            LEFT OUTER JOIN `siteUserAssoc`
            ON `users`.`uID` = `siteUserAssoc`.`suaUserID`
            AND `site`.`sID` = `siteUserAssoc`.`suaSiteID`
            %s";
        $usersTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";

        foreach (self::getClass('UserManager')
            ->getColumns() as $common => &$real
        ) {
            if ('id' == $common) {
                $tableID = $real;
            }
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        $columns[] = [
            'db' => 'suaSiteID',
            'dt' => 'association'
        ];
        echo json_encode(
            FOGManagerController::simple(
                $pass_vars,
                'users',
                $tableID,
                $columns,
                $usersSqlStr,
                $usersFilterStr,
                $usersTotalStr
            )
        );
        exit;
    }
}
