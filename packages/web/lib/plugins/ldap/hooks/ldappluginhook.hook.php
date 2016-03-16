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
        $User = $arguments['User'];
        if ($User instanceof User && $User->isValid()) return;
        foreach ((array)self::getClass('LDAPManager')->find() AS $i => &$LDAP) {
            if (!$LDAP->isValid()) continue;
            $User = self::getClass('User',@max($this->getSubObjectIDs('User',array('name'=>$username))));
            if (!$LDAP->authLDAP($username,$password)) continue;
            if ($User->isValid()) $User->set('password',$password);
            else {
                $User = self::getClass('User')
                    ->set('name',$username)
                    ->set('type',1)
                    ->set('password',md5($password));
            }
            if (!$User->save()) throw new Exception(_('User create/update failed'));
            $arguments['User'] = $User;
            unset($LDAP);
            break;
        }
    }
}
$LDAPPluginHook = new LDAPPluginHook();
$HookManager->register('USER_LOGGING_IN',array($LDAPPluginHook,'check_addUser'));
