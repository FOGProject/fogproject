<?php
/**
 * The one place the reported Secure Boot format lives, pinned.
 *
 * SecureBootState::fromBootRequest() turns three strings out of an
 * unauthenticated POST body into one of six words. Every consumer -- the host
 * grid, the host form, and the enrollment task's refusal -- reads that word and
 * nothing else, so a wrong classification is not a wrong label: it is either a
 * host that can never be enrolled, or a host that looks enrollable and is not.
 *
 * The values asserted below were MEASURED, on 2026-08-28, not derived from
 * iPXE's documentation:
 *
 *   QEMU + OVMF_CODE.secboot.fd, plain OVMF_VARS.fd (Setup Mode)
 *       platform=efi  SecureBoot=[00]  SetupMode=[01]
 *   QEMU + OVMF_CODE.fd (firmware with no Secure Boot support at all)
 *       platform=efi  SecureBoot=[]    SetupMode=[]
 *   QEMU + SeaBIOS + FOG's own ipxe.lkrn, through a real default.ipxe
 *       platform=pcbios  SecureBoot=[]  SetupMode=[]
 *
 * and the wire form, captured off the same BIOS run:
 *
 *   mac0=52%3A54%3A00%3A12%3A34%3A56&platform=pcbios&secureboot=&setupmode=
 *
 * which is why null and '' are two different cases here and not one. A param
 * iPXE could not expand still appears in the body, so filter_input() returns
 * '' for a current default.ipxe and null only for one written before this
 * shipped. Collapsing them would make every legacy-BIOS host indistinguishable
 * from every not-yet-booted host.
 *
 * `01` for ENFORCING is the one value not observed here: an enforcing OVMF
 * refuses an unsigned iPXE outright ("Access Denied -- rejected probably by
 * Secure Boot"), so reading it needs the signed chain on the VirtualBox rig.
 * It is asserted from iPXE's own formatter -- efi_settings.c assigns
 * setting_type_hex, which is two hex digits per byte -- and the classifier is
 * built so that being wrong about it fails CLOSED: anything that is not
 * recognizably "off" is not an enrollment target.
 *
 * DB-free. SecureBootState touches no globals and no database.
 *
 * Usage: php tests/secureboot-state-classifier.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Boot\SecureBootState;

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';

require_once $webroot . '/src/Boot/SecureBootState.php';

$failures = 0;
$checks = 0;

/**
 * Assert two values are identical, reporting the case name on failure.
 *
 * A CLOSURE over the two counters, not a global function with `global`
 * statements, and both halves of that matter for the second PHPStan pass --
 * which analyses all of tests/ as one unit:
 *
 *   - fourteen other files here declare a global check() with four different
 *     signatures, and a fifteenth merges with them into errors about
 *     parameters this file does not have;
 *   - a counter mutated through `global` is invisible to PHPStan, so the
 *     final `$failures > 0` reads as "always false" -- which would have made
 *     this file report a pass whatever it found, had the analyser not said so.
 *
 * @param string $what     what is being asserted
 * @param mixed  $expected the expected value
 * @param mixed  $actual   what was produced
 *
 * @return void
 */
$sbCheck = function ($what, $expected, $actual) use (&$failures, &$checks) {
    ++$checks;
    if ($expected === $actual) {
        return;
    }
    ++$failures;
    fwrite(
        STDERR,
        sprintf(
            "FAIL: %s\n  expected: %s\n  actual:   %s\n",
            $what,
            var_export($expected, true),
            var_export($actual, true)
        )
    );
};

