<?php
/**
 * LDAPPluginHook enables our checks as required
 *
 * PHP version 5
 *
 * @category LDAPPluginHook
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * LDAPPluginHook enables our checks as required
 *
 * @category LDAPPluginHook
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPPluginHook extends Hook
{
    const LDAP_TYPES = ['990', '991'];
    const LDAP_ADMIN = '990';
    const LDAP_MOBILE = '991';
    /**
     * The user types to filter
     *
     * @var array
     */
    private static $_userTypes = [];
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'LDAPPluginHook';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'LDAP Hook';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to enact upon.
     *
     * @var string
     */
    public $node = 'ldap';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, self::$pluginsinstalled)) {
            return;
        }
        $userFilterTypes = self::getSetting('FOG_PLUGIN_LDAP_USER_FILTER');
        $types = explode(',', $userFilterTypes);
        foreach ($types as &$type) {
            $type = trim($type);
            if (!$type) {
                continue;
            }
            self::$_userTypes[] = $type;
            unset($type);
        }
        if (count(self::$_userTypes) < 1) {
            self::$_userTypes = self::LDAP_TYPES;
        }
        self::$HookManager->register(
            'USER_LOGGING_IN',
            [$this, 'checkAddUser']
        )->register(
            'USER_TYPE_HOOK',
            [$this, 'setLdapType']
        )->register(
            'USER_TYPES_FILTER',
            [$this, 'setTypeFilter']
        )->register(
            'USER_TYPE_VALID',
            [$this, 'isLdapType']
        );
    }
    /**
     * Checks and creates users if they're valid
     *
     * @param mixed $arguments the item to adjust
     *
     * @throws Exception
     * @return void
     */
    public function checkAddUser($arguments)
    {
        $user = trim($arguments['username']);
        $pass = trim($arguments['password']);
        $ldapTypes = self::$_userTypes;
        /**
         * Check the user and validate the type is not
         * our ldap inserted items. If not return as the
         * user is already allowed.
         */
        $tmpUser = $arguments['user']
            ->set('name', $user)
            ->load('name');
        if ($tmpUser->isValid()) {
            $ldapType = $tmpUser->get('type');
            if (!in_array($ldapType, $ldapTypes)) {
                return;
            }
        }
        /**
         * Create our new user (initially at least)
         */
        Route::listem('ldap');
        $items = json_decode(
            Route::getData()
        );
        foreach ($items->data as &$ldap) {
            $access = self::getClass('LDAP', $ldap->id)->authLDAP($user, $pass);
            if ($access) {
                $displayName = self::getClass('LDAP', $ldap->id)
                    ->getDisplayName($user, $pass);
            }
            unset($ldap);
            switch ($access) {
                case 2:
                    // This is an admin account, break the loop
                    $tmpUser
                        ->set('name', $user)
                        ->set('password', $pass)
                        ->set('display', $displayName)
                        ->set('type', self::LDAP_ADMIN)
                        ->save();
                    break 2;
                case 1:
                    // This is an unprivileged user account.
                    $tmpUser
                        ->set('name', $user)
                        ->set('password', $pass)
                        ->set('display', $displayName)
                        ->set('type', self::LDAP_MOBILE)
                        ->save();
                    break;
                default:
                    $tmpUser = new User(-1);
            }
            unset($ldap);
        }
        $arguments['user'] = $tmpUser;
    }
    /**
     * Sets our ldap types
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function setLdapType($arguments)
    {
        if ($arguments['type'] == self::LDAP_ADMIN) {
            $arguments['type'] = 0;
        } elseif ($arguments['type'] == self::LDAP_MOBILE) {
            $arguments['type'] = 1;
        }
    }
    /**
     * Sets our user type to filter from user list
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function setTypeFilter($arguments)
    {
        $arguments['types'] = self::$_userTypes;
    }
    /**
     * Tests if the user is containing the ldap types.
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function isLdapType($arguments)
    {
        $types = self::$_userTypes;
        if (in_array($arguments['type'], $types)) {
            $arguments['typeIsValid'] = false;
        }
    }
}
