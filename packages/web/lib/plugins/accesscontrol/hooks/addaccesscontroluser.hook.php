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
        self::$HookManager
            ->register(
                'USER_HEADER_DATA',
                array(
                    $this,
                    'userTableHeader'
                )
            )
            ->register(
                'USER_DATA',
                array(
                    $this,
                    'userData'
                )
            )
            ->register(
                'USER_FIELDS',
                array(
                    $this,
                    'userFields'
                )
            )
            ->register(
                'USER_ADD_SUCCESS',
                array(
                    $this,
                    'userAddAccessControl'
                )
            )
            ->register(
                'USER_UPDATE_SUCCESS',
                array(
                    $this,
                    'userAddAccessControl'
                )
            )
            ->register(
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'addNotes'
                )
            );
    }
    /**
     * This function modifies the header of the user page.
     * Add one column calls 'Role'
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userTableHeader($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }
        foreach ((array)$arguments['headerData'] as $index => &$str) {
            if ($index == 5) {
                $arguments['headerData'][$index] = _('Role');
                $arguments['headerData'][] = $str;
            }
            unset($str);
        }
    }
    /**
     * This function modifies the data of the user page.
     * Add one column calls 'Role'
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userData($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }
        foreach ((array)$arguments['attributes'] as $index => &$str) {
            if ($index == 5) {
                $arguments['attributes'][$index] = array();
                $arguments['attributes'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['templates'] as $index => &$str) {
            if ($index == 5) {
                $arguments['templates'][$index] = '${role}';
                $arguments['templates'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['data'] as $index => &$vals) {
            $find = array(
                'userID' => $vals['id']
            );
            $Roles = self::getSubObjectIDs(
                'AccessControlAssociation',
                $find,
                'accesscontrolID'
            );
            $cnt = count($Roles);
            if ($cnt !== 1) {
                $arguments['data'][$index]['role'] = '';
                continue;
            }
            $RoleNames = array_values(
                array_unique(
                    array_filter(
                        self::getSubObjectIDs(
                            'AccessControl',
                            array('id' => $Roles),
                            'name'
                        )
                    )
                )
            );
            $arguments['data'][$index]['role'] = $RoleNames[0];
            unset($vals);
            unset($Roles, $RoleNames);
        }
    }
    /**
     * This function adds a new column in the result table.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userFields($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        $AccessControls = self::getSubObjectIDs(
            'AccessControlAssociation',
            array(
                'userID' => $arguments['User']->get('id')
            ),
            'accesscontrolID'
        );
        $cnt = self::getClass('AccessControlManager')->count(
            array(
                'id' => $AccessControls
            )
        );
        if ($cnt !== 1) {
            $acID = 0;
        } else {
            $AccessControls = self::getSubObjectIDs(
                'AccessControl',
                array('id' => $AccessControls)
            );
            $acID = $AccessControls[0];
        }
        self::arrayInsertAfter(
            _('User Name'),
            $arguments['fields'],
            _('User Access Control'),
            self::getClass('AccessControlManager')->buildSelectBox(
                $acID
            )
        );
    }
    /**
     * This function adds one entry in the roleUserAssoc table in the DB
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userAddAccessControl($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
        global $tab;
        $subs = array(
            'add',
            'edit',
            'addPost',
            'editPost'
        );
        if ($node != 'user') {
            return;
        }
        if (!in_array($sub, $subs)) {
            return;
        }
        self::getClass('AccessControlAssociationManager')->destroy(
            array(
                'userID' => $arguments['User']->get('id')
            )
        );
        $cnt = self::getClass('AccessControlManager')
            ->count(
                array('id' => $_REQUEST['accesscontrol'])
            );
        if ($cnt !== 1) {
            return;
        }
        $Role = new AccessControl($_REQUEST['accesscontrol']);
        self::getClass('AccessControlAssociation')
            ->set('userID', $arguments['User']->get('id'))
            ->load('userID')
            ->set('accesscontrolID', $_REQUEST['accesscontrol'])
            ->set(
                'name',
                sprintf(
                    '%s-%s',
                    $Role->get('name'),
                    $arguments['User']->get('name')
                )
            )
            ->save();
    }
    /**
     * This function adds role to notes
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function addNotes($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
        global $tab;
        if ($node != 'user') {
            return;
        }
        if (count($arguments['notes']) < 1) {
            return;
        }
        $AccessControls = self::getSubObjectIDs(
            'AccessControlAssociation',
            array(
                'userID' => $arguments['object']->get('id')
            ),
            'accesscontrolID'
        );
        $cnt = count($AccessControls);
        if ($cnt !== 1) {
            $acID = 0;
        } else {
            $AccessControls = array_values(
                array_unique(
                    array_filter(
                        self::getSubObjectIDs(
                            'AccessControl',
                            array('id' => $AccessControls),
                            'name'
                        )
                    )
                )
            );
            $acID = $AccessControls[0];
        }
        $arguments['notes'][_('Role')] = $acID;
    }
}
