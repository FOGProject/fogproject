<?php
/**
 * The agent update state: what a host's verdict on a version turns into.
 *
 * Schema 435 puts one word on every host saying whether it is converging on
 * the version it was told to run, so that "which machines refused 0.4.2" is
 * a filter on the host list instead of a walk through the audit log. That
 * word is derived, and every derivation in it is a decision this file pins.
 *
 * The four states, and why the last two are not one 'failed':
 *
 * - refused: a check the agent runs on the way IN said no. Bad version
 *   string, signature that does not verify, replayed or expired manifest,
 *   bytes that do not match their hash. Each is a property of what the
 *   SERVER asked for, so every other host will refuse it identically.
 * - cannot: the agent accepted the instruction and could not carry it out.
 *   No signing root in the build, no artifact for the platform, the fetch,
 *   the arming or the swap failed. Each is a property of ONE machine or the
 *   path to the mirror, so the rest of the fleet may be fine.
 *
 * Collapsing those two loses the only thing the column is for: they send
 * different people to different places. So the split is pinned code by code,
 * and an unrecognized code must land on 'cannot' -- an agent newer than this
 * server will emit codes this server has never heard of, and guessing
 * 'refused' would accuse the admin's chosen version of being broken on
 * evidence nobody can read.
 *
 * The other three decisions here, each of which was a live bug shape:
 *
 * - 'applied' is PENDING, not ok. The agent reports it after the swap and
 *   BEFORE the restart, so agentVersion has not moved yet. Calling it ok
 *   would light the column green on a promise; the next poll makes it ok on
 *   the strength of the version actually reported.
 * - 'unchanged' is two different facts on one wire status. "already 0.4.2"
 *   is arrival; "deferred, imaging in flight" is a host that stood aside.
 *   One is ok and one is pending and they must not share an answer.
 * - reconcile() must NOT clear a failure just because the versions still
 *   disagree. That would erase the reason on the very next poll seconds
 *   later, and the column would never show a refusal to anyone. A failure
 *   clears by ARRIVING, or by the question being withdrawn.
 *
 * The detail codes are the agent's, emitted by
 * internal/provider/update/update.go in FOGProject/fog-agent (the Detail*
 * constants) and by its revert report. They are listed literally here
 * because that repository is not present in this one's CI; a code added
 * there and not here classifies as 'cannot', which is the safe direction.
 *
 * Usage: php tests/agent-update-state.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-update-state');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * Equality with the actual value in the label, so a failure says what it got
 * rather than only which line disagreed. FogChecks::check() takes a boolean.
 *
 * @param string $label what is being asserted
 * @param mixed  $want  the expected value
 * @param mixed  $got   the value under test
 *
 * @return bool
 */
$eq = function ($label, $want, $got) use ($t) {
    return $t->check(
        $label . ' [want ' . var_export($want, true)
            . ', got ' . var_export($got, true) . ']',
        $want === $got
    );
};

use FOG\Agent\Update;
use FOG\Items\Host;

// ------------------------------------------------------------------- code()

// The agent puts its machine-readable code first and the prose after, split
// by either a colon or a space. Both separators appear in real details.
$eq('code() reads the token before a colon', 'signature_invalid', Update::code('signature_invalid: the manifest is not signed by a key this build trusts'));
$eq('code() reads the token before a space', 'reverted', Update::code('reverted: 0.4.2 -> 0.4.1'));
$eq('code() of a bare code is the code', 'hash_mismatch', Update::code('hash_mismatch'));
$eq('code() of prose with no code is harmless', 'already', Update::code('already 0.4.1'));
$eq('code() of nothing is nothing', '', Update::code(''));

// --------------------------------------------------------------- classify()

// Every code the agent can emit, and the side of the line it falls on. A
// change to either column here is a change to who gets paged.
$refusals = [
    'bad_desired_version',
    'below_floor',
    'signature_invalid',
    'stale_manifest',
    'hash_mismatch',
];
$cannots = [
    'no_signing_root',
    'no_artifact',
    'fetch_failed',
    'cannot_arm_rollback',
    'swap_failed',
];
foreach ($refusals as $code) {
    $eq(
        "failed/$code is a refusal: the version or the manifest is wrong, not the machine",
        Update::STATE_REFUSED,
        Update::classify('failed', $code . ': some prose')
    );
}
foreach ($cannots as $code) {
    $eq(
        "failed/$code is a cannot: this machine or its path to the mirror",
        Update::STATE_CANNOT,
        Update::classify('failed', $code . ': some prose')
    );
}
$eq(
    'a code this server has never heard of is a cannot, not a refusal',
    Update::STATE_CANNOT,
    Update::classify('failed', 'some_future_code: from a newer agent')
);
$eq(
    'a failure with no detail at all is still a cannot',
    Update::STATE_CANNOT,
    Update::classify('failed', '')
);

