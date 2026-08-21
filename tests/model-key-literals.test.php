<?php
/**
 * Every ->set() string literal must name a key some model declares.
 *
 * FOGController::set() resolves a key against databaseFields,
 * databaseFieldsFlipped and additionalFields, and does so CASE-SENSITIVELY
 * (fogcontroller.class.php: array_key_exists on the declared arrays). A key
 * that matches none of them throws "Invalid key being set" -- and set()
 * catches its own exception into debug(), which is silent at the default log
 * level.
 *
 * So renaming a model's friendly key breaks every caller of the old name
 * silently. The write still succeeds, because save() only writes the fields
 * it can resolve and fills the rest from their defaults; the caller's value
 * is simply gone. Nothing errors, nothing logs, and the row looks plausible.
 *
 * That is not hypothetical. UserTracking's key was renamed 'datetime' ->
 * 'createdTime' on 2018-04-07 in 1549cdd12 -- a 49-file commit titled
 * "Cleaner formatting of url to datatable" -- which did not touch
 * UserTrack::json(), its only writer. For the next eight years a fog-client
 * reporting a queued or offline login had its date recorded in utDate and the
 * server's clock in utDateTime, on the same row. This test is what would have
 * caught it in the commit that caused it.
 *
 * WHAT THIS CHECKS, and why it is shaped this way. It does NOT try to work
 * out which class a literal's receiver is: a chain root is not recoverable
 * from a token stream in general, and an attempt at it reports the key in
 * getClass('Role', $this->get('roleID')) as belonging to Role. Instead it
 * asks a weaker question that needs no receiver and has no false positives:
 * is this literal a key that ANY model declares? A literal no model anywhere
 * declares cannot be correct on any receiver, so it is a typo or a stale
 * name. A literal that is right for the wrong class still passes -- catching
 * that needs receiver attribution, which is a different and much larger test.
 *
 * get() is DELIBERATELY NOT CHECKED, though it resolves keys identically and
 * returns false for one it does not have. Scanning it was tried: it found a
 * real bug -- hostmanagement.page.php read Inventory's 'caseversion' where
 * the model declares 'casever', so Chassis Version rendered empty for every
 * host, fixed alongside this test -- but it also produced sixteen false
 * positives, because get() is not a method only models have. PDODB result
 * objects answer ->get('total'), ->get('COLUMN_NAME'), ->get('sql_mode'),
 * and group management calls ->get('n')/('d')/('v') on its own value
 * objects. A gate with a 16:1 false-positive rate gets an allow-list, then
 * gets ignored, then gets deleted. set() alone has none at all: 636 literals,
 * four violations, all four in the block exempted below.
 *
 * Usage: php tests/model-key-literals.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

/*
 * Floors, so a broken regex or a bad file filter cannot report a clean pass
 * by scanning nothing. Both are far below the counts at the time of writing
 * (733 declared keys, 636 set() literals).
 */
const MIN_KEYS = 500;
const MIN_LITERALS = 400;

/*
 * Host::createTasking() sets four keys Task does not declare. They are dead
 * -- silently dropped since they were written -- and they are exempt rather
 * than fixed because RENAMING THEM IS NOT THE FIX and would be a regression:
 *
 *   'tasktype'     -> Task declares 'type'
 *   'TaskState'    -> Task declares 'state'
 *   'StorageGroup' -> Task declares 'storagegroup'   (case mismatch)
 *   'StorageNode'  -> Task declares 'storagenode'    (case mismatch)
 *
 * All four are redundant: Task's databaseFieldClassRelationships already
 * builds each object from the corresponding *ID field, and createTasking()
 * sets every one of those IDs in the same chain. Worse, 'StorageNode' is
 * passed `new StorageNode()` -- an EMPTY object -- so correcting the key
 * would replace a working lazy load with an invalid one.
 *
 * Removing them is the real fix and is a behaviour change to the task
 * creation path, which is not this test's call to make.
 */
$exempt = [
    'packages/web/lib/fog/host.class.php' => [
        'tasktype',
        'TaskState',
        'StorageGroup',
        'StorageNode',
    ],
];

$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "*.php"')),
    function ($f) {
        return '' !== $f
            && is_readable($f)
            && 0 !== strpos($f, 'packages/web/vendor/');
    }
);

/*
 * The union of every key any model declares. Parsed from source rather than
 * by loading the classes: the test has to run in CI, where there is no
 * database and no generated config.class.php to boot FOG with.
 */
$known = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    foreach (['databaseFields', 'additionalFields'] as $prop) {
        if (!preg_match('#\$' . $prop . '\s*=\s*\[(.*?)\];#s', $src, $m)) {
            continue;
        }
        preg_match_all('#[\'"](\w+)[\'"]#', $m[1], $keys);
        foreach ($keys[1] as $key) {
            $known[$key] = true;
        }
    }
}

$literals = 0;
$bad = [];
foreach ($files as $file) {
    $lines = explode("\n", file_get_contents($file));
    foreach ($lines as $i => $line) {
        if (!preg_match_all('#->set\(\s*[\'"](\w+)[\'"]#', $line, $m)) {
            continue;
        }
        foreach ($m[1] as $key) {
            $literals++;
            if (isset($known[$key])) {
                continue;
            }
            if (in_array($key, $exempt[$file] ?? [], true)) {
                continue;
            }
            $bad[] = sprintf('%s:%d  ->set(\'%s\')', $file, $i + 1, $key);
        }
    }
}

$fail = false;

if (count($known) < MIN_KEYS) {
    printf(
        "FAIL: only %d declared keys found (expected at least %d).\n"
        . "      The model scan is broken; it is not that the models shrank.\n",
        count($known),
        MIN_KEYS
    );
    $fail = true;
}

if ($literals < MIN_LITERALS) {
    printf(
        "FAIL: only %d set() literals found (expected at least %d).\n"
        . "      The literal scan is broken; it is not that the callers went away.\n",
        $literals,
        MIN_LITERALS
    );
    $fail = true;
}

if (count($bad) > 0) {
    printf(
        "FAIL: %d set() literal(s) name a key no model declares.\n\n",
        count($bad)
    );
    foreach ($bad as $line) {
        echo '  ', $line, "\n";
    }
    echo "\n"
        . "  Each of these is silently dropped at runtime: set() throws and\n"
        . "  swallows its own exception, so the value never reaches the row.\n"
        . "  Either the literal is a typo, or a model key was renamed and this\n"
        . "  caller was not updated with it.\n";
    $fail = true;
}

if ($fail) {
    exit(1);
}

printf(
    "PASS: %d set() literals checked against %d declared model keys.\n",
    $literals,
    count($known)
);
exit(0);
