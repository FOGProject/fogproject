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
		$username = $arguments['username'];
		$password = $arguments['password'];
		$User = $arguments['User'];
		if (!in_array($this->node,$_SESSION['PluginsInstalled']))
			return;
		foreach($this->getClass('LDAPManager')->find() AS $LDAP) {
			if ($LDAP->authLDAP($username,$password)) {
				$UserByName = current($this->getClass('UserManager')->find(array('name' => $username)));
				if ($UserByName) {
					$arguments['User'] = $UserByName;
					break;
				} else if (!$User || !$User->isValid()) {
					$tmpUser = new User(array(
							'name' => $username,
							'type' => 1,
							'password' => md5($password),
							'createdBy' => 'fog',
					));
					if (!$tmpUser->save())
						throw new Exception('Database update failed');
					$this->FOGCore->logHistory(sprintf('%s: ID: %s, Name: %s', _('User created'), $tmpUser->get('id'), $tmpUser->get('name')));
					$arguments['User'] = $tmpUser;
					break;
				}
			}
		}
	}
}
$LDAPPluginHook = new LDAPPluginHook();
// Register Hooks
$HookManager->register('USER_LOGGING_IN', array($LDAPPluginHook,'check_addUser'));
