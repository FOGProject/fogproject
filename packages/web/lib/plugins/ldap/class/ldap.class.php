<?php
class LDAP extends FOGController
{
    protected $databaseTable = 'LDAPServers';
    protected $databaseFields = array(
        'id' => 'lsID',
        'name' => 'lsName',
        'description' => 'lsDesc',
        'createdBy' => 'lsCreatedBy',
        'createdTime' => 'lsCreatedTime',
        'address' => 'lsAddress',
        'port' => 'lsPort',
        'DN' => 'lsDN',
        'admin' => 'lsAdminCreate',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'address',
        'DN',
        'port'
    );
    private function LDAPUp($timeout = 3)
    {
        $ldap = 'ldap';
        if (!in_array($this->get('port'), array(389, 636))) {
            throw new Exception(_('Port is not valid ldap/ldaps ports'));
        }
        $sock = @pfsockopen($this->get('address'), $this->get('port'), $errno, $errstr, $timeout);
        if ($sock === false) {
            return false;
        }
        fclose($sock);
        return sprintf('%s%s://%s', $ldap, ($this->get('port') === 636 ? 's' : ''), $this->get('address'));
    }
    public function authLDAP($user, $pass)
    {
        if (!$server = $this->LDAPUp()) {
            return false;
        }
        $user = trim(preg_replace('/[^a-zA-Z0-9\-\_@\.]/', '', $user));
        $MainDN = explode('.', $this->get('name'));
        $MainDN = sprintf('dc=%s', implode(',dc=', $MainDN));
        $ldapconn = ldap_connect($server, $this->get('port'));
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
        $userdn = sprintf('uid=%s,%s', $user, $MainDN);
        if (!ldap_bind($ldapconn, $userdn, $pass)) {
            if (!ldap_bind($ldapconn, sprintf('%s\%s', $this->get('name'), $user), $pass)) {
                if (!ldap_bind($ldapconn, sprintf('%s@%s', $user, $this->get('name')), $pass)) {
                    return false;
                }
            }
            $countcheck = true;
        }
        $searchroutes = preg_split('/[\s,.]+/', $this->get('DN'));
        $searchnonMain = preg_grep('/^[\s]?[^d][^c]/i', $searchroutes);
        $searchdn = sprintf('(&(%s))', implode(')(', (array)$searchnonMain));
        $search = ldap_search($ldapconn, $MainDN, $searchdn, array('uniquemember'));
        if (!$search) {
            $searchdn = sprintf('(memberOf=%s)', $this->get('DN'));
            $search = ldap_search($ldapconn, $MainDN, $searchdn, array('memberOf'));
        }
        if (!$search) {
            return false;
        }
        $result = ldap_get_entries($ldapconn, $search);
        ldap_unbind($ldapconn);
        if ($result['count'] < 1) {
            return false;
        }
        if ($countcheck) {
            return true;
        }
        for ($i = 0; $i < $result['count']; $i++) {
            if ($result[$i]['uniquemember']['count'] < 1) {
                return false;
            }
            foreach ($result[$i]['uniquemember'] as &$val) {
                if (false !== strpos(trim($val), trim($userdn))) {
                    return true;
                }
                unset($val);
            }
        }
        return false;
    }
}
