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
     * Ldap connection itself
     *
     * @var resource
     */
    private static $_ldapconn;
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
        'searchScope',
        /**
         * I think it's find these are not "required"
         * What if you want admin group to only be done by
         * a particular ldap server?
         *
         * Same for a "user group" why should we require both?
         */
        // 'adminGroup',
        // 'userGroup',
    );
    /**
     * Magic function to enable ldap_ function calls using
     * an object oriented call structure
     *
     * @param string $function the function to call (only the back half).
     * @param array  $args     the functions required arguments
     *
     * @throws Exception
     * @return function return
     */
    private function __call($function, $args)
    {
        $function = 'ldap_'.$function;
        if (!function_exists($function)) {
            throw new Exception(_('Function does not exist')." $function");
        }
        array_unshift($args, self::$_ldapconn);
        return call_user_func_array($function, $args);
    }
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
    private function _ldapParseDn()
    {
        $parser = $this->explode_dn(0);
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
        self::$_ldapconn = ldap_connect(
            $server,
            $port
        );
        /**
         * Sets the ldap options we need
         */
        $this->set_option(
            LDAP_OPT_PROTOCOL_VERSION,
            3
        );
        $this->set_option(
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
            $bind = @$this->bind($bindDN, $bindPass);
            /**
             * If we cannot bind return immediately
             */
            if (!$bind) {
                return false;
            }
            /**
             * Set our filter to return our object
             */
            $filter = sprintf(
                '(&(|(objectcategory=person)(objectclass=person))(%s=%s))',
                $userNamAttr,
                $user
            );
            /**
             * Setup bind DN attribute
             */
            $attr = array('dn');
            /**
             * Gather our required information
             */
            switch ($searchScope) {
                case 1:
                    /**
                     * One level down but not base
                     */
                    $result = $this->list($userSearchDN, $filter, $attr);
                    break;
                case 2:
                    /**
                     * Search base and all subtree ous
                     */
                    $result = $this->search($userSearchDN, $filter, $attr);
                    break;
                default:
                    /**
                     * Search base only
                     */
                    $result = $this->read($userSearchDN, $filter, $attr);
            }
            $retcount = $this->count_entries($result);
            /**
             * If multiple entries or no entries return immediately
             */
            if ($retcount != 1) {
                $this->unbind();
                return false;
            }
            /**
             * Only one entry
             */
            $entries = $this->get_entries($result);
            /**
             * Pull out the user dn
             */
            $userDN = $entries[0]['dn'];
            /**
             * Rebind as the user
             */
            $bind = @$this->bind($userDN, $pass);
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
                    $result = $this->list($userSearchDN, $filter, $attr);
                    break;
                case 2:
                    /**
                     * Search base and all subtree ous
                     */
                    $result = $this->search($userSearchDN, $filter, $attr);
                    break;
                default:
                    /**
                     * Search base only
                     */
                    $result = $this->read($userSearchDN, $filter, $attr);
            }
            /**
             * Get number of entries returned
             */
            $retcount = $this->count_entries($result);
            /**
             * If no data return immediately
             */
            if ($retcount < 1) {
                $this->unbind();
                return false;
            }
            /**
             * Get the entries found
             */
            $entries = $this->get_entries($result);
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
                /**
                 * If this cycle doesn't have the dn, skip it
                 */
                if (!isset($entry['dn'])) {
                    continue;
                }
                /**
                 * Get the dn entry we need to test against
                 */
                $dn = $entry['dn'];
                /**
                 * Get the users related to this dn
                 */
                $users = $entry[$grpMemberAttr];
                /**
                 * Tests the presence of our admin group
                 */
                $admin = strpos($dn, $adminGroup);
                /**
                 * Tests the presence of our mobile group
                 */
                $user = strpos($dn, $userGroup);
                /**
                 * If we can't find our relative dn
                 * set access level to 0 and break loop
                 */
                if (false === $admin && false === $user) {
                    continue;
                }
                /**
                 * If the dn is in the admin scope
                 */
                if (false !== $admin) {
                    /**
                     * Test if the user dn exists in this group
                     */
                    $adm = preg_grep($pat, $users);
                    /**
                     * Ensure we only return "filled" items
                     */
                    $adm = array_filter($adm);
                    /**
                     * If so, no need to move on, set access level and break loop
                     */
                    if (count($adm) > 0) {
                        $accessLevel = 2;
                        break;
                    }
                }
                /**
                 * If the dn is in the mobile scope
                 */
                if (false !== $user) {
                    /**
                     * Test if the user dn exists in this group
                     */
                    $usr = preg_grep($pat, $users);
                    /**
                     * Ensure we only return "filled" items
                     */
                    $usr = array_filter($usr);
                    /**
                     * If so, set our access level.  We remain in loop
                     * so if another ldap server has this same user as admin
                     * it can get it's proper permissions.
                     */
                    if (count($usr) > 0) {
                        $accessLevel = 1;
                    }
                }
            }
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
            if (!@$this->bind($userDN1, $pass)
                && !@$this->bind($userDN2, $pass)
                && !@$this->bind($userDN3, $pass)
            ) {
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
                    $result = $this->list($userSearchDN, $filter, $attr);
                    break;
                case 2:
                    /**
                     * Search base and all subtree ous
                     */
                    $result = $this->search($userSearchDN, $filter, $attr);
                    break;
                default:
                    /**
                     * Search base only
                     */
                    $result = $this->read($userSearchDN, $filter, $attr);
            }
            /**
             * Get number of entries returned
             */
            $retcount = $this->count_entries($result);
            /**
             * If no data return immediately
             */
            if ($retcount < 1) {
                $this->unbind();
                return false;
            }
            /**
             * Get the entries found
             */
            $entries = $this->get_entries($result);
            /**
             * Check groups for membership
             */
            foreach ((array)$entries as &$entry) {
                /**
                 * If this cycle doesn't have the dn, skip it
                 */
                if (!isset($entry['dn'])) {
                    continue;
                }
                /**
                 * Get the dn entry we need to test against
                 */
                $dn = $entry['dn'];
                /**
                 * Get the users related to this dn
                 */
                $users = $entry[$grpMemberAttr];
                /**
                 * Tests the presence of our admin group
                 */
                $admin = strpos($dn, $adminGroup);
                /**
                 * Tests the presence of our mobile group
                 */
                $user = strpos($dn, $userGroup);
                /**
                 * If we can't find our relative dn
                 * set access level to 0 and break loop
                 */
                if (false === $admin && false === $user) {
                    continue;
                }
                /**
                 * If the dn is in the admin scope
                 */
                if (false !== $admin) {
                    /**
                     * Test if the user dn exists in this group
                     */
                    $adm = preg_grep($pat, $users);
                    /**
                     * Ensure we only return "filled" items
                     */
                    $adm = array_filter($adm);
                    /**
                     * If so, no need to move on, set access level and break loop
                     */
                    if (count($adm) > 0) {
                        $accessLevel = 2;
                        break;
                    }
                }
                /**
                 * If the dn is in the mobile scope
                 */
                if (false !== $user) {
                    /**
                     * Test if the user dn exists in this group
                     */
                    $usr = preg_grep($pat, $users);
                    /**
                     * Ensure we only return "filled" items
                     */
                    $usr = array_filter($usr);
                    /**
                     * If so, set our access level.  We remain in loop
                     * so if another ldap server has this same user as admin
                     * it can get it's proper permissions.
                     */
                    if (count($usr) > 0) {
                        $accessLevel = 1;
                    }
                }
            }
        }
        /**
         * Close our connection
         */
        $this->unbind();
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
