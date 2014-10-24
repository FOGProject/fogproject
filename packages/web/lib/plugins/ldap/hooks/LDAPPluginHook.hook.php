<?php
class LDAPPluginHook extends Hook
{
	var $name = 'LDAPPluginHook';
	var $description = 'LDAP Hook';
	var $author = 'Fernando Gietz';
	var $active = true;
	var $node = 'ldap';
	public function check_addUser($arguments)
	{
		$username = $arguments['username'];
		$password = $arguments['password'];
		$User = $arguments['User'];
		$LDAPplugin = current($this->getClass('PluginManager')->find(array('name' => strtoupper($this->node),'installed' => 1, 'state' => 1)));
		if ($LDAPplugin && $LDAPplugin->isValid())
		{
			foreach($this->getClass('LDAPManager')->find() AS $LDAP)
			{
				if ($LDAP->authLDAP($username,$password))
				{
					$UserByName = current($this->getClass('UserManager')->find(array('name' => $username)));
					if ($UserByName && $UserByName->isValid())
					{
						if ($UserByName->get('type') == '1' || preg_match('#mobile#i',$_SERVER['PHP_SELF']))
							$arguments['User'] = $UserByName;
						else
						{
							$tmpUser = new User(array(
									'name' => $username,
									'type' => 1,
									'password' => md5($password),
									'createdBy' => 'fog',
							));
							if ($tmpUser->save())
							{
								$this->logHistory(sprintf('%s: ID: %s, Name: %s', _('User created'), $tmpUser->get('id'), $tmpUser->get('name')));
								$arguments['User'] = $tmpUser;
							}
							else
								throw new Exception('Database update failed');
						}
						break;
					}
					else if (!$User || !$User->isValid())
					{
						$tmpUser = new User(array(
								'name' => $username,
								'type' => 1,
								'password' => md5($password),
								'createdBy' => 'fog',
						));
						if ($tmpUser->save())
						{
							$this->logHistory(sprintf('%s: ID: %s, Name: %s', _('User created'), $tmpUser->get('id'), $tmpUser->get('name')));
							return $tmpUser;
						}
						else
							throw new Exception('Database update failed');
					}
				}
			}
		}
	}

}
$LDAPPluginHook = new LDAPPluginHook();
// Register Hooks
$HookManager->register('USER_LOGGING_IN', array($LDAPPluginHook,'check_addUser'));
