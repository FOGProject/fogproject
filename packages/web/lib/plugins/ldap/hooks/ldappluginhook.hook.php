<?php
class LDAPPluginHook extends Hook {
    public $name = 'LDAPPluginHook';
    public $description = 'LDAP Hook';
    public $author = 'Fernando Gietz';
    public $active = true;
    public $node = 'ldap';
    public function check_addUser($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $username = $arguments['username'];
        $password = $arguments['password'];
        if (self::getClass('User')->password_validate($username,$password)) return;
        if (self::$FOGUser->isValid()) return;
        $ldapSet = function(&$LDAP,&$index) use($username,$password) {
            if (self::$FOGUser->isValid()) return;
            if (!$LDAP->isValid()) return;
            if (!$LDAP->authLDAP($username,$password)) return;
            self::$FOGUser
                ->set('name',$username)
                ->set('password',$password)
                ->set('type',!$LDAP->get('admin'));
            if (!self::$FOGUser->save()) throw new Exception(_('User create/update failed'));
            unset($LDAP,$index);
        };
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('admin'=>1));
        array_walk($LDAPs,$ldapSet);
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('admin'=>0));
        array_walk($LDAPs,$ldapSet);
    }
}
$LDAPPluginHook = new LDAPPluginHook();
$HookManager->register('USER_LOGGING_IN',array($LDAPPluginHook,'check_addUser'));
