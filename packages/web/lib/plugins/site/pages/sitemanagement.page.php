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
     * Creates new item.
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
     * Site general post element
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
        $this->renderAssocTab(
            'site-host',
            _('Site Host Associations'),
            _('Host Name'),
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
     * Edit.
     *
     * @return void
     */
    public function edit()
    {
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
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new Exception(_('Site update failed!'));
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
            'sitehostassociation',
            'siteHostAssoc',
            '`hosts`.`hostID`',
            '`siteHostAssoc`.`shaHostID`',
            '`siteHostAssoc`.`shaSiteID`',
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
            'siteuserassociation',
            'siteUserAssoc',
            '`users`.`uID`',
            '`siteUserAssoc`.`suaUserID`',
            '`siteUserAssoc`.`suaSiteID`',
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
