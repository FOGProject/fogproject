<?php
/**
 * Secrets are removed once, at the emitter, and the strip follows the nesting.
 *
 * There is exactly one rule now and this file is where it is written down:
 *
 *   listem(), getter() and expandRelations() NEVER strip. They hand back the
 *   whole object, because the daemons, the LDAP bind and the Product Keys
 *   report are all internal callers that legitimately need the value.
 *   stripSensitivePayload(), on the way out of printer(), removes secrets --
 *   from the top-level object AND from every entity nested inside it.
 *
 * It did not used to hold, and both halves of the breakage were real:
 *
 *   - getter() stripped tier 2 as it built each level. That covered embeds
 *     made by recursing into getter() and nothing else, so the 14 that were a
 *     plain `$class->get('x')->get()` were stripped at no level. Proven on the
 *     live lab: GET /task/66 returned the storage node's FTP password and the
 *     host's 128-character client token in the clear.
 *   - because getter() stripped, Route::getItem() -- which goes through it --
 *     handed internal callers a redacted object, while Route::getList() --
 *     which reads listem()'s rows before the emitter -- handed them a whole
 *     one. The replicator asked getItem() for the node it was about to send
 *     to, got no password, and every transfer was refused at login and
 *     blamed on the admin's stored credential.
 *
 * The fix is that embed() records what class each nested object is, at the
 * point it serializes it, and the emitter walks that. Nothing here asserts
 * the shape of a line: the registry is seeded and the real
 * stripSensitivePayload() is run.
 *
 * Usage: php tests/api-nested-secret-strip.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('nested-secret-strip');
// sensitiveFieldMap() fires API_SENSITIVE_FIELDS, and HookManager
// resolves its known-events list through Route::getIds() -- so even the
// declaration reads below need a database to answer.
FogTestHarness::fakeDb();

use FOG\Router\Route;

$failures = [];

$nestedProp = new \ReflectionProperty('FOG\\Router\\Route', 'nestedClasses');
$nestedProp->setAccessible(true);
$emitProp = new \ReflectionProperty('FOG\\Router\\Route', 'emitClassname');
$emitProp->setAccessible(true);

/**
 * Runs the real emitter strip with a given nesting registry.
 *
 * @param array  $nested  parent => [key => child class]
 * @param string $emit    the emitter's fallback classname
 * @param array  $payload the payload to strip
 *
 * @return array
 */
function emit(array $nested, $emit, array $payload)
{
    global $nestedProp, $emitProp;
    $nestedProp->setValue(null, $nested);
    $emitProp->setValue(null, $emit);
    return (array) Route::stripSensitivePayload($payload);
}

/**
 * Records a failed expectation.
 *
 * @param string $label what was being checked
 * @param bool   $ok    whether it held
 * @param string $extra optional detail
 *
 * @return void
 */
function check($label, $ok, $extra = '')
{
    global $failures;
    if (!$ok) {
        $failures[] = '  ' . $label . ($extra ? "\n    " . $extra : '');
    }
}

// ---------------------------------------------------------------------------
// The declarations everything else depends on. If these stop naming the
// fields as secret, every check below passes for the wrong reason.
// ---------------------------------------------------------------------------
$node = Route::unfilterableFields('storagenode');
check(
    'storagenode pass and key are declared sensitive',
    in_array('pass', $node, true) && in_array('key', $node, true)
);
$host = Route::unfilterableFields('host');
check(
    'host ADPass and token are declared sensitive',
    in_array('ADPass', $host, true) && in_array('token', $host, true)
);

// ---------------------------------------------------------------------------
// 1. The exact payload that leaked: GET /task/{id}.
// ---------------------------------------------------------------------------
$taskNesting = [
    'task' => ['storagenode' => 'storagenode', 'host' => 'host'],
];
$task = emit(
    $taskNesting,
    'task',
    [
        'id' => 66,
        '_lang' => 'task',
        'storagenode' => [
            'id' => 1,
            'name' => 'DefaultMember',
            'user' => 'fogproject',
            'pass' => 'P>gTKZQ4T7jYsxsoUr6W',
            'key' => 'NODEHMACKEY',
        ],
        'host' => [
            'id' => 154,
            'name' => 'fos-deploy-test',
            'ADPass' => 'JOINPW',
            'token' => str_repeat('a', 128),
            'sec_tok' => 'SECTOK',
        ],
    ]
);
check(
    'task.storagenode.pass is stripped',
    !array_key_exists('pass', $task['storagenode']),
    'this is the field GET /task/66 returned in the clear'
);
check('task.storagenode.key is stripped', !array_key_exists('key', $task['storagenode']));
check('task.host.token is stripped', !array_key_exists('token', $task['host']));
check('task.host.ADPass is stripped', !array_key_exists('ADPass', $task['host']));
check('task.host.sec_tok is stripped', !array_key_exists('sec_tok', $task['host']));
check(
    'the rest of the payload is untouched',
    66 === ($task['id'] ?? null)
    && 'DefaultMember' === ($task['storagenode']['name'] ?? null)
    && 'fogproject' === ($task['storagenode']['user'] ?? null)
    && 'fos-deploy-test' === ($task['host']['name'] ?? null)
);

