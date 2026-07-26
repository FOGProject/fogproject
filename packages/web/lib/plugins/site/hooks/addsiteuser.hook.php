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
        $this->registerInstalled([
            ['PLUGINS_INJECT_TABDATA', 'userTabData'],
            ['USER_EDIT_SUCCESS', 'userAddSiteEdit'],
            ['USER_ADD_FIELDS', 'userAddSiteField'],
        ]);
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
                'col-sm-3 col-form-label',
                'site',
                _('User Site')
            ) => $siteSelector
        ];

        $buttons = FOGPage::makeButton(
            'site-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        // Create-and-associate, the same button and modal the core association
        // tabs get. Added before the *_FIELDS event so a listener can still see
        // it, and immediately after Update so Update stays the row's rightmost
        // (primary) button with this one to its left. The modal it returns is
        // echoed after the form -- see below for why it cannot go inside.
        $createModal = FOGPage::renderAssocCreate(
            'user-site',
            'site',
            $buttons,
            $obj->get('id')
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
            '',
            'user-site-form',
            FOGPage::makeTabUpdateURL(
                'user-site',
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
        // Outside the form, deliberately. The modal holds the fetched create
        // form, and a <form> inside another <form> is invalid markup: the
        // browser drops the inner one and the create would post nothing.
        echo $createModal;
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
        self::checkAuthAndCSRF();
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
        self::checkAuthAndCSRF();
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
                'col-sm-3 col-form-label',
                'site',
                _('User Site')
            )
        ] = $siteSelector;
    }
}
