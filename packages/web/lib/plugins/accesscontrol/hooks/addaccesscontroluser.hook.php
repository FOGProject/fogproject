<?php
/**
 * Modifies Access control Users.
 *
 * PHP version 5
 *
 * @category AddAccessControlUser
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Modifies Access control Users.
 *
 * @category AddAccessControlUser
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddAccessControlUser extends Hook
{
    public $name = 'AddAccessControlUser';
    public $description = 'Add AccessControl to Users';
    public $active = true;
    public $node = 'accesscontrol';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'PLUGINS_INJECT_TABDATA',
            [$this, 'userTabData']
        )->register(
            'USER_EDIT_SUCCESS',
            [$this, 'userAddAccessControlEdit']
        )->register(
            'USER_ADD_FIELDS',
            [$this, 'userAddAccessControlField']
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
            'name' => _('Role Association'),
            'id' => 'user-accesscontrol',
            'generator' => function () use ($obj) {
                $this->userAccesscontrol($obj);
            }
        ];
    }
    /**
     * The user access control display.
     *
     * @param object $obj The user object we're working with.
     *
     * @return void
     */
    public function userAccesscontrol($obj)
    {
        Route::listem('accesscontrolassociation');
        $items = json_decode(
            Route::getData()
        );
        $accesscontrol = 0;
        foreach ((array)$items->data as &$item) {
            if ($item->userID == $obj->get('id')) {
                $accesscontrol = $item->accesscontrolID;
                unset($item);
                break;
            }
            unset($item);
        }
        $accesscontrolID = (int)filter_input(
            INPUT_POST,
            'accesscontrol'
        ) ?: $accesscontrol;
        // User access controls
        $accesscontrolSelector = self::getClass('AccessControlManager')
            ->buildSelectBox($accesscontrolID, 'accesscontrol');
        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'accesscontrol',
                _('User Role')
            ) => $accesscontrolSelector
        ];
        $buttons = FOGPage::makeButton(
            'accesscontrol-send',
            _('Update'),
            'btn btn-primary pull-right'
        );
        self::$HookManager->processEvent(
            'USER_ACCESSCONTROL_FIELDS',
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
            'user-accesscontrol-form',
            FOGPage::makeTabUpdateURL(
                'user-accesscontrol',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }

    /**
     * The user access control updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     * @throws Exception
     */
    public function userAccesscontrolPost($obj)
    {
        $accesscontrolID = trim(
            (int)filter_input(
                INPUT_POST,
                'accesscontrol'
            )
        );
        $insert_fields = ['userID', 'accesscontrolID'];
        $insert_values = [];
        $users = [$obj->get('id')];
        if (count($users ?: []) > 0) {
            Route::deletemass(
                'accesscontrolassociation',
                ['userID' => $userID]
            );
            if ($accesscontrolID > 0) {
                foreach ((array)$users as $ind => &$userID) {
                    $insert_values[] = [$userID, $accesscontrolID];
                    unset($userID);
                }
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('AccessControlAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The user accesscontrol selector
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function userAddAccessControlEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'user') {
            return;
        }
        $obj = $arguments['User'];
        try {
            switch ($tab) {
                case 'user-accesscontrol':
                    $this->userAccesscontrolPost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'USER_EDIT_ACCESSCONTROL_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('User role updated!'),
                    'title' => _('User Role Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $arguments['hook'] = 'USER_EDIT_ACCESSCONTROL_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Role Update Fail')
                ]
            );
        }
    }
    /**
     * The user access control field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function userAddAccessControlField($arguments)
    {
        global $node;
        if ($node != 'user') {
            return;
        }
        $accesscontrolID = (int)filter_input(INPUT_POST, 'accesscontrol');
        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'accesscontrol',
                _('User Role')
            )
        ] = self::getClass('AccessControlManager')->buildSelectBox(
            $accesscontrolID,
            'accesscontrol'
        );
    }
}
