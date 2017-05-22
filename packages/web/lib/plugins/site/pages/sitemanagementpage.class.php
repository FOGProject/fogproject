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
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'c filter-false'),
        );
        self::$returnData = function (&$Site) {
            if (!$Site->isValid()) {
                return;
            }
            $this->obj->loadHosts($Site->get('id'));
            $this->data[] = array(
                'id' => $Site->get('id'),
                'name' => $Site->get('name'),
                'description' => $Site->get('description'),
                'hosts' => sprintf(
                    '%s',
                    count($this->obj->get('hosts'))
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
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );

        $fields = array(
            _('Site Name') => '<input class="smaller" type="text" name="name"/>',
            _('Site Description') => sprintf(
                '<textarea name="description">%s</textarea>',
                $_REQUEST['description']
            ),
            '&nbsp;' => sprintf(
                '<input name="add" class="smaller" type="submit" value="%s"/>',
                _('Add')
            ),
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

        printf('<form method="post" action="%s">', $this->formAction);
        $this->render();
        echo '</form>';
    }
    /**
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $name = trim($_REQUEST['name']);
            $exists = self::getClass('SiteManager')
                ->exists(trim($name));
            if ($exists) {
                throw new Exception(_('Site already Exists, please try again.'));
            }
            if (!$name) {
                throw new Exception(_('Please enter a name for this site.'));
            }

            $description = $_REQUEST['description'];
            $Site = self::getClass('Site')
                ->set('name', $name)
                ->set('description', $description);
            if (!$Site->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Site Added, editing!'));
            self::redirect(
                sprintf(
                    '?node=site&sub=edit&id=%s',
                    $Site->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
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
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Site Name') => sprintf(
                '<input class="smaller" type="text" name="name" value="%s"/>',
                (
                    $_REQUEST['name'] ?
                    $_REQUEST['name'] :
                    $this->obj->get('name')
                )
            ),
            _('Site Description') => '<textarea name="description">'
            . (
                $_REQUEST['description'] ?
                $_REQUEST['description'] :
                $this->obj->get('description')
            )
            . '</textarea>',
            '&nbsp;' => sprintf(
                '<input name="update" class="smaller" type="submit" value="%s"/>',
                _('Update')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
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
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        echo '</form>';
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
        try {
            if ($_REQUEST['name'] != $this->obj->get('name')
                && $this->obj->getManager()->exists($_REQUEST['name'])
            ) {
                throw new Exception(_('A site with that name already exists.'));
            }
            if (isset($_REQUEST['update'])) {
                $description = $_REQUEST['description'];
                $this->obj
                    ->set('name', $_REQUEST['name'])
                    ->set('description', $_REQUEST['description']);
                if (!$this->obj->save()) {
                    throw new Exception(_('Failed to update'));
                }
                self::setMessage(_('Site Updated'));
                self::redirect(
                    sprintf(
                        '?node=site&sub=edit&id=%d',
                        $this->obj->get('id')
                    )
                );
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
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
                'class' => 'l filter-false',
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
                'class' => 'l filter-false'
            ),
            array(
                'class' => 'l'
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
