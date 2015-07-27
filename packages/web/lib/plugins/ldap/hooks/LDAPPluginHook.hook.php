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
		$username = $arguments[username];
		$password = $arguments[password];
		$User = $arguments[User];
        if (!in_array($this->node,$_SESSION[PluginsInstalled])) return;
        $LDAPs = $this->getClass(LDAPManager)->find();
		foreach($LDAPs AS $i => &$LDAP) {
			if ($LDAP->authLDAP($username,$password)) {
				$UserByName = current($this->getClass(UserManager)->find(array(name=>$username)));
				if ($UserByName) {
					$arguments[User] = $UserByName;
					break;
                } else if (!$User || !$User->isValid()) {
                    $User = $this->getClass(User)
                        ->set(name,$username)
                        ->set(type,1)
                        ->set(password,md5($password))
                        ->set(createdBy,$this->FOGUser->get(name));
					if (!$User->save()) throw new Exception('Database update failed');
					$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('User created'), $User->get(id), $User->get(name)));
					$arguments[User] = $User;
					break;
				}
			}
        }
        unset($LDAP);
	}
}
$LDAPPluginHook = new LDAPPluginHook();
// Register Hooks
$HookManager->register('USER_LOGGING_IN', array($LDAPPluginHook,'check_addUser'));
