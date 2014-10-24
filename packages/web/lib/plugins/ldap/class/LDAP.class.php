<?php
class LDAP extends FOGController
{
	// Table
	public $databaseTable = 'LDAPServers';

	// Name -> Database field name
	public $databaseFields = array(
			'id'		=> 'lsID',
			'name'		=> 'lsName',
			'description' => 'lsDesc',
			'createdBy'	=> 'lsCreatedBy',
			'createdTime' => 'lsCreatedTime',
			'address' => 'lsAddress',
			'port'		=> 'lsPort',
			'DN'		=> 'lsDN',
	);

	public $databaseFieldsRequired = array(
			'name',
			'address',
			'DN',
			'port'
	);
	public function destroy($field = 'id')
	{
		$this->getClass('LDAPManager')->find(array('LDAPID' => $this->get('id')));
		return parent::destroy($field);
	}
	public function authLDAP($user,$pass)
	{
		$server = $this->get('address');
		$port = $this->get('port');
		if ($fp = fsockopen($server,$port,$errno,$errstr,3))
			fclose($fp);
		
		$con = @ldap_connect("ldaps://".$server , $port);
		// set protocol options
		ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($con, LDAP_OPT_REFERRALS, 0);
		$r=ldap_bind($con, "uid=".$user.",".$this->get('DN'), $pass);
		ldap_close($con);
		if ($r)
			return true;
		else
			return false;
	}
}