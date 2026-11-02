<?php
/**
 * A small LDAP client for the operations FOG performs on a directory.
 *
 * PHP version 7.4+
 *
 * @category Net
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Net;

/**
 * A small LDAP client for the operations FOG performs on a directory.
 *
 * A protocol client, which is why it lives beside FOGFTP and FOGSSH rather
 * than with the directory-membership classes: it knows how to talk LDAP and
 * nothing about hosts, OUs or what FOG wants. The policy -- which object
 * should be where, and what to do when it is not -- is
 * Agent\DirectoryPlacement.
 *
 * Distinct from the `ldap` plugin, which authenticates FOG's USERS against a
 * directory. Different directory potentially, different account certainly:
 * that one reads people, this one moves computer objects, and an install
 * with no plugin still needs this.
 *
 * Standalone, like FOGFTP, FOGSSH and Ping beside it -- none of them extend
 * FOGBase, and a protocol client has no use for a settings cache or a
 * database handle. It also keeps four thousand lines of inherited method
 * names from colliding with the natural ones for a client object: FOGBase
 * already has a static error() and a static lasterror(), and a non-static
 * method of either name is a fatal error the moment the class loads.
 *
 * ext-ldap is a hard dependency of a FOG install -- every supported distro's
 * package list carries php-ldap and the installer uncomments the extension
 * -- so there is no "if available" path here.
 *
 * @category Net
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGLdap
{
    /**
     * Seconds to wait for the directory, connecting and searching alike.
     *
     * Short on purpose. This runs inside a host's poll, and a directory
     * that has gone away must cost that host a few seconds and a recorded
     * error, never its check-in.
     */
    const TIMEOUT = 5;

    /**
     * Seconds a Move may wait for the directory's answer.
     *
     * Without a bound a hung Modify DN holds the worker, and its MAY_HAVE_RUN
     * row, for as long as the server stalls. DirectoryPlacementIntent keeps
     * MAY_HAVE_RUN rows from an operator's clear for longer than this plus a
     * margin, so the clear window is real.
     */
    const MOVE_TIMEOUT = 60;

    /**
     * The connection, once bound.
     *
     * ldap_connect() hands back a resource on PHP 7.4 and an LDAP\Connection
     * object from 8.1 on. The analyzed range starts at 7.4, where that class
     * does not exist, so it cannot be named here -- phpstan.neon pins
     * phpVersion.min at 70400 and reports it as an unknown class.
     *
     * @var resource|null
     */
    private $_ld = null;

    /**
     * The last error, in words.
     *
     * @var string
     */
    private $_error = '';

    /**
     * What the last moveTo() learned about whether its request reached the
     * directory: kind is not_sent, request_result_unknown, ldap_rejection,
     * local_or_transport_error or ldap_success, and code is the LDAP error
     * number when there is one.
     *
     * @var array{kind:string,code:int|null,message?:string}
     */
    private $_moveOutcome = ['kind' => 'not_sent', 'code' => null];

    /**
     * What the last moveTo() learned about its own request.
     *
     * A false return alone cannot tell "the directory refused" from "the
     * request may have been applied". The placement intent record needs that
     * difference, so it keeps this beside the result.
     *
     * @return array{kind:string,code:int|null,message?:string}
     */
    public function moveOutcome()
    {
        return $this->_moveOutcome;
    }

    /**
     * Opens and binds a connection.
     *
     * @param string $uri    ldaps://dc.example.com, or ldap:// to StartTLS
     * @param string $bindDn a userPrincipalName or a full DN
     * @param string $pass   the bind password
     * @param string $caFile a CA certificate path, or '' for the system store
     *
     * @return bool
     */
    public function connect($uri, $bindDn, $pass, $caFile = '')
    {
        $uri = trim((string)$uri);
        if ('' === $uri || '' === trim((string)$bindDn) || '' === (string)$pass) {
            $this->_error = 'directory connection is not configured';
            return false;
        }
        // Set BEFORE ldap_connect: the TLS context is built when the
        // connection is created, so a CA file applied afterward is read too
        // late and the handshake fails against a private CA -- which is
        // every directory anyone actually runs.
        if ('' !== trim((string)$caFile)) {
            if (!is_readable($caFile)) {
                $this->_error = 'CA certificate is not readable: ' . $caFile;
                return false;
            }
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caFile);
        }

        $ld = @ldap_connect($uri);
        if (!$ld) {
            $this->_error = 'could not parse the directory URI: ' . $uri;
            return false;
        }
        ldap_set_option($ld, LDAP_OPT_PROTOCOL_VERSION, 3);
        // Chasing a referral would silently send the bind credential to a
        // server the admin did not configure.
        ldap_set_option($ld, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ld, LDAP_OPT_NETWORK_TIMEOUT, self::TIMEOUT);
        ldap_set_option($ld, LDAP_OPT_TIMELIMIT, self::TIMEOUT);

        // A plain ldap:// is promoted rather than used. The bind carries the
        // credential in the clear otherwise, and Active Directory refuses a
        // simple bind on an unprotected connection regardless -- observed as
        // "Strong(er) authentication required", which reads like a password
        // problem and is not one.
        if (0 === stripos($uri, 'ldap://') && !@ldap_start_tls($ld)) {
            $this->_error = 'StartTLS failed: ' . ldap_error($ld);
            return false;
        }

        if (!@ldap_bind($ld, $bindDn, $pass)) {
            $this->_error = 'bind failed: ' . ldap_error($ld);
            return false;
        }
        $this->_ld = $ld;
        return true;
    }

    /**
     * Reads the RootDSE context for the requested DNS domain.
     *
     * @param string $wantedDnsDomain      the DNS domain expected by the caller
     * @param bool   $includeNamingContexts also read, and require, the
     *                                      namingContexts the DC serves
     *
     * @return array{status:string,base_dn?:string,dc?:string,naming_contexts?:array<int,string>,reason?:string}
     */
    public function domainContext($wantedDnsDomain, $includeNamingContexts = false)
    {
        $wantedDnsDomain = strtolower(rtrim(trim((string)$wantedDnsDomain), '.'));
        if (!$this->_ld) {
            return ['status' => 'error', 'reason' => 'not_connected'];
        }
        if (!self::_validDnsName($wantedDnsDomain)) {
            return ['status' => 'error', 'reason' => 'invalid_domain'];
        }
        $res = @ldap_read(
            $this->_ld,
            '',
            '(objectClass=*)',
            $includeNamingContexts
                ? ['defaultNamingContext', 'dnsHostName', 'namingContexts']
                : ['defaultNamingContext', 'dnsHostName']
        );
        if (!$res) {
            return ['status' => 'error', 'reason' => 'rootdse_read_failed'];
        }
        $entries = @ldap_get_entries($this->_ld, $res);
        if (!is_array($entries) || 1 !== (int)($entries['count'] ?? -1)) {
            return ['status' => 'error', 'reason' => 'invalid_rootdse_result'];
        }
        if (!self::_completeResult($this->_ld, $res)) {
            return ['status' => 'error', 'reason' => 'incomplete_rootdse_result'];
        }
        $entry = $entries[0] ?? [];
        $baseValues = $entry['defaultnamingcontext'] ?? null;
        $dcValues = $entry['dnshostname'] ?? null;
        if (!is_array($baseValues) || (int)($baseValues['count'] ?? 0) !== 1
            || !is_array($dcValues) || (int)($dcValues['count'] ?? 0) !== 1
        ) {
            return ['status' => 'error', 'reason' => 'missing_rootdse_attribute'];
        }
        $baseDn = trim((string)($baseValues[0] ?? ''));
        $dc = strtolower(rtrim(trim((string)($dcValues[0] ?? '')), '.'));
        $contextDomain = self::_domainFromNamingContext($baseDn);
        if (null === $contextDomain || $contextDomain !== $wantedDnsDomain
            || !self::_validDnsName($dc)
        ) {
            return ['status' => 'error', 'reason' => 'rootdse_domain_mismatch'];
        }
        $context = ['status' => 'found', 'base_dn' => $baseDn, 'dc' => $dc];
        if ($includeNamingContexts) {
            $contexts = self::_values($entry, 'namingcontexts');
            if (!$contexts || !array_filter($contexts, static function ($nc) use ($baseDn) {
                return 0 === strcasecmp($nc, $baseDn);
            })) {
                return ['status' => 'error', 'reason' => 'naming_contexts_unavailable'];
            }
            $context['naming_contexts'] = $contexts;
        }
        return $context;
    }

    /**
     * Validates the configured search scope against this connection's naming
     * context.
     *
     * Legacy facts can contain a NetBIOS alias rather than a DNS domain. The
     * configured base remains the search scope; it need not be the NC root.
     *
     * @param string $searchBase the configured search base
     *
     * @return array{status:string,base_dn?:string,dc?:string,naming_contexts?:array<int,string>,reason?:string}
     */
    public function placementContext($searchBase)
    {
        $parts = @ldap_explode_dn((string)$searchBase, 0);
        if (!is_array($parts) || empty($parts['count'])) {
            return ['status' => 'error', 'reason' => 'invalid_search_base'];
        }
        $labels = [];
        for ($i = (int)$parts['count'] - 1; $i >= 0; --$i) {
            if (!preg_match('/^DC=([A-Za-z0-9-]+)$/iD', $parts[$i], $m)) {
                break;
            }
            array_unshift($labels, $m[1]);
        }
        $context = $this->domainContext(implode('.', $labels), true);
        if ('found' !== $context['status']
            || !self::samePlacementNC($searchBase, $context)) {
            return ['status' => 'error', 'reason' => 'search_base_nc_mismatch'];
        }
        return $context;
    }

    /**
     * Whether a DN lies in the domain naming context of this connection.
     *
     * The Configuration, Schema and application NCs can share a domain DN
     * suffix. This matches the most specific NC the same DC advertises.
     *
     * @param string $dn      the distinguished name to place
     * @param array  $context the result of domainContext() with naming contexts
     *
     * @return bool
     */
    public static function samePlacementNC($dn, array $context)
    {
        $best = '';
        foreach ($context['naming_contexts'] ?? [] as $nc) {
            if (is_string($nc) && strlen($nc) > strlen($best) && self::sameNamingContext($dn, $nc)) {
                $best = $nc;
            }
        }
        return '' !== $best && 0 === strcasecmp($best, $context['base_dn']);
    }

    /**
     * Reads a single computer account without collapsing query failures into
     * an absence result.
     *
     * @param string $baseDn  where to search
     * @param string $account sAMAccountName, with or without its dollar
     *
     * @return array{
     *     status:string,
     *     record?:array{
     *         guid:string,sid:string,dn:string,account:string,dns:string,
     *         uac:int,pwd_last_set:string,spns:array<int,string>
     *     },
     *     reason?:string
     * }
     */
    public function computerRecord($baseDn, $account)
    {
        if (!$this->_ld) {
            return ['status' => 'error', 'reason' => 'not_connected'];
        }
        $baseDn = trim((string)$baseDn);
        $account = trim((string)$account);
        if ('' === $baseDn) {
            return ['status' => 'error', 'reason' => 'invalid_search_base'];
        }
        if (substr($account, -1) === '$') {
            $account = substr($account, 0, -1);
        }
        if ('' === $account || false !== strpos($account, '$')) {
            return ['status' => 'error', 'reason' => 'invalid_account'];
        }
        $filter = sprintf(
            '(&(objectClass=computer)(sAMAccountName=%s$))',
            ldap_escape($account, '', LDAP_ESCAPE_FILTER)
        );
        $attributes = [
            'objectClass',
            'objectGUID',
            'objectSid',
            'distinguishedName',
            'sAMAccountName',
            'dNSHostName',
            'userAccountControl',
            'pwdLastSet',
            'servicePrincipalName'
        ];
        $res = @ldap_search($this->_ld, $baseDn, $filter, $attributes);
        if (!$res) {
            return ['status' => 'error', 'reason' => 'search_failed'];
        }
        $entries = @ldap_get_entries($this->_ld, $res);
        if (!is_array($entries) || !array_key_exists('count', $entries)
            || !self::_completeResult($this->_ld, $res)
        ) {
            return ['status' => 'error', 'reason' => 'incomplete_search'];
        }
        $count = (int)$entries['count'];
        if (0 === $count) {
            return ['status' => 'absent'];
        }
        if (1 !== $count) {
            return ['status' => 'conflict', 'reason' => 'multiple_matches'];
        }
        $entry = $entries[0] ?? [];
        $classes = self::_values($entry, 'objectclass');
        if (null === $classes) {
            return ['status' => 'error', 'reason' => 'missing_record_attribute'];
        }
        if (!in_array('computer', array_map('strtolower', $classes), true)) {
            return ['status' => 'conflict', 'reason' => 'not_computer'];
        }
        $guidValues = self::_values($entry, 'objectguid');
        $sidValues = self::_values($entry, 'objectsid');
        $dnValues = self::_values($entry, 'distinguishedname');
        $samValues = self::_values($entry, 'samaccountname');
        $dnsValues = self::_values($entry, 'dnshostname');
        $uacValues = self::_values($entry, 'useraccountcontrol');
        $pwdValues = self::_values($entry, 'pwdlastset');
        $spnValues = self::_values($entry, 'serviceprincipalname');
        if ((array_key_exists('dnshostname', $entry) && null === $dnsValues)
            || (array_key_exists('serviceprincipalname', $entry) && null === $spnValues)
        ) {
            return ['status' => 'error', 'reason' => 'invalid_optional_attribute'];
        }
        if (null === $guidValues || 1 !== count($guidValues)
            || null === $sidValues || 1 !== count($sidValues)
            || null === $dnValues || 1 !== count($dnValues)
            || null === $samValues || 1 !== count($samValues)
            || null === $uacValues || 1 !== count($uacValues)
            || null === $pwdValues || 1 !== count($pwdValues)
            || (null !== $dnsValues && count($dnsValues) > 1)
        ) {
            return ['status' => 'error', 'reason' => 'missing_record_attribute'];
        }
        $guid = self::_guid($guidValues[0]);
        $sid = self::_sid($sidValues[0]);
        $dn = trim($dnValues[0]);
        $sam = $samValues[0];
        $dns = null === $dnsValues || 0 === count($dnsValues)
            ? ''
            : strtolower(rtrim(trim($dnsValues[0]), '.'));
        $uacText = $uacValues[0];
        $pwdText = $pwdValues[0];
        if (null === $guid || null === $sid || '' === $dn || '' === $sam
            || 0 !== strcasecmp($sam, $account . '$')
            || !preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $uacText)
            || !preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $pwdText)
            || (string)(int)$uacText !== $uacText
            || ('' !== $dns && !self::_validDnsName($dns))
        ) {
            return ['status' => 'error', 'reason' => 'invalid_record'];
        }
        $uac = (int)$uacText;
        if (0 !== ($uac & (0x2000 | 0x04000000))) {
            return ['status' => 'conflict', 'reason' => 'unsupported_account_control_flags'];
        }
        $spns = null === $spnValues || 0 === count($spnValues)
            ? []
            : array_values(array_unique($spnValues));
        sort($spns, SORT_STRING);
        return [
            'status' => 'found',
            'record' => [
                'guid' => $guid,
                'sid' => $sid,
                'dn' => $dn,
                'account' => $sam,
                'dns' => $dns,
                'uac' => $uac,
                'pwd_last_set' => $pwdText,
                'spns' => $spns
            ]
        ];
    }

    /**
     * Whether an LDAP search completed without a partial result or referral.
     *
     * @param mixed $ld  connection
     * @param mixed $res result
     *
     * @return bool
     */
    private static function _completeResult($ld, $res)
    {
        $code = $matched = $message = $referrals = null;
        if (!@ldap_parse_result($ld, $res, $code, $matched, $message, $referrals)) {
            return false;
        }
        return 0 === (int)$code && empty($referrals);
    }

    /**
     * Gets one LDAP attribute's values, preserving binary strings.
     *
     * @param array  $entry LDAP entry from ldap_get_entries()
     * @param string $name  lower-case attribute name
     *
     * @return array<int,string>|null
     */
    private static function _values(array $entry, $name)
    {
        $values = $entry[$name] ?? null;
        if (!is_array($values) || !isset($values['count'])) {
            return null;
        }
        $out = [];
        for ($i = 0; $i < (int)$values['count']; $i++) {
            if (!array_key_exists($i, $values) || !is_string($values[$i])) {
                return null;
            }
            $out[] = $values[$i];
        }
        return $out;
    }

    /**
     * Converts Active Directory's little-endian objectGUID bytes to UUID.
     *
     * @param mixed $bytes raw objectGUID
     *
     * @return string|null
     */
    private static function _guid($bytes)
    {
        if (!is_string($bytes) || 16 !== strlen($bytes)) {
            return null;
        }
        $parts = unpack('Vfirst/vsecond/vthird', substr($bytes, 0, 8));
        if (!is_array($parts)) {
            return null;
        }
        $tail = bin2hex(substr($bytes, 8));
        $hex = sprintf(
            '%08x-%04x-%04x-%s-%s',
            $parts['first'],
            $parts['second'],
            $parts['third'],
            substr($tail, 0, 4),
            substr($tail, 4)
        );
        return strtolower($hex);
    }

    /**
     * Strictly decodes the binary SID form returned by Active Directory.
     *
     * @param mixed $bytes raw objectSid
     *
     * @return string|null
     */
    private static function _sid($bytes)
    {
        if (!is_string($bytes) || strlen($bytes) < 8 || 1 !== ord($bytes[0])) {
            return null;
        }
        $count = ord($bytes[1]);
        if (strlen($bytes) !== 8 + (4 * $count)) {
            return null;
        }
        $authority = hexdec(bin2hex(substr($bytes, 2, 6)));
        $subAuthorities = $count
            ? array_values(unpack('V' . $count, substr($bytes, 8)))
            : [];
        return 'S-1-' . $authority . (count($subAuthorities)
            ? '-' . implode('-', $subAuthorities)
            : '');
    }

    /**
     * Maps a simple AD domain naming context to its DNS domain.
     *
     * @param string $baseDn naming context
     *
     * @return string|null
     */
    private static function _domainFromNamingContext($baseDn)
    {
        $labels = [];
        foreach (explode(',', (string)$baseDn) as $rdn) {
            if (!preg_match(
                '/\A\s*DC=([A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)\s*\z/i',
                $rdn,
                $match
            )) {
                return null;
            }
            $labels[] = strtolower($match[1]);
        }
        $domain = implode('.', $labels);
        return self::_validDnsName($domain) ? $domain : null;
    }

    /**
     * Validates an ASCII DNS FQDN (not an IP address or single label).
     *
     * @param mixed $name DNS name
     *
     * @return bool
     */
    private static function _validDnsName($name)
    {
        if (!is_string($name) || strlen($name) > 253
            || false !== strpos($name, ':')
            || false !== filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            return false;
        }
        $labels = explode('.', $name);
        if (count($labels) < 2) {
            return false;
        }
        foreach ($labels as $label) {
            if (strlen($label) < 1 || strlen($label) > 63
                || !preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\z/D', $label)
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Every organizational unit at or below a base, sorted by DN.
     *
     * For an admin choosing where a host belongs (GH-1879), so it reads the
     * whole subtree, a page at a time: Active Directory returns at most 1000
     * entries to an unpaged search and reports the rest as a size-limit
     * error, which would show a large directory as a truncated tree.
     *
     * Null on failure, with the reason in error(). An empty list is a valid
     * answer: a base that holds no OUs.
     *
     * @param string $baseDn where to start; the base itself is included
     *                       when it is an OU
     *
     * @return array<int,array{dn:string,name:string}>|null
     */
    public function listOUs($baseDn)
    {
        if (!$this->_ld || '' === trim((string)$baseDn)) {
            $this->_error = 'no search base configured';
            return null;
        }
        $ous = [];
        $cookie = '';
        do {
            $controls = [
                [
                    'oid' => LDAP_CONTROL_PAGEDRESULTS,
                    'value' => ['size' => 500, 'cookie' => $cookie]
                ]
            ];
            $res = @ldap_search(
                $this->_ld,
                $baseDn,
                '(objectClass=organizationalUnit)',
                ['ou'],
                0,
                -1,
                -1,
                LDAP_DEREF_NEVER,
                $controls
            );
            if (!$res) {
                $this->_error = 'search failed: ' . ldap_error($this->_ld);
                return null;
            }
            $entries = @ldap_get_entries($this->_ld, $res);
            $count = (int)($entries['count'] ?? 0);
            for ($i = 0; $i < $count; $i++) {
                $dn = (string)($entries[$i]['dn'] ?? '');
                if ('' === $dn) {
                    continue;
                }
                $ous[] = [
                    'dn' => $dn,
                    'name' => (string)($entries[$i]['ou'][0] ?? self::rdn($dn))
                ];
            }
            // The server hands back a cookie while more pages remain and an
            // empty one on the last page. A server that ignores the control
            // returns no cookie at all, which ends the loop after one page.
            $cookie = '';
            $errcode = $matcheddn = $errmsg = $referrals = null;
            $resControls = [];
            if (@ldap_parse_result(
                $this->_ld,
                $res,
                $errcode,
                $matcheddn,
                $errmsg,
                $referrals,
                $resControls
            )) {
                $cookie = (string)(
                    $resControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? ''
                );
            }
        } while ('' !== $cookie);
        // Sorted from the root down, so a parent always precedes its
        // children. A DN reads leaf first, so it is compared reversed.
        usort(
            $ous,
            function ($a, $b) {
                return strcasecmp(
                    self::_rootFirst($a['dn']),
                    self::_rootFirst($b['dn'])
                );
            }
        );
        return $ous;
    }

    /**
     * A DN with its components reversed, root first, for sorting.
     *
     * @param string $dn the distinguished name
     *
     * @return string
     */
    private static function _rootFirst($dn)
    {
        $parts = [];
        $dn = (string)$dn;
        while ('' !== $dn) {
            $parts[] = self::rdn($dn);
            $dn = self::parentDn($dn);
        }
        return implode(',', array_reverse($parts));
    }

    /**
     * Moves an object into another container, keeping its name.
     *
     * One LDAP Modify DN. The object keeps its SID, its password, its group
     * memberships and everything escrowed on it, and the machine is not
     * involved and need not be running -- which is the whole of design
     * 0009's argument against the unjoin-and-rejoin an admin is reduced to
     * today.
     *
     * A caller that names the object by guidDN() has no RDN to read from the
     * name, so it passes the RDN it saw when it identified the object.
     * moveOutcome() tells what this call learned about its request.
     *
     * @param string $dn       the object's current distinguished name, or its
     *                         guidDN()
     * @param string $parentDn the container to move it into, or its guidDN()
     * @param string $rdn      the object's RDN; read from $dn when empty
     *
     * @return bool
     */
    public function moveTo($dn, $parentDn, $rdn = '')
    {
        $this->_moveOutcome = ['kind' => 'not_sent', 'code' => null];
        if (!$this->_ld) {
            $this->_error = 'not connected';
            return false;
        }
        $dn = trim((string)$dn);
        $parentDn = trim((string)$parentDn);
        if ('' === $dn || '' === $parentDn) {
            $this->_error = 'a move needs both an object and a destination';
            return false;
        }
        if ('' === trim((string)$rdn) && 0 === stripos($dn, '<GUID=')) {
            $this->_error = 'a Move by GUID needs the RDN';
            return false;
        }
        $rdn = '' === trim((string)$rdn) ? self::rdn($dn) : trim((string)$rdn);
        if ('' === $rdn) {
            $this->_error = 'cannot read the RDN of ' . $dn;
            return false;
        }
        // deleteoldrdn=true: the name is not changing, so keeping the old
        // RDN as an extra value would leave a second name on the object.
        $this->_moveOutcome = ['kind' => 'request_result_unknown', 'code' => null];
        // Bounds the synchronous modify. Set here, not in connect(), so the
        // short search timeout of every other caller stays as it is.
        ldap_set_option($this->_ld, LDAP_OPT_TIMEOUT, self::MOVE_TIMEOUT);
        if (!@ldap_rename($this->_ld, $dn, $rdn, $parentDn, true)) {
            $this->_error = ldap_error($this->_ld);
            $code = (int)ldap_errno($this->_ld);
            $this->_moveOutcome = [
                'kind' => $code > 0 && $code <= 80 ? 'ldap_rejection' : 'local_or_transport_error',
                'code' => $code, 'message' => $this->_error
            ];
            return false;
        }
        $this->_moveOutcome = ['kind' => 'ldap_success', 'code' => 0];
        return true;
    }

    /**
     * The 16 raw bytes of a GUID given as a UUID. The caller has checked the
     * shape.
     *
     * @param string $guid the UUID
     *
     * @return string
     */
    public static function guidBytes($guid)
    {
        return hex2bin(str_replace('-', '', strtolower($guid)));
    }

    /**
     * The binary-GUID form of a DN for an Active Directory object.
     *
     * A DN can be reused by another object after a delete and re-create. The
     * GUID cannot, so a caller that must act on one object names it this way.
     * A reusable DN is never a fallback.
     *
     * @param string $guid the objectGUID as a UUID
     *
     * @return string
     */
    public static function guidDN($guid)
    {
        if (!\FOG\Agent\DirectoryRejoin::guid($guid)) {
            throw new \InvalidArgumentException('invalid immutable LDAP identity');
        }
        $bytes = self::guidBytes($guid);
        $binary = strrev(substr($bytes, 0, 4)) . strrev(substr($bytes, 4, 2))
            . strrev(substr($bytes, 6, 2)) . substr($bytes, 8);
        return '<GUID=' . bin2hex($binary) . '>';
    }

    /**
     * Whether a DN is the naming context or lies beneath it.
     *
     * @param mixed $dn the distinguished name
     * @param mixed $nc the naming context
     *
     * @return bool
     */
    public static function sameNamingContext($dn, $nc)
    {
        return is_string($dn) && is_string($nc) && '' !== $nc
            && (0 === strcasecmp($dn, $nc) || 0 === strcasecmp(substr($dn, -strlen($nc) - 1), ',' . $nc));
    }

    /**
     * Resolves a destination container's identity on this connection.
     *
     * @param mixed $dn the container's DN or guidDN()
     *
     * @return array{status:string,guid?:string,dn?:string,reason?:string}
     */
    public function containerIdentity($dn)
    {
        if (!$this->_ld || !is_string($dn) || '' === trim($dn)) {
            return ['status' => 'error', 'reason' => 'destination_unavailable'];
        }
        $result = @ldap_read(
            $this->_ld,
            $dn,
            '(|(objectClass=organizationalUnit)(objectClass=container)(objectClass=domainDNS))',
            ['objectGUID', 'distinguishedName', 'objectClass']
        );
        if (!$result || !self::_completeResult($this->_ld, $result)) {
            return ['status' => 'error', 'reason' => 'destination_read_failed'];
        }
        $entries = ldap_get_entries($this->_ld, $result);
        if (!is_array($entries) || 1 !== (int)($entries['count'] ?? -1)) {
            return ['status' => 'error', 'reason' => 'destination_not_unique'];
        }
        $guids = self::_values($entries[0], 'objectguid');
        $dns = self::_values($entries[0], 'distinguishedname');
        if (!is_array($guids) || !is_array($dns) || count($guids) !== 1 || count($dns) !== 1
            || null === ($guid = self::_guid($guids[0]))) {
            return ['status' => 'error', 'reason' => 'destination_identity_incomplete'];
        }
        return ['status' => 'found', 'guid' => $guid, 'dn' => $dns[0]];
    }

    /**
     * The last failure, for recording against the host.
     *
     * @return string
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close()
    {
        if ($this->_ld) {
            @ldap_unbind($this->_ld);
            $this->_ld = null;
        }
    }

    /**
     * The first component of a distinguished name.
     *
     * `CN=WS-014,OU=Sales,DC=corp,DC=com` yields `CN=WS-014`. RFC 4514
     * escapes a literal comma in a value as `\,`, so a plain explode would
     * cut a name like `CN=Smith\, John` in half and rename the object to
     * half of itself.
     *
     * @param string $dn the distinguished name
     *
     * @return string
     */
    public static function rdn($dn)
    {
        $dn = (string)$dn;
        $escaped = false;
        $len = strlen($dn);
        for ($i = 0; $i < $len; $i++) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ('\\' === $dn[$i]) {
                $escaped = true;
                continue;
            }
            if (',' === $dn[$i]) {
                return substr($dn, 0, $i);
            }
        }
        return $dn;
    }

    /**
     * Everything after the first component: the container holding an object.
     *
     * @param string $dn the distinguished name
     *
     * @return string
     */
    public static function parentDn($dn)
    {
        $rdn = self::rdn($dn);
        if ($rdn === $dn) {
            return '';
        }
        return trim(substr((string)$dn, strlen($rdn) + 1));
    }
}