// ---------------------------------------------------------------- classify --
// platform, secureboot, setupmode, expected state, why this case exists
$cases = [
    [
        null, null, null, SecureBootState::UNKNOWN,
        'default.ipxe predating this change sends neither param',
    ],
    [
        'efi', null, null, SecureBootState::UNKNOWN,
        'an EFI machine on an old default.ipxe is still unreported, not nonefi',
    ],
    [
        'pcbios', '', '', SecureBootState::NONEFI,
        'MEASURED: legacy BIOS, both params present and empty',
    ],
    [
        'pcbios', '00', '00', SecureBootState::NONEFI,
        'platform wins over any value a BIOS machine could somehow send',
    ],
    [
        'efi', '', '', SecureBootState::NOEFIVARS,
        'MEASURED: UEFI firmware exposing no Secure Boot variables',
    ],
    [
        'efi', '00', '01', SecureBootState::SETUP,
        'MEASURED: Setup Mode. SetupMode is tested first, as sbState() does',
    ],
    [
        'efi', '01', '01', SecureBootState::SETUP,
        'contradictory pair still resolves to setup, matching sbState()',
    ],
    [
        'efi', '01', '00', SecureBootState::ENFORCING,
        'User Mode, Secure Boot on',
    ],
    [
        'efi', '00', '00', SecureBootState::DISABLED,
        'User Mode, Secure Boot off -- the state ADR 0008 was written for',
    ],
    [
        'EFI', '00', '00', SecureBootState::DISABLED,
        'platform compared case-insensitively',
    ],
    [
        'efi', '00 ', ' 00', SecureBootState::DISABLED,
        'whitespace trimmed -- failing here would fail OPEN into not-off',
    ],
    [
        'efi', '02', '00', SecureBootState::NOEFIVARS,
        'a byte that is neither 00 nor 01 must not collapse into disabled',
    ],
    [
        'efi', 'ff', '', SecureBootState::NOEFIVARS,
        'unreadable-but-present is not the same as off',
    ],
    [
        'efi', '', '01', SecureBootState::SETUP,
        'SetupMode alone is enough; firmware need not expose both',
    ],
    [
        'efi', '00', '', SecureBootState::DISABLED,
        'SecureBoot alone is enough',
    ],
];

foreach ($cases as $case) {
    list($platform, $sb, $sm, $expected, $why) = $case;
    $sbCheck(
        sprintf(
            'classify(%s, %s, %s) -- %s',
            var_export($platform, true),
            var_export($sb, true),
            var_export($sm, true),
            $why
        ),
        $expected,
        SecureBootState::fromBootRequest($platform, $sb, $sm)
    );
}

// ------------------------------------------------------------- eligibility --
// The refusal reads this and nothing else. UNKNOWN is true by decision, not by
// omission: nothing is reported until a host PXE boots, so refusing it outright
// would make the enrollment task unusable on every existing fleet until a full
// boot cycle had happened. The host list filter is what keeps an unknown host
// from LOOKING eligible.
$eligible = [
    SecureBootState::UNKNOWN => true,
    SecureBootState::SETUP => true,
    SecureBootState::DISABLED => true,
    SecureBootState::ENFORCING => false,
    SecureBootState::NONEFI => false,
    SecureBootState::NOEFIVARS => false,
];
foreach ($eligible as $state => $expected) {
    $sbCheck(
        "isEnrollmentTarget('$state')",
        $expected,
        SecureBootState::isEnrollmentTarget($state)
    );
}

// A value this build does not recognize -- written by an older build, a
// plugin, or by hand in the database -- is treated as unreported rather than
// trusted, so it is allowed with the same warning UNKNOWN gets.
$sbCheck('isEnrollmentTarget(null)', true, SecureBootState::isEnrollmentTarget(null));
$sbCheck('isEnrollmentTarget("")', true, SecureBootState::isEnrollmentTarget(''));
$sbCheck(
    'isEnrollmentTarget("wat")',
    true,
    SecureBootState::isEnrollmentTarget('wat')
);
$sbCheck('isUnreported(null)', true, SecureBootState::isUnreported(null));
$sbCheck('isUnreported("wat")', true, SecureBootState::isUnreported('wat'));
$sbCheck(
    'isUnreported(unknown)',
    true,
    SecureBootState::isUnreported(SecureBootState::UNKNOWN)
);
$sbCheck(
    'isUnreported(disabled)',
    false,
    SecureBootState::isUnreported(SecureBootState::DISABLED)
);

