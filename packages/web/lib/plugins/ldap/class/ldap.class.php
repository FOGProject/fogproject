<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAP
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * LDAP Authentication plugin
 *
 * @category LDAP
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAP extends FOGController
{
    /**
     * The ldap table
     *
     * @var string
     */
    protected $databaseTable = 'LDAPServers';
    /**
     * The LDAP table fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'lsID',
        'name' => 'lsName',
        'description' => 'lsDesc',
        'createdBy' => 'lsCreatedBy',
        'createdTime' => 'lsCreatedTime',
        'address' => 'lsAddress',
        'port' => 'lsPort',
        'searchDN' => 'lsUserSearchDN',
        'userNamAttr' => 'lsUserNamAttr',
        'grpMemberAttr' => 'lsGrpMemberAttr',
        'adminGroup' => 'lsAdminGroup',
        'userGroup' => 'lsUserGroup',
        'searchScope' => 'lsSearchScope',
        'bindDN' => 'lsBindDN',
        'bindPwd' => 'lsBindPwd',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'address',
        'port',
        'searchDN',
        'userNamAttr',
        'grpMemberAttr',
        'adminGroup',
        'userGroup',
        'searchScope',
    );
    /**
     * Tests if the server is up and available
     *
     * @param int $timeout how long before timeout
     *
     * @return bool|string
     */
    private function _ldapUp($timeout = 3)
    {
        $ldap = 'ldap';
        $ports = array(389, 636);
        $port = $this->get('port');
        $address = $this->get('address');
        if (!in_array($port, $ports)) {
            throw new Exception(_('Port is not valid ldap/ldaps port'));
        }
        $sock = @pfsockopen(
            $address,
            $port,
            $errno,
            $errstr,
            $timeout
        );
        if ($sock === false) {
            return false;
        }
        fclose($sock);
        return sprintf(
            '%s%s://%s',
            $ldap,
            (
                $port == 636 ?
                's' :
                ''
            ),
            $address
        );
    }
    /**
     * Parses the DN
     *
     * @param string $dn the DN to parse
     *
     * @return array
     */
    private function _ldapParseDn($dn)
    {
        $parser = ldap_explode_dn($dn, 0);
        $out = array();
        foreach ((array)$parser as $key => &$value) {
            if (false !== strstr($value, '=')) {
                list(
                    $prefix,
                    $data
                ) = explode('=', $value);
                $data = preg_replace(
                    '/\\\([0-9A-Fa-f]{2})/e',
                    "''.chr(hexdec('\\1')).''",
                    $data
                );
                if (isset($current_prefix)
                    && $prefix == $current_prefix
                ) {
                    $out[$prefix][] = $data;
                } else {
                    $current_prefix = $prefix;
                    $out[$prefix][] = $data;
                }
            }
            unset($value);
        }
        return $out;
    }
    /**
     * Checks if the user/pass are valid
     *
     * @param string $user the username to validate
     * @param string $pass the password to validate
     *
     * @return bool|int
     */
    public function authLDAP($user, $pass)
    {
        /**
         * Trim the values just incase somebody is trying
         * to break in by using spaces -- prevent dos attack I imagine.
         */
        $user = trim($user);
        $pass = trim($pass);
        /**
         * User and/or Pass is empty
         *
         * @return bool
         */
        if (empty($user) || empty($pass)) {
            return false;
        }
        /**
         * Server is not reachable
         *
         * @return bool
         */
        if (!$server = $this->_ldapUp()) {
            return false;
        }
        /**
         * Clean up username.  We only want the user's short name
         * without any domain component.
         *
         * Regex is: any characters that are NOT A-Z, a-z, 0-9, -,
         * _, @, or .
         */
        $user = preg_replace(
            '/[^a-zA-Z0-9\-\_\@\.]/',
            '',
            $user
        );
        $user = trim($user);
        /**
         * If, after character checking, the user is empty
         *
         * @return bool
         */
        if (empty($user)) {
            return false;
        }
        $port = $this->get('port');
        /**
         * Open connection to the server
         */
        $ldapconn = ldap_connect(
            $server,
            $port
        );
        /**
         * Sets the ldap options we need
         */
        ldap_set_option(
            $ldapconn,
            LDAP_OPT_PROTOCOL_VERSION,
            3
        );
        ldap_set_option(
            $ldapconn,
            LDAP_OPT_REFERRALS,
            0
        );
        /**
         * Sets our default accessLevel to 0.
         * 0 = fail
         * 1 = mobile
         * 2 = admin
         */
        $accessLevel = 0;
        /**
         * Set up our search/group information
         */
        $userSearchDN = $this->get('searchDN');
        $adminGroup = strtolower($this->get('adminGroup'));
        $userGroup = strtolower($this->get('userGroup'));
        $userNamAttr = strtolower($this->get('userNamAttr'));
        $grpMemberAttr = strtolower($this->get('grpMemberAttr'));
        /**
         * Parse our user search DN
         */
        $entries = $this->_ldapParseDN($userSearchDN);
        /**
         * Combine to get the Domain in information.
         */
        $userDomain = implode('.', $entries['DC']);
        $userDN = sprintf(
            '%s@%s',
            $user,
            $userDomain
        );
        /**
         * If we cannot bind using the information
         *
         * @return bool
         */
        if (!ldap_bind($ldapconn, $userDN, $pass)) {
            return false;
        }
        /**
         * User is authorized, get group membership
         */
        $filter = sprintf(
            '(&(%s=%s)(%s=%s)',
            'objectCategory',
            'person',
            $userNamAttr,
            $user
        );
        $attr = array($grpMemberAttr);
        /**
         * Perform the search
         */
        $result = ldap_search(
            $ldapconn,
            $userSearchDN,
            $filter,
            $attr
        );
        /**
         * Count the number of entries returned
         */
        $retcount = ldap_count_entries(
            $ldapconn,
            $result
        );
        /**
         * If the count is 0 accesslevel is 0
         * unbind the login
         *
         * @return bool
         */
        if ($retcount < 1) {
            ldap_unbind($ldapconn);
            return false;
        }
        /**
         * Get all of our entries
         */
        $entries = ldap_get_entries(
            $ldapconn,
            $result
        );
        /**
         * Loop the entries to set the accesslevel.
         */
        foreach ((array)$entries[0][$grpMemberAttr] as &$grps) {
            $grps = strtolower($grps);
            if (false !== strpos($grps, $adminGroup)) {
                $accessLevel = 2;
                break;
            }
            if (false !== strpos($grps, $userGroup)) {
                $accessLevel = 1;
            }
        }
        /**
         * Unbind the login
         */
        ldap_unbind($ldapconn);
        /**
         * If access level is not changed
         *
         * @return bool
         */
        if ($accessLevel === 0) {
            return false;
        }
        /**
         * Return the access level
         *
         * @return int
         */
        return $accessLevel;
    }
}
