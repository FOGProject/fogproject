<?php
/**
 * Proves FOG\Util\MassEdit never writes what it was not asked to write.
 *
 * ADR 0038 decision 11 calls the three-state field the single requirement
 * most likely to be got wrong, "because the wrong version looks identical
 * until somebody's images are gone". That is the whole difficulty: a
 * mass-edit form that defaults wrong does not error, does not warn, and does
 * not look different. It submits, the page says it worked, and four hundred
 * hosts hold a value nobody chose.
 *
 * So the property under test is not "does it set values" -- that half is
 * obvious and would be noticed in a day. It is the negative one: NO INPUT
 * REACHES A COLUMN THE SUBMISSION DID NOT EXPLICITLY ASK TO CHANGE. Every
 * case below is a way of arriving at a write without having asked for one,
 * and each is checked to produce LEAVE:
 *
 *   - no action posted at all
 *   - an action of '' , 'SET' in the wrong case, or a typo
 *   - an action of 0 or '0', which loose comparison would match a constant
 *   - a key the caller never offered
 *   - actions or values arriving as null, which is what
 *     filter_input_array() hands back when nothing matched
 *   - SET with no corresponding value control
 *   - SET whose value arrived as an array
 *
 * The positive cases are here too, because a gate that refuses everything is
 * also broken -- it is just broken in the direction somebody notices.
 *
 * No database: this is pure resolution, and keeping it that way means the
 * check runs everywhere rather than only where a server exists.
 *
 * Usage: php tests/mass-edit-fails-closed.test.php
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

$root = dirname(__DIR__);
require_once $root . '/packages/web/src/Base/FOGBase.php';
require_once $root . '/packages/web/src/Util/MassEdit.php';

use FOG\Util\MassEdit;

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

$keys = ['kernel', 'kernelArgs', 'productKey', 'image'];
$spec = [
    'kernel' => ['field' => 'kernel'],
    'kernelArgs' => ['field' => 'kernelArgs'],
    'productKey' => ['field' => 'productKey'],
    // An int column: "empty" is 0, not ''. Writing '' into an int column
    // stores 0 on a permissive server and errors on a strict one.
    'image' => ['field' => 'imageID', 'empty' => 0],
];

/**
 * Every way of failing to ask for a change. Each must resolve to LEAVE and
 * must contribute nothing to the column map.
 */
$closedCases = [
    'nothing posted at all' => [null, null],
    'an empty actions map' => [[], []],
    // The case that actually exercises the DEFAULT. Everything else here
    // posts an action for `kernel`, so the `?? LEAVE` never fires and a
    // mutation flipping that default to SET slips through -- proven, by
    // running it. A request carrying values and no actions is also the
    // realistic hand-made shape: it is what you get by scraping a form's
    // inputs and dropping the controls you did not understand.
    'values posted with no actions at all' => [
        [],
        ['kernel' => 'bzImage', 'kernelArgs' => 'quiet'],
    ],
    'an action of the empty string' => [
        ['kernel' => ''],
        ['kernel' => 'bzImage'],
    ],
    'an action in the wrong case' => [
        ['kernel' => 'SET'],
        ['kernel' => 'bzImage'],
    ],
    'a misspelled action' => [
        ['kernel' => 'sett'],
        ['kernel' => 'bzImage'],
    ],
    // The loose-comparison trap, and it is VERSION-DEPENDENT: on PHP 7.4
    // `0 == 'leave'` is true, on PHP 8 it is false. So dropping the strict
    // flag from the in_array() is a real defect that this case reddens only
    // on the 7.4 arm -- verified by mutating it on 8.3, where it passes
    // either way. The suite runs both, which is what makes the check worth
    // keeping; anyone re-running the mutation locally on 8.x and concluding
    // the flag is decorative is reading a false negative.
    'an action of integer zero' => [
        ['kernel' => 0],
        ['kernel' => 'bzImage'],
    ],
    'an action of string zero' => [
        ['kernel' => '0'],
        ['kernel' => 'bzImage'],
    ],
    'an action that is itself an array' => [
        ['kernel' => ['set']],
        ['kernel' => 'bzImage'],
    ],
    'actions posted as a string rather than a map' => [
        'set',
        ['kernel' => 'bzImage'],
    ],
    'SET with no value control at all' => [
        ['kernel' => MassEdit::SET],
        [],
    ],
    'SET whose value arrived as an array' => [
        ['kernel' => MassEdit::SET],
        ['kernel' => ['a', 'b']],
    ],
];

