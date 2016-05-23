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
        if ($arguments['User']->isValid()) return;
        $ldapSet = function(&$LDAP,&$index) use($username,$password,&$User) {
            if ($User->isValid()) return;
            if (!$LDAP->isValid()) return;
            if (!$LDAP->authLDAP($username,$password)) {
                $User = self::getClass('User',0);
                return;
            }
            $User = self::getClass('User')
                ->set('name',$username)
                ->set('password',$password)
                ->set('type',(int)!$LDAP->get('admin'));
            if (!$User->save()) throw new Exception(_('User create/update failed'));
            unset($LDAP,$index);
        };
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('admin'=>0));
        array_walk($LDAPs,$ldapSet);
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('admin'=>1));
        array_walk($LDAPs,$ldapSet);
        $arguments['User'] = $User;
    }
}
$LDAPPluginHook = new LDAPPluginHook();
$HookManager->register('USER_LOGGING_IN',array($LDAPPluginHook,'check_addUser'));