// ---------------------------------------------------------------------------
// 2. The tier-1 carve-out: top level keeps it, nesting never does.
//    fog-client reads a host's ADPass back from GET /host/{id} to join a
//    domain. Propagating $alwaysOnly into the nesting would re-open case 1;
//    dropping it at the top would break domain joins silently.
// ---------------------------------------------------------------------------
$single = emit([], 'host', ['id' => 9, 'name' => 'pc', 'ADPass' => 'JOINPW']);
check(
    'a direct single-entity GET still carries tier 1 (fog-client ADPass)',
    'JOINPW' === ($single['ADPass'] ?? null)
);
$row = emit([], 'host', ['_lang' => 'host', 'data' => [
    ['id' => 9, 'name' => 'pc', 'ADPass' => 'JOINPW'],
]]);
check(
    'a LIST row does not carry tier 1',
    !array_key_exists('ADPass', $row['data'][0])
);

// ---------------------------------------------------------------------------
// 3. Nesting inside a list row -- the storagegroup grid's masternode column,
//    which is the leak the old getter()-side strip was written for and which
//    must stay closed.
// ---------------------------------------------------------------------------
$grid = emit(
    ['storagegroup' => ['masternode' => 'storagenode']],
    'storagegroup',
    [
        '_lang' => 'storagegroup',
        'data' => [
            [
                'id' => 1,
                'name' => 'default',
                'masternode' => [
                    'id' => 1,
                    'name' => 'DefaultMember',
                    'pass' => 'NODEFTPPW',
                    'key' => 'NODEHMACKEY',
                ],
            ],
        ],
    ]
);
check(
    'storagegroup list: masternode.pass is stripped',
    !array_key_exists('pass', $grid['data'][0]['masternode'])
);
check(
    'storagegroup list: masternode.key is stripped',
    !array_key_exists('key', $grid['data'][0]['masternode'])
);
check(
    'storagegroup list: the master node is still identifiable',
    'DefaultMember' === ($grid['data'][0]['masternode']['name'] ?? null)
);

// ---------------------------------------------------------------------------
// 4. A to-many expanded relation is a LIST of entities under one key. The
//    single-object shape and the list shape are different code paths and
//    only one of them was ever exercised by hand.
// ---------------------------------------------------------------------------
$many = emit(
    ['group' => ['hosts' => 'host']],
    'group',
    [
        'id' => 2,
        '_lang' => 'group',
        'hosts' => [
            ['id' => 1, 'name' => 'a', 'ADPass' => 'PW1'],
            ['id' => 2, 'name' => 'b', 'ADPass' => 'PW2'],
        ],
    ]
);
check(
    'every entity in a to-many relation is stripped, not just the first',
    !array_key_exists('ADPass', $many['hosts'][0])
    && !array_key_exists('ADPass', $many['hosts'][1])
);
check(
    'a to-many relation keeps all of its members',
    2 === count($many['hosts'])
    && 'a' === ($many['hosts'][0]['name'] ?? null)
    && 'b' === ($many['hosts'][1]['name'] ?? null)
);

// ---------------------------------------------------------------------------
// 5. Depth. task -> host -> inventory is three levels today; the walk has to
//    keep going rather than stop after one.
// ---------------------------------------------------------------------------
$deep = emit(
    [
        'task' => ['host' => 'host'],
        'host' => ['image' => 'image'],
        'image' => ['storagenode' => 'storagenode'],
    ],
    'task',
    [
        'id' => 1,
        '_lang' => 'task',
        'host' => [
            'id' => 9,
            'image' => [
                'id' => 3,
                'storagenode' => ['id' => 1, 'name' => 'n', 'pass' => 'DEEPPW'],
            ],
        ],
    ]
);
check(
    'the walk reaches a secret three levels down',
    !array_key_exists(
        'pass',
        $deep['host']['image']['storagenode'] ?? ['pass' => 'still here']
    )
);

// ---------------------------------------------------------------------------
// 6. A registry entry naming a key that is not an entity, or is absent, must
//    not disturb the payload -- the registry is filled in at runtime and a
//    given payload carries only some of what it lists.
// ---------------------------------------------------------------------------
$sparse = emit(
    ['task' => ['storagenode' => 'storagenode', 'host' => 'host']],
    'task',
    ['id' => 1, '_lang' => 'task', 'host' => null, 'pct' => '0000000000']
);
check(
    'a registered key that is absent or not an array is left alone',
    // array_key_exists, not ?? -- the value under test IS null, and ??
    // cannot tell "present and null" from "not there".
    array_key_exists('host', $sparse)
    && null === $sparse['host']
    && '0000000000' === ($sparse['pct'] ?? null)
);

