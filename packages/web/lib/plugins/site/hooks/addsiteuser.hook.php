<?php
/**
 * Associate user to a Site.
 *
 * PHP version 7
 *
 * @category AddSiteUser
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Associate user to a Site.
 *
 * @category AddSiteUser
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteUser extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteUser';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add users to a Site';
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'PLUGINS_INJECT_TABDATA',
            [$this, 'userTabData']
        )->register(
            'USER_EDIT_SUCCESS',
            [$this, 'userAddSiteEdit']
        )->register(
            'USER_ADD_FIELDS',
            [$this, 'userAddSiteField']
        );
    }
    /**
     * The user tab data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function userTabData($arguments)
    {
        global $node;
        if ($node != 'user') {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['pluginsTabData'][] = [
            'name' => _('Site Association'),
            'id' => 'user-site',
            'generator' => function () use ($obj) {
                $this->userSite($obj);
            }
        ];
    }
    /**
     * The user site display
     *
     * @param object $obj The user object we're working with.
     *
     * @return void
     */
    public function userSite($obj)
    {
        Route::listem('siteuserassociation');
        $items = json_decode(
            Route::getData()
        );
        $site = 0;
        foreach ((array)$items->data as &$item) {
            if ($item->userID == $obj->get('id')) {
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
                'col-sm-3 control-label',
                'site',
                _('User Site')
            ) => $siteSelector
        ];

        $buttons = FOGPage::makeButton(
            'site-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'USER_SITE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$obj
            ]
        );
        $rendered = FOGPage::formFields($fields);
        unset($fields);

        echo FOGPage::makeFormTag(
            'form-horizontal',
            'user-site-form',
            FOGPage::makeTabUpdateURL(
                'user-site',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Site');
        echo '</h4>';
        echo '</div>';
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
     * The site updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function userSitePost($obj)
    {
        $siteID = trim(
            (int)filter_input(INPUT_POST, 'site')
        );
        $insert_fields = ['userID', 'siteID'];
        $insert_values = [];
        $users = [$obj->get('id')];
        if (count($users ?: [])) {
            Route::deletemass(
                'siteuserassociation',
                ['userID' => $users]
            );
            if ($siteID > 0) {
                foreach ((array)$users as $ind => &$userID) {
                    $insert_values[] = [$userID, $siteID];
                    unset($userID);
                }
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('SiteUserAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The user site selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function userAddSiteEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'user') {
            return;
        }
        $obj = $arguments['User'];
        try {
            switch ($tab) {
                case 'user-site':
                    $this->userSitePost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'USER_EDIT_SITE_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('User Site Updated!'),
                    'title' => _('User Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'USER_EDIT_SITE_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Site Update Fail')
                ]
            );
        }
    }
    /**
     * The user site field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function userAddSiteField($arguments)
    {
        global $node;
        if ($node != 'user') {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'site',
                _('User Site')
            )
        ] = $siteSelector;
    }
}
