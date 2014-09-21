<?php
class UserLoginEntry
{
	private $strUser, $strInTime, $strOutTime, $blClean;
	function __construct( $username )
	{
		$this->strUser = $username;
	}
	function setLogInTime( $time ) { $this->strInTime = $time; }
	function setLogOutTime( $time ) { $this->strOutTime = $time; }
	function setClean( $bl ) { $this->blClean = $bl; }
	function getUser() { return $this->strUser; }
	function getLogInTime() { return $this->strInTime; }
	function getLogOutTime() { return $this->strOutTime; }
	function isClean() { return $this->blClean; }		
}