// ---------------------------------------------------------------------------
// 7. The registry is what drives the walk. An unregistered nested entity is
//    NOT stripped -- which is exactly why embed() derives the class from the
//    object instead of leaving a table for someone to update by hand. This
//    check states the consequence plainly rather than pretending the walk is
//    magic.
// ---------------------------------------------------------------------------
$unreg = emit(
    [],
    'task',
    ['id' => 1, '_lang' => 'task', 'storagenode' => ['pass' => 'PW']]
);
check(
    'an UNREGISTERED nested entity keeps its secret -- registration is'
    . ' load-bearing, so embed() must derive it and never be bypassed',
    'PW' === ($unreg['storagenode']['pass'] ?? null)
);

// So embed() itself is driven here, not just the walk it feeds. Seeding
// the registry by hand and asserting only on the walk left the single most
// important mutation alive: deleting embed()'s registration line passed the
// whole file.
$embedMethod = new \ReflectionMethod('FOG\\Router\\Route', 'embed');
$embedMethod->setAccessible(true);
$nestedProp->setValue(null, []);

$sn = new FOG\Items\StorageNode();
$embedded = $embedMethod->invoke(null, 'task', 'storagenode', $sn);
$recorded = $nestedProp->getValue();
check(
    'embed() registers the child class under the parent and key',
    'storagenode' === ($recorded['task']['storagenode'] ?? null),
    'got: ' . json_encode($recorded)
);
check('embed() still returns the serialized child', is_array($embedded));

// Derived from the OBJECT, never from the key -- that is the property that
// makes a wrong registration impossible, and the storagegroup grid's
// masternode column depends on it.
$nestedProp->setValue(null, []);
$embedMethod->invoke(null, 'storagegroup', 'masternode', new FOG\Items\StorageNode());
$derived = $nestedProp->getValue();
check(
    'embed() derives the class from the object, not from the key name',
    'storagenode' === ($derived['storagegroup']['masternode'] ?? null),
    'got: ' . json_encode($derived)
);

// A relation that did not resolve is the #895 shape: get() on a string is
// fatal. It must answer [] and register nothing.
$nestedProp->setValue(null, []);
$none = $embedMethod->invoke(null, 'snapintask', 'snapinjob', 'a string');
check(
    'embed() answers [] for a relation that did not resolve',
    [] === $none
);
check(
    'embed() registers nothing for a relation that did not resolve',
    [] === $nestedProp->getValue()
);

// The registration and the walk, end to end: no hand-seeded registry.
$nestedProp->setValue(null, []);
$embedMethod->invoke(null, 'task', 'storagenode', $sn);
$emitProp->setValue(null, 'task');
$endToEnd = (array) Route::stripSensitivePayload(
    [
        'id' => 1,
        '_lang' => 'task',
        'storagenode' => ['id' => 1, 'name' => 'n', 'pass' => 'REALPW'],
    ]
);
check(
    'embed() registering is what lets the emitter strip -- end to end, with'
    . ' nothing seeded by hand',
    !array_key_exists('pass', $endToEnd['storagenode'])
);

// Every embed inside getter() has to go through embed(); a raw
// ->get()->get() would produce exactly the unregistered case above.
$src = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Router/Route.php'
);
$gs = strpos($src, 'public static function getter($classname, $class)');
$depth = 0;
$gend = null;
for ($i = strpos($src, '{', $gs), $n = strlen($src); $i < $n; $i++) {
    if ('{' === $src[$i]) {
        $depth++;
    } elseif ('}' === $src[$i]) {
        $depth--;
        if (0 === $depth) {
            $gend = $i;
            break;
        }
    }
}
$getterBody = false === $gs ? '' : substr($src, $gs, $gend - $gs + 1);
check(
    'getter() was found so the sweep below means something',
    '' !== $getterBody
);
preg_match_all(
    "/\\\$class->get\('[A-Za-z_]+'\\)->get\(\)|\\\$[A-Za-z_]+->get\(\)\s*:/",
    $getterBody,
    $rawEmbeds
);
check(
    'no embed in getter() bypasses embed() with a raw ->get()->get()',
    empty($rawEmbeds[0]),
    empty($rawEmbeds[0]) ? '' : 'found: ' . implode(', ', $rawEmbeds[0])
);
check(
    'getter() no longer strips -- that is what made getItem() and getList()'
    . ' disagree',
    false === strpos($getterBody, 'self::stripSensitive(')
);

$nestedProp->setValue(null, []);
$emitProp->setValue(null, '');

if ($failures) {
    fwrite(
        STDERR,
        "FAIL: api nested secret strip\n" . implode("\n", $failures) . "\n"
    );
    exit(1);
}

echo "PASS: api nested secret strip (27 checks)\n";
exit(0);
