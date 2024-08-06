<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteManagement extends FOGPage
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
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Host Count'),
            _('User Count')
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

        $site = filter_input(INPUT_POST, 'site');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'site',
                _('Site Name')
            ) => self::makeInput(
                'form-control sitename-input',
                'site',
                _('Site Name'),
                'text',
                'site',
                $site,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Site Description')
            ) => self::makeTextarea(
                'form-control sitedescription-input',
                'description',
                _('Site Description'),
                'description',
                $description
            )
        ];

        $buttons = self::makeButton(
            'send',
            _('Create'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'SITE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Site' => self::getClass('Site')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'site-create-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="site-create">';
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
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function addModal()
    {
        $site = filter_input(INPUT_POST, 'site');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'site',
                _('Site Name')
            ) => self::makeInput(
                'form-control sitename-input',
                'site',
                _('Site Name'),
                'text',
                'site',
                $site,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Site Description')
            ) => self::makeTextarea(
                'form-control sitedescription-input',
                'description',
                _('Site Description'),
                'description',
                $description
            )
        ];

        self::$HookManager->processEvent(
            'SITE_ADD_FIELDS',
            [
                'fields' => &$fields,
                'Site' => self::getClass('Site')
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'create-form',
            '../management/index.php?node=site&sub=add',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo $rendered;
        echo '</form>';
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
        try {
            $exists = self::getClass('SiteManager')
                ->exists($site);
            if ($exists) {
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
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'SITE_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Site added!'),
                    'title' => _('Site Create Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SITE_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Site Create Fail')
                ]
            );
        }
        // header(
        //     'Location: ../management/index.php?node=site&sub=edit&id='
        //     . $Site->get('id')
        // );
        self::$HookManager->processEvent(
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

        $labelClass = 'col-sm-3 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'site',
                _('Site Name')
            ) => self::makeInput(
                'form-control sitename-input',
                'site',
                _('Site Name'),
                'text',
                'site',
                $site,
                true
            ),
            self::makeLabel(
                $labelClass,
                'description',
                _('Site Description')
            ) => self::makeTextarea(
                'form-control sitedescription-input',
                'description',
                _('Site Description'),
                'description',
                $description
            )
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
            'SITE_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Site' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'site-general-form',
            self::makeTabUpdateURL(
                'site-general',
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

        $exists = self::getClass('SiteManager')
            ->exists($site);
        if ($site != $this->obj->get('name')
            && $exists
        ) {
            throw new Exception(_('A site already exists with this name!'));
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
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'site-host',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'site-host-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'site-host-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Site Host Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'site-host-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('host');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates site hosts.
     *
     * @return void
     */
    public function siteHostPost()
    {
        if (isset($_POST['confirmadd'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['additems'];
            if (count($hosts ?: []) > 0) {
                $this->obj->addHost($hosts);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $hosts = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $hosts = $hosts['remitems'];
            if (count($hosts ?: []) > 0) {
                $this->obj->removeHost($hosts);
            }
        }
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
        $this->attributes = [
            [],
            ['width' => 16]
        ];
        $props = ' method="post" action="'
            . self::makeTabUpdateURL(
                'site-user',
                $this->obj->get('id')
            )
            . '" ';

        $buttons = self::makeButton(
            'site-user-send',
            _('Add selected'),
            'btn btn-primary pull-right',
            $props
        );
        $buttons .= self::makeButton(
            'site-user-remove',
            _('Remove selected'),
            'btn btn-danger pull-left',
            $props
        );

        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Site User Associations');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'site-user-table', $buttons);
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $this->assocDelModal('user');
        echo '</div>';
        echo '</div>';
    }
    /**
     * Updates site users.
     *
     * @return void
     */
    public function siteUserPost()
    {
        if (isset($_POST['confirmadd'])) {
            $users = filter_input_array(
                INPUT_POST,
                [
                    'additems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $users = $users['additems'];
            if (count($users ?: []) > 0) {
                $this->obj->addUser($users);
            }
        }
        if (isset($_POST['confirmdel'])) {
            $users = filter_input_array(
                INPUT_POST,
                [
                    'remitems' => [
                        'flags' => FILTER_REQUIRE_ARRAY
                    ]
                ]
            );
            $users = $users['remitems'];
            if (count($users ?: []) > 0) {
                $this->obj->removeUser($users);
            }
        }
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
            'generator' => function () {
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
                        'generator' => function () {
                            $this->siteHosts();
                        }
                    ],
                    [
                        'name' => _('User Association'),
                        'id' => 'site-user',
                        'generator' => function () {
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
        self::$HookManager->processEvent(
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
            }
            if (!$this->obj->save()) {
                $serverFault = true;
                throw new Exception(_('Site update failed!'));
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'SITE_EDIT_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Site updated!'),
                    'title' => _('Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'SITE_EDIT_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Site Update Fail')
                ]
            );
        }
        
        self::$HookManager->processEvent(
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
        $join = [
            'LEFT OUTER JOIN `siteHostAssoc` ON '
            . "`hosts`.`hostID` = `siteHostAssoc`.`shaHostID` "
            . "AND `siteHostAssoc`.`shaSiteID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'siteAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'host',
            'sitehostassociation',
            $join,
            '',
            $columns
        );
    }
    /**
     * Gets the user list.
     *
     * @return void
     */
    public function getUsersList()
    {
        $join = [
            'LEFT OUTER JOIN `siteUserAssoc` ON '
            . "`users`.`uID` = `siteUserAssoc`.`suaUserID` "
            . "AND `siteUserAssoc`.`suaSiteID` = '" . $this->obj->get('id') . "'"
        ];
        $columns[] = [
            'db' => 'siteAssoc',
            'dt' => 'association',
            'removeFromQuery' => true
        ];
        return $this->obj->getItemsList(
            'user',
            'siteuserassociation',
            $join,
            '',
            $columns
        );
    }
}