// isKnown() gates every stored read, so it must not accept near-misses.
$sbCheck('isKnown(disabled)', true, SecureBootState::isKnown('disabled'));
$sbCheck('isKnown("Disabled")', false, SecureBootState::isKnown('Disabled'));
$sbCheck('isKnown("")', false, SecureBootState::isKnown(''));
$sbCheck('isKnown(null)', false, SecureBootState::isKnown(null));

// ----------------------------------------------------------------- refusal --
// refusalReason() is what BOTH refusal sites call -- Host::createImagePackage()
// throws on it, Group::createImagePackage() filters on it -- so it carries more
// weight than anything else here. It had no coverage on the first cut of this
// file, and a mutation that made it return '' for an enforcing host passed
// green: the gate would have gone on reporting a pass while the server silently
// stopped refusing the one target ADR 0008 exists to keep tasks away from.
$refusing = [
    SecureBootState::ENFORCING,
    SecureBootState::NONEFI,
    SecureBootState::NOEFIVARS,
];
$reasons = [];
foreach ($refusing as $state) {
    $reason = SecureBootState::refusalReason($state);
    $sbCheck("refusalReason('$state') refuses", true, '' !== $reason);
    $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
}
// Distinct messages, so a case falling through to the default is visible. All
// three refuse, so asserting only "non-empty" would not notice one collapsing
// into another and telling an administrator the wrong thing to do about it.
foreach ($reasons as $reason => $count) {
    $sbCheck('each refused state has its own message', 1, $count);
}
// The states that must NOT be refused, asserted through the same function the
// call sites use rather than through isEnrollmentTarget() alone -- the two have
// to agree, and only checking one lets them drift.
foreach (
    [
        SecureBootState::UNKNOWN,
        SecureBootState::SETUP,
        SecureBootState::DISABLED,
    ] as $state
) {
    $sbCheck(
        "refusalReason('$state') permits",
        '',
        SecureBootState::refusalReason($state)
    );
}
$sbCheck('refusalReason(null) permits', '', SecureBootState::refusalReason(null));
$sbCheck(
    'refusalReason("wat") permits an unrecognized stored value',
    '',
    SecureBootState::refusalReason('wat')
);

// ------------------------------------------------------------------ labels --
// Every state must have its own label. A missing case falling through to the
// default would render an enforcing machine as "Never reported", which is the
// one substitution that turns a refusal into a mystery.
$labels = [];
foreach (
    [
        SecureBootState::UNKNOWN,
        SecureBootState::NONEFI,
        SecureBootState::NOEFIVARS,
        SecureBootState::SETUP,
        SecureBootState::ENFORCING,
        SecureBootState::DISABLED,
    ] as $state
) {
    $label = SecureBootState::label($state);
    $sbCheck("label('$state') is non-empty", true, '' !== $label);
    $labels[$label] = ($labels[$label] ?? 0) + 1;
}
foreach ($labels as $label => $count) {
    $sbCheck("label '$label' is used by exactly one state", 1, $count);
}

// ------------------------------------------------------- fingerprint format --
// One normalizer, because this format was written out longhand in four files
// before it had a home: the host form, the report endpoint, the Secure Boot
// configuration page and FOS. Any two of them drifting turns every comparison
// into a silent false, which does not read as a bug -- it reads as "this host
// trusts an older certificate", and sends somebody to re-enroll a machine that
// was already correct.
$canonical = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99'
    . ':AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99';
$bare = str_replace(':', '', $canonical);

$accepts = [
    'the canonical form itself' => $canonical,
    'lower-case colon form' => strtolower($canonical),
    'bare hex, as a copy-paste produces' => $bare,
    'bare lower-case hex' => strtolower($bare),
    'surrounding whitespace' => "  $canonical\n",
];
foreach ($accepts as $why => $input) {
    $sbCheck(
        "normalizeFingerprint accepts $why",
        $canonical,
        SecureBootState::normalizeFingerprint($input)
    );
}

