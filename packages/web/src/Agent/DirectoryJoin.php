<?php
/**
 * The join half of directory membership for an enrolled agent.
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
use FOG\Items\AgentEnrollment;
use FOG\Items\Host;
use FOG\Items\HostDirectory;
use FOG\Router\Route;

/**
 * What the agent is told about joining a domain, and what it reports back
 * (design 0009 section 6).
 *
 * The half only the machine can do. Membership is a property of the machine
 * -- its computer account, its secure channel, its Kerberos keytab -- so it
 * is the machine that joins, and the server's job is to decide whether it
 * should and to hand over the credential for exactly as long as that takes.
 *
 * The contrast with the legacy client is the whole point of this class.
 * `Client\HostnameChanger::json()` puts `ADUser` and `ADPass` in the answer
 * to EVERY check-in of EVERY host with `useAD` set -- joined or not, forever,
 * in cleartext once the client decrypts it. A joined estate is an estate
 * where every machine holds a credential that can create computer objects in
 * the directory, and it holds it permanently, for no reason: it is already
 * joined.
 *
 * Here the credential is sent only to a host the server BELIEVES is not
 * joined, only while that is true, and not again for an hour after an
 * attempt. Most hosts in most estates never receive it at all. The one
 * exception is a joined Windows host with a rename outstanding (design
 * 0017), under the same cooldown.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryJoin extends FOGBase
{
    /**
     * Seconds before a host is sent the credential again after an attempt.
     *
     * Not politeness. A join that fails on a bad password is a FAILED
     * AUTHENTICATION against somebody's domain controller, and without a
     * cooldown it is one per host per poll -- which is how a service account
     * with a lockout policy gets locked out, taking every other host's join
     * with it. An hour is short enough that fixing the password is not a
     * long wait and long enough that a fleet cannot trip a lockout.
     *
     * It also covers the gap after a SUCCESSFUL join: the machine's own
     * report of its new membership arrives on a later poll, and until it
     * does the server would otherwise still believe the host unjoined and
     * send the credential once more.
     */
    const RETRY_AFTER = 3600;

    /**
     * What the agent may report for a join.
     *
     * `refused` is the one worth explaining: it is the agent declining to
     * act on what it was sent, rather than trying and failing. A machine
     * already in a DIFFERENT domain is the case that matters -- getting it
     * to the right one means leaving the wrong one, which resets the
     * computer account's password and can cost the object its SID, and the
     * agent will not do that as a side effect of an edit.
     */
    const STATUS_JOINED = 'joined';
    const STATUS_ALREADY_JOINED = 'already_joined';
    const STATUSES = [
        'joined', 'already_joined', 'renamed', 'failed', 'unsupported',
        'refused'
    ];

    /**
     * The statuses that mean the machine is where it should be, so any
     * error recorded against it is stale.
     */
    const SETTLED_STATUSES = ['joined', 'already_joined', 'renamed'];

    /**
     * The longest NetBIOS computer name. A computer account is this much of
     * the host name plus a dollar sign, so a longer host name never equals
     * its account, and comparing the whole name would send the credential
     * to that host every RETRY_AFTER forever.
     */
    const NETBIOS_MAX = 15;

    /**
     * Longest error kept: the column is a varchar(255) because this is a
     * line an admin reads in a report, not a log.
     */
    const MAX_ERROR = 255;

    /**
     * The join block for a host, or null when there is nothing to send.
     *
     * Null is the answer for the overwhelming majority of hosts and every
     * one of the reasons is a reason NOT to put a credential on a machine:
     *
     * - The host is not set to use AD, or names no domain. Nothing to join.
     * - The host has never reported its membership. The server does not
     *   know whether it is joined, and a credential is not something to
     *   send on a guess. It arrives one poll later, once the machine has
     *   said where it is (facts are recorded after this runs, by design --
     *   see Route::agentPoll).
     * - The host is already in the domain it should be in. This is the
     *   resting state of a working estate.
     * - The host is joined to some OTHER domain. The agent would refuse,
     *   so sending the credential would achieve nothing and expose it; the
     *   Directory Membership report shows the mismatch instead.
     * - An attempt was made within RETRY_AFTER.
     *
     * One exception sends it to a JOINED host (design 0017): a Windows host
     * in the right domain whose computer account does not carry the host's
     * name. The machine cannot rename its own object -- the lab measured
     * access denied -- so the rename needs the join credential, and only
     * for as long as the rename is outstanding.
     *
     * @param Host $Host the principal
     *
     * @return array|null
     */
    public static function desired(Host $Host)
    {
        return self::blockFor(
            $Host,
            self::observed($Host),
            self::isWindows($Host)
        );
    }

    /**
     * The decision, given the host and what it last reported.
     *
     * Split from the lookup for the reason ReportManagement splits its
     * fetch from its emit: the rule about when a credential leaves this
     * server is the part worth testing, and it needs no database to state.
     *
     * @param Host               $Host     the principal
     * @param HostDirectory|null $Observed what it last reported, or null
     * @param bool               $windows  whether the host's agent runs on
     *                                     Windows, the one platform that
     *                                     renames through the directory
     *
     * @return array|null
     */
    public static function blockFor(
        Host $Host,
        HostDirectory $Observed = null,
        $windows = false
    ) {
        if (!(bool)$Host->get('useAD')) {
            return null;
        }
        $domain = trim((string)$Host->get('ADDomain'));
        if ('' === $domain) {
            return null;
        }

        if (null === $Observed) {
            // Never reported. Ask again next poll, when it has.
            return null;
        }
        $renameTo = '';
        if ((bool)$Observed->get('joined')) {
            // Joined to something. Somewhere else, the agent would refuse;
            // where it belongs, the only work left is a rename. Anything
            // else is no reason to hand over a credential.
            $renameTo = self::renameFor($Host, $Observed, $domain, $windows);
            if ('' === $renameTo) {
                return null;
            }
        }
        if (self::cooling($Observed)) {
            return null;
        }

        $user = self::joinUser($Host, $domain);
        $pass = self::joinPassword($Host);
        if ('' === $user || '' === $pass) {
            // No credential to send. Deliberately still returns a block:
            // the agent reports `refused` with a message naming the missing
            // fields, which is how an admin finds out. Sending nothing at
            // all would look identical to a host that is already joined.
            $user = $pass = '';
        }

        $block = [
            'domain' => $domain,
            // The short name where the host's own report supplied it. Used
            // by the agent only to recognize that it is already in this
            // domain, never to join.
            'netbios' => (string)$Observed->get('netbios'),
            // The container the object is CREATED in. Semicolons are
            // stripped the way the legacy client's block does: hostADOU has
            // always been allowed to hold a list and only the first is a
            // container.
            'ou' => str_replace(';', '', (string)$Host->get('ADOU')),
            'username' => $user,
            'password' => $pass,
            // The host's existing "Enforce Hostname | AD Join Reboots"
            // flag: may the agent reboot to finish the join. The agent's
            // reboot coordinator still owns the when.
            'reboot' => (bool)$Host->get('enforce')
        ];
        if ('' !== $renameTo) {
            // Present only for a rename. Its absence means "join", so an
            // agent that predates the field reads the block as a join,
            // finds itself already in the domain, and does nothing.
            $block['rename_to'] = $renameTo;
        }

        return $block;
    }

    /**
     * The name a joined host's computer object should be renamed to, or
     * an empty string when there is no rename to do.
     *
     * Every condition is an observation, not an assumption. The machine
     * account comes from the machine's own report; an empty one means the
     * server cannot tell, and a credential is not sent on a guess.
     *
     * @param Host          $Host     the host
     * @param HostDirectory $Observed what it last reported
     * @param string        $domain   the domain it should be in
     * @param bool          $windows  whether its agent runs on Windows
     *
     * @return string
     */
    protected static function renameFor(
        Host $Host,
        HostDirectory $Observed,
        $domain,
        $windows
    ) {
        if (!$windows) {
            // Only Windows renames through the directory. A Linux host's
            // account keeps its old name after hostnamectl (design 0017
            // section 3.5), so the mismatch would send the credential to it
            // every hour for nothing.
            return '';
        }
        if ($Observed->domainDrifted($domain)) {
            // In another domain: the agent refuses, and 0009 section 1.1
            // says why it must.
            return '';
        }
        $account = strtoupper(trim((string)$Observed->get('machineAccount')));
        $name = trim((string)$Host->get('name'));
        if ('' === $account || '' === $name) {
            return '';
        }
        $want = strtoupper(substr($name, 0, self::NETBIOS_MAX)) . '$';
        if ($want === $account) {
            return '';
        }

        return $name;
    }

    /**
     * Whether the host's issued agent runs on Windows.
     *
     * Read the way State reads it for product-key activation: the platform
     * the agent declared when it enrolled.
     *
     * @param Host $Host the host
     *
     * @return bool
     */
    protected static function isWindows(Host $Host)
    {
        $os = Route::getIds(
            'agentenrollment',
            [
                'hostID' => (int)$Host->get('id'),
                'state' => AgentEnrollment::STATE_ISSUED
            ],
            'os'
        );

        return in_array('windows', (array)$os, true);
    }

    /**
     * Records what the agent did about the join.
     *
     * @param Host  $Host   the host the certificate bound
     * @param int   $hostID the host the agent says it is reporting about
     * @param array $body   the reported result
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return string the status recorded
     */
    public static function report(Host $Host, $hostID, array $body)
    {
        // The row a join result is about is the host's own membership, and
        // the agent addresses it by its host id. Checked rather than
        // ignored: a host reporting on somebody else's membership is a host
        // writing a row that is not its own.
        if ((int)$hostID !== (int)$Host->get('id')) {
            throw new \RuntimeException('not this host\'s membership', 404);
        }
        $status = (string)($body['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('unknown status', 400);
        }
        $error = self::errorFor($status, (string)($body['details'] ?? ''));

        $Observed = self::observed($Host);
        if (null === $Observed) {
            // A result about a host with no membership row. Possible only
            // if the row was deleted between the state fetch and the
            // report; the outcome is still worth an audit line, it simply
            // has nowhere to be stamped.
            self::_audit($Host, $status, $error);
            return $status;
        }

        $Observed
            // Named for the ATTEMPT, not the join: this is stamped whenever
            // the agent acted, so a name like hdJoinedAt would claim a join
            // happened on every occasion one did not -- and it is what the
            // RETRY_AFTER cooldown reads.
            ->set('joinAt', self::stamp())
            ->set('joinError', $error)
            ->save();

        // An already_joined heartbeat is not news, and it is what a working
        // estate reports forever. Auditing it would bury the results that
        // matter.
        if (self::STATUS_ALREADY_JOINED !== $status) {
            self::_audit($Host, $status, $error);
        }

        return $status;
    }

    /**
     * The error to record for a reported status.
     *
     * A settled status clears whatever was there: an admin chasing a stale
     * message against a machine that is now joined is worse off than one
     * chasing none.
     *
     * @param string $status the reported status
     * @param string $error  the reported message
     *
     * @return string
     */
    protected static function errorFor($status, $error)
    {
        if (in_array($status, self::SETTLED_STATUSES, true)) {
            return '';
        }

        return substr(trim($error), 0, self::MAX_ERROR);
    }

    /**
     * Whether an attempt is too recent to make another.
     *
     * @param HostDirectory $Observed the membership row
     *
     * @return bool
     */
    protected static function cooling(HostDirectory $Observed)
    {
        return self::coolingUntil($Observed) > self::niceDate()->getTimestamp();
    }

    /**
     * When the cooldown after the last attempt ends, as a Unix time, or 0
     * when no attempt was ever stamped.
     *
     * @param HostDirectory $Observed the membership row
     *
     * @return int
     */
    protected static function coolingUntil(HostDirectory $Observed)
    {
        $at = trim((string)$Observed->get('joinAt'));
        // validDate() rather than a literal: there stays one definition of
        // what an empty date is, and MySQL's zero date is only one of the
        // shapes an untouched column comes back as.
        if ('' === $at || !self::validDate($at)) {
            return 0;
        }

        return self::niceDate($at, self::storageTimeZone())->getTimestamp()
            + self::RETRY_AFTER;
    }

    /**
     * When a rename that is due but held by the cooldown may go ahead, as
     * an ISO 8601 UTC time, or an empty string when no rename is waiting.
     *
     * The agent cannot see the cooldown. Without this, a joined Windows
     * host renamed in FOG within an hour of its last join or rename logs
     * `hostname: failed` at every poll and gives no reason (design 0017).
     * It rides the hostname block, not the directory block: an agent that
     * predates it ignores the field, where a directory block with no
     * credential would make it report `refused`, and that report stamps a
     * new attempt and restarts the cooldown at every poll.
     *
     * @param Host $Host the host
     *
     * @return string
     */
    public static function renameWait(Host $Host)
    {
        // The two cheap checks first: this runs on every poll of every
        // host with the hostname capability, and most hosts stop here.
        if (!(bool)$Host->get('useAD')
            || '' === trim((string)$Host->get('ADDomain'))
        ) {
            return '';
        }

        return self::waitFor($Host, self::observed($Host), self::isWindows($Host));
    }

    /**
     * The decision behind renameWait(), given what the host last reported.
     *
     * @param Host               $Host     the host
     * @param HostDirectory|null $Observed what it last reported, or null
     * @param bool               $windows  whether its agent runs on Windows
     *
     * @return string
     */
    public static function waitFor(
        Host $Host,
        HostDirectory $Observed = null,
        $windows = false
    ) {
        $domain = trim((string)$Host->get('ADDomain'));
        if (!(bool)$Host->get('useAD') || '' === $domain || null === $Observed
            || !(bool)$Observed->get('joined')
            || '' === self::renameFor($Host, $Observed, $domain, $windows)
        ) {
            return '';
        }
        $until = self::coolingUntil($Observed);
        if ($until <= self::niceDate()->getTimestamp()) {
            return '';
        }

        return gmdate('Y-m-d\TH:i:s\Z', $until);
    }

    /**
     * The joining account, domain-qualified.
     *
     * Same rule the legacy client's block uses, so an admin who has typed
     * `CORP\svc-join` or `svc-join@corp.example.com` into the host record
     * gets what they typed, and a bare name is qualified with the domain.
     * The agent strips the qualifier again for adcli and realm, which want
     * the bare sAMAccountName -- one spelling on the wire, each consumer
     * adapting it, rather than the server sending two.
     *
     * @param Host   $Host   the host
     * @param string $domain the domain being joined
     *
     * @return string
     */
    protected static function joinUser(Host $Host, $domain)
    {
        $user = trim((string)$Host->get('ADUser'));
        if ('' === $user) {
            return '';
        }
        if (false !== strpos($user, '\\') || false !== strpos($user, '@')) {
            return $user;
        }

        return $domain . '\\' . $user;
    }

    /**
     * The joining account's password, as typed.
     *
     * Reuses DirectoryPlacement's decoder rather than repeating the
     * three-shape dance a third time. The legacy client's copy in
     * `Client\HostnameChanger::json()` has the non-strict base64 bug that
     * one documents -- it is left alone here because changing what the
     * legacy client is sent is a separate, riskier change.
     *
     * @param Host $Host the host
     *
     * @return string
     */
    protected static function joinPassword(Host $Host)
    {
        return DirectoryPlacement::decodeStored((string)$Host->get('ADPass'));
    }

    /**
     * The host's reported membership row, or null when it has never
     * reported.
     *
     * @param Host $Host the host
     *
     * @return HostDirectory|null
     */
    protected static function observed(Host $Host)
    {
        $ids = Route::getIds(
            'hostdirectory',
            ['hostID' => (int)$Host->get('id')],
            'id'
        );
        $id = (int)(array_shift($ids) ?: 0);
        if ($id < 1) {
            return null;
        }
        $Observed = new HostDirectory($id);

        return $Observed->isValid() ? $Observed : null;
    }

    /**
     * Now, in storage time.
     *
     * @return string
     */
    protected static function stamp()
    {
        return self::niceDate()
            ->setTimezone(self::storageTimeZone())
            ->format('Y-m-d H:i:s');
    }

    /**
     * One audit line for a join result.
     *
     * @param Host   $Host   the host
     * @param string $status what the agent said it did
     * @param string $error  the message, if any
     *
     * @return void
     */
    private static function _audit(Host $Host, $status, $error)
    {
        Audit::record(
            [
                'type' => 'agent.result',
                'subjectType' => 'host',
                'subjectID' => (int)$Host->get('id'),
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'text' => substr(
                    sprintf(
                        'directory join %s%s',
                        $status,
                        '' === $error ? '' : ': ' . $error
                    ),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }
}
