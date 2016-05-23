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
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('type'=>0));
        $User = self::getClass('User',0);
        $ldapSet = function(&$LDAP,&$index) use($username,$password,&$User) {
            if ($User->isValid()) return false;
            if (!$LDAP->isValid()) return false;
            if (!$LDAP->authLDAP($username,$password)) {
                $User = self::getClass('User',0);
                return false;
            }
            $User = self::getClass('User')
                ->set('name',$username)
                ->set('password',$password)
                ->set('type',(int)!$LDAP->get('admin'));
            if (!$User->save()) throw new Exception(_('User create/update failed'));
            unset($LDAP,$index);
        };
        array_walk($LDAPs,$ldapSet);
        $LDAPs = (array)self::getClass('LDAPManager')->find(array('type'=>1));
        array_walk($LDAPs,$ldapSet);
        $arguments['User'] = $User->load();
    }
}
$LDAPPluginHook = new LDAPPluginHook();
$HookManager->register('USER_LOGGING_IN',array($LDAPPluginHook,'check_addUser'));
