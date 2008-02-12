<?php
class User
{
	private $strUserName, $strAuthIP, $strTime;

	function __construct($username, $authIp, $authTime)
	{
		$this->strUserName = $username;
		$this->strAuthIP = $authIp;
		$this->strTime = $authTime;
	}
	
	public function getUserName() { return $this->strUserName; }
	public function getAuthIp() { return $this->strAuthIP; }
	public function getAuthTime() { return $this->strTime; }
	public function isLoggedIn() { return true; }
}
?>
