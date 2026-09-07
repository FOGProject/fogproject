<?php
/**
 * The version an agent should be running, and where to find out what it is.
 *
 * PHP version 7.4+
 *
 * @category Update
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;

/**
 * Self-update, design 0015.
 *
 * This server names a version and nothing else. What that version IS comes
 * from a release manifest signed by FOG Project, which the agent verifies
 * against a root compiled into its own binary before it will run a byte of
 * it. So a compromised or simply mistaken FOG server can choose which
 * PUBLISHED version a fleet runs, and cannot publish one -- which is the
 * boundary that makes it safe for an update channel to reach every managed
 * machine automatically with no human in the loop.
 *
 * That is also why `manifest_url` can be sent from here at all. A hostile
 * server pointing an agent at its own mirror achieves one of three things:
 * the mirror serves nothing, it serves an older signed manifest (refused by
 * the agent's sequence floor), or it serves the real one. There is no
 * fourth option, so the URL needs no trust and no permission of its own.
 *
 * There is no legacy module behind this capability, so unlike every other
 * one it is not gated on `moduleStatusByHost`. It is gated on an admin
 * having set a version, and the shipped default is empty: no host begins
 * updating itself because somebody upgraded their server.
 *
 * @category Update
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Update extends FOGBase
{
    /**
     * The capability name on the wire.
     */
    const CAPABILITY = 'update';

    /**
     * Where a host stands in relation to the version it was told to run
     * (schema 435). Stored on the host so it can be a column and a filter:
     * the whole point is turning "something out there refused" into a list
     * of the hosts that did.
     *
     * STATE_NONE is not "healthy", it is "not asked" -- the shipped default
     * where no desired version is set anywhere.
     */
    const STATE_NONE = '';
    const STATE_OK = 'ok';
    const STATE_PENDING = 'pending';
    const STATE_REFUSED = 'refused';
    const STATE_CANNOT = 'cannot';

    /**
     * The agent's failure vocabulary, split by who has to fix it.
     *
     * REFUSED means a check the agent performs on the way in said no. The
     * asked-for version is bad, or the manifest describing it did not
     * verify -- wrong signature, replayed or expired, or bytes that do not
     * hash to what it promised. Every one of these is a property of what
     * this server asked for, so the next host will refuse it identically
     * and the thing to look at is the version and the mirror.
     *
     * CANNOT means the agent accepted the instruction and could not carry
     * it out: this build has no signing root, the manifest has no artifact
     * for the machine's platform, the download failed, the rollback could
     * not be armed, or the swap itself failed. These are properties of one
     * machine or of the path between it and the mirror, so other hosts may
     * be perfectly fine and the thing to look at is that host.
     *
     * Splitting them is the entire value of the column over a bare
     * "failed": the two send you to different places. A detail this map
     * does not recognize -- an older or newer agent with a code this server
     * has not heard of -- is treated as CANNOT, because "this machine did
     * not get there" is the safer of the two things to say about an
     * unknown, and it does not accuse the admin's chosen version of being
     * broken on evidence we cannot read.
     *
     * Keys are matched against the detail's leading token, which is how the
     * agent emits them: "signature_invalid: the manifest is not signed...".
     */
    const REFUSALS = [
        'bad_desired_version',
        'below_floor',
        'signature_invalid',
        'stale_manifest',
        'hash_mismatch'
    ];

    /**
     * The audit types a transition records (design 0015 section 13). These
     * are machine-originated rows: ADR 0021 decision 4 applies, so they
     * carry createdBy 'fog', authSource 'agent' and an empty permission,
     * saying truthfully that no authorization was consulted.
     */
    const AUDIT_APPLIED = 'agent.update.applied';
    const AUDIT_REFUSED = 'agent.update.refused';
    const AUDIT_REVERTED = 'agent.update.reverted';

    /**
     * The leading token of a detail, which is where the agent puts its
     * machine-readable code.
     *
     * Three separators, because the agent uses all three and a code
     * contains none of them: a colon before prose ("signature_invalid: the
     * manifest is not signed..."), a comma before a clause ("deferred,
     * imaging in flight") and a plain space ("already 0.4.1"). Missing the
     * comma is not a cosmetic bug -- it made every deferred update read as
     * 'ok', so a host that had stood aside for an imaging job showed as
     * having arrived at a version it had not installed.
     *
     * @param string $detail the reported detail
     *
     * @return string
     */
    public static function code($detail)
    {
        $detail = trim((string)$detail);
        $cut = strcspn($detail, ":, ");
        return substr($detail, 0, $cut);
    }

    /**
     * Turns one reported result into the state to store on the host.
     *
     * 'applied' is PENDING rather than OK on purpose. The agent reports it
     * after the swap and before the restart, so at that moment the host is
     * running the old binary still and agentVersion has not moved. Calling
     * it ok here would light the column green on the strength of a promise;
     * the next poll arrives as the new version and reconcile() makes it ok
     * on the strength of the version actually reported. The window is one
     * poll interval and it says "in flight", which is true.
     *
     * @param string $status the result status
     * @param string $detail the result detail
     *
     * @return string one of the STATE_* constants
     */
    public static function classify($status, $detail)
    {
        $code = self::code($detail);
        if ('reverted' === $code) {
            return self::STATE_CANNOT;
        }
        switch ($status) {
            case 'applied':
            case 'pending_reboot':
                return self::STATE_PENDING;
            case 'unchanged':
                // Two very different unchangeds share one status on the wire:
                // "already at that version" is arrival, "deferred, X in
                // flight" is a host that stood aside for an imaging job and
                // will try again.
                return 'deferred' === $code ? self::STATE_PENDING : self::STATE_OK;
            case 'failed':
                return in_array($code, self::REFUSALS, true)
                    ? self::STATE_REFUSED
                    : self::STATE_CANNOT;
        }
        return self::STATE_PENDING;
    }

    /**
     * The audit type for a reported result, or '' for one not worth a row.
     *
     * "Checked, already at the desired version" is deliberately not
     * audited: that is every poll on every host forever, and ADR 0021
     * decision 4's scope limit -- writes that change state a person would
     * care about, "not every checkin" -- excludes it explicitly.
     *
     * @param string $status the result status
     * @param string $detail the result detail
     *
     * @return string an AUDIT_* constant, or '' to record nothing
     */
    public static function auditType($status, $detail)
    {
        if ('reverted' === self::code($detail)) {
            return self::AUDIT_REVERTED;
        }
        if ('applied' === $status) {
            return self::AUDIT_APPLIED;
        }
        if ('failed' === $status) {
            return self::AUDIT_REFUSED;
        }
        return '';
    }

    /**
     * The line the audit log shows for one update report.
     *
     * Two of the three types arrive with a detail that already names its
     * own transition, and prefixing those with "running -> desired"
     * prints the same arrow twice:
     *
     *   applied   the agent sends "0.1.1 -> 0.1.2, restarting", which
     *             rendered as "0.1.1 -> 0.1.2: 0.1.1 -> 0.1.2,
     *             restarting" (observed on host 239, 2026-09-07).
     *   reverted  the same, and it runs the other way, so the prefix put
     *             two arrows in one line saying opposite things:
     *             "da11e37 -> 9.9.9: reverted: 9.9.9 -> da11e37".
     *
     * Those two are also the AGENT's account of what it did, which beats
     * the server's: the agent knows what it was actually running, while
     * $running is only the last value that got reported. A refusal is the
     * other shape -- its detail is a reason ("signature_invalid: ..."),
     * so the prefix is the only place the versions appear at all.
     *
     * @param string $type    one of the AUDIT_* constants
     * @param string $running the version the host last reported
     * @param string $desired the version it was asked to run
     * @param string $detail  the agent's own words
     *
     * @return string
     */
    public static function auditText($type, $running, $desired, $detail)
    {
        if (self::AUDIT_REVERTED === $type || self::AUDIT_APPLIED === $type) {
            return (string)$detail;
        }
        return sprintf(
            '%s -> %s%s',
            '' === (string)$running ? _('unknown') : (string)$running,
            '' === (string)$desired ? _('unknown') : (string)$desired,
            '' === (string)$detail ? '' : ': ' . $detail
        );
    }

    /**
     * The state a poll implies, given what the host just said it is running.
     *
     * Called on every poll, and it is what closes the loop: a host that has
     * arrived goes ok without needing to report anything, and a host whose
     * desired version is cleared goes back to "not asked" instead of
     * carrying a stale refusal forever.
     *
     * A failure is NOT cleared just because the versions still disagree --
     * that would erase the reason on the very next poll, seconds later,
     * leaving a column that never shows a refusal to anyone. It is cleared
     * by arriving, or by the question being withdrawn.
     *
     * @param Host   $Host    the host that just polled
     * @param string $running the version it reports running
     *
     * @return string one of the STATE_* constants
     */
    public static function reconcile(Host $Host, $running)
    {
        $want = self::version($Host);
        if ('' === $want) {
            return self::STATE_NONE;
        }
        if ('' !== $running && $running === $want) {
            return self::STATE_OK;
        }
        $now = (string)$Host->get('agentUpdateState');
        if (self::STATE_REFUSED === $now || self::STATE_CANNOT === $now) {
            return $now;
        }
        return self::STATE_PENDING;
    }

    /**
     * The version this host should be running, or '' for none.
     *
     * The host's own value is an OVERRIDE, not a floor: it wins even when
     * it is lower than the global. That is deliberate and it is the whole
     * recovery story. The likeliest shape of a bad release is one that
     * installs, starts and polls perfectly well and then behaves badly --
     * local rollback cannot catch that, because by every local measure the
     * agent is healthy. The only fix is the server naming an older
     * version, and a max() of host and global could not express it.
     *
     * The cost is that an override is sticky, and a forgotten one holds a
     * machine back silently. That is paid for by showing it: the host list
     * says which hosts carry one, so "everything not following the fleet"
     * is a filter and clearing them is one mass edit.
     *
     * @param Host $Host the host asking
     *
     * @return string
     */
    public static function version(Host $Host)
    {
        $own = trim((string)$Host->get('agentDesiredVersion'));
        if ('' !== $own) {
            return $own;
        }
        return trim((string)self::getSetting('FOG_AGENT_DESIRED_VERSION'));
    }

    /**
     * The update block of the desired state, or null when this host has no
     * desired version and the capability is not offered at all.
     *
     * @param Host $Host the host asking
     *
     * @return array|null
     */
    public static function desired(Host $Host)
    {
        $version = self::version($Host);
        if ('' === $version) {
            return null;
        }
        $block = ['desired' => $version];
        // Sent only when a mirror is configured. An absent key means "the
        // location built into the agent", which is a different statement
        // from an empty one and the agent reads it as such.
        $url = trim((string)self::getSetting('FOG_AGENT_UPDATE_MANIFEST_URL'));
        if ('' !== $url) {
            $block['manifest_url'] = $url;
        }
        return $block;
    }
}
