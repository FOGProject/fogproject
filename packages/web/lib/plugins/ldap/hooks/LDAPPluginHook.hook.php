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
        $noUser = false;
        // If we aren't active but we are loaded for some reason, return
        if (!in_array($this->node,$_SESSION[PluginsInstalled])) return;
		$username = $arguments[username];
		$password = $arguments[password];
        $User = $arguments[User];
        // If the user logging in is already valid don't continue on
        // May think about commenting the line below in case you ONLY
        // want LDAP users to be able to login
        if ($User instanceof User && $User->isValid()) return;
        $LDAPs = $this->getClass(LDAPManager)->find();
        foreach($LDAPs AS $i => &$LDAP) {
            // When signing in, we should verify if the user already exists
            // Then authenticate, if LDAP auth is correct and
            // the user exists in fog, update users password to the
            // new value.  This way it is truly in sync with LDAP
            $User = $this->getClass(UserManager)->find(array(name=>$username));
            $User = @array_shift($User);
            if ($LDAP->authLDAP($username,$password)) {
                // If the user authenticates, update the users password to match.
                if ($User instanceof User && $User->isValid()) $User->set(password,$password);
                else {
                    $noUser = true;
                    $User = $this->getClass(User)
                        ->set(name,$username)
                        ->set(type,1)
                        ->set(password,md5($password))
                        ->set(createdBy,$this->FOGUser->get(name));
                }
                if (!$User->save()) throw new Exception('User create/update failed');
				if ($noUser === true) $this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('User created'), $User->get(id), $User->get(name)));
                $arguments[User] = $User;
                break;
			}
        }
        unset($LDAP);
	}
}
$LDAPPluginHook = new LDAPPluginHook();
// Register Hooks
$HookManager->register('USER_LOGGING_IN', array($LDAPPluginHook,'check_addUser'));