foreach ($closedCases as $label => $case) {
    list($actions, $values) = $case;
    $resolved = MassEdit::resolve($keys, $actions, $values);
    $check(
        'leaves everything alone: ' . $label,
        MassEdit::LEAVE === ($resolved['kernel']['action'] ?? null)
    );
    $check(
        'writes no column: ' . $label,
        [] === MassEdit::columnUpdates($resolved, $spec)
    );
}

// A key the caller never offered must not reach the resolution at all, let
// alone a column. This is the difference between "a plugin may edit its own
// fields" and "a submission may name any column it likes".
$resolved = MassEdit::resolve(
    $keys,
    ['hostADPass' => MassEdit::SET, 'kernel' => MassEdit::LEAVE],
    ['hostADPass' => 'hunter2']
);
$check(
    'a key outside the offered list is not resolved',
    !array_key_exists('hostADPass', $resolved)
);
$check(
    'a key outside the offered list writes nothing',
    [] === MassEdit::columnUpdates(
        $resolved,
        $spec + ['hostADPass' => ['field' => 'ADPass']]
    )
);

// ...and a key that IS offered but has no spec entry still writes nothing.
// The spec is what says a key may touch a column; being offered only says it
// may be resolved.
$resolved = MassEdit::resolve(
    ['somethingElse'],
    ['somethingElse' => MassEdit::SET],
    ['somethingElse' => 'x']
);
$check(
    'an offered key with no spec entry writes no column',
    [] === MassEdit::columnUpdates($resolved, $spec)
);

// Every offered key comes back, so no caller has to interpret an absence.
$resolved = MassEdit::resolve($keys, ['kernel' => MassEdit::SET], ['kernel' => 'a']);
$check(
    'every offered key is present in the result',
    $keys === array_keys($resolved)
);

// The positive half.
$resolved = MassEdit::resolve(
    $keys,
    [
        'kernel' => MassEdit::SET,
        'kernelArgs' => MassEdit::CLEAR,
        'image' => MassEdit::CLEAR,
        'productKey' => MassEdit::LEAVE,
    ],
    [
        'kernel' => '  bzImage  ',
        // A value posted alongside CLEAR is ignored, not written.
        'kernelArgs' => 'quiet',
        'productKey' => 'AAAAA-BBBBB',
    ]
);
$check(
    'SET writes the value, trimmed',
    'bzImage' === ($resolved['kernel']['value'] ?? null)
);
$check(
    'CLEAR ignores any value posted with it',
    '' === ($resolved['kernelArgs']['value'] ?? null)
);
$check(
    'LEAVE writes nothing even when a value was posted',
    MassEdit::LEAVE === ($resolved['productKey']['action'] ?? null)
);
$updates = MassEdit::columnUpdates($resolved, $spec);
$check(
    'the column map carries exactly what was set and cleared',
    ['kernel' => 'bzImage', 'kernelArgs' => '', 'imageID' => 0] === $updates
);
$check(
    "CLEAR uses the field's own empty value, not a blanket ''",
    array_key_exists('imageID', $updates) && 0 === $updates['imageID']
);
$check(
    'touched() names only the fields that were acted on',
    ['kernel', 'kernelArgs', 'image'] === MassEdit::touched($resolved)
);

// SET with a present but empty value is a deliberate blanking, not a
// malformed request: the action control and the value control are separate,
// so choosing SET and leaving the box empty is somebody saying "make it
// empty". Stated here so the behavior is pinned rather than discovered.
$resolved = MassEdit::resolve(
    ['kernel'],
    ['kernel' => MassEdit::SET],
    ['kernel' => '']
);
$check(
    'SET with a present but empty value writes empty',
    MassEdit::SET === $resolved['kernel']['action']
        && ['kernel' => ''] === MassEdit::columnUpdates($resolved, $spec)
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the mass edit does not fail closed:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  mass edit fails closed: %d checks\n", $checks);
exit(0);
