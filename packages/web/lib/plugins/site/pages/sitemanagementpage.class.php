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
        self::$foglang['ExportSite'] = _('Export Sites');
        self::$foglang['ImportSite'] = _('Import Sites');
        parent::__construct($this->name);
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
        echo '<h3 class="box-title">';
        echo _('Create New Site');
        echo '</h3>';
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

        // Site Host Association
        $tabData[] = [
            'name' => _('Host Association'),
            'id' => 'site-host',
            'generator' => function() {
                echo 'TODO: Make functional';
            }
        ];

        // Site User Association
        $tabData[] = [
            'name' => _('User Association'),
            'id' => 'site-user',
            'generator' => function() {
                echo 'TODO: Make functional';
            }
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
     * List the hosts which are associated to a site
     *
     * @return void
     */
    public function assocHost()
    {
        $this->data = array();
        echo '<!-- Host membership -->';
        printf(
            '<div id="%s-membership">',
            $this->node
        );
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxhost"'
            . 'class="toggle-checkboxhost"/>',
            _('Host')
        );
        $this->templates = array(
            '<input type="checkbox" name="host[]" value="${host_id}" '
            . 'class="toggle-host"/>',
            sprintf(
                '<a href="?node=%ssub=edit&id=${host_id}" '
                . 'title="%s: ${host_name}">${host_name}</a>',
                'host',
                _('Edit')
            )
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false',
            ),
            array()
        );
        foreach ((array)self::getClass('HostManager')
            ->find(
                array('id' => $this->obj->get('hostsnotinme'))
            ) as &$Host
        ) {
            $this->data[] = array(
                'host_id' => $Host->get('id'),
                'host_name' => $Host->get('name'),
            );
            unset($Host);
        }
        if (count($this->data) > 0) {
            self::$HookManager
                ->processEvent(
                    'SITE_ASSOCHOST_NOT_IN_ME',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            printf(
                '<form method="post" action="%s"><label for="hostMeShow">'
                . '<p class="c"> %s %s&nbsp;&nbsp;<input '
                . 'type="checkbox" name="hostMeShow" id="hostMeShow"/>'
                . '</p></label><div id="hostNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                _('Check here to see hosts not within this'),
                $this->node,
                _('Modify site membership for'),
                $this->obj->get('name')
            );
            $this->render();
            printf(
                '</div><br/><p class="c"><input type="submit" '
                . 'value="%s %s(s) %s %s" name="addHosts"/></p><br/>',
                _('Add'),
                _('host'),
                _('to'),
                $this->node
            );
        }
        $this->data = array();
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Host')
        );
        $this->templates = array(
            '<input type="checkbox" name="hostdel[]" value="${host_id}" '
            . 'class="toggle-action"/>',
            sprintf(
                '<a href="?node=%ssub=edit&id=${host_id}" '
                . 'title="%s: ${host_name}">${host_name}</a>',
                'host',
                _('Edit')
            )
        );
        foreach ((array)self::getClass('HostManager')
            ->find(
                array('id' => $this->obj->get('hosts'))
            ) as &$Host
        ) {
            $this->data[] = array(
                'host_id' => $Host->get('id'),
                'host_name' => $Host->get('name'),
            );
            unset($Host);
        }
        self::$HookManager
            ->processEvent(
                'SITE_ASSOCHOST_DATA',
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
        if (count($this->data)) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s %ss %s %s" name="remhost"/></p>',
                _('Delete Selected'),
                _('host'),
                _('from'),
                $this->node
            );
        }
        $this->data = array();
    }
    /**
     * Post assoc host adjustments.
     *
     * @return void
     */
    public function assocHostPost()
    {
        $this->membershipPost();
    }
    /**
     * Custom membership method.
     *
     * @return void
     */
    public function membership()
    {
        $this->data = array();
        echo '<!-- Membership -->';
        printf(
            '<div id="%s-membership">',
            $this->node
        );
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkboxuser" '
            . 'class="toggle-checkboxuser"/>',
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            sprintf(
                '<input type="checkbox" name="user[]" value="${user_id}" '
                . 'class="toggle-%s"/>',
                'user'
            ),
            sprintf(
                '<a href="?node=%s&sub=edit&id=${user_id}" '
                . 'title="Edit: ${user_name}">${user_name}</a>',
                'user'
            ),
            '${friendly}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
            ),
            array()
        );
        foreach ((array)self::getClass('UserManager')
            ->find(
                array(
                    'id' => $this->obj->get('usersnotinme'),
                )
            ) as &$User
        ) {
            $this->data[] = array(
                'user_id' => $User->get('id'),
                'user_name' => $User->get('name'),
                'friendly' => $User->get('display')
            );
            unset($User);
        }
        if (count($this->data) > 0) {
            self::$HookManager->processEvent(
                'OBJ_USERS_NOT_IN_ME',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
            printf(
                '<form method="post" action="%s"><label for="userMeShow">'
                . '<p class="c">%s %s&nbsp;&nbsp;<input '
                . 'type="checkbox" name="userMeShow" id="userMeShow"/>'
                . '</p></label><div id="userNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                _('Check here to see users not within this'),
                $this->node,
                _('Modify Membership for'),
                $this->obj->get('name')
            );
            $this->render();
            printf(
                '</div><br/><p class="c"><input type="submit" '
                . 'value="%s %s(s) %s %s" name="addUsers"/></p><br/>',
                _('Add'),
                _('user'),
                _('to'),
                $this->node
            );
        }
        $this->data = array();
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Username'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<input type="checkbox" name="userdel[]" '
            . 'value="${user_id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${user_id}" '
                . 'title="%s: ${user_name}">${user_name}</a>',
                'user',
                _('Edit')
            ),
            '${friendly}'
        );
        foreach ((array)self::getClass('UserManager')
            ->find(
                array(
                    'id' => $this->obj->get('users'),
                )
            ) as &$User
        ) {
            $this->data[] = array(
                'user_id' => $User->get('id'),
                'user_name' => $User->get('name'),
                'friendly' => $User->get('display')
            );
            unset($User);
        }
        self::$HookManager
            ->processEvent(
                'SITE_USER_DATA',
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
        if (count($this->data)) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s %ss %s %s" name="remusers"/></p>',
                _('Delete Selected'),
                _('user'),
                _('from'),
                $this->node
            );
        }
        $this->data = array();
    }
    /**
     * Customize membership actions
     *
     * @return void
     */
    public function membershipPost()
    {
        if (isset($_REQUEST['addUsers'])) {
            $this->obj->addUser($_REQUEST['user']);
        }
        if (isset($_REQUEST['remusers'])) {
            $this->obj->removeUser($_REQUEST['userdel']);
        }
        if (isset($_REQUEST['addHosts'])) {
            $this->obj->addHost($_REQUEST['host']);
        }
        if (isset($_REQUEST['remhost'])) {
            $this->obj->removeHost($_REQUEST['hostdel']);
        }
        if ($this->obj->save()) {
            self::setMessage(
                sprintf(
                    '%s %s',
                    $this->obj->get('name'),
                    _('saved successfully')
                )
            );
        }
        self::redirect($this->formAction);
    }
}
