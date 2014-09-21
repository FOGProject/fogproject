<?php
class UserManager extends FOGManagerController
{
	// Search query
	public $searchQuery = 'SELECT * FROM users WHERE uName LIKE "%${keyword}%"';
	// Custom function
	function isPasswordValid($password, $passwordConfirm)
	{
		try
		{
			// Error checking
			if ($password != $passwordConfirm)
				throw new Exception('Passwords do not match');
			if (strlen($password) < $GLOBALS['FOGCore']->getSetting('FOG_USER_MINPASSLENGTH'))
				throw new Exception('Password too short');
			if (preg_replace('/[' . preg_quote(addSlashes($GLOBALS['FOGCore']->getSetting('FOG_USER_VALIDPASSCHARS'))) . ']/', '', $password) != '')
				throw new Exception('Invalid characters in password');
			// Success
			return true;
		}
		catch (Exception $e)
		{
			throw new Exception($e->getMessage());
			// Fail
			return false;
		}
	}
}
