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
        $this->name = _('Site Control Management');
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
                    'membershipHost',
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
                'class' => 'parser-false filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array('class' => 'parser-false filter-false'),
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
//                'hosts' => $Site->getHostCount()
                'hosts' => self::getClass('SiteHostAssociationManager')
                        ->count(
                            ['siteID' => $Site->id]
                        )
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
    public function membershipHost()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="togglerHost">'
            . '<input type="checkbox" name="toggle-checkbox'
            . $this->node
            . '" class="toggle-checkboxhost" id="togglerHost"/>'
            . '</label>',
            _('Host Name')
        );
        $this->templates = array(
            '<label for="host-${host_id}">'
            . '<input type="checkbox" name="host[]" class="toggle-'
            . 'host" id="host-${host_id}" '
            . 'value="${host_id}"/>'
            . '</label>',
            '<a href="?node=host&sub=edit&id=${host_id}">'
            . '${host_name}</a>'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'parser-false filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${host_name}'
            )
        );
        Route::listem('host');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->hosts;
        $getter = 'hostsnotinme';
        $returnData = function (&$item) use (&$getter) {
            $this->obj->get($getter);
            if (!in_array($item->id, (array)$this->obj->get($getter))) {
                return;
            }
            $this->data[] = array(
                'host_id' => $item->id,
                'host_name' => $item->name
            );
        };
        array_walk($items, $returnData);
        echo '<!-- Host Membership -->';
        echo '<div class="col-xs-9">';
        echo '<div class="tab-pane fade in active" id="'
            . $this->node
            . '-membershipHost">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Host Membership');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        if (count($this->data) > 0) {
            $notInMe = $meShow = 'host';
            $meShow .= 'MeShow';
            $notInMe .= 'NotInMe';
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="'
                . $meShow
                . '">';
            echo '<input type="checkbox" name="'
                . $meShow
                . '" id="'
                . $meShow
                . '"/>';
            echo _('Check here to see what hosts can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially panel panel-info" id="'
                . $notInMe
                . '">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add Hosts');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updatehosts" class="control-label col-xs-4">';
            echo _('Add selected hosts');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="addHosts" '
                . 'id="updatehosts" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
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
            '<label for="togglerHosts1">'
            . '<input type="checkbox" name="toggle-hostrm" '
            . 'class="toggle-checkboxhostrm" id="togglerHosts1"/></label>',
            _('Host Name')
        );
        $this->templates = array(
            '<label for="hostrm-${host_id}">'
            . '<input type="checkbox" name="hostdel[]" '
            . 'value="${host_id}" class="toggle-hostrm" id="'
            . 'hostrm-${host_id}"/>'
            . '</label>',
            '<a href="?node=host&sub=edit&id=${host_id}">'
            . '${host_name}</a>'
        );
        $getter = 'hosts';
        array_walk($items, $returnData);
        if (count($this->data) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Remove Hosts');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="remhosts" class="control-label col-xs-4">';
            echo _('Remove selected hosts');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="remhosts" class='
                . '"btn btn-danger btn-block" id="remhosts">'
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
     * Post assoc host adjustments.
     *
     * @return void
     */
    public function membershipHostPost()
    {
        $flags = array(
            'flags' => FILTER_REQUIRE_ARRAY
        );
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'host' => $flags,
                'hostdel' => $flags
            )
        );
        $hosts = $reqitems['host'];
        $hostsdel = $reqitems['hostdel'];
        if (isset($_POST['addHosts'])) {
            $this->obj->addHost($hosts);
        }
        if (isset($_POST['remhosts'])) {
            $this->obj->removeHost($hostsdel);
        }
        if ($this->obj->save()) {
            self::redirect($this->formAction);
        }
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
            . '" class="toggle-checkboxuser" id="toggler"/>'
            . '</label>',
            _('User Name'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="user-${user_id}">'
            . '<input type="checkbox" name="user[]" class="toggle-'
            . 'user" id="user-${user_id}" '
            . 'value="${user_id}"/>'
            . '</label>',
            '<a href="?node=user&sub=edit&id=${user_id}">'
            . '${user_name}</a>',
            '${friendly}'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'parser-false filter-false'
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
        $returnData = function (&$item) use (&$getter) {
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
            echo '<div class="text-center">';
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
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="addUsers" '
                . 'id="updateusers" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
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
            . 'class="toggle-checkboxuserrm" id="toggler1"/></label>',
            _('User Name'),
            _('Friendly Name')
        );
        $this->templates = array(
            '<label for="userrm-${user_id}">'
            . '<input type="checkbox" name="userdel[]" '
            . 'value="${user_id}" class="toggle-userrm" id="'
            . 'userrm-${user_id}"/>'
            . '</label>',
            '<a href="?node=user&sub=edit&id=${user_id}">'
            . '${user_name}</a>',
            '${friendly}'
        );
        $getter = 'users';
        array_walk($items, $returnData);
        if (count($this->data) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
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
        $flags = array(
            'flags' => FILTER_REQUIRE_ARRAY
        );
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'user' => $flags,
                'userdel' => $flags
            )
        );
        $users = $reqitems['user'];
        $usersdel = $reqitems['userdel'];
        if (isset($_POST['addUsers'])) {
            $this->obj->addUser($users);
        }
        if (isset($_POST['remusers'])) {
            $this->obj->removeUser($usersdel);
        }
        if ($this->obj->save()) {
            self::redirect($this->formAction);
        }
    }
}
