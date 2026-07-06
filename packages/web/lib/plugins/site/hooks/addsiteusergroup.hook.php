<?php
/**
 * Associate a user group to a Site.
 *
 * PHP version 7
 *
 * @category AddSiteUserGroup
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Associate a user group to a Site.
 *
 * @category AddSiteUserGroup
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteUserGroup extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteUserGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add a user group to a Site';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The plugin this hook works on.
     *
     * @return void
     */
    public $node = 'site';
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['PLUGINS_INJECT_TABDATA', 'usergroupTabData'],
            ['USERGROUP_EDIT_SUCCESS', 'usergroupAddSiteEdit'],
            ['USERGROUP_ADD_FIELDS', 'usergroupAddSiteField'],
        ]);
    }
    /**
     * The user group tab data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function usergroupTabData($arguments)
    {
        global $node;
        if ($node != 'usergroup') {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['pluginsTabData'][] = [
            'name' => _('Site Association'),
            'id' => 'usergroup-site',
            'generator' => function () use ($obj) {
                $this->usergroupSite($obj);
            }
        ];
    }
    /**
     * The user group site display
     *
     * @param object $obj The user group object we're working with.
     *
     * @return void
     */
    public function usergroupSite($obj)
    {
        Route::listem('siteusergroupassociation');
        $items = json_decode(
            Route::getData()
        );
        $site = 0;
        foreach ((array)$items->data as &$item) {
            if ($item->usergroupID == $obj->get('id')) {
                $site = $item->siteID;
                unset($item);
                break;
            }
            unset($item);
        }
        $siteID = (
            (int)filter_input(INPUT_POST, 'site') ?:
            $site
        );
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 col-form-label',
                'site',
                _('User Group Site')
            ) => $siteSelector
        ];

        $buttons = FOGPage::makeButton(
            'site-send',
            _('Update'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'USERGROUP_SITE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'UserGroup' => &$obj
            ]
        );
        $rendered = FOGPage::formFields($fields);
        unset($fields);

        echo FOGPage::makeFormTag(
            '',
            'usergroup-site-form',
            FOGPage::makeTabUpdateURL(
                'usergroup-site',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Site');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * The site updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function usergroupSitePost($obj)
    {
        self::checkAuthAndCSRF();
        $siteID = trim(
            (int)filter_input(INPUT_POST, 'site')
        );
        $insert_fields = ['usergroupID', 'siteID'];
        $insert_values = [];
        $usergroups = [$obj->get('id')];
        if (count($usergroups ?: [])) {
            Route::deletemass(
                'siteusergroupassociation',
                ['usergroupID' => $usergroups]
            );
            if ($siteID > 0) {
                foreach ((array)$usergroups as $ind => &$usergroupID) {
                    $insert_values[] = [$usergroupID, $siteID];
                    unset($usergroupID);
                }
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('SiteUserGroupAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The user group site selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function usergroupAddSiteEdit($arguments)
    {
        self::checkAuthAndCSRF();
        global $tab;
        global $node;
        if ($node != 'usergroup') {
            return;
        }
        $obj = $arguments['UserGroup'];
        try {
            switch ($tab) {
                case 'usergroup-site':
                    $this->usergroupSitePost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'USERGROUP_EDIT_SITE_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('User Group Site Updated!'),
                    'title' => _('User Group Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'USERGROUP_EDIT_SITE_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Group Site Update Fail')
                ]
            );
        }
    }
    /**
     * The user group site field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function usergroupAddSiteField($arguments)
    {
        global $node;
        if ($node != 'usergroup') {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 col-form-label',
                'site',
                _('User Group Site')
            )
        ] = $siteSelector;
    }
}
