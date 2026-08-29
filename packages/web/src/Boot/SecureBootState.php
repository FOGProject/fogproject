<?php
/**
 * A machine's reported Secure Boot posture, as one word
 *
 * PHP version 7.4+
 *
 * @category SecureBootState
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

/**
 * A machine's reported Secure Boot posture, as one word
 *
 * Two things report this and they must agree, so the vocabulary and the
 * comparison live here rather than at either reporting site:
 *
 *   - iPXE, on every PXE boot, via `${efi/SecureBoot}` and `${efi/SetupMode}`
 *     posted by default.ipxe. Cheap, early, and it happens whether or not
 *     FOS ever runs.
 *   - FOS, when the enrollment task runs, via sbState() in
 *     usr/share/fog/lib/secureboot-funcs.sh.
 *
 * The five machine states below are FOS's names, taken verbatim from that
 * function so the two reporters cannot drift into two vocabularies for the
 * same fact. UNKNOWN is the sixth and is server-side only: it is what a host
 * reads as before anything has reported, and it is deliberately not a
 * synonym for anything else.
 *
 * It also owns the CERTIFICATE FINGERPRINT format, for the same
 * one-place-or-it-drifts reason. The ledger's asserted half stores the
 * SHA-256 of what was enrolled specifically so that "does this machine trust
 * the certificate this server serves today" is answerable (ADR 0029 decision
 * 5), and that answer is a string comparison between two values which were
 * being formatted longhand in four separate files.
 *
 * ADVISORY ONLY. Every value here originates in an unauthenticated request
 * body -- boot.php has no credential to demand, and the task-completion
 * report is only as trustworthy as the client running it. Nothing may read
 * these as a security control. See ADR 0029 for the full constraint; the
 * short form is that spoofing DISABLED costs a wasted task, and spoofing
 * ENFORCING must cost nothing at all. That applies to the fingerprint too:
 * FRESH below means "this host claims a fingerprint equal to ours", never
 * "this host is verified".
 *
 * @category SecureBootState
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SecureBootState
{
    /**
     * Nothing has reported yet.
     *
     * @var string
     */
    const UNKNOWN = 'unknown';
    /**
     * Booted BIOS/CSM. Secure Boot is not a concept on this boot.
     *
     * @var string
     */
    const NONEFI = 'nonefi';
    /**
     * UEFI, but the Secure Boot variables could not be read.
     *
     * @var string
     */
    const NOEFIVARS = 'noefivars';
    /**
     * Setup Mode: the platform key has been cleared, so db/KEK/PK are
     * writable without a signature. The only state the automatic path needs.
     *
     * @var string
     */
    const SETUP = 'setup';
    /**
     * User Mode with Secure Boot ON.
     *
     * @var string
     */
    const ENFORCING = 'enforcing';
    /**
     * User Mode with Secure Boot OFF.
     *
     * @var string
     */
    const DISABLED = 'disabled';
    /**
     * Every value this class will store, in reporting order.
     *
     * @var array
     */
    private static $_all = [
        self::UNKNOWN,
        self::NONEFI,
        self::NOEFIVARS,
        self::SETUP,
        self::ENFORCING,
        self::DISABLED,
    ];
    /**
     * Whether a stored value is one this class knows.
     *
     * A column read is not a promise: a value could have been written by an
     * older build, by a plugin, or by hand in the database. Anything
     * unrecognized is treated as UNKNOWN by the callers rather than trusted.
     *
     * @param mixed $state the value to test
     *
     * @return bool
     */
    public static function isKnown($state)
    {
        return in_array((string)$state, self::$_all, true);
    }
    /**
     * Classify what default.ipxe posted into one of the six words above.
     *
     * The three inputs are exactly what boot.php can see, and each of the
     * three "absent" cases is a different fact:
     *
     *   $secureBoot === null   the param was not sent at all, which on this
     *                          endpoint means an older default.ipxe. The
     *                          installer rewrites that file on every run, so
     *                          this clears itself; until it does, the honest
     *                          answer is UNKNOWN and not NONEFI.
     *   $secureBoot === ''     the param was sent and expanded to nothing.
     *                          iPXE substitutes an empty string for a
     *                          setting it cannot fetch (core/settings.c,
     *                          "Treat invalid setting names as empty"), so
     *                          this is a real observation: either a BIOS
     *                          build, where the `efi` settings block is
     *                          never registered at all, or UEFI firmware
     *                          that does not expose the variables.
     *   $platform !== 'efi'    settles which of those two it was.
     *
     * Measured 2026-08-28 rather than assumed, because all three readings are
     * indistinguishable if you only look at the value:
     *
     *   UEFI, Setup Mode        SecureBoot=00  SetupMode=01
     *   UEFI, no SB support     SecureBoot=''  SetupMode=''
     *   pcbios (ipxe.lkrn)      SecureBoot=''  SetupMode=''   platform=pcbios
     *
     * The values are hex because efi_settings.c assigns setting_type_hex to
     * any EFI variable with no declared type, and these are one byte, so
     * they arrive as exactly two lowercase hex digits with no delimiter.
     * Compared case-insensitively and after a trim anyway: the format is
     * iPXE's to change, and a comparison that breaks on '00 ' would fail
     * open into "not off", which is the direction that silently stops the
     * enrollment task working.
     *
     * SetupMode is tested BEFORE SecureBoot, matching sbState(). A machine in
     * Setup Mode has no platform key, so it cannot be enforcing, but the
     * ordering is what makes that a stated rule rather than a coincidence.
     *
     * @param string|null $platform   the ${platform} iPXE reported
     * @param string|null $secureBoot the ${efi/SecureBoot} iPXE reported
     * @param string|null $setupMode  the ${efi/SetupMode} iPXE reported
     *
     * @return string one of the constants above
     */
    public static function fromBootRequest(
        $platform,
        $secureBoot,
        $setupMode
    ) {
        if (null === $secureBoot && null === $setupMode) {
            return self::UNKNOWN;
        }
        if ('efi' !== strtolower(trim((string)$platform))) {
            return self::NONEFI;
        }
        $sb = strtolower(trim((string)$secureBoot));
        $sm = strtolower(trim((string)$setupMode));
        if ('' === $sb && '' === $sm) {
            return self::NOEFIVARS;
        }
        if ('01' === $sm) {
            return self::SETUP;
        }
        if ('01' === $sb) {
            return self::ENFORCING;
        }
        if ('00' === $sb) {
            return self::DISABLED;
        }
        // Reachable: firmware is not obliged to expose both variables, and a
        // value that is neither 00 nor 01 is a byte nothing here understands.
        // NOEFIVARS rather than DISABLED, because "we could not read this"
        // must never collapse into the one answer that makes a host look
        // like a valid enrollment target.
        return self::NOEFIVARS;
    }
    /**
     * Whether the enrollment task can do anything useful on this host.
     *
     * True for the two states where FOS can boot and has somewhere to put
     * the certificate. SETUP is the good one -- fog.enrollsb writes db and
     * finishes with nobody at the keyboard -- and DISABLED is the one ADR
     * 0008 was written for, where it stages a MOK for a human to confirm.
     *
     * UNKNOWN is true, and that is a deliberate softening of "refuse
     * anything not positively known to be off". Nothing is reported until a
     * host PXE boots, so a hard refusal would make this task unusable on
     * every existing fleet until a full boot cycle had happened, in exchange
     * for preventing the one thing that already happens today on every such
     * host: a wasted task. The host list filter is what stops an unknown
     * host LOOKING eligible; this is only about whether an admin who asks
     * for it anyway is stopped.
     *
     * The three false states are false for reasons FOS states itself:
     * fog.enrollsb aborts on nonefi and noefivars, and cannot run at all
     * when enforcing because the machine will not boot FOS in the first
     * place.
     *
     * @param mixed $state the stored state
     *
     * @return bool
     */
    public static function isEnrollmentTarget($state)
    {
        if (!self::isKnown($state)) {
            return true;
        }

        return in_array(
            (string)$state,
            [self::UNKNOWN, self::SETUP, self::DISABLED],
            true
        );
    }
    /**
     * Whether scheduling against this host is a guess rather than a decision.
     *
     * Separate from isEnrollmentTarget() on purpose: UNKNOWN is allowed
     * through and still has to be said out loud, and the two answers are
     * needed in different places -- the refusal, and the warning next to it.
     *
     * @param mixed $state the stored state
     *
     * @return bool
     */
    public static function isUnreported($state)
    {
        return !self::isKnown($state) || self::UNKNOWN === (string)$state;
    }
    /**
     * Why the enrollment task will not run here, or '' if it will.
     *
     * Lives here rather than at either call site because there ARE two call
     * sites and they are not on the same code path: a single-host task goes
     * through Host::createImagePackage(), and a group task for a non-imaging
     * type never calls that method at all -- it batch-inserts over the group's
     * host ids. Two hand-written refusals with the same intent is how one of
     * them ends up saying something different, or stops firing.
     *
     * Each message names what the machine is and what to do about it, because
     * the alternative is an admin scheduling against 200 machines and watching
     * every one fail to boot with nothing to say why. That is the failure ADR
     * 0008 already identified and put in the task description; this is the
     * same warning delivered at the moment it can still be acted on.
     *
     * @param mixed $state the stored state
     *
     * @return string the refusal, or '' when the task may proceed
     */
    public static function refusalReason($state)
    {
        if (self::isEnrollmentTarget($state)) {
            return '';
        }
        switch ((string)$state) {
            case self::ENFORCING:
                return _(
                    'This host last reported Secure Boot as ON. It does not '
                    . 'trust this server\'s kernel yet, so it cannot boot FOS '
                    . 'to run the task at all. Turn Secure Boot off in its '
                    . 'firmware first, or enroll from local media.'
                );
            case self::NONEFI:
                return _(
                    'This host last booted in legacy BIOS mode, where Secure '
                    . 'Boot does not exist. There is nothing for this task to '
                    . 'enroll into.'
                );
            default:
                return _(
                    'This host last reported UEFI firmware whose Secure Boot '
                    . 'state could not be read, so FOS has nowhere to write '
                    . 'the certificate.'
                );
        }
    }
    /**
     * A translated label for a stored state.
     *
     * Written for an admin who has never met the term "Setup Mode", because
     * this string is the whole of what most people will ever read about it.
     * The Secure Boot configuration page carries the long form.
     *
     * @param mixed $state the stored state
     *
     * @return string
     */
    public static function label($state)
    {
        switch ((string)$state) {
            case self::NONEFI:
                return _('Legacy BIOS (no Secure Boot)');
            case self::NOEFIVARS:
                return _('UEFI, state unreadable');
            case self::SETUP:
                return _('Setup Mode (unattended enrollment)');
            case self::ENFORCING:
                return _('Secure Boot ON');
            case self::DISABLED:
                return _('Secure Boot OFF');
            default:
                return _('Never reported');
        }
    }

    // ------------------------------------------------------------------
    // The certificate half of the ledger.
    //
    // hostSbEnrollCert exists to be COMPARED, not merely stored. Without
    // this section the column was write-only in practice: the value was
    // recorded, rendered and exported, and the one question it was added to
    // answer -- "does this machine trust what I am serving today" -- was left
    // to an administrator eyeballing 95 hex characters against a different
    // page. That is not an answer, it is the raw material for one.
    // ------------------------------------------------------------------

    /**
     * The stored fingerprint equals the certificate this server serves.
     *
     * @var string
     */
    const FRESH = 'current';
    /**
     * The stored fingerprint is some OTHER certificate.
     *
     * @var string
     */
    const STALE = 'stale';
    /**
     * Memoised server fingerprint. false = not yet computed.
     *
     * @var string|false
     */
    private static $_serverPrint = false;
    /**
     * Where the installer puts the Secure Boot signing certificate.
     *
     * Third copy of this path avoided rather than added: it was already
     * spelled out in FOGConfigurationPage::secureBoot() and twice in
     * IpxeBootMenu. A path written in four places is a path that moves in
     * three.
     *
     * @return string '' when BASEPATH is not defined (CLI tests, tooling)
     */
    public static function certPath()
    {
        if (!defined('BASEPATH')) {
            return '';
        }

        return BASEPATH . 'service/secureboot' . DS . 'MOK.der';
    }
    /**
     * Canonicalize a SHA-256 fingerprint, or reject it.
     *
     * One format, one place. The colon-separated upper-case form is what
     * FOGConfigurationPage::secureBoot() displays and what FOS's
     * sbCertFingerprint() prints, so it is what gets stored -- but a value
     * arriving from a human is as likely to be the bare hex a copy-paste
     * produces, and a value arriving from FOS could be either.
     *
     * Rejecting rather than storing-whatever-arrived is the point. This
     * column's only use is an equality test, and a comparison against
     * something that is not a SHA-256 can only ever be false -- silently, and
     * looking exactly like "this host trusts an older certificate", which is
     * the one wrong answer that sends somebody to re-enroll a machine that is
     * already fine.
     *
     * @param mixed $value bare or colon-separated hex, any case
     *
     * @return string the canonical form, or '' if it is not a SHA-256
     */
    public static function normalizeFingerprint($value)
    {
        $bare = str_replace(':', '', strtoupper(trim((string)$value)));
        if (!preg_match('/^[0-9A-F]{64}$/', $bare)) {
            return '';
        }

        return implode(':', str_split($bare, 2));
    }
    /**
     * The fingerprint of the certificate this server is serving right now.
     *
     * The SHA-256 of the DER bytes IS the certificate fingerprint, so there
     * is no openssl round trip -- the same reasoning, and the same one line,
     * the Secure Boot configuration page has always used.
     *
     * Memoised because the grid asks once per row and the file does not
     * change within a request.
     *
     * @return string '' when this server has no signing certificate
     */
    public static function serverFingerprint()
    {
        if (false !== self::$_serverPrint) {
            return self::$_serverPrint;
        }
        self::$_serverPrint = '';
        $cert = self::certPath();
        if ('' !== $cert && is_readable($cert)) {
            $hash = hash_file('sha256', $cert);
            if (false !== $hash) {
                self::$_serverPrint = self::normalizeFingerprint($hash);
            }
        }

        return self::$_serverPrint;
    }
    /**
     * Whether a host's enrollment record matches what this server serves.
     *
     * Returns '' -- not STALE -- for every case where the question cannot be
     * answered: nothing recorded against the host, an unparseable stored
     * value, or a server with no signing certificate at all. Those are three
     * different kinds of "we do not know", and none of them is evidence that
     * a machine trusts the wrong key. Reporting them as STALE would put a
     * red badge on every host in a fleet that has simply never enrolled, and
     * a warning that is on everything is a warning nobody reads.
     *
     * @param mixed       $stored the host's hostSbEnrollCert
     * @param string|null $server override, for tests; the live value if null
     *
     * @return string FRESH, STALE, or '' when it cannot be answered
     */
    public static function enrollmentFreshness($stored, $server = null)
    {
        $stored = self::normalizeFingerprint($stored);
        if ('' === $stored) {
            return '';
        }
        $server = null === $server
            ? self::serverFingerprint()
            : self::normalizeFingerprint($server);
        if ('' === $server) {
            return '';
        }

        return $stored === $server ? self::FRESH : self::STALE;
    }
    /**
     * A translated label for a freshness result.
     *
     * The stale wording names the CONSEQUENCE rather than the mismatch,
     * because the mismatch on its own does not tell anyone what to do: a
     * machine trusting a superseded certificate boots fine until the day the
     * old FOS kernels stop being served, and then stops booting with no
     * apparent cause.
     *
     * @param string $freshness FRESH, STALE or ''
     *
     * @return string '' when there is nothing to say
     */
    public static function freshnessLabel($freshness)
    {
        switch ((string)$freshness) {
            case self::FRESH:
                return _("Matches this server's current certificate");
            case self::STALE:
                return _(
                    'Does NOT match this server\'s current certificate -- '
                    . 'this host trusts a superseded one and will stop '
                    . 'booting under Secure Boot once it is retired. Re-run '
                    . 'the Secure Boot enrollment task on it.'
                );
            default:
                return '';
        }
    }
}
