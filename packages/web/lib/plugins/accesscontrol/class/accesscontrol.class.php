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
    protected $databaseFields = [
        'id' => 'rID',
        'name' => 'rName',
        'description' => 'rDesc',
        'createdBy' => 'rCreatedBy',
        'createdTime' => 'rCreatedTime'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'description',
        'users',
        'accesscontrolrules'
    ];
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
        $find = ['accesscontrolID' => $this->get('id')];
        Route::ids(
            'accesscontrolassociation',
            $find,
            'userID'
        );
        $accesscontrols = json_decode(
            Route::getData(),
            true
        );
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        $find = ['types' => $types];
        Route::ids(
            'user',
            $find
        );
        $userid = json_decode(
            Route::getData(),
            true
        );
        $associds = array_diff(
            $accesscontrols,
            $userid
        );
        unset($userid);
        $this->set('users', (array)$associds);
    }
    /**
     * Load rules
     *
     * @return void
     */
    protected function loadAccesscontrolrules()
    {
        $find = ['accesscontrolID' => $this->get('id')];
        Route::ids(
            'accesscontrolruleassociation',
            $find,
            'accesscontrolruleID'
        );
        $ruleIDs = json_decode(
            Route::getData(),
            true
        );
        $this->set('accesscontrolrules', (array)$ruleIDs);
    }
}
