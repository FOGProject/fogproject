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
    protected $databaseFields = [
        'id' => 'lsID',
        'name' => 'lsName',
        'description' => 'lsDesc',
        'createdBy' => 'lsCreatedBy',
        'createdTime' => 'lsCreatedTime',
        'address' => 'lsAddress',
        'port' => 'lsPort',
        'searchDN' => 'lsUserSearchDN',
        'userNamAttr' => 'lsUserNamAttr',
        'grpNamAttr' => 'lsGroupNamAttr',
        'grpMemberAttr' => 'lsGrpMemberAttr',
        // lsAdminGroup/lsUserGroup are deliberately absent. The two group
        // buckets were replaced by per-group LDAPGroups mappings, so nothing
        // can write them any more and mapping them here only exposed dead
        // values through the export, the report and the API. The COLUMNS stay
        // (see LDAPManager::createSql): migrateGroupMappings() still folds
        // them into LDAPGroups on the first install after the upgrade, and it
        // reads them with raw SQL rather than through this map.
        'searchScope' => 'lsSearchScope',
        'bindDN' => 'lsBindDN',
        'bindPwd' => 'lsBindPwd',
        'grpSearchDN' => 'lsGrpSearchDN',
        'useGroupMatch' => 'lsUseGroupMatch',
        'displayNameOn' => 'lsDisplayNameEnabled',
        'displayNameAttr' => 'lsDisplayNameAttr',
        'isLdaps' => 'lsIsLDAPs',
        'allowapi' => 'lsAllowAPI',
        // Nested/transitive group membership (#884). Per server rather than
        // global: whether nesting works and what it costs is a property of
        // the directory, and one install can have an AD and an OpenLDAP
        // configured at the same time.
        //
        // 'off' keeps today's direct-only resolution. Nothing reads either
        // of these yet -- the strategies land in their own stories.
        'nestedGroups' => 'lsNestedGroups',
        // 0 means "inherit FOG_PLUGIN_LDAP_NESTED_DEPTH".
        'nestedDepth' => 'lsNestedDepth'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'address',
        'port',
        'searchDN',
        //'userNamAttr',
        //'grpNamAttr',
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
    ];
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
        $nonresourcefuncs = [
            '8859_to_t61',
            'connect',
            'dn2ufn',
            'err2str',
            'escape',
            'explode_dn',
            't61_to_8859',
        ];
        if (!in_array($func, $nonresourcefuncs)) {
            array_unshift($args, self::$_ldapconn);
        }
        return $function(...$args);
    }
    /**
     * Perform unbind and return boolean
     *
     * Assuming true in all error cases.
     */
    public function unbind()
    {
        if (self::$_ldapconn) {
            try {
                return @ldap_unbind(self::$_ldapconn);
            } catch (TypeError $e) {
                error_log(print_r($e, 1));
            } catch (Throwable $e) {
                error_log(print_r($e, 1));
            } finally {
                // Clear the handle whatever happened, so the guard above
                // means "there is a connection to close" rather than "one
                // was opened at some point".
                //
                // Before PHP 8 this was self-correcting: ldap_unbind closed
                // a resource and the stale value was merely useless. Under
                // PHP 8.1+ the handle is an \LDAP\Connection object that
                // stays truthy after closing, so a second unbind() walked
                // straight past the guard into ldap_unbind on a closed
                // connection -- which throws Error, is not suppressed by @,
                // and landed in the catch below as a full print_r dump of
                // the exception. That is ~50 log lines per LDAP sign in,
                // because getDisplayName() opens with a defensive unbind().
                self::$_ldapconn = null;
            }
        }
        return true;
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
        $ldaps = $this->get('isLdaps');
        $port = $this->get('port');
        $ports = explode(',', self::getSetting('FOG_PLUGIN_LDAP_PORTS'));
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
            '%s%s://%s:%s',
            $ldap,
            (
                $ldaps ?
                's' :
                ''
            ),
            $address,
            $port
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
        $out = [];
        /**
         * Loop the parsed information so we get
         * the values in a mroe usable and joinable form.
         */
        foreach ((array)$parser as $key => $value) {
            if (false !== strstr($value, '=')) {
                list(
                    $prefix,
                    $data
                ) = explode('=', $value);
                $prefix = strtoupper($prefix);
                $data = preg_replace_callback(
                    "/\\\([0-9A-Fa-f]{2})/",
                    function ($matches) {
                        foreach ((array)$matches as $match) {
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
        }
        return $out;
    }
    /**
     * Checks and sets the display name based on the displayName
     *
     * @return string
     */
    public function getDisplayName($user, $pass)
    {
        if (!$this->get('displayNameOn')) {
            return trim($user);
        }
        /**
         * Ensure any trailing bindings are removed
         */
        @$this->unbind();

        /**
         * Trim the values just in case somebody is trying
         * to break in by using spaces -- prefent dos attack I imagine.
         */
        $user = trim($user);
        $pass = trim($pass);
        /**
         * User and/or Pass is empty
         *
         * @return string
         */
        if (empty($user)) {
            error_log(_('Username was blank'));
            return $user;
        }
        if (empty($pass)) {
            return $user;
        }
        /**
         * Server is not reachable
         *
         * @return bool
         */
        if (!$server = $this->_ldapUp()) {
            return $user;
        }
        /**
         * Test the username for funky characters and return
         * immediately if found.
         */
        $test = preg_match(
            User::PATTERN,
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
        /**
         * Open connection to the server
         */
        self::$_ldapconn = ldap_connect($server);
        /**
         * If we can't connect return immediately
         */
        if (!self::$_ldapconn) {
            error_log(
                sprintf(
                    '%s %s() %s %s',
                    _('Plugin'),
                    __METHOD__,
                    _('We cannot connect to LDAP server'),
                    $server
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
         * Setup bind dn and password
         */
        $bindDN = $this->get('bindDN');
        /**
         * The bind password.
         */
        $bindPass = $this->get('bindPwd');
        /**
         * Set up our search/group information
         */
        $searchDN = $this->get('searchDN');
        /**
         * Parse our user search DN
         */
        $parsedDN = $this->_ldapParseDn($searchDN);
        /**
         * The user name attribute in use (e.g. uid=)
         */
        $usrNamAttr = strtolower($this->get('userNamAttr'));
        /**
         * The display name Attribute
         */
        $displayNameAttr = strtolower(trim($this->get('displayNameAttr')));
        /**
         * If binddn is set run through it.
         * Of course we don't need to do this if the
         * use group match isn't set.  We do still need
         * to run the main parsing checks.
         */
        if (!empty($bindDN)) {
            /**
             * Trims the bind pass.
             */
            $bindPass = trim($bindPass);
            /**
             * We need to decrypt the stored pass.
             */
            $bindPasstest = self::aesdecrypt($bindPass);
            if ($test_base64 = base64_decode($bindPasstest)) {
                if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                    $bindPass = $test_base64;
                }
            } elseif (mb_detect_encoding($bindPasstest, 'utf-8', true)) {
                $bindPass = $bindPasstest;
            }
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
                        '%s %s() %s %s',
                        _('Plugin'),
                        __METHOD__,
                        _('Cannot bind to the LDAP server'),
                        $server
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
            $attr = ['dn'];
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
        $attr = [$displayNameAttr];
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
        return $entries[0][$displayNameAttr][0];
    }
    /**
     * Removes the server and the group mappings scoped to it.
     *
     * Mappings are per-server by design, so a mapping whose server is gone
     * can never be read again. Deleting them keeps the table from
     * accumulating rows nothing can reach, and stops a later server that
     * happens to reuse this auto-increment id from inheriting them.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @param string $key the key to destroy on
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        $id = (int)$this->get('id');
        if ($id > 0) {
            /**
             * Destroyed one at a time rather than with a mass delete, so
             * each group also takes its role and user group associations
             * with it -- LDAPGroup::destroy() is what knows about those.
             */
            $groupIds = (array)Route::getIds(
                'ldapgroup',
                ['serverID' => $id],
                'id'
            );
            foreach ($groupIds as $groupId) {
                self::getClass('LDAPGroup', (int)$groupId)->destroy();
            }
        }
        return parent::destroy($key);
    }
    /**
     * Authenticates the user against this server.
     *
     * @param string $user the username to authenticate
     * @param string $pass the password to authenticate with
     *
     * @return array|bool the matched mapped group names, or false when the
     *                    server grants this user nothing. An empty array
     *                    means the credential bound but group matching is
     *                    off, so there was nothing to match against.
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
            User::PATTERN,
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
        /**
         * Open connection to the server
         */
        self::$_ldapconn = ldap_connect($server);
        /**
         * If we can't connect return immediately
         */
        if (!self::$_ldapconn) {
            error_log(
                sprintf(
                    '%s %s() %s %s',
                    _('Plugin'),
                    __METHOD__,
                    _('We cannot connect to LDAP server'),
                    $server
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
         * The group name attribute in use (e.g. name=)
         */
        $grpNamAttr = strtolower($this->get('grpNamAttr'));
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
         * Whether the block below reads the user's DN back from the
         * directory. When it does, $userDN already holds the canonical DN
         * and the identical lookup further down is a wasted round trip --
         * slapd's own log shows two identical
         * (&(|(objectcategory=person)(objectclass=person))(uid=x)) queries
         * for a single sign-in, and on a remote directory a round trip is
         * ~35ms.
         *
         * On the else path the DN is *constructed* (uid=user,searchDN, or
         * user@domain, or domain\user) rather than read back, and the
         * guess that happened to bind is not necessarily the canonical DN,
         * so that path still has to look it up.
         */
        $userDNFromSearch = ($useGroupMatch > 0 && !empty($bindDN));
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
            $bindPasstest = self::aesdecrypt($bindPass);
            if ($test_base64 = base64_decode($bindPasstest)) {
                if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                    $bindPass = $test_base64;
                }
            } elseif (mb_detect_encoding($bindPasstest, 'utf-8', true)) {
                $bindPass = $bindPasstest;
            }
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
                        '%s %s() %s %s',
                        _('Plugin'),
                        __METHOD__,
                        _('Cannot bind to the LDAP server'),
                        $server
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
            $attr = ['dn'];
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
        /**
         * If binddn is set run through it.
         * Of course we don't need to do this if the
         * use group match isn't set.  We do still need
         * to run the main parsing checks.
         */
        if (!empty($bindDN)) {
            /**
             * Trims the bind pass.
             */
            $bindPass = trim($bindPass);
            /**
             * We need to decrypt the stored pass.
             */
            $bindPasstest = self::aesdecrypt($bindPass);
            if ($test_base64 = base64_decode($bindPasstest)) {
                if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                    $bindPass = $test_base64;
                }
            } elseif (mb_detect_encoding($bindPasstest, 'utf-8', true)) {
                $bindPass = $bindPasstest;
            }
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
                        '%s %s() %s %s',
                        _('Plugin'),
                        __METHOD__,
                        _('Cannot bind to the LDAP server'),
                        $server
                    )
                );
                return false;
            }
        }
        /**
         * Skipped when the bind-DN path above already read this exact
         * answer back from the directory; see $userDNFromSearch.
         */
        if (!$userDNFromSearch) {
            $attr = ['dn'];
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
        }
        /**
         * The bind above already proved the identity. What is left is
         * "which of this server's mapped groups is this user in?", which
         * only means anything when group matching is switched on.
         *
         * With group matching off we cannot enumerate groups at all, so
         * the empty array below is not "matched nothing" -- it is "there
         * was nothing to match against". The caller separates the two by
         * reading useGroupMatch, and gives that case the single configured
         * fallback role (FOG_PLUGIN_LDAP_NOMATCH_ROLE).
         */
        if (!$useGroupMatch) {
            @$this->unbind();
            return [];
        }
        $matched = $this->_getMatchedGroups(
            $grpNamAttr,
            $grpMemAttr,
            $userDN,
            $user
        );
        /**
         * Close our connection
         */
        @$this->unbind();
        /**
         * Group matching is on and the user is in none of the mapped
         * groups, so this server grants nothing. Denying here preserves
         * the old accessLevel == 0 behaviour: a bind alone has never been
         * enough when the server is configured to check groups.
         */
        if (empty($matched)) {
            error_log(
                sprintf(
                    '%s %s() %s. %s!',
                    _('Plugin'),
                    __METHOD__,
                    _('User matched none of the mapped groups'),
                    _('No access is allowed')
                )
            );
            return false;
        }
        /**
         * Return the group names that matched
         *
         * @return array
         */
        return $matched;
    }
    /**
     * The directory group names mapped to something on this server.
     *
     * Read with raw bound SQL rather than Route::getIds() on purpose:
     * _buildSql() rewrites '*' and '+' in a scalar filter value into a SQL
     * LIKE wildcard, and both are legal in an LDAP group name -- '+'
     * separates the components of a multi-valued RDN. A group called
     * "Techs+Chicago" would otherwise silently match rows it must not.
     *
     * @return array the distinct group names, empty when none are mapped
     */
    private function _mappedGroupNames()
    {
        try {
            $rows = self::$DB
                ->query(
                    'SELECT `lgName` FROM `LDAPGroups` '
                    . 'WHERE `lgServerID` = :server',
                    [],
                    ['server' => (int)$this->get('id')]
                )
                ->fetch('', 'fetch_all')
                ->get();
        } catch (Exception $e) {
            error_log(
                sprintf(
                    '%s %s() %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Could not read the group mappings'),
                    $e->getMessage()
                )
            );
            return [];
        }
        /**
         * PDODB reports a failed query as false rather than throwing
         * (throwOnQueryError is off), and (array)false is [false], not [].
         * Normalise before iterating or a missing table becomes a bogus
         * row instead of no rows.
         */
        if (!is_array($rows)) {
            return [];
        }
        $names = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['lgName'] ?? ''));
            if ('' !== $name) {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }
    /**
     * Which of this server's mapped groups the user belongs to.
     *
     * This replaces _getAccessLevel(), which answered the narrower
     * question "is this user an admin, a plain user, or neither?" by
     * running the same membership query twice -- once against the admin
     * group list, once against the user group list -- and collapsing the
     * answer to 2, 1 or 0. That shape could only ever express two tiers.
     *
     * Returning the matched names instead costs nothing: the old code
     * already OR'd a comma-separated list of names into one filter, so
     * asking for the group name attribute back turns two scalar queries
     * into one that says which names matched. Everything above this is
     * then free to treat membership as additive, the way RBAC does.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @param string $grpNamAttr the group name item
     * @param string $grpMemAttr the group finder item
     * @param string $userDN     the user dn information
     * @param string $user       the actual username
     *
     * @return array the mapped group names the user is a member of
     */
    private function _getMatchedGroups($grpNamAttr, $grpMemAttr, $userDN, $user)
    {
        $mapped = $this->_mappedGroupNames();
        /**
         * Nothing is mapped, so nothing can match. Bail before querying
         * the directory rather than building a filter with an empty OR.
         */
        if (empty($mapped)) {
            return [];
        }
        /**
         * The user name attribute in use (e.g. uid=)
         */
        $usrNamAttr = strtolower($this->get('userNamAttr'));
        /**
         * Use search base where the groups are located
         */
        $grpSearchDN = $this->get('grpSearchDN');
        if (!$grpSearchDN) {
            $parsedDN = $this->_ldapParseDn($userDN);
            $grpSearchDN = 'dc='
                . implode(',dc=', $parsedDN['DC']);
        }
        /**
         * Nesting inverts the query, so it cannot share the filter below.
         *
         * The filter below ORs the *mapped* names in, which is only correct
         * while membership is direct. The group that bridges a user to a
         * mapped group is usually not itself mapped -- in
         * "all-staff -> all-techs -> chicago-techs -> alice" nobody maps
         * "chicago-techs" -- so a filter that names the mapped groups up
         * front can never reach alice. _expandGroups() therefore discovers
         * real membership first and intersects against the mapped names at
         * the end.
         *
         * 'chain' pushes transitivity into the directory and keeps the
         * single-query shape, so it stays with the filter below once it
         * lands (story 3 of #884). Until then it resolves through the walk
         * rather than silently degrading to direct-only: the admin asked
         * for nesting, and answering with no nesting at all is the exact
         * failure #884 exists to kill. Logged so it is not silent either.
         *
         * Refs https://github.com/FOGProject/fogproject/issues/884
         */
        $nested = (string)$this->get('nestedGroups');
        if ('chain' === $nested) {
            error_log(
                sprintf(
                    '%s %s() %s. %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('The chain strategy is not implemented yet, '
                    . 'resolving nesting with expand instead'),
                    _('LDAP Server'),
                    $this->get('name')
                )
            );
            $nested = 'expand';
        }
        if ('expand' === $nested) {
            return $this->_expandGroups(
                $grpNamAttr,
                $grpMemAttr,
                $grpSearchDN,
                $userDN,
                $user,
                $mapped
            );
        }
        /**
         * Group filter layout should be consistent across
         * the board.
         *
         * Group names are escaped here where the two-bucket version did
         * not escape them. They now come from an admin-editable table
         * rather than a settings string, and an unescaped '*' or ')' in a
         * name would otherwise alter the filter's meaning.
         *
         * The second argument is '' rather than null throughout this file:
         * ldap_escape()'s $ignore is a non-nullable string, and passing null
         * to a non-nullable internal parameter is deprecated in PHP 8.1+ and
         * is slated to become a TypeError. '' is what null coerced to
         * anyway, so this is the same escape with no notice attached.
         */
        $grpNamAttr_forimplode = ')(' . $grpNamAttr . '=';
        $escaped = [];
        foreach ($mapped as $name) {
            $escaped[] = $this->escape($name, '', LDAP_ESCAPE_FILTER);
        }
        $filter = sprintf(
            '(&(|(%s=%s))(|(%s=%s)(%s=%s=%s)(%s=%s)))',
            $grpNamAttr,
            implode($grpNamAttr_forimplode, $escaped),
            $grpMemAttr,
            $this->escape($userDN, '', LDAP_ESCAPE_FILTER),
            $grpMemAttr,
            $usrNamAttr,
            $this->escape($user, '', LDAP_ESCAPE_FILTER),
            $grpMemAttr,
            $this->escape($user, '', LDAP_ESCAPE_FILTER)
        );
        /**
         * Ask for the name back -- that is what turns "did anything match"
         * into "which ones matched".
         */
        $attr = [$grpNamAttr, $grpMemAttr];
        /**
         * Read in the attributes
         */
        $result = $this->_result($grpSearchDN, $filter, $attr);
        if (false !== $result) {
            $matched = $this->_namesFromEntries(
                $this->get_entries($result),
                $grpNamAttr,
                $mapped
            );
            if (!empty($matched)) {
                return $matched;
            }
        }
        /**
         * The filter returned nothing usable, so fall back to the looping
         * method. This path is kept because some directories do not answer
         * the compound filter above -- it is the same fallback the
         * two-bucket version had, generalised from two names to N.
         *
         * Setup the generalized filter
         */
        $filter = sprintf(
            '(%s=*)',
            $grpMemAttr
        );
        /**
         * The attribute to get.
         */
        $attr = [$grpNamAttr, $grpMemAttr];
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
            return [];
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
            preg_quote($userDN, '#')
        );
        /**
         * Check groups for membership
         */
        $matched = [];
        foreach ((array)$entries as $entry) {
            /**
             * If this cycle doesn't have the dn, skip it
             */
            if (!isset($entry['dn'])) {
                continue;
            }
            /**
             * Which mapped names this group's dn corresponds to. The
             * substring test is what the two-bucket version used, kept so
             * directories that name a group only in its DN still match.
             */
            $dn = $entry['dn'];
            $hits = [];
            foreach ($mapped as $name) {
                if (false !== stripos($dn, $name)) {
                    $hits[] = $name;
                }
            }
            if (empty($hits)) {
                continue;
            }
            /**
             * Test if the user dn exists in this group. Unlike the tiered
             * version there is no early break: every group the user is in
             * contributes, because the targets are additive.
             */
            $users = $entry[$grpMemAttr] ?? [];
            $found = array_filter(preg_grep($pat, (array)$users));
            if (count($found) > 0) {
                $matched = array_merge($matched, $hits);
            }
        }
        return array_values(array_unique($matched));
    }
    /**
     * How many levels the nested walk may descend on this server.
     *
     * Per-server override wins, 0 there means "inherit the global". The
     * literal at the bottom is not a third setting: it is there because an
     * absent or zero global would otherwise make `expand` resolve nothing
     * at all, which is the silent no-op #884 exists to kill.
     *
     * @return int always at least 1
     */
    private function _nestedDepth()
    {
        $depth = (int)$this->get('nestedDepth');
        if ($depth < 1) {
            $depth = (int)self::getSetting('FOG_PLUGIN_LDAP_NESTED_DEPTH');
        }
        if ($depth < 1) {
            $depth = 10;
        }
        return $depth;
    }
    /**
     * Walks the group tree upwards from the user and reports the mapped
     * groups reached, however many hops away they are.
     *
     * One query per level: the whole frontier is OR'd into a single filter
     * rather than queried group by group, because a round trip is the
     * expensive part (0.26 ms on loopback vs 36.66 ms to a remote
     * directory). Measured, this is not a worry: OpenLDAP answered a
     * 1.3 MB / 25,000-clause filter in 107 ms and Samba took 2,000
     * clauses, so the visited-set and the depth cap bound this long before
     * filter size does. Hence no breadth chunking and no breadth cap.
     *
     * Deliberately additive and deliberately widening: a parent group's
     * role is granted to everyone beneath it, including users who already
     * matched directly. There is no "only if nothing else matched" carve
     * out, because that would make one user's roles depend on their other
     * memberships.
     *
     * `memberUid`-style groups (posixGroup) stay direct-only here, and by
     * schema rather than by choice -- memberUid holds bare usernames, so it
     * cannot express a group inside a group. Levels 1+ still cost one
     * query each against such a server; they simply match nothing.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/884
     *
     * @param string $grpNamAttr  the group name item
     * @param string $grpMemAttr  the group finder item
     * @param string $grpSearchDN where the groups live
     * @param string $userDN      the user dn information
     * @param string $user        the actual username
     * @param array  $mapped      this server's mapped group names
     *
     * @return array the mapped group names the user reaches
     */
    private function _expandGroups(
        $grpNamAttr,
        $grpMemAttr,
        $grpSearchDN,
        $userDN,
        $user,
        array $mapped
    ) {
        /**
         * The user name attribute in use (e.g. uid=)
         */
        $usrNamAttr = strtolower($this->get('userNamAttr'));
        $cap = $this->_nestedDepth();
        $attr = [$grpNamAttr, $grpMemAttr];
        /**
         * Level 0 seeds all three member forms the direct filter tests --
         * full DN, attribute-qualified name, bare name. That is what makes
         * `expand` a strict superset of `off`: switching nesting on can
         * add access but can never take any away. Levels 1+ can only ever
         * be DNs, because that is what the directory returns.
         */
        $frontier = [
            sprintf(
                '(%s=%s)',
                $grpMemAttr,
                $this->escape($userDN, '', LDAP_ESCAPE_FILTER)
            ),
            sprintf(
                '(%s=%s=%s)',
                $grpMemAttr,
                $usrNamAttr,
                $this->escape($user, '', LDAP_ESCAPE_FILTER)
            ),
            sprintf(
                '(%s=%s)',
                $grpMemAttr,
                $this->escape($user, '', LDAP_ESCAPE_FILTER)
            )
        ];
        $visited = [];
        $discovered = [];
        for ($level = 0; $level < $cap && !empty($frontier); $level++) {
            $filter = '(|' . implode('', $frontier) . ')';
            $result = $this->_rawSearch($grpSearchDN, $filter, $attr);
            /**
             * The search itself failed, which is not the same as "this
             * level has no parents". Stop walking and grant what was
             * already proven rather than inventing the rest -- if level 0
             * is what failed, nothing is proven and the caller denies.
             */
            if (false === $result) {
                error_log(
                    sprintf(
                        '%s %s() %s. %s: %s; %s: %s',
                        _('Plugin'),
                        __METHOD__,
                        _('Nested group search failed'),
                        _('LDAP Server'),
                        $this->get('name'),
                        _('Level'),
                        $level
                    )
                );
                break;
            }
            $entries = (array)$this->get_entries($result);
            $count = (int)($entries['count'] ?? 0);
            $frontier = [];
            for ($i = 0; $i < $count; $i++) {
                $dn = (string)($entries[$i]['dn'] ?? '');
                if ('' === $dn) {
                    continue;
                }
                /**
                 * Keyed on the DN, lower-cased because DN comparison is
                 * case insensitive and a directory that answers with a
                 * different spelling at a different level would otherwise
                 * re-walk the same group.
                 *
                 * Marked at discovery rather than at expansion, which is
                 * what makes a diamond cost one query instead of two: both
                 * paths into a group see it as visited the moment the first
                 * one finds it. There is no separate cycle check because
                 * this *is* the cycle handling -- cycle-a <-> cycle-b
                 * terminates here, not at the depth cap.
                 */
                $key = strtolower($dn);
                if (isset($visited[$key])) {
                    continue;
                }
                $visited[$key] = true;
                $discovered[] = $entries[$i];
                $frontier[] = sprintf(
                    '(%s=%s)',
                    $grpMemAttr,
                    $this->escape($dn, '', LDAP_ESCAPE_FILTER)
                );
            }
        }
        /**
         * Still groups left to expand means the cap stopped the walk, so
         * the answer below is incomplete and somebody is missing access
         * they were configured to have. Say so loudly, naming what to
         * raise: silent truncation is exactly the failure mode #884 was
         * filed about.
         */
        if (!empty($frontier)) {
            error_log(
                sprintf(
                    '%s %s() %s. %s: %s; %s: %s; %s: %d',
                    _('Plugin'),
                    __METHOD__,
                    _('Nested group depth cap reached, so group '
                    . 'membership may be incomplete'),
                    _('LDAP Server'),
                    $this->get('name'),
                    _('Username'),
                    $user,
                    _('Depth'),
                    $cap
                )
            );
        }
        /**
         * Membership is already proven by the queries above, so all that
         * is left is which of the discovered groups are mapped. The DN
         * substring test is on because it is the crutch the `(member=*)`
         * fallback offers today for directories that only name a group in
         * its DN, and `expand` never reaches that fallback -- it would
         * otherwise be the one thing nesting took away. It costs no extra
         * query here; the groups are already in hand.
         */
        return $this->_namesFromEntries(
            $discovered,
            $grpNamAttr,
            $mapped,
            true
        );
    }
    /**
     * Pulls the mapped group names out of a set of directory entries.
     *
     * The directory decides the spelling it returns, so a name is matched
     * case-insensitively and then reported using the spelling stored in
     * the mapping table -- that is the one the lookup in
     * LDAPPluginHook has to match against.
     *
     * @param array  $entries    entries as returned by get_entries()
     * @param string $grpNamAttr the group name attribute
     * @param array  $mapped     the mapped group names to match against
     * @param bool   $matchDn    also match a mapped name as a DN substring
     *
     * @return array
     */
    private function _namesFromEntries(
        $entries,
        $grpNamAttr,
        array $mapped,
        $matchDn = false
    ) {
        $lookup = [];
        foreach ($mapped as $name) {
            $lookup[strtolower($name)] = $name;
        }
        $matched = [];
        foreach ((array)$entries as $entry) {
            /**
             * The DN test is the same loose substring compare the
             * `(member=*)` fallback in _getMatchedGroups() has always used,
             * and it is off unless a caller asks for it -- the mapped-name
             * filter path must keep answering exactly what it answers
             * today.
             */
            if ($matchDn && isset($entry['dn'])) {
                foreach ($mapped as $name) {
                    if (false !== stripos((string)$entry['dn'], $name)) {
                        $matched[] = $name;
                    }
                }
            }
            if (!isset($entry[$grpNamAttr])) {
                continue;
            }
            foreach ((array)$entry[$grpNamAttr] as $key => $value) {
                /**
                 * get_entries() puts an item count alongside the values.
                 */
                if ('count' === $key) {
                    continue;
                }
                $key = strtolower(trim((string)$value));
                if (isset($lookup[$key])) {
                    $matched[] = $lookup[$key];
                }
            }
        }
        return array_values(array_unique($matched));
    }
    /**
     * The ldap_* read function this server's search scope asks for.
     *
     * Split out of _result() so the nested-group walk can run a search
     * under the admin's configured scope without inheriting _result()'s
     * "no entries means failure" verdict; see _expandGroups().
     *
     * Search scope
     * 0 = read
     * 1 = list (ls on current directory)
     * 2 = search (ls -R on current directory)
     *
     * @return string the back half of the ldap_ function name
     */
    private function _searchMethod()
    {
        switch ((int)$this->get('searchScope')) {
            case 1:
                return 'list';
            case 2:
                return 'search';
        }
        return 'read';
    }
    /**
     * Runs one search and hands back whatever the directory said.
     *
     * No interpretation: false means the search itself failed, a result
     * with zero entries means the filter matched nothing. _result()
     * collapses those two into false, which is fine for the callers that
     * only need "did I get my one entry", but the nested walk has to tell
     * "this level has no parents" from "the query broke".
     *
     * @param string $searchDN the search dn
     * @param string $filter   filter string
     * @param array  $attr     attributes to get
     *
     * @return resource|false
     */
    private function _rawSearch($searchDN, $filter, array $attr)
    {
        /**
         * Ensure our search dn is utf-8 encoded for searching
         */
        $searchDN = mb_convert_encoding($searchDN, 'utf-8');
        $method = $this->_searchMethod();
        return $this->{$method}($searchDN, $filter, $attr);
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
        $method = $this->_searchMethod();
        /**
         * Get the results
         */
        $result = $this->_rawSearch($searchDN, $filter, $attr);
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
        $keys = [
            'searchDN',
            'grpSearchDN',
            'bindDN'
        ];
        if (in_array($key, $keys)) {
            $dn = trim(parent::get($key));
            $dn = strtolower($dn);
            $dn = html_entity_decode(
                $dn,
                ENT_QUOTES | ENT_HTML401,
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