// applied is a promise, not an arrival.
$eq('applied is pending: the swap happened, the restart has not', Update::STATE_PENDING, Update::classify('applied', '0.4.1 -> 0.4.2, restarting'));
$eq('pending_reboot is pending', Update::STATE_PENDING, Update::classify('pending_reboot', 'waiting on a reboot'));

// unchanged carries two different facts.
$eq('unchanged/already is ok', Update::STATE_OK, Update::classify('unchanged', 'already 0.4.2'));
$eq('unchanged/deferred is pending, not ok', Update::STATE_PENDING, Update::classify('unchanged', 'deferred, imaging in flight'));

// A revert outranks its wire status: it is reported as a failure, but what
// it means is "this machine tried and went back", which is a cannot.
$eq('a revert report is a cannot whatever status carries it', Update::STATE_CANNOT, Update::classify('failed', 'reverted: 0.4.2 -> 0.4.1 (no successful poll before 2026-01-01T00:00:00Z)'));

// -------------------------------------------------------------- auditType()

$eq('applied records agent.update.applied', Update::AUDIT_APPLIED, Update::auditType('applied', '0.4.1 -> 0.4.2, restarting'));
$eq('a failure records agent.update.refused', Update::AUDIT_REFUSED, Update::auditType('failed', 'signature_invalid: nope'));
$eq('a revert records agent.update.reverted, not refused', Update::AUDIT_REVERTED, Update::auditType('failed', 'reverted: 0.4.2 -> 0.4.1'));
// ADR 0021 decision 4's scope limit: writes that change state a person would
// care about, "not every checkin". "Already at the desired version" is every
// poll on every host forever.
$eq('already at the version records NOTHING', '', Update::auditType('unchanged', 'already 0.4.2'));
$eq('deferred records nothing either', '', Update::auditType('unchanged', 'deferred, imaging in flight'));
// auditText: which types already name their own transition.
//
// This is the bug the live rollout printed on host 239 (2026-09-07): the
// agent's applied detail IS a transition, so prefixing it with the
// server's own "running -> desired" rendered the same arrow twice.
$eq(
    'applied uses the agent detail alone, no duplicated arrow',
    '0.1.1 -> 0.1.2, restarting',
    Update::auditText(Update::AUDIT_APPLIED, '0.1.1', '0.1.2', '0.1.1 -> 0.1.2, restarting')
);
$eq(
    'reverted uses the agent detail alone too',
    'reverted: 9.9.9 -> da11e37',
    Update::auditText(Update::AUDIT_REVERTED, 'da11e37', '9.9.9', 'reverted: 9.9.9 -> da11e37')
);
// A refusal's detail is a REASON, so the prefix is the only place the
// versions appear at all -- dropping it there would lose them.
$eq(
    'a refusal keeps the running -> desired prefix',
    '0.1.1 -> 9.9.9: signature_invalid: the manifest is not signed by a key this build trusts',
    Update::auditText(Update::AUDIT_REFUSED, '0.1.1', '9.9.9', 'signature_invalid: the manifest is not signed by a key this build trusts')
);
$eq(
    'an unreported running version reads unknown rather than an empty arrow',
    'unknown -> 0.1.2: no_artifact',
    Update::auditText(Update::AUDIT_REFUSED, '', '0.1.2', 'no_artifact')
);

$eq('the three audit types are distinct', 3, count(array_unique([Update::AUDIT_APPLIED, Update::AUDIT_REFUSED, Update::AUDIT_REVERTED])));
// The host tab and the Logging page both read auditLog with a LIKE on
// AgentActivityManagement::TYPE_PREFIX, so a type outside that prefix would
// be recorded and then be invisible in both places.
foreach ([Update::AUDIT_APPLIED, Update::AUDIT_REFUSED, Update::AUDIT_REVERTED] as $type) {
    $eq(
        "$type is under the agent. prefix, or it never shows in Agent Activity",
        0,
        strpos($type, \FOG\Pages\AgentActivityManagement::TYPE_PREFIX)
    );
}

// -------------------------------------------------------------- reconcile()

/**
 * A host carrying a desired version, a running version and a stored state.
 *
 * @param string $desired the per-host override
 * @param string $running what it last reported running
 * @param string $state   the state currently stored
 *
 * @return Host
 */
function updateHost($desired, $running, $state)
{
    $Host = new Host();
    $Host->set('agentDesiredVersion', $desired)
        ->set('agentVersion', $running)
        ->set('agentUpdateState', $state);

    return $Host;
}

