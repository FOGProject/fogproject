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
        // 'searchDN',
        // 'userNamAttr',
        // 'grpMemberAttr',
        // 'adminGroup',
        // 'userGroup',
        // 'searchScope',
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
                $prefix = strtoupper($prefix);
                $pat = preg_replace_callback(
                    "/\\\([0-9A-Fa-f]{2})/",
                    function ($matches) {
                        foreach ((array)$matches as &$match) {
                            return chr(hexdec($match));
                        }
                    },
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
            1
        );
        /**
         * Sets our default accessLevel to 0.
         * 0 = fail
         * 1 = mobile
         * 2 = admin
         */
        $accessLevel = 0;
        /**
         * Setup bind dn and password
         */
        $bindDN = strtolower($this->get('bindDN'));
        $bindPass = $this->get('bindPwd');
        /**
         * Setup the admin/user scopes to use for creating
         * our users later on
         */
        $adminGroup = strtolower($this->get('adminGroup'));
        $userGroup = strtolower($this->get('userGroup'));
        /**
         * The user name attribute in use (e.g. uid=)
         */
        $userNamAttr = strtolower($this->get('userNamAttr'));
        /**
         * The group member attribute in use (e.g. memberOf=)
         */
        $grpMemberAttr = strtolower($this->get('grpMemberAttr'));
        /**
         * How deep to search from the base we're looking into
         */
        $searchScope = $this->get('searchScope');
        /**
         * Parse our user search DN
         */
        $entries = $this->_ldapParseDn($userSearchDN);
        /**
         * Set up our search/group information
         */
        $userSearchDN = strtolower($this->get('searchDN'));
        /**
         * Set our filter to return our object
         */
        $filter = sprintf(
            '(&(|(objectcategory=person)(objectclass=person))(%s=%s))',
            $userNamAttr,
            $user
        );
        /**
         * If binddn is set run:
         */
        if (!empty($bindDN)) {
            $bindPass = trim($bindPass);
            $bindPass = $this->aesdecrypt($bindPass);
            /**
             * If no bind password return immediately
             */
            if (empty($bindPass)) {
                return false;
            }
            /**
             * Make our bindDN/pass connection
             */
            $bind = ldap_bind($ldapconn, $bindDN, $bindPass);
            /**
             * If we cannot bind return immediately
             */
            if (!$bind) {
                return false;
            }
            /**
             * Setup bind DN attribute
             */
            $attr = array('dn');
            switch ($searchScope) {
                case 1:
                    /**
                     * One level down but not base
                     */
                    $result = ldap_list($ldapconn, $userSearchDN, $filter, $attr);
                    break;
                case 2:
                    /**
                     * Search base and all subtree ous
                     */
                    $result = ldap_search($ldapconn, $userSearchDN, $filter, $attr);
                    break;
                default:
                    /**
                     * Search base only
                     */
                    $result = ldap_read($ldapconn, $userSearchDN, $filter, $attr);
            }
            $retcount = ldap_count_entries($ldapconn, $result);
            /**
             * If multiple entries or no entries return immediately
             */
            if ($retcount != 1) {
                ldap_unbind($ldapconn);
                return false;
            }
            /**
             * Only one entry
             */
            $entries = ldap_get_entries($ldapconn, $result);
            /**
             * Pull out the user dn
             */
            $userDN = $entries[0]['dn'];
            /**
             * Rebind as the user
             */
            $bind = @ldap_bind($ldapconn, $userDN, $pass);
            /**
             * If user unable to bind return immediately
             */
            if (!$bind) {
                return false;
            }
            /**
             * Setup our new filter
             */
            $filter = sprintf(
                '(%s=*)',
                $grpMemberAttr
            );
            /**
             * Allows the user to use multiple attributes
             * via a comma separated list
             */
            $attr = array($grpMemberAttr);
            $attr = array_map('trim', $attr);
            /**
             * Read in the attributes
             */
            switch ($searchScope) {
                case 1:
                    /**
                     * One level down but not base
                     */
                    $result = ldap_list($ldapconn, $userSearchDN, $filter, $attr);
                    break;
                case 2:
                    /**
                     * Search base and all subtree ous
                     */
                    $result = ldap_search($ldapconn, $userSearchDN, $filter, $attr);
                    break;
                default:
                    /**
                     * Search base only
                     */
                    $result = ldap_read($ldapconn, $userSearchDN, $filter, $attr);
            }
            /**
             * Get number of entries returned
             */
            $retcount = ldap_count_entries($ldapconn, $result);
            /**
             * If no data return immediately
             */
            if ($retcount < 1) {
                ldap_unbind($ldapconn);
                return false;
            }
            /**
             * Get the entries found
             */
            $entries = ldap_get_entries($ldapconn, $result);
            /**
             * Setup pattern for later
             */
            $pat = sprintf(
                '#%s#i',
                $userDN
            );
            /**
             * Check groups for membership
             */
            foreach ((array)$entries as &$entry) {
                if (!isset($entry['dn'])) {
                    continue;
                }
                $dn = $entry['dn'];
                $users = $entry[$grpMemberAttr];
                $admin = strpos($dn, $adminGroup);
                $user = strpos($dn, $userGroup);
                /**
                 * If we can't find our relative dn
                 * set access level to 0 and break loop
                 */
                if (false === $admin && false === $user) {
                    continue;
                }
                if (false !== $admin) {
                    $adm = preg_grep($pat, $users);
                    if (count($adm) > 0) {
                        $accessLevel = 2;
                        break;
                    }
                }
                if (false !== $user) {
                    $usr = preg_grep($pat, $users);
                    if (count($usr) > 0) {
                        $accessLevel = 1;
                    }
                }
            }
            /**
             * Close our connection
             */
            ldap_unbind($ldapconn);
        } else {
            /**
             * Combine to get the Domain in information.
             */
            $userDomain = implode('.', $entries['DC']);
            /**
             * Setup a multitude of ways to bind
             */
            $userDN1 = sprintf(
                '%s@%s',
                $user,
                $userDomain
            );
            $userDN2 = sprintf(
                '%s\%s',
                $userDomain,
                $user
            );
            $userDN3 = sprintf(
                '%s=%s,dc=%s',
                $userNamAttr,
                $user,
                implode(',dc=', $entries['DC'])
            );
            /**
             * If our ways here don't work, return immediately
             */
            if (!@ldap_bind($ldapconn, $userDN1, $pass)
                && !@ldap_bind($ldapconn, $userDN2, $pass)
                && !@ldap_bind($ldapconn, $userDN3, $pass)
            ) {
                return false;
            }
            /**
             * Allows the user to enter multiple attributes
             * via a comma separated list
             */
            $attr = explode(',', $grpMemberAttr);
            $attr = array_map('trim', $attr);
            /**
             * Read in the attributes
             */
            switch ($searchScope) {
                case 1:
                    /**
                     * One level down but not base
                     */
                    $result = ldap_list($ldapconn, $userSearchDN, $filter, $attr);
                    break;
                case 2:
                    /**
                     * Search base and all subtree ous
                     */
                    $result = ldap_search($ldapconn, $userSearchDN, $filter, $attr);
                    break;
                default:
                    /**
                     * Search base only
                     */
                    $result = ldap_read($ldapconn, $userSearchDN, $filter, $attr);
            }
            /**
             * Get number of entries returned
             */
            $retcount = ldap_count_entries($ldapconn, $result);
            /**
             * If no data return immediately
             */
            if ($retcount < 1) {
                ldap_unbind($ldapconn);
                return false;
            }
            /**
             * Get the entries found
             */
            $entries = ldap_get_entries($ldapconn, $result);
            /**
             * Check groups for membership
             */
            foreach ((array)$entries as &$entry) {
                if (!isset($entry['dn'])) {
                    continue;
                }
                $dn = $entry['dn'];
                $users = $entry[$grpMemberAttr];
                $admin = strpos($dn, $adminGroup);
                $user = strpos($dn, $userGroup);
                /**
                 * If we can't find our relative dn
                 * set access level to 0 and break loop
                 */
                if (false === $admin && false === $user) {
                    continue;
                }
                if (false !== $admin) {
                    $adm = preg_grep($pat, $users);
                    if (count($adm) > 0) {
                        $accessLevel = 2;
                        break;
                    }
                }
                if (false !== $user) {
                    $usr = preg_grep($pat, $users);
                    if (count($usr) > 0) {
                        $accessLevel = 1;
                    }
                }
            }
            /**
             * Close our connection
             */
            ldap_unbind($ldapconn);
        }
        /**
         * If access level is not changed
         *
         * @return bool
         */
        if ($accessLevel == 0) {
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
