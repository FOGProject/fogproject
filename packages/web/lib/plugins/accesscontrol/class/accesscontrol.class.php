<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControl
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControl
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControl extends FOGController
{
    /**
     * The example table.
     *
     * @var string
     */
    protected $databaseTable = 'roles';
    /**
     * The database fields and commonized items.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'rID',
        'name' => 'rName',
        'description' => 'rDesc',
        'createdBy' => 'rCreatedBy',
        'createdTime' => 'rCreatedTime',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'description',
        'users',
        'usersnotinme',
        'accesscontrolrules',
        'accesscontrolrulesnotinme',
    );
    /**
     * Add user to access control.
     *
     * @param array $addArray The users to add.
     *
     * @return object
     */
    public function addUser($addArray)
    {
        return $this->addRemItem(
            'users',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove user from access control.
     *
     * @param array $removeArray The users to remove.
     *
     * @return object
     */
    public function removeUser($removeArray)
    {
        return $this->addRemItem(
            'users',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Add rule to access control.
     *
     * @param array $addArray The rules to add.
     *
     * @return object
     */
    public function addRule($addArray)
    {
        return $this->addRemItem(
            'accesscontrolrules',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove rule from access control.
     *
     * @param array $removeArray The rules to remove.
     *
     * @return object
     */
    public function removeRule($removeArray)
    {
        return $this->addRemItem(
            'accesscontrolrules',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Stores/updates the accesscontrol
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('AccessControl', 'user')
            ->assocSetter('AccessControlRule', 'accesscontrolrule')
            ->load();
    }
    /**
     * Load users
     *
     * @return void
     */
    protected function loadUsers()
    {
        $associds = self::getSubObjectIDs(
            'AccessControlAssociation',
            array('accesscontrolID' => $this->get('id')),
            'userID'
        );
        $types = array();
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            array('types' => &$types)
        );
        $userid = self::getSubObjectIDs(
            'User',
            array('type' => $types)
        );
        $associds = array_diff(
            $associds,
            $userid
        );
        unset($userid);
        $userids = self::getSubObjectIDs(
            'User',
            array('id' => $associds)
        );
        $this->set('users', (array)$userids);
    }
    /**
     * Load items not with this object
     *
     * @return void
     */
    protected function loadUsersnotinme()
    {
        $find = array('id' => $this->get('users'));
        $userids = array_diff(
            self::getSubObjectIDs('User'),
            $this->get('users')
        );
        $types = array();
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            array('types' => &$types)
        );
        $users = array();
        foreach ((array)self::getClass('UserManager')
            ->find(array('id' => $userids)) as &$User
        ) {
            if (in_array($User->get('type'), $types)) {
                continue;
            }
            $users[] = $User->get('id');
            unset($User);
        }
        unset($userids, $types);
        $this->set('usersnotinme', (array)$users);
        unset($users);
    }
    /**
     * Load rules
     *
     * @return void
     */
    protected function loadAccesscontrolrules()
    {
        $associds = self::getSubObjectIDs(
            'AccessControlRuleAssociation',
            array('accesscontrolID' => $this->get('id')),
            'accesscontrolruleID'
        );
        $ruleids = self::getSubObjectIDs(
            'AccessControlRule',
            array('id' => $associds)
        );
        $this->set('accesscontrolrules', (array)$ruleids);
    }
    /**
     * Load items not with this object
     *
     * @return void
     */
    protected function loadAccesscontrolrulesnotinme()
    {
        $rules = array_diff(
            self::getSubObjectIDs('AccessControlRule'),
            $this->get('accesscontrolrules')
        );
        $this->set('accesscontrolrulesnotinme', (array)$rules);
    }
}
