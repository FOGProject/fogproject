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
     * The distinguished name of the computer object with this machine
     * account name, or '' if there is not exactly one.
     *
     * The fallback for a host that could not report its own DN, which is
     * every Linux host: no join tool there exposes it. Exactly one match or
     * nothing -- two objects with the same account name across a forest is
     * a situation to report, never one to guess between.
     *
     * @param string $baseDn  where to search
     * @param string $account the machine account, with or without its dollar
     *
     * @return string
     */
    public function findComputer($baseDn, $account)
    {
        if (!$this->_ld || '' === trim((string)$baseDn)) {
            $this->_error = 'no search base configured';
            return '';
        }
        $account = rtrim(trim((string)$account), '$');
        if ('' === $account) {
            return '';
        }
        $filter = sprintf(
            '(&(objectClass=computer)(sAMAccountName=%s$))',
            ldap_escape($account, '', LDAP_ESCAPE_FILTER)
        );
        $res = @ldap_search($this->_ld, $baseDn, $filter, ['distinguishedName']);
        if (!$res) {
            $this->_error = 'search failed: ' . ldap_error($this->_ld);
            return '';
        }
        $entries = @ldap_get_entries($this->_ld, $res);
        $count = (int)($entries['count'] ?? 0);
        if (1 !== $count) {
            $this->_error = 0 === $count
                ? 'no computer object named ' . $account . '$'
                : $count . ' computer objects named ' . $account . '$';
            return '';
        }
        return (string)($entries[0]['distinguishedname'][0] ?? '');
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
     * @param string $dn       the object's current distinguished name
     * @param string $parentDn the container to move it into
     *
     * @return bool
     */
    public function moveTo($dn, $parentDn)
    {
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
        $rdn = self::rdn($dn);
        if ('' === $rdn) {
            $this->_error = 'cannot read the RDN of ' . $dn;
            return false;
        }
        // deleteoldrdn=true: the name is not changing, so keeping the old
        // RDN as an extra value would leave a second name on the object.
        if (!@ldap_rename($this->_ld, $dn, $rdn, $parentDn, true)) {
            $this->_error = ldap_error($this->_ld);
            return false;
        }
        return true;
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
