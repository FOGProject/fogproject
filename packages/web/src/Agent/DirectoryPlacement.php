<?php
/**
 * Moves a host's computer object into the OU the host record asks for.
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\HostDirectory;
use FOG\Net\FOGLdap;

/**
 * Moves a host's computer object into the OU the host record asks for
 * (design 0009 section 5).
 *
 * The half of directory membership only the directory can do. A computer
 * object's container is a property of an object in a directory, not of the
 * machine -- so this is one LDAP Modify DN from the server, with the machine
 * uninvolved and not necessarily even running.
 *
 * What FOG does today instead, because the legacy client never compares an
 * OU at all: nothing. Editing a host's OU has no effect, forever, and the
 * workaround an admin is left with is to unjoin and rejoin, which resets the
 * computer account's password and, where the object is recreated, gives the
 * machine a new SID -- losing its group memberships, its escrowed BitLocker
 * keys, its LAPS password and any certificate issued to it.
 *
 * Off unless configured. This writes to somebody's directory, so it must
 * never start working because they upgraded.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryPlacement extends FOGBase
{
    /**
     * Seconds before the directory is consulted again about one host.
     *
     * Every attempt is stamped, successful or not, because this bounds two
     * different things. A failure, first: the ones worth expecting -- the
     * directory is down, the account lost its rights, the OU was renamed --
     * do not clear in five minutes, and without a cooldown a broken
     * directory is dialed once per poll per host forever with every host
     * paying the connection timeout. And a host that cannot report its own
     * DN, second: only the directory knows where its object sits, so the
     * question has to be asked rather than answered from the row, and once
     * an hour is the price of an OU move landing on a Linux host.
     *
     * A host that DOES report its DN is not on this clock while it is where
     * it belongs -- that comparison is free, so it happens every poll and an
     * OU change on a Windows host is acted on immediately.
     */
    const RETRY_AFTER = 3600;

    /**
     * Whether placement is switched on and configured.
     *
     * @return bool
     */
    public static function enabled()
    {
        return (bool)self::getSetting('FOG_DIRECTORY_PLACEMENT_ENABLED')
            && '' !== trim((string)self::getSetting('FOG_DIRECTORY_LDAP_URI'));
    }

    /**
     * Places one host's computer object, if it is not already where the host
     * record says it should be.
     *
     * Never throws. It is called from the poll, and a directory problem must
     * cost the host a recorded error rather than its check-in -- the same
     * rule the fact reports follow.
     *
     * @param Host          $Host      the host the certificate bound
     * @param HostDirectory $Directory what the machine reported
     *
     * @return void
     */
    public static function ensure(Host $Host, HostDirectory $Directory)
    {
        try {
            self::_ensure($Host, $Directory);
        } catch (\Throwable $e) {
            // Recorded, not raised. Whatever went wrong here, the host has
            // still checked in and its facts are still stored.
            self::_record($Directory, 'placement failed: ' . $e->getMessage());
        }
    }

    /**
     * The body of ensure(), free to fail.
     *
     * @param Host          $Host      the host
     * @param HostDirectory $Directory the observation
     *
     * @return void
     */
    private static function _ensure(Host $Host, HostDirectory $Directory)
    {
        if (!self::enabled() || !$Host->get('useAD')) {
            return;
        }
        $wantOU = trim((string)$Host->get('ADOU'));
        if ('' === $wantOU) {
            // No OU expressed. An admin who never set one is not a machine
            // in the wrong place, and moving it somewhere would be FOG
            // inventing an intention.
            return;
        }
        if (!$Directory->get('joined')) {
            // Not joined: there is no object to move, and creating one is a
            // join, which is section 6 and needs the machine.
            return;
        }
        if ('' !== $Directory->containerDN() && !$Directory->ouDrifted($wantOU)) {
            // The host reported its own DN and it is the right one. Nothing
            // to ask the directory, so no connection and no cooldown: a
            // healthy Windows fleet never dials LDAP at all.
            return;
        }
        if (self::_cooling($Directory)) {
            return;
        }

        $ldap = new FOGLdap();
        $ok = $ldap->connect(
            (string)self::getSetting('FOG_DIRECTORY_LDAP_URI'),
            (string)self::getSetting('FOG_DIRECTORY_BIND_DN'),
            self::_bindPassword(),
            (string)self::getSetting('FOG_DIRECTORY_CA_CERT')
        );
        if (!$ok) {
            self::_record($Directory, $ldap->error());
            return;
        }

        try {
            $dn = trim((string)$Directory->get('computerDN'));
            if ('' === $dn) {
                // Normal on Linux: no join tool there exposes the DN. The
                // directory knows, though, and asking it is better than
                // asking the machine -- it is the authority on where its own
                // objects live, and it can answer for a machine that is off.
                $dn = $ldap->findComputer(
                    (string)self::getSetting('FOG_DIRECTORY_BASE_DN'),
                    (string)$Directory->get('machineAccount')
                );
                if ('' === $dn) {
                    self::_record($Directory, $ldap->error());
                    return;
                }
                // Learned from the directory, so the report stops saying
                // "unknown" for this host and the free comparison above
                // works from the next poll on.
                $Directory->set('computerDN', $dn);
                if (!$Directory->ouDrifted($wantOU)) {
                    self::_record($Directory, '');
                    return;
                }
            }
            if (!$ldap->moveTo($dn, $wantOU)) {
                self::_record($Directory, $ldap->error());
                return;
            }
            // Where the object now is, not where we asked it to go: a true
            // return from ldap_rename is the directory confirming the object
            // is there. Recording it keeps the next poll's free comparison
            // correct -- leaving the old DN would make FOG move an object
            // that has already moved, once every poll forever.
            $Directory->set(
                'computerDN',
                FOGLdap::rdn($dn) . ',' . $wantOU
            );
        } finally {
            $ldap->close();
        }

        self::_record($Directory, '');

        Audit::record(
            [
                'type' => 'agent.directory.move',
                'subjectType' => 'host',
                'subjectID' => (int)$Host->get('id'),
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => 1,
                'text' => substr(
                    'moved the computer object to ' . $wantOU,
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }

    /**
     * Whether this host was consulted about recently enough to leave alone.
     *
     * @param HostDirectory $Directory the observation
     *
     * @return bool
     */
    private static function _cooling(HostDirectory $Directory)
    {
        $at = (string)$Directory->get('placementAt');
        if (!self::validDate($at)) {
            return false;
        }
        return (time() - self::niceDate($at)->getTimestamp()) < self::RETRY_AFTER;
    }

    /**
     * Stamps the attempt and stores its outcome.
     *
     * An empty error is success. The stamp is written either way, because
     * it is what the retry cooldown reads.
     *
     * @param HostDirectory $Directory the observation
     * @param string        $error     the failure, or '' for success
     *
     * @return void
     */
    private static function _record(HostDirectory $Directory, $error)
    {
        $Directory
            ->set('placementAt', self::stamp())
            ->set('placementError', substr(trim((string)$error), 0, 255))
            ->save();
    }

    /**
     * Now, on the clock the database stores.
     *
     * niceDate() with an explicit storage timezone, not date(): FOG writes
     * datetimes on storageTimeZone() and reads them back the same way, and
     * mixing that with PHP's default zone is what made a one-second user
     * session read as five hours and blanked a report column twice.
     *
     * @return string
     */
    private static function stamp()
    {
        return self::niceDate()
            ->setTimezone(self::storageTimeZone())
            ->format('Y-m-d H:i:s');
    }

    /**
     * The bind password, as typed.
     *
     * Three shapes have to read back, because FOG's own settings accept all
     * three: what an admin types into the configuration page (raw), what a
     * script may store (base64), and an aesdecrypt-able value written by an
     * older tool. aesdecrypt() returns anything without a `|` unchanged, so
     * it is safe to run over all of them.
     *
     * The base64 test is STRICT, deliberately, and this is where the LDAP
     * plugin's version of this probe goes wrong. It asks
     * `if ($x = base64_decode($test))`, and non-strict base64_decode does not
     * fail on a non-base64 string -- it skips the characters outside the
     * alphabet and decodes whatever is left. Feed it an ordinary password and
     * it hands back a few bytes of garbage, which mb_detect_encoding will
     * accept as UTF-8 often enough to matter, and FOG then binds with a
     * string the admin never typed. Round-tripping the encode is the only
     * check that actually distinguishes the two.
     *
     * @return string
     */
    private static function _bindPassword()
    {
        return self::decodeStored(
            (string)self::getSetting('FOG_DIRECTORY_BIND_PASSWORD')
        );
    }

    /**
     * One of FOG's stored secrets, as it was typed.
     *
     * Public because DirectoryJoin reads the host's `hostADPass` with the
     * same three shapes and the same trap; a third copy of the dance is how
     * the buggy version in `Client\HostnameChanger` came to exist.
     *
     * @param string $stored what the column or setting holds
     *
     * @return string
     */
    public static function decodeStored($stored)
    {
        $pass = trim((string)$stored);
        if ('' === $pass) {
            return '';
        }
        $pass = (string)self::aesdecrypt($pass);
        $decoded = base64_decode($pass, true);
        if (false !== $decoded
            && '' !== $decoded
            && base64_encode($decoded) === $pass
            && mb_detect_encoding($decoded, 'utf-8', true)
        ) {
            return $decoded;
        }
        return $pass;
    }
}
