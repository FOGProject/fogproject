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
 * attempt. Most hosts in most estates never receive it at all.
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
        'joined', 'already_joined', 'failed', 'unsupported', 'refused'
    ];

    /**
     * The statuses that mean the machine is where it should be, so any
     * error recorded against it is stale.
     */
    const SETTLED_STATUSES = ['joined', 'already_joined'];

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
     * @param Host $Host the principal
     *
     * @return array|null
     */
    public static function desired(Host $Host)
    {
        return self::blockFor($Host, self::observed($Host));
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
     *
     * @return array|null
     */
    public static function blockFor(Host $Host, HostDirectory $Observed = null)
    {
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
        if ((bool)$Observed->get('joined')) {
            // Joined to something. Either it is where it belongs, or it is
            // somewhere else and the agent would refuse; neither is a
            // reason to hand over a credential.
            return null;
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

        return [
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
        $at = trim((string)$Observed->get('joinAt'));
        // validDate() rather than a literal: there stays one definition of
        // what an empty date is, and MySQL's zero date is only one of the
        // shapes an untouched column comes back as.
        if ('' === $at || !self::validDate($at)) {
            return false;
        }

        return (self::niceDate()->getTimestamp()
            - self::niceDate($at, self::storageTimeZone())->getTimestamp())
            < self::RETRY_AFTER;
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