// Rejected, and rejected as '' rather than as something storable. Anything
// that is not a SHA-256 can only ever compare false.
$rejects = [
    'empty' => '',
    'null' => null,
    'one digit short' => substr($bare, 0, 63),
    'one digit long' => $bare . 'A',
    'non-hex' => str_repeat('Z', 64),
    'a SHA-1, which is what MokManager displays' => str_repeat('AB', 20),
    'prose' => 'not a fingerprint',
];
foreach ($rejects as $why => $input) {
    $sbCheck(
        "normalizeFingerprint rejects $why",
        '',
        SecureBootState::normalizeFingerprint($input)
    );
}

// -------------------------------------------------------------- freshness --
// The comparison the column exists for (ADR 0029 decision 5). Asserted with
// the server value INJECTED rather than read off disk, so these cases run
// anywhere -- serverFingerprint() reads BASEPATH, which a DB-free test has no
// business defining.
$other = str_replace(':', '', $canonical);
$other = implode(':', str_split(str_repeat('11', 32), 2));

$sbCheck(
    'an identical fingerprint is current',
    SecureBootState::FRESH,
    SecureBootState::enrollmentFreshness($canonical, $canonical)
);
// The case the normalizer is FOR: the same certificate written two ways must
// not read as two certificates.
$sbCheck(
    'bare hex against colon form is still the same certificate',
    SecureBootState::FRESH,
    SecureBootState::enrollmentFreshness(strtolower($bare), $canonical)
);
$sbCheck(
    'a different fingerprint is stale',
    SecureBootState::STALE,
    SecureBootState::enrollmentFreshness($canonical, $other)
);

// Three different kinds of "cannot answer", none of which is evidence that a
// machine trusts the wrong key. Reporting any of them as stale would badge
// every host in a fleet that has simply never enrolled, and a warning that is
// on everything is a warning nobody reads.
$unanswerable = [
    'nothing recorded against the host' => ['', $canonical],
    'null recorded against the host' => [null, $canonical],
    'an unparseable stored value' => ['wat', $canonical],
    'this server has no signing certificate' => [$canonical, ''],
    'neither side has anything' => ['', ''],
];
foreach ($unanswerable as $why => $pair) {
    $sbCheck(
        "freshness is unanswerable when $why",
        '',
        SecureBootState::enrollmentFreshness($pair[0], $pair[1])
    );
}

// Labels: distinct, non-empty for both answers, and EMPTY for the
// unanswerable case. The host form renders the field only when this is
// non-empty, so a default that returned prose would put a disabled box on
// every host that has never enrolled anything.
$fresh = SecureBootState::freshnessLabel(SecureBootState::FRESH);
$stale = SecureBootState::freshnessLabel(SecureBootState::STALE);
$sbCheck('the current label says something', true, '' !== $fresh);
$sbCheck('the stale label says something', true, '' !== $stale);
$sbCheck('the two labels differ', true, $fresh !== $stale);
$sbCheck("an unanswerable freshness has no label", '', SecureBootState::freshnessLabel(''));
$sbCheck(
    'an unrecognized freshness has no label',
    '',
    SecureBootState::freshnessLabel('wat')
);
// The stale wording has to name what to DO. A mismatch on its own does not
// tell anyone anything: the machine boots fine until the old kernels stop
// being served, and then stops booting with no apparent cause.
$sbCheck(
    'the stale label says to re-run the enrollment task',
    true,
    false !== stripos($stale, 're-run')
);

// ------------------------------------------------------------------ result --
if ($failures > 0) {
    fwrite(STDERR, "\n$failures of $checks checks FAILED\n");
    exit(1);
}
fwrite(STDOUT, "PASS: $checks checks\n");
exit(0);
