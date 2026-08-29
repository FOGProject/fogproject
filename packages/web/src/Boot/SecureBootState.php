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
 *   - FOS, when the enrolment task runs, via sbState() in
 *     usr/share/fog/lib/secureboot-funcs.sh.
 *
 * The five machine states below are FOS's names, taken verbatim from that
 * function so the two reporters cannot drift into two vocabularies for the
 * same fact. UNKNOWN is the sixth and is server-side only: it is what a host
 * reads as before anything has reported, and it is deliberately not a
 * synonym for anything else.
 *
 * ADVISORY ONLY. Every value here originates in an unauthenticated request
 * body -- boot.php has no credential to demand, and the task-completion
 * report is only as trustworthy as the client running it. Nothing may read
 * these as a security control. See ADR 0029 for the full constraint; the
 * short form is that spoofing DISABLED costs a wasted task, and spoofing
 * ENFORCING must cost nothing at all.
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
     * unrecognised is treated as UNKNOWN by the callers rather than trusted.
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
     * enrolment task working.
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
        // like a valid enrolment target.
        return self::NOEFIVARS;
    }
    /**
     * Whether the enrolment task can do anything useful on this host.
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
    public static function isEnrolmentTarget($state)
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
     * Separate from isEnrolmentTarget() on purpose: UNKNOWN is allowed
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
     * Why the enrolment task will not run here, or '' if it will.
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
        if (self::isEnrolmentTarget($state)) {
            return '';
        }
        switch ((string)$state) {
            case self::ENFORCING:
                return _(
                    'This host last reported Secure Boot as ON. It does not '
                    . 'trust this server\'s kernel yet, so it cannot boot FOS '
                    . 'to run the task at all. Turn Secure Boot off in its '
                    . 'firmware first, or enrol from local media.'
                );
            case self::NONEFI:
                return _(
                    'This host last booted in legacy BIOS mode, where Secure '
                    . 'Boot does not exist. There is nothing for this task to '
                    . 'enrol into.'
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
                return _('Setup Mode (unattended enrolment)');
            case self::ENFORCING:
                return _('Secure Boot ON');
            case self::DISABLED:
                return _('Secure Boot OFF');
            default:
                return _('Never reported');
        }
    }
}
