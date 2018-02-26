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
        self::$HookManager
            ->register(
                'TABDATA_HOOK',
                array(
                    $this,
                    'userTabData'
                )
            )
            ->register(
                'USER_EDIT_SUCCESS',
                array(
                    $this,
                    'userAddSiteEdit'
                )
            )
            ->register(
                'USER_ADD_FIELDS',
                array(
                    $this,
                    'userAddSiteField'
                )
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        $obj = $arguments['User'];
        $arguments['tabData'][] = [
            'name' => _('Site Association'),
            'id' => 'user-site',
            'generator' => function() use ($obj) {
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
        $siteID = (int)filter_input(
            INPUT_POST,
            'site'
        ) ?: $site;
        // User sites
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');
        $fields = [
            '<label for="site" class="col-sm-2 control-label">'
            . _('User Site')
            . '</label>' => &$siteSelector
        ];
        self::$HookManager
            ->processEvent(
                'USER_SITE_FIELDS',
                [
                    'fields' => &$fields,
                    'User' => &$obj
                ]
            );
        $rendered = FOGPage::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo '<form id="user-site-form" class="form-horizontal" method="post" action="'
            . FOGPage::makeTabUpdateURL('user-site', $obj->get('id'))
            . '" novalidate>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="site-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
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
            (int)filter_input(
                INPUT_POST,
                'site'
            )
        );
        $Site = new Site($siteID);
        if (!$Site->isValid() && is_numeric($siteID)) {
            throw new Exception(_('Select a valid site'));
        }
        $insert_fields = ['userID', 'siteID'];
        $insert_values = [];
        $users = [$obj->get('id')];
        if (count($users) > 0) {
            self::getClass('SiteUserAssociationManager')->destroy(
                ['userID' => $users]
            );
            foreach ((array)$users as $ind => &$userID) {
                $insert_values[] = [$userID, $siteID];
                unset($userID);
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        $obj = $arguments['User'];
        try {
            switch ($tab) {
            case 'user-site':
                $this->userSitePost($obj);
                break;
            }
            $arguments['code'] = 201;
            $argumetns['hook'] = 'USER_EDIT_SITE_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('User Site Updated!'),
                    'title' => _('User Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = 400;
            $arguments['hook'] = 'USER_EDIT_SITE_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Update Site Fail')
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $arguments['fields'][
            '<label for="site" class="col-sm-2 control-label">'
            . _('User Site')
            . '</label>'] = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');
    }
}
