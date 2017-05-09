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
        'grpSearchDN' => 'lsGrpSearchDN',
        'useGroupMatch' => 'lsUseGroupMatch',
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
        //'userNamAttr',
        //'grpMemberAttr',
        'searchScope',
        'useGroupMatch',
        // 'grpSearchDN',
        /**
         * I think it's fine these are not "required"
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
    public function __call($function, $args)
    {
        $func = $function;
        $function = 'ldap_'.$func;
        if (!function_exists($function)) {
            throw new Exception(
                sprintf(
                    '%s %s',
                    _('Function does not exist'),
                    $function
                )
            );
        }
        $nonresourcefuncs = array(
            '8859_to_t61',
            'connect',
            'dn2ufn',
            'err2str',
            'escape',
            'explode_dn',
            't61_to_8859',
        );
        if (!in_array($func, $nonresourcefuncs)) {
            array_unshift($args, self::$_ldapconn);
        }
        $ret = call_user_func_array($function, $args);
        return $ret;
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
    private function _ldapParseDn($dn)
    {
        /**
         * Explode the DN into it's sub components.
         */
        $parser = $this->explode_dn($dn, 0);
        /**
         * Initialize our out array.
         */
        $out = array();
        /**
         * Loop the parsed information so we get
         * the values in a mroe usable and joinable form.
         */
        foreach ((array)$parser as $key => &$value) {
            if (false !== strstr($value, '=')) {
                list(
                    $prefix,
                    $data
                ) = explode('=', $value);
                $prefix = strtoupper($prefix);
                preg_replace_callback(
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
         * Ensure any trailing bindings are removed
         */
        @$this->unbind();
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
        if (empty($user)
            || empty($pass)
        ) {
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
         * Test the username for funky characters and return
         * immediately if found.
         */
        $test = preg_match(
            '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
            $user
        );
        if (!$test) {
            return false;
        }
        /**
         * If, after character checking, the user is empty
         *
         * @return bool
         */
        if (empty($user)) {
            return false;
        }
        $port = (int)$this->get('port');
        /**
         * Open connection to the server
         */
        self::$_ldapconn = ldap_connect(
            $server,
            $port
        );
        /**
         * If we can't connect return immediately
         */
        if (!self::$_ldapconn) {
            error_log(
                sprintf(
                    '%s %s() %s %s:%d',
                    _('Plugin'),
                    __METHOD__,
                    _('We cannot connect to LDAP server'),
                    $server,
                    $port
                )
            );
            return false;
        }
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
         * Flag to tell if we use ldap groups or not
         */
        $useGroupMatch = $this->get('useGroupMatch');
        /**
         * Setup bind dn and password
         */
        $bindDN = $this->get('bindDN');
        /**
         * The bind password.
         */
        $bindPass = $this->get('bindPwd');
        /**
         * The user name attribute in use (e.g. uid=)
         */
        $usrNamAttr = strtolower($this->get('userNamAttr'));
        /**
         * The group member attribute in use (e.g. memberOf=)
         */
        $grpMemAttr = strtolower($this->get('grpMemberAttr'));
        /**
         * Set up our search/group information
         */
        $searchDN = $this->get('searchDN');
        /**
         * Parse our user search DN
         */
        $parsedDN = $this->_ldapParseDn($searchDN);
        /**
         * If binddn is set run through it.
         * Of course we don't need to do this if the
         * use group match isn't set.  We do still need
         * to run the main parsing checks.
         */
        if ($useGroupMatch > 0 && !empty($bindDN)) {
            /**
             * Trims the bind pass.
             */
            $bindPass = trim($bindPass);
            /**
             * We need to decrypt the stored pass.
             */
            $bindPass = self::aesdecrypt($bindPass);
            /**
             * If no bind password return immediately
             */
            if (empty($bindPass)) {
                error_log(
                    sprintf(
                        '%s %s() %s %s!',
                        _('Plugin'),
                        __METHOD__,
                        _('Using the group match function'),
                        _('but bind password is not set')
                    )
                );
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
                error_log(
                    sprintf(
                        '%s %s() %s %s:%d',
                        _('Plugin'),
                        __METHOD__,
                        _('Cannot bind to the LDAP server'),
                        $server,
                        $port
                    )
                );
                return false;
            }
            /**
             * Set our filter to return our object
             */
            $filter = sprintf(
                '(&(|(objectcategory=person)(objectclass=person))(%s=%s))',
                $usrNamAttr,
                $user
            );
            /**
             * Setup bind DN attribute
             */
            $attr = array('dn');
            /**
             * Get our results
             */
            $result = $this->_result($searchDN, $filter, $attr);
            /**
             * Return immediately if the result is false
             */
            if ($result === false) {
                error_log(
                    sprintf(
                        '%s %s() %s. %s: %s; %s: %s',
                        _('Plugin'),
                        __METHOD__,
                        _('Search results returned false'),
                        _('Search DN'),
                        $searchDN,
                        _('Filter'),
                        $filter
                    )
                );
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
                error_log(
                    sprintf(
                        '%s %s() %s. %s: %s',
                        _('Plugin'),
                        __METHOD__,
                        _('User was not authorized by the LDAP server'),
                        _('User DN'),
                        $userDN
                    )
                );
                return false;
            }
        } else {
            /**
             * Parse the search dn
             */
            $parsedDN = $this->_ldapParseDn($searchDN);
            /**
             * Combine to get the Domain in information.
             */
            $userDomain = implode('.', (array)$parsedDN['DC']);
            /**
             * Setup a multitude of ways to bind
             */
            $userDN = sprintf(
                '%s=%s,%s',
                $usrNamAttr,
                $user,
                $searchDN
            );
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
            /**
             * If our ways here don't work, return immediately
             */
            if (!@$this->bind($userDN, $pass)) {
                $userDN = $userDN1;
            }
            if (!@$this->bind($userDN, $pass)) {
                $userDN = $userDN2;
            }
            if (!@$this->bind($userDN, $pass)) {
                error_log(
                    sprintf(
                        '%s %s() %s.',
                        _('Plugin'),
                        __METHOD__,
                        _('All methods of binding have failed')
                    )
                );
                @$this->unbind();
                return false;
            }
        }
        $attr = array('dn');
        $filter = sprintf(
            '(&(|(objectcategory=person)(objectclass=person))(%s=%s))',
            $usrNamAttr,
            $user
        );
        $result = $this->_result($searchDN, $filter, $attr);
        if (false === $result) {
            error_log(
                sprintf(
                    '%s %s() %s. %s: %s; %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Search DN did not return any results'),
                    _('Search DN'),
                    $searchDN,
                    _('Filter'),
                    $filter
                )
            );
            @$this->unbind();
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
         * If use group match is used, get access level,
         * otherwise group scanning isn't used. Assume all
         * are admins.
         */
        if ($useGroupMatch) {
            $accessLevel = $this->_getAccessLevel($grpMemAttr, $userDN);
        } else {
            $accessLevel = 2;
        }
        /**
         * Close our connection
         */
        @$this->unbind();
        /**
         * If access level is not changed
         *
         * @return bool
         */
        if ($accessLevel == 0) {
            error_log(
                sprintf(
                    '%s %s() %s. %s!',
                    _('Plugin'),
                    __METHOD__,
                    _('Access level is still 0 or false'),
                    _('No access is allowed')
                )
            );
            return false;
        }
        /**
         * Return the access level
         *
         * @return int
         */
        return $accessLevel;
    }
    /**
     * Get's the access level
     *
     * @param string $grpMemAttr the group finder item
     * @param string $userDN     the user dn information
     *
     * @return int
     */
    private function _getAccessLevel($grpMemAttr, $userDN)
    {
        /**
         * Preset our access level value
         */
        $accessLevel = false;
        /**
         * Get our admin group
         */
        $adminGroup = $this->get('adminGroup');
        /**
         * Get our user group
         */
        $userGroup = $this->get('userGroup');
        /**
         * Use search base where the groups are located
         */
        $grpSearchDN = $this->get('grpSearchDN');
        if (!$grpSearchDN) {
            $parsedDN = $this->_ldapParseDn($userDN);
            $grpSearchDN = 'dc='.implode(',dc=', $parsedDN['DC']);
        }
        /**
         * Setup our new filter
         */
        $adminGroups = explode(',', $adminGroup);
        $adminGroups = array_map('trim', $adminGroups);
        $filter = sprintf(
            '(&(|(name=%s))(%s=%s))',
            implode(')(name=', (array)$adminGroups),
            $grpMemAttr,
            $this->escape($userDN, null, LDAP_ESCAPE_FILTER)
        );
        /**
         * The attribute to get.
         */
        $attr = array($grpMemAttr);
        /**
         * Read in the attributes
         */
        $result = $this->_result($grpSearchDN, $filter, $attr);
        if (false !== $result) {
            return 2;
        }
        /**
         * If no record is returned then user is not in the
         * admin group. Change the filter and check the mobile
         * group for membership.
         */
        $userGroups = explode(',', $userGroup);
        $userGroups = array_map('trim', $userGroups);
        $filter = sprintf(
            '(&(|(name=%s))(%s=%s))',
            implode(')(name=', (array)$userGroups),
            $grpMemAttr,
            $this->escape($userDN, null, LDAP_ESCAPE_FILTER)
        );
        /**
         * The attribute to get.
         */
        $attr = array($grpMemAttr);
        /**
         * Execute the ldap query
         */
        $result = $this->_result($grpSearchDN, $filter, $attr);
        /**
         * If no record is returned then lets try the looping method
         */
        if (false !== $result) {
            return 1;
        }
        /**
         * Setup the generalized filter
         */
        $filter = sprintf(
            '(%s=*)',
            $grpMemAttr
        );
        /**
         * The attribute to get.
         */
        $attr = array($grpMemAttr);
        /**
         * Read in the attributes
         */
        $result = $this->_result($grpSearchDN, $filter, $attr);
        /**
         * Return immediately if the result is false
         */
        if (false === $result) {
            error_log(
                sprintf(
                    '%s %s() %s. %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Group Search DN did not return any results'),
                    _('Group Search DN'),
                    $grpSearchDN
                )
            );
            @$this->unbind();
            return false;
        }
        /**
         * Get the entries found
         */
        $entries = $this->get_entries($result);
        /**
         * Setup pattern for later, the i means ignore case
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
            $users = $entry[$grpMemAttr];
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
             * set go back to top of loop.
             */
            if (false === $admin
                && false === $user
            ) {
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
                 * If so, set our access level. We remain in loop
                 * so if another group has this same user as admin
                 * it can get it's proper permissions.
                 */
                if (count($usr) > 0) {
                    $accessLevel = 1;
                }
            }
        }
        /**
         * Return the access level
         */
        return $accessLevel;
    }
    /**
     * Get the results
     *
     * @param string $searchDN the search dn
     * @param string $filter   filter string
     * @param array  $attr     attributes to get
     *
     * @return resource
     */
    private function _result($searchDN, $filter, array $attr)
    {
        /**
         * Search scope
         * 0 = read
         * 1 = list (ls on current directory)
         * 2 = search (ls -R on current directory)
         */
        $searchScope = (int)$this->get('searchScope');
        /**
         * Set our method caller
         */
        switch ($searchScope) {
        case 1:
            $method = 'list';
            break;
        case 2:
            $method = 'search';
            break;
        default:
            $method = 'read';
        }
        /**
         * Ensure our search dn is utf-8 encoded for searching
         */
        $searchDN = mb_convert_encoding($searchDN, 'utf-8');
        /**
         * Get the results
         */
        $result = $this->{$method}($searchDN, $filter, $attr);
        /**
         * Count our entries
         */
        $retcount = $this->count_entries($result);
        /**
         * If multiple entries or no entries return immediately
         */
        if ($retcount < 1) {
            error_log(
                sprintf(
                    '%s %s(). %s: %s; %s: %s; %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Search Method'),
                    $method,
                    _('Filter'),
                    $filter,
                    _('Result'),
                    $retcount
                )
            );
            return false;
        }
        /**
         * Return the result
         */
        return $result;
    }
    /**
     * Gets an item from the key sent, if no key all object data is returned.
     *
     * @param string $key the key to get.
     *
     * @return mixed
     */
    public function get($key = '')
    {
        $keys = array(
            'searchDN',
            'grpSearchDN',
            'bindDN',
            'adminGroup',
            'userGroup'
        );
        if (in_array($key, $keys)) {
            $dn = trim(parent::get($key));
            $dn = strtolower($dn);
            $dn = html_entity_decode(
                $dn,
                ENT_QUOTES,
                'utf-8'
            );
            $dn = mb_convert_case(
                $dn,
                MB_CASE_LOWER,
                'utf-8'
            );
            $this->set($key, $dn);
        }
        return parent::get($key);
    }
}
