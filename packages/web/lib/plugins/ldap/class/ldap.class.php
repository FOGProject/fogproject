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
     * The matching rule that makes a member= test transitive.
     *
     * LDAP_MATCHING_RULE_IN_CHAIN. Active Directory only, and it only means
     * anything against a DN value -- see _getMatchedGroups().
     *
     * @var string
     */
    const CHAIN_MATCHING_RULE = '1.2.840.113556.1.4.1941';
    /**
     * The rootDSE supportedCapabilities OID that says the rule above works.
     *
     * LDAP_CAP_ACTIVE_DIRECTORY_OID. Measured to discriminate correctly:
     * Samba AD advertises it, OpenLDAP and ldap.forumsys.com advertise no
     * capabilities at all.
     *
     * @var string
     */
    const CHAIN_CAPABILITY = '1.2.840.113556.1.4.800';
    /**
     * The legal lsTlsVerify values, and the authority on them.
     *
     * The model owns this list rather than the management page because save()
     * enforces it for every writer, and because it has to stay in step with
     * the column's ENUM. The page's label map is presentation only and keys
     * off these values.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/893
     *
     * @var array
     */
    const TLS_VERIFY_LEVELS = ['inherit', 'hard', 'never'];
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
        'nestedDepth' => 'lsNestedDepth',
        // LDAPS certificate verification, per server (#893). 'inherit' means
        // leave ldap.conf's TLS_REQCERT alone, which is what the plugin has
        // always done; 'hard' and 'never' override it for this server only.
        'tlsVerify' => 'lsTlsVerify',
        // A CA file (or directory) to trust for this server. Empty means use
        // whatever the system already trusts.
        'tlsCaCert' => 'lsTlsCaCert'
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
     * The process-inherited TLS verification level, read once per request.
     *
     * @var int|null
     */
    private static $_tlsBaseline;
    /**
     * The inherited verification level, so 'inherit' can be honoured.
     *
     * These options are process-global in the OpenLDAP client, and
     * ldap_get_option() refuses a null handle, so the only way to learn what
     * the process started with is to ask a connection before anything has
     * overridden it. ldap_connect() does no network I/O -- it just parses
     * the URI -- so a throwaway handle is cheap and cannot fail on an
     * unreachable directory.
     *
     * Cached because it must be the value from *before* the first override:
     * reading it again later would return whatever the previous server set.
     *
     * @return int
     */
    private static function _tlsBaseline()
    {
        if (null === self::$_tlsBaseline) {
            $level = null;
            $probe = @ldap_connect('ldap://127.0.0.1');
            if ($probe
                && @ldap_get_option($probe, LDAP_OPT_X_TLS_REQUIRE_CERT, $level)
                && null !== $level
            ) {
                self::$_tlsBaseline = (int)$level;
            } else {
                /**
                 * Could not read it, so assume the strict end rather than the
                 * lax one. Guessing 'never' here would silently disable
                 * verification for every server set to 'inherit'.
                 */
                self::$_tlsBaseline = LDAP_OPT_X_TLS_HARD;
            }
        }
        return self::$_tlsBaseline;
    }
    /**
     * Applies this server's LDAPS certificate settings before connecting.
     *
     * Must be called immediately before every ldap_connect(), and must set
     * the options *unconditionally* rather than only when this server asks
     * for something unusual. Both options are process-global and authLDAP()
     * walks every configured server in one request, so leaving a previous
     * server's value in place would let one server's relaxed verification
     * silently apply to the next one -- measured: after setting the global to
     * HARD, the following connection reads HARD.
     *
     * Setting them on the connection handle instead does not work. The
     * OpenLDAP client reads these at handle-creation time, so a
     * post-connect set is accepted and then ignored, which is the trap that
     * made this look like an unreachable server rather than a TLS failure.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/893
     *
     * @param string $verify  inherit, hard or never
     * @param string $caCert  a CA file/directory to trust, or empty
     *
     * @return void
     */
    private static function _applyTlsOptions($verify, $caCert)
    {
        switch ((string)$verify) {
            case 'never':
                $level = LDAP_OPT_X_TLS_NEVER;
                break;
            case 'hard':
                $level = LDAP_OPT_X_TLS_HARD;
                break;
            default:
                $level = self::_tlsBaseline();
        }
        @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $level);
        /**
         * The CA path, unlike the level above, is set ONLY when this server
         * names one, and deliberately so.
         *
         * ldap_get_option() reports nothing for CACERTFILE, so there is no
         * baseline to restore and '' is the only available reset -- but
         * ldap.conf may legitimately carry a TLS_CACERT, and blanking it on
         * every connect would break installs that rely on it. Breaking a
         * working configuration is worse than the alternative.
         *
         * The alternative, accepted knowingly: a CA named by one server stays
         * set for a later server in the same request that names none. That
         * leak only ever *adds* a trusted issuer the admin configured
         * elsewhere in this same install -- it cannot disable verification,
         * because the level above is always set explicitly. Naming a CA on
         * each server that needs one avoids it entirely.
         */
        $caCert = trim((string)$caCert);
        if ('' !== $caCert) {
            @ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caCert);
        }
        if ('' !== $caCert && !is_readable($caCert)) {
            error_log(
                sprintf(
                    '%s %s() %s. %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _(
                        'The configured LDAPS CA path cannot be read by the '
                        . 'web server, so certificate verification will '
                        . 'likely fail'
                    ),
                    _('CA Path'),
                    $caCert
                )
            );
        }
    }
    /**
     * Stores the server, refusing a chain strategy the directory cannot do.
     *
     * The guard lives here rather than in the add/edit post handlers because
     * the REST API reaches this table without going through them: a
     * PUT /fog/ldap/<id>/edit carrying {"nestedGroups":"chain"} was verified
     * to store chain against an OpenLDAP server, where the matching rule
     * matches nothing and every nested sign-in silently fails. Validating on
     * the way into the row covers every writer rather than the one that
     * remembered to ask -- the same reasoning as RolePermission::save().
     *
     * Refs https://github.com/FOGProject/fogproject/issues/884
     *
     * @throws Exception
     * @return bool
     */
    public function save()
    {
        $this->_assertChainSupported();
        $this->_assertTlsVerifyValid();
        // Trim before validating and storing: a trailing space makes the path
        // unreadable, and an unreadable CA path takes the whole directory
        // offline (see assertValidCaCertPath()).
        $this->set('tlsCaCert', trim((string)$this->get('tlsCaCert')));
        self::assertValidCaCertPath($this->get('tlsCaCert'));
        return parent::save();
    }
    /**
     * Throws if a CA certificate path is one we know cannot work.
     *
     * The single authority on the rule: LDAP::save() calls it so every writer
     * is covered, and the management page calls it too so the message lands on
     * the form next to the field the admin just typed in. Public static
     * because the page validates a POST before any object exists.
     *
     * This matters more than it looks. An unreadable CACERTFILE does not
     * merely fail verification -- it makes the following ldap_connect()
     * return false outright, **at every verification level including
     * 'never'**. Measured: storing a relative path on the AD fixture dropped
     * that server out of authentication completely, and the only symptom was
     * users quietly losing the roles it granted. One unvalidated REST write
     * could take a directory offline.
     *
     * Absolute only, because this path is read inside the web server process:
     * a relative path resolves against php-fpm's working directory, which
     * differs between the Apache and nginx deployments and which an admin
     * cannot see. Length-capped because the column is VARCHAR(255) and a
     * truncated path points at nothing.
     *
     * Readability is deliberately NOT checked here. php-fpm may run as a
     * different user than the one that placed the file (GH-849), and admins
     * configure a server before its certificate is in place -- the same
     * reasoning that lets an unverifiable chain strategy save.
     * _applyTlsOptions() logs that case at connect time.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/893
     *
     * @param string $caCert the path to check; empty is always valid
     *
     * @throws Exception
     * @return void
     */
    public static function assertValidCaCertPath($caCert)
    {
        $caCert = trim((string)$caCert);
        if ('' === $caCert) {
            return;
        }
        if ('/' !== $caCert[0]) {
            throw new Exception(
                _('The CA certificate path must be absolute')
            );
        }
        if (strlen($caCert) > 255) {
            throw new Exception(
                _('The CA certificate path is too long (255 characters max)')
            );
        }
    }
    /**
     * Throws if tlsVerify is not one of the levels the column allows.
     *
     * Here rather than only in the management page's _tlsFromPost() for the
     * same reason _assertChainSupported() is: the REST API reaches this table
     * without going through the form. A PUT carrying {"tlsVerify":"garbage"}
     * lands on a non-strict MySQL as '', which reads back as the column
     * default -- silently relaxing verification on a server an admin had
     * deliberately set to 'hard'. Validating on the way into the row covers
     * every writer rather than the one that remembered to ask.
     *
     * Blank is normalised rather than refused. It is what a pre-#893 row or an
     * earlier non-strict write leaves behind, and throwing on it would make an
     * existing row impossible to save at all -- including impossible to fix.
     * Normalising to 'inherit' matches the column default, so nothing changes
     * behaviour; it only stops '' propagating.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/893
     *
     * @throws Exception
     * @return void
     */
    private function _assertTlsVerifyValid()
    {
        $verify = trim((string)$this->get('tlsVerify'));
        if ('' === $verify) {
            $this->set('tlsVerify', 'inherit');
            return;
        }
        if (!in_array($verify, self::TLS_VERIFY_LEVELS, true)) {
            throw new Exception(
                sprintf(
                    /* translators: %s is the rejected value */
                    _('Invalid certificate verification level: %s'),
                    $verify
                )
            );
        }
    }
    /**
     * Throws if this server is set to chain but the directory cannot chain.
     *
     * Only a *successful* rootDSE read that lacks the capability is treated
     * as a refusal. If the directory could not be read at all -- down, wrong
     * port, TLS refused -- absence is not proven, so the save proceeds with
     * a log line: admins routinely configure a server before it is
     * reachable, and blocking that would make the plugin unconfigurable for
     * a transient reason.
     *
     * @throws Exception
     * @return void
     */
    private function _assertChainSupported()
    {
        if ('chain' !== (string)$this->get('nestedGroups')) {
            return;
        }
        $supported = self::supportsChain(
            $this->get('address'),
            $this->get('port'),
            $this->get('isLdaps'),
            $this->get('tlsVerify'),
            $this->get('tlsCaCert')
        );
        if (false === $supported) {
            throw new Exception(
                _(
                    'This directory does not advertise support for nested '
                    . 'group chaining, so the chain strategy would match '
                    . 'nothing. Use the expand strategy instead.'
                )
            );
        }
        if (null === $supported) {
            error_log(
                sprintf(
                    '%s %s() %s. %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _(
                        'Could not read the directory to confirm it supports '
                        . 'nested group chaining, saving the chain strategy '
                        . 'unverified'
                    ),
                    _('LDAP Server'),
                    $this->get('name')
                )
            );
        }
    }
    /**
     * Asks a directory whether it implements the chain matching rule.
     *
     * Reads rootDSE **anonymously** -- no bind. Active Directory allows that
     * by design (it is how a client discovers the domain), and it means the
     * answer does not depend on the bind credentials being correct yet, nor
     * on Samba's default refusal of a simple bind over plaintext. Verified
     * against the AD fixture over plain LDAP with no bind at all.
     *
     * Static, and using the raw ldap_* functions rather than this class's
     * __call() wrapper, so probing cannot disturb self::$_ldapconn -- the
     * page needs an answer while validating a POST, before any server object
     * exists to hold a connection.
     *
     * @param string $address the directory address
     * @param int    $port    the port to reach it on
     * @param bool   $isLdaps whether that port speaks ldaps
     * @param string $verify  this server's tls verification level
     * @param string $caCert  this server's tls CA path
     *
     * @return bool|null true supported, false not, null undeterminable
     */
    public static function supportsChain(
        $address,
        $port,
        $isLdaps,
        $verify = 'inherit',
        $caCert = ''
    ) {
        $address = trim((string)$address);
        if ('' === $address) {
            return null;
        }
        $uri = sprintf(
            'ldap%s://%s:%d',
            ($isLdaps ? 's' : ''),
            $address,
            (int)$port
        );
        /**
         * The probe has to trust the certificate on the same terms the real
         * sign-in will, or a server perfectly reachable over LDAPS answers
         * "undeterminable" and its chain setting goes in unverified.
         */
        self::_applyTlsOptions($verify, $caCert);
        $conn = @ldap_connect($uri);
        if (!$conn) {
            return null;
        }
        @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        /**
         * Bounded so an unreachable directory cannot hang the save.
         */
        @ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 3);
        $result = @ldap_read(
            $conn,
            '',
            '(objectclass=*)',
            ['supportedCapabilities']
        );
        if (false === $result) {
            @ldap_unbind($conn);
            return null;
        }
        $entries = (array)@ldap_get_entries($conn, $result);
        @ldap_unbind($conn);
        if (empty($entries['count'])) {
            return null;
        }
        $caps = (array)($entries[0]['supportedcapabilities'] ?? []);
        unset($caps['count']);
        return in_array(self::CHAIN_CAPABILITY, $caps);
    }
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
        /**
         * Immediately before the connect, never after: the OpenLDAP client
         * reads the TLS options when the handle is created (#893).
         */
        self::_applyTlsOptions(
            $this->get('tlsVerify'),
            $this->get('tlsCaCert')
        );
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
        /**
         * Immediately before the connect, never after: the OpenLDAP client
         * reads the TLS options when the handle is created (#893).
         */
        self::_applyTlsOptions(
            $this->get('tlsVerify'),
            $this->get('tlsCaCert')
        );
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
         * 'chain' keeps the filter below exactly as it is and lets the
         * directory resolve transitivity server-side, so it stays a single
         * query -- see the matching rule applied below.
         *
         * Refs https://github.com/FOGProject/fogproject/issues/884
         */
        $nested = (string)$this->get('nestedGroups');
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
        /**
         * Under 'chain' the matching rule is appended to the member
         * attribute in the FULL-DN alternative only, which is what makes the
         * one query below transitive.
         *
         * Not the other two alternatives: the rule walks a DN through the
         * directory, so it is meaningless against a bare or
         * attribute-qualified username. 1.5 applies it to the qualified form
         * as well, which cannot match anything. Leaving those two direct is
         * also what keeps chain a superset of off.
         */
        $chainMemAttr = $grpMemAttr;
        if ('chain' === $nested) {
            $chainMemAttr .= ':' . self::CHAIN_MATCHING_RULE . ':';
        }
        $filter = sprintf(
            '(&(|(%s=%s))(|(%s=%s)(%s=%s=%s)(%s=%s)))',
            $grpNamAttr,
            implode($grpNamAttr_forimplode, $escaped),
            $chainMemAttr,
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
