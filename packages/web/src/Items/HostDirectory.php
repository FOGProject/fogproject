<?php
/**
 * What directory a host is actually a member of (design 0009).
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * What directory a host is actually a member of (design 0009).
 *
 * The contrast to draw is with the `hosts` table's own AD columns, which
 * this does not replace: hostADDomain and hostADOU are INTENT, what an admin
 * typed into a form. This is OBSERVATION, what the machine reported about
 * itself. FOG has recorded the first since 1.x and has never recorded the
 * second, which is why "which of my machines are not where I think they are"
 * has had no answer.
 *
 * One row per host, replaced in place. Not a history: a membership is a
 * current state, so there is nothing here to age out.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostDirectory extends FOGController
{
    /**
     * The hostDirectory table.
     *
     * @var string
     */
    protected $databaseTable = 'hostDirectory';
    /**
     * The hostDirectory fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hdID',
        'hostID' => 'hdHostID',
        'joined' => 'hdJoined',
        'kind' => 'hdKind',
        'domain' => 'hdDomain',
        'netbios' => 'hdNetbios',
        'computerDN' => 'hdComputerDN',
        'machineAccount' => 'hdMachineAccount',
        'site' => 'hdSite',
        'observedAt' => 'hdObservedAt',
        'placementAt' => 'hdPlacementAt',
        'placementError' => 'hdPlacementError',
        'joinAt' => 'hdJoinAt',
        'joinError' => 'hdJoinError'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'host'
    ];
    /**
     * Return the associated host object.
     *
     * @return object
     */
    public function getHost()
    {
        if (!array_key_exists('host', $this->data)) {
            $this->set('host', new Host($this->get('hostID')));
        }
        return $this->get('host');
    }
    /**
     * The container the computer object sits in.
     *
     * Everything after the first UNESCAPED comma: the DN
     * `CN=WS-014,OU=Sales,DC=corp,DC=com` yields `OU=Sales,DC=corp,DC=com`.
     * RFC 4514 escapes a literal comma in an RDN value as `\,`, so a plain
     * explode would cut a name like `CN=Smith\, John` in half and report a
     * container that does not exist.
     *
     * Empty when nothing was reported, which is normal: no Linux join tool
     * exposes the DN.
     *
     * @return string
     */
    public function containerDN()
    {
        $dn = (string)$this->get('computerDN');
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
                return trim(substr($dn, $i + 1));
            }
        }
        return '';
    }
    /**
     * Whether the observed placement differs from the host's desired one.
     *
     * The comparison is deliberately conservative: it reports drift only
     * when both sides are known and they differ. An empty desired OU is an
     * admin who never expressed a preference, and an empty observed DN is a
     * platform that cannot report one -- neither is a machine in the wrong
     * place, and reporting either as drift would fill the report with rows
     * nobody can act on.
     *
     * DNs compare case-insensitively: LDAP attribute types and the standard
     * DC/OU/CN naming attributes are all case-insensitive in AD, so
     * `OU=Sales` and `ou=sales` are the same container.
     *
     * @param string $desiredOU the host's hostADOU
     *
     * @return bool
     */
    public function ouDrifted($desiredOU)
    {
        $want = trim((string)$desiredOU);
        $have = $this->containerDN();
        if ('' === $want || '' === $have) {
            return false;
        }
        return 0 !== strcasecmp(
            self::normalizeDN($want),
            self::normalizeDN($have)
        );
    }
    /**
     * Whether the observed domain differs from the host's desired one.
     *
     * hostADDomain has always held whichever spelling an admin typed, so a
     * DNS name is compared against the DNS name AND a short name against
     * the NetBIOS name; where the agent could not report a NetBIOS name --
     * realmd does not expose one -- the first label of the DNS name is
     * accepted, because CORP for corp.example.com is the overwhelmingly
     * common case and calling it drift would be a false alarm.
     *
     * @param string $desiredDomain the host's hostADDomain
     *
     * @return bool
     */
    public function domainDrifted($desiredDomain)
    {
        $want = strtolower(trim((string)$desiredDomain));
        if ('' === $want) {
            return false;
        }
        if (!$this->get('joined')) {
            // A host that is supposed to be in a domain and is in none is
            // the clearest drift there is.
            return true;
        }
        $domain = strtolower((string)$this->get('domain'));
        $netbios = strtolower((string)$this->get('netbios'));
        if ('' === $netbios && '' !== $domain) {
            $netbios = strstr($domain, '.', true) ?: $domain;
        }
        return $want !== $domain && $want !== $netbios;
    }
    /**
     * Strips the spacing variations a DN may carry so two spellings of the
     * same container compare equal.
     *
     * `OU=Sales, DC=corp, DC=com` and `OU=Sales,DC=corp,DC=com` are the same
     * container; without this the report would show drift on whitespace.
     *
     * @param string $dn the distinguished name
     *
     * @return string
     */
    protected static function normalizeDN($dn)
    {
        return preg_replace('/\s*,\s*/', ',', trim((string)$dn));
    }
}