$eq(
    'arriving at the desired version is ok',
    Update::STATE_OK,
    Update::reconcile(updateHost('0.4.2', '0.4.2', Update::STATE_PENDING), '0.4.2')
);
$eq(
    'arriving CLEARS a refusal: the host got there in the end',
    Update::STATE_OK,
    Update::reconcile(updateHost('0.4.2', '0.4.2', Update::STATE_REFUSED), '0.4.2')
);
$eq(
    'not there yet, nothing reported: pending',
    Update::STATE_PENDING,
    Update::reconcile(updateHost('0.4.2', '0.4.1', ''), '0.4.1')
);
$eq(
    'a refusal SURVIVES a poll that is still short of the version',
    Update::STATE_REFUSED,
    Update::reconcile(updateHost('0.4.2', '0.4.1', Update::STATE_REFUSED), '0.4.1')
);
$eq(
    'a cannot survives too',
    Update::STATE_CANNOT,
    Update::reconcile(updateHost('0.4.2', '0.4.1', Update::STATE_CANNOT), '0.4.1')
);
$eq(
    'withdrawing the desired version clears a refusal to "not asked"',
    Update::STATE_NONE,
    Update::reconcile(updateHost('', '0.4.1', Update::STATE_REFUSED), '0.4.1')
);
$eq(
    'a host that has never reported a version is pending, not ok',
    Update::STATE_PENDING,
    Update::reconcile(updateHost('0.4.2', '', ''), '')
);
// The empty-equals-empty trap: with no desired version AND no running
// version, a naive equality test reports ok on a host nothing was asked of.
$eq(
    'no desired version at all is "not asked", never ok',
    Update::STATE_NONE,
    Update::reconcile(updateHost('', '', ''), '')
);

// ------------------------------------------------- the "v" prefix, one form

// The bug this section pins, seen in the lab on 2026-09-08: two hosts that
// had successfully updated to 0.1.6 sat in `pending` overnight. Nothing was
// wrong with either of them. A release is tagged `v0.1.6` and
// build/cross.sh stamps main.Version from the tag, so the agent reports
// `v0.1.6`; the desired version is written `0.1.6`, the form the manifest
// and the settings field use. reconcile() compared the raw strings, they
// never matched, and the state could not reach ok by any route -- the one
// state that is supposed to be self-clearing was the one that could not
// clear. The agent's own comparator has always trimmed the prefix
// (internal/provider/update/version.go); the server had not.
$eq('normalize() strips a leading v', '0.1.6', Update::normalize('v0.1.6'));
$eq('normalize() strips an upper-case V a human typed', '0.1.6', Update::normalize('V0.1.6'));
$eq('normalize() leaves a bare version alone', '0.1.6', Update::normalize('0.1.6'));
$eq('normalize() trims surrounding space', '0.1.6', Update::normalize('  v0.1.6  '));
$eq('normalize() of nothing is nothing', '', Update::normalize(''));
// Only the prefix, and only one of it: a v anywhere else is part of the
// string the admin or the agent meant, not decoration to be eaten.
$eq('normalize() strips ONE v, not a run of them', 'v0.1.6', Update::normalize('vv0.1.6'));
$eq('normalize() leaves a v inside a pre-release tag', '0.1.6-rc.v2', Update::normalize('0.1.6-rc.v2'));

$eq(
    'a host reporting v0.1.6 has ARRIVED at a desired 0.1.6',
    Update::STATE_OK,
    Update::reconcile(updateHost('0.1.6', 'v0.1.6', Update::STATE_PENDING), 'v0.1.6')
);
$eq(
    'and the mirror: a desired v0.1.6 typed into the field is reached by 0.1.6',
    Update::STATE_OK,
    Update::reconcile(updateHost('v0.1.6', '0.1.6', Update::STATE_PENDING), '0.1.6')
);
$eq(
    'both spellings the same way round is still ok',
    Update::STATE_OK,
    Update::reconcile(updateHost('v0.1.6', 'v0.1.6', Update::STATE_PENDING), 'v0.1.6')
);
// The prefix is noise; the numbers are not. Trimming it must not make two
// genuinely different versions look like arrival.
$eq(
    'v0.1.5 has NOT arrived at 0.1.6',
    Update::STATE_PENDING,
    Update::reconcile(updateHost('0.1.6', 'v0.1.5', ''), 'v0.1.5')
);

// The column value IS the search term (the host list stores and displays the
// same word), so the states must stay short, lowercase and free of spaces.
foreach ([Update::STATE_OK, Update::STATE_PENDING, Update::STATE_REFUSED, Update::STATE_CANNOT] as $state) {
    $eq(
        "state '$state' is a single searchable lowercase word",
        1,
        preg_match('/^[a-z]{1,16}$/', $state)
    );
}

$t->finish();
