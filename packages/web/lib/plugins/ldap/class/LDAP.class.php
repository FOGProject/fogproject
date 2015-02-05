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
	private function LDAPUp($timeout = 3)
	{
		if ($this->get('port') != 389 && $this->get('port') != 689)
			throw new Exception(_('Port is not valid ldap/ldaps ports');
		$port = $this->get('port');
		$server = $this->get('port') == 689 ? 'ldaps://'.$this->get('address') : $this->get('address');
		$sock = fsockopen($server, $this->get('port'), $errno, $errstr, $timeout);
		if (!$sock) return false;
		fclose($sock);
		$this->set('address', $server);
		return true;
	}
	public function authLDAP($user,$pass)
	{
		if (!$this->LDAPUp()) return false;
		$ldapconn = @ldap_connect($this->get('address'),$this->get('port'));
		// set protocol options
		ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
		if (!ldap_bind($ldapconn,"uid=$user,{$this->get(DN)}",$pass))
			return false;
		ldap_close($ldapconn);
		return true;
	}
}
