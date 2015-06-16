<?php
class LDAP extends FOGController {
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
	public function destroy($field = 'id') {
		$this->getClass('LDAPManager')->find(array('LDAPID' => $this->get('id')));
		return parent::destroy($field);
	}
	private function LDAPUp($timeout = 3) {
		if (!in_array($this->get('port'),array(389,636))) throw new Exception(_('Port is not valid ldap/ldaps ports'));
		$sock = fsockopen($this->get('address'), $this->get('port'), $errno, $errstr, $timeout);
		if (!$sock) return false;
		fclose($sock);
		return $this->get('port') == 636 ? 'ldaps://'.$this->get('address') : $this->get('address');
	}
	public function authLDAP($user,$pass) {
		if (!$server = $this->LDAPUp()) return false;
		$ldapconn = @ldap_connect($server,$this->get('port'));
		// set protocol options
		ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
		if (!ldap_bind($ldapconn,"uid=$user,{$this->get(DN)}",$pass)) return false;
		return ldap_close($ldapconn);
	}
}
