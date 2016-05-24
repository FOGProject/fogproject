<?php
class LDAPPluginHook extends Hook {
    public $name = 'LDAPPluginHook';
    public $description = 'LDAP Hook';
    public $author = 'Fernando Gietz';
    public $active = true;
    public $node = 'ldap';
    public function check_addUser($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (self::$FOGUser->isValid()) return;
        $username = $arguments['username'];
        $password = $arguments['password'];
        $ldapSet = function(&$LDAP,&$index) use($username,$password,&$currentUser) {
            if (self::$FOGUser->isValid()) return;
            if (!$LDAP->isValid()) return;
            if (!$LDAP->authLDAP($username,$password)) return;
            self::$FOGUser
                ->set('id',0)
                ->set('name',$username)
                ->set('name',$username)
                ->set('password',$password)
                ->set('type',(int)!$LDAP->get('admin'));
            if (!self::$FOGUser->save()) throw new Exception(_('User create/update failed'));
            unset($LDAP,$index);
        };
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('admin'=>1));
        array_walk($LDAPs,$ldapSet);
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('admin'=>0));
        array_walk($LDAPs,$ldapSet);
        self::$FOGCore->attemptLogin($username,$password);
    }
}
$LDAPPluginHook = new LDAPPluginHook();
$HookManager->register('USER_LOGGING_IN',array($LDAPPluginHook,'check_addUser'));
