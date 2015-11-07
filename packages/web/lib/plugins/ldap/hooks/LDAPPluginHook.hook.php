<?php
class LDAPPluginHook extends Hook {
    public function __construct() {
        $this->name = 'LDAPPluginHook';
        $this->description = 'LDAP Hook';
        $this->author = 'Fernando Gietz';
        $this->active = true;
        $this->node = 'ldap';
    }
    public function check_addUser($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $username = $arguments['username'];
        $password = $arguments['password'];
        $User = $arguments['User'];
        if ($User instanceof User && $User->isValid()) return;
        $LDAPs = $this->getClass('LDAPManager')->find();
        foreach($LDAPs AS $i => &$LDAP) {
            $User = $this->getClass('UserManager')->find(array('name'=>$username));
            $User = @array_shift($User);
            if ($LDAP->authLDAP($username,$password)) {
                if ($User instanceof User && $User->isValid()) $User->set('password',$password);
                else {
                    $noUser = true;
                    $User = $this->getClass('User')
                        ->set('name',$username)
                        ->set('type',1)
                        ->set('password',md5($password));
                }
                if (!$User->save()) throw new Exception('User create/update failed');
                $arguments['User'] = $User;
                break;
            }
        }
        unset($LDAP);
    }
}
$LDAPPluginHook = new LDAPPluginHook();
$HookManager->register('USER_LOGGING_IN', array($LDAPPluginHook,'check_addUser'));
