<?php
/**
 * Site management page.
 *
 * PHP version 5
 *
 * @category SiteManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Site management page.
 *
 * Originally the Site Control plugin's page by Fernando Gietz. Moved into
 * core with sites themselves, and pointed at the core tables schema step
 * 331 creates rather than the plugin tables step 332 drops.
 *
 * @category SiteManagement
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteManagement extends FOGPage
{
    /**
     * The node this page works with.
     *
     * @var string
     */
    public $node = 'site';
    /**
     * Constructor.
     *
     * @param string $name The name for the page.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Site Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Name'),
            _('Host Count'),
            _('User Count'),
            _('Group Count'),
            _('User Group Count')
        ];
        $this->attributes = [
            [],
            ['width' => 5],
            ['width' => 5],
            ['width' => 5],
            ['width' => 5]
        ];
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $site = filter_input(INPUT_POST, 'site');
        $description = filter_input(INPUT_POST, 'description');

        $labelClass = 'col-sm-3 col-form-label';

        return [
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
    }
    /**
     * Creates new item.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'site',
            _('Create New Site'),
            'SITE_ADD_FIELDS',
            'Site'
        );
    }
    /**
     * Creates new item, as a modal for create-and-associate.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'site',
            'SITE_ADD_FIELDS',
            'Site'
        );
    }
    /**
     * Add post.
     *
     * @return void
     */
    public function addPost()
    {
        $this->handleAddPost(
            'Site',
            'SITE_ADD',
            _('Site added!'),
            _('Site Create Success'),
            _('Site Create Fail'),
            function (&$serverFault) {
                $site = trim(
                    filter_input(INPUT_POST, 'site')
                );
                $description = trim(
                    filter_input(INPUT_POST, 'description')
                );
                // `sites`.`siteName` is UNIQUE, and save() builds an
                // INSERT ... ON DUPLICATE KEY UPDATE -- so without this
                // check creating a site with an existing name silently
                // OVERWRITES that site instead of failing.
                $exists = self::getClass('SiteManager')
                    ->exists($site);
                if ($exists) {
                    throw new \Exception(
                        _('A site already exists with this name!')
                    );
                }
                $Site = self::getClass('Site')
                    ->set('name', $site)
                    ->set('description', $description);
                if (!$Site->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Add site failed!'));
                }
                return $Site;
            }
        );
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

        $labelClass = 'col-sm-3 col-form-label';

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
            ),
            // A checkbox rather than a button, because the flag has two
            // directions and a button only has one. The value never reaches
            // save() either way -- makeCatchAll() writes the column in SQL,
            // for the reasons in Site::$additionalFields.
            self::makeLabel(
                $labelClass,
                'catchall',
                _('Catch-All Site')
            ) => self::makeInput(
                'catchall-input',
                'catchall',
                '',
                'checkbox',
                'catchall',
                '',
                false,
                false,
                -1,
                -1,
                $this->obj->isCatchAll() ? 'checked' : ''
            )
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
            '',
            'site-general-form',
            self::makeTabUpdateURL(
                'site-general',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        if ($this->obj->isCatchAll()) {
            // Said plainly, because the consequence is invisible from the
            // member lists: this site's members are not what grants access,
            // the flag is.
            echo '<div class="alert alert-info">'
                . _(
                    'This is the catch-all site. Its members are in scope '
                    . 'for everything, including hosts registered after '
                    . 'they joined.'
                )
                . '</div>';
        }
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
     * Site general post element.
     *
     * @return void
     */
    public function siteGeneralPost()
    {
        self::checkAuthAndCSRF();
        $site = trim(
            filter_input(INPUT_POST, 'site')
        );
        $description = trim(
            filter_input(INPUT_POST, 'description')
        );

        // Same silent-overwrite guard as addPost(); a rename onto an
        // existing name would take that site's row with it.
        $exists = self::getClass('SiteManager')
            ->exists($site);
        if ($site != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(_('A site already exists with this name!'));
        }

        $this->obj
            ->set('name', $site)
            ->set('description', $description);

        // Set through the model, which clears the incumbent in the same
        // transaction. Applied here rather than left to the save() below
        // because save() drops null-valued fields -- unticking the box
        // would otherwise report success and change nothing.
        //
        // Only written when it actually changed: makeCatchAll(true) on the
        // site that already holds the flag would clear every OTHER site's
        // and rewrite its own for no reason, and an unticked box on a site
        // that was never the catch-all would run an UPDATE per save.
        $wanted = (bool)filter_input(INPUT_POST, 'catchall');
        if ($wanted !== $this->obj->isCatchAll()) {
            $this->obj->makeCatchAll($wanted);
        }
    }
    /**
     * Presents the hosts list.
     *
     * @return void
     */
    public function siteHosts()
    {
        $this->renderAssocTab(
            'site-host',
            _('Site Host Associations'),
            _('Host Name'),
            'host',
            'btn btn-primary float-end',
            '',
            'host'
        );
    }
    /**
     * Updates site hosts.
     *
     * @return void
     */
    public function siteHostPost()
    {
        $this->assocPost('addHost', 'removeHost');
    }
    /**
     * Presents the users list.
     *
     * @return void
     */
    public function siteUsers()
    {
        $this->renderAssocTab(
            'site-user',
            _('Site User Associations'),
            _('User Name'),
            'user',
            'btn btn-primary float-end',
            '',
            'user'
        );
    }
    /**
     * Updates site users.
     *
     * @return void
     */
    public function siteUserPost()
    {
        $this->assocPost('addUser', 'removeUser');
    }
    /**
     * Presents the groups list.
     *
     * @return void
     */
    public function siteGroups()
    {
        $this->renderAssocTab(
            'site-group',
            _('Site Group Associations'),
            _('Group Name'),
            'group',
            'btn btn-primary float-end',
            '',
            'group'
        );
    }
    /**
     * Updates site groups.
     *
     * @return void
     */
    public function siteGroupPost()
    {
        $this->assocPost('addGroup', 'removeGroup');
    }
    /**
     * Presents the user groups list.
     *
     * @return void
     */
    public function siteUserGroups()
    {
        $this->renderAssocTab(
            'site-usergroup',
            _('Site User Group Associations'),
            _('User Group Name'),
            'usergroup',
            'btn btn-primary float-end',
            '',
            'usergroup',
            _('User Group')
        );
    }
    /**
     * Updates site user groups.
     *
     * @return void
     */
    public function siteUserGroupPost()
    {
        $this->assocPost('addUserGroup', 'removeUserGroup');
    }
    /**
     * Edit existing item.
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        $tabData[] = [
            'name' => _('General'),
            'id' => 'site-general',
            'generator' => function () {
                $this->siteGeneral();
            }
        ];

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
                    ],
                    [
                        'name' => _('Group Association'),
                        'id' => 'site-group',
                        'generator' => function () {
                            $this->siteGroups();
                        }
                    ],
                    [
                        'name' => _('User Group Association'),
                        'id' => 'site-usergroup',
                        'generator' => function () {
                            $this->siteUserGroups();
                        }
                    ]
                ]
            ]
        ];
        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Edit post.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'Site',
            'SITE_EDIT',
            _('Site updated!'),
            _('Site Update Success'),
            _('Site Update Fail'),
            function (&$serverFault) {
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
                    case 'site-group':
                        $this->siteGroupPost();
                        break;
                    case 'site-usergroup':
                        $this->siteUserGroupPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('Site update failed!'));
                }
            }
        );
    }
    /**
     * Gets the host list.
     *
     * @return void
     */
    public function getHostsList()
    {
        return $this->assocItemsList(
            'host',
            'sitehostmember',
            'siteHostMembers',
            '`hosts`.`hostID`',
            '`siteHostMembers`.`shmHostID`',
            '`siteHostMembers`.`shmSiteID`',
            [
                [
                    'db' => 'siteAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the user list.
     *
     * @return void
     */
    public function getUsersList()
    {
        return $this->assocItemsList(
            'user',
            'siteusermember',
            'siteUserMembers',
            '`users`.`uID`',
            '`siteUserMembers`.`sumUserID`',
            '`siteUserMembers`.`sumSiteID`',
            [
                [
                    'db' => 'siteAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the group list.
     *
     * @return void
     */
    public function getGroupsList()
    {
        return $this->assocItemsList(
            'group',
            'sitegroupmember',
            'siteGroupMembers',
            '`groups`.`groupID`',
            '`siteGroupMembers`.`sgmGroupID`',
            '`siteGroupMembers`.`sgmSiteID`',
            [
                [
                    'db' => 'siteAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the user group list.
     *
     * @return void
     */
    public function getUserGroupsList()
    {
        return $this->assocItemsList(
            'usergroup',
            'siteusergroupmember',
            'siteUserGroupMembers',
            '`userGroups`.`ugID`',
            '`siteUserGroupMembers`.`sugmUserGroupID`',
            '`siteUserGroupMembers`.`sugmSiteID`',
            [
                [
                    'db' => 'siteAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SiteManagement', 'SiteManagement');
