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
        $this->name = 'Site Control Management';
        /**
         * Add this page to the PAGES_WITH_OBJECTS hook event.
         */
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );
        /**
         * Get our $_GET['node'], $_GET['sub'], and $_GET['id']
         * in a nicer to use format.
         */
        global $node;
        global $sub;
        global $id;
        self::$foglang['ExportSite'] = _('Export Sites');
        self::$foglang['ImportSite'] = _('Import Sites');
        parent::__construct($this->name);
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                sprintf(
                    '?node=%s&sub=%s&id=%s',
                    $this->node,
                    'assocHost',
                    $id
                ) => _('Hosts Associated'),
                    "$this->delformat" => self::$foglang['Delete'],
                );
            $this->notes = array(
                _('Site') => $this->obj->get('name'),
                _('Description') => sprintf(
                    '%s',
                    $this->obj->get('description')
                ),
                _('Host Associated') => sprintf(
                    '%s',
                    count($this->obj->get('hosts'))
                )
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" checked/>',
            _('Site Name'),
            _('Site Description'),
            _('Hosts')
        );
        $this->templates = array(
            '<input type="checkbox" name="location[]" value='
            . '"${id}" class="toggle-action" checked/>',
            '<a href="?node=site&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${description}',
            '${hosts}'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array('class' => 'filter-false'),
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $Site the object to use
         *
         * @return void
         */
        self::$returnData = function (&$Site) {
            $this->data[] = array(
                'id' => $Site->id,
                'name' => $Site->name,
                'description' => $Site->description,
                'hosts' => $Site->hostcount
            );
            unset($Site);
        };
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Site');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        $name = filter_input(INPUT_POST, 'name');
        $description = filter_input(INPUT_POST, 'description');
        $fields = array(
            '<label for="site">'
            . _('Site Name')
            . '</label>' => '<div class="input-group">'
            . '<input type="text" name="name" '
            . 'value="'
            . $name
            . '" class="form-control" '
            . 'id="site" required/>',
            '<label for="description">'
            . _('Site Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea class="form-control" '
            . 'id="description" name="description">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="add">'
            . _('Create Site')
            . '</label>' => '<button type="submit" class="btn btn-info btn-block" '
            . 'id="add" name="add">'
            . _('Create')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'SITE_FIELDS',
                array(
                    'fields' => &$fields,
                    'Site' => self::getClass('Site')
                )
            );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'SITE_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
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
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('SITE_ADD_POST');
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        try {
            $exists = self::getClass('SiteManager')
                ->exists($name);
            if ($exists) {
                throw new Exception(_('A site already exists with this name!'));
            }
            $Site = self::getClass('Site')
                ->set('name', $name)
                ->set('description', $description);
            if (!$Site->save()) {
                throw new Exception(_('Add site failed!'));
            }
            $hook = 'SITE_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Site added!'),
                    'title' => _('Site Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SITE_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Site Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Site' => &$Site)
            );
        unset($Site);
        echo $msg;
        exit;
    }
    /**
     * Display site general information.
     *
     * @return void
     */
    public function siteGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $name = (
            filter_input(INPUT_POST, 'name') ?:
            $this->obj->get('name')
        );
        $description = (
            filter_input(INPUT_POST, 'description') ?:
            $this->obj->get('description')
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $fields = array(
            '<label for="name">'
            . _('Site Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control sitename-input" type="text" '
            . 'name="name" id="name" '
            . 'value="'
            . $name
            . '"/>'
            . '</div>',
            '<label for="description">'
            . _('Site Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control sitedesc-input" '
            . 'id="description">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="updategen">'
            . _('Make Changes?')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="updategen" name="update">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'SITE_FIELDS',
                array(
                    'fields' => &$fields,
                    'Site' => &$this->obj
                )
            );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'SITE_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="site-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Site General');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=site-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Edit.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->siteGeneral();
        echo '</div>';
    }
    /**
     * Edit post.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'SITE_EDIT_POST',
                array(
                    'Site' => &$this->obj
                )
            );
        global $tab;
        $name = trim(
            filter_input(INPUT_POST, 'name')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );
        try {
            switch ($tab) {
            case 'site-gen':
                if ($this->obj->get('name') != $name
                    && self::getClass('SiteManager')->exists(
                        $name,
                        $this->obj->get('id')
                    )
                ) {
                    throw new Exception(
                        _('A site alread exists with this name!')
                    );
                }
                $this->obj
                    ->set('name', $name)
                    ->set('description', $description);
                break;
            }
            if (!$this->obj->save()) {
                throw new Exception(_('Site update failed!'));
            }
            $hook = 'SITE_UPDATE_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Site Updated!'),
                    'title' => _('Site Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'SITE_UPDATE_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Site Update Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('Site' => &$this->obj)
            );
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox'
            . $this->node
            . '" class="toggle-checkbox" id="toggler"/>'
            . '</label>',
            _('User Name'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="user-${user_id}">'
            . '<input type="checkbox" name="user[]" class="toggle-'
            . 'user" id="user-${user_id}" '
            . 'value="${user_id"/>',
            '<a href="../management/index.php?node=user&sub=edit&id=${user_id}">'
            . '${user_name}</a>',
            '${friendly}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${user_name}'
            ),
            array()
        );
        Route::listem('user');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->users;
        $getter = 'usersnotinme';
        $returnData = function(&$item) use (&$getter) {
            $this->obj->get($getter);
            if (!in_array($item->id, (array)$this->obj->get($getter))) {
                return;
            }
            $this->data[] = array(
                'user_id' => $item->id,
                'user_name' => $item->name,
                'friendly' => $item->display
            );
        };
        array_walk($items, $returnData);
        echo '<!-- Membership -->';
        echo '<div class="col-xs-9">';
        echo '<div class="tab-pane fade in active" id="'
            . $this->node
            . '-membership">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->childClass
            . ' '
            . _('Membership');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        if (count($this->data) > 0) {
            $notInMe = $meShow = 'user';
            $meShow .= 'MeShow';
            $notInMe .= 'NotInMe';
            echo '<div clss="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="'
                . $meShow
                . '">';
            echo '<input type="checkbox" name="'
                . $meShow
                . '" id="'
                . $meShow
                . '"/>';
            echo _('Check here to see what users can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially panel panel-info" id="'
                . $notInMe
                . '">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Users');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updateusers" class="control-label col-xs-4">';
            echo _('Add selected users');
            echo '</label>';
            echo '<div class="col-xs8">';
            echo '<button type="submit" name="addUsers" '
                . 'id="updateusers" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates
        );
        $this->headerData = array(
            '<label for="toggler1">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler1"/></label>',
            _('User Name'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="userrm-${user_id}">'
            . '<input type="checkbox" name="userdel[]" '
            . 'value="${user_id}" class="toggle-action" id="'
            . 'userrm-${user_id}"/>'
            . '</label>',
            '<a href="../management/index.php?node=user&sub=edit&id=${user_id}">'
            . '${user_name}</a>',
            '${friendly}'
        );
        $getter = 'users';
        array_walk($items, $returnData);
        if (count($this->data) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 clas="title">';
            echo _('Remove Users');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="remusers" class="control-label col-xs-4">';
            echo _('Remove selected users');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="remusers" class='
                . '"btn btn-danger btn-block" id="remusers">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
