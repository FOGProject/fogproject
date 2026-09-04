<?php
/**
 * getClass() may no longer be called with a string literal, and every literal
 * that IS still allowed must name a class spelled exactly as declared.
 *
 * TWO invariants, and the first one is the newer and larger of the two.
 *
 * 1. A LITERAL getClass() IS REFUSED. `getClass('Host')` is `new Host()` with
 *    the type erased: FOGBase::getClass() is documented `@return object|mixed`,
 *    so PHPStan cannot check a single thing done to the result and no editor
 *    can follow it to a definition. The 459 sites that used to be spelled that
 *    way became plain `new` expressions, and this stops them growing back.
 *
 *    getClass() itself stays, for the one shape `new` cannot express: a class
 *    named by a VARIABLE. `Route`, `Authorization::_scopeClassVars()` and
 *    `OpenAPI::_entitySchema()` hold a lowercase API string and turn it into a
 *    class through FOGBase::qualify(); that is what the function is for now.
 *
 *    The props form -- `getClass('X', '', true)` -- is also still allowed,
 *    because it returns ReflectionClass::getDefaultProperties() rather than an
 *    instance and has no `new` equivalent at all. So is 'ReflectionClass',
 *    which getClass() special-cases.
 *
 * 2. Every literal that survives must name a class that is actually declared,
 *    spelled exactly as it is declared.
 *
 * PHP resolves class names case-insensitively, so getClass('snapinjob') has
 * always found SnapinJob and getClass('MACAddressASsociationManager') has
 * always found MACAddressAssociationManager -- typo and all. Nothing errors,
 * so nothing ever pointed the drift out, and the tree accumulated six of
 * them.
 *
 * Two reasons that is worth a test rather than a shrug:
 *
 *   1. A misspelled literal is invisible to grep. Anyone searching for
 *      MACAddressAssociationManager to find its callers misses the one in
 *      hostmanagement.page.php, which is exactly the sort of thing that
 *      makes a refactor miss a site.
 *   2. It teaches the wrong convention. A tree where half the literals are
 *      lowercase invites more lowercase literals.
 *
 * Note what this deliberately does NOT claim: that case-correct literals are
 * *required* for the Phase 3 namespacing to work. They are not -- lookup
 * stays case-insensitive through a class_alias, so all six spellings would
 * have kept working. This is hygiene, and keeping it separate from the
 * namespacing is what lets that diff stay mechanical.
 *
 * A literal naming a PHP built-in (getClass('DateTimeZone')) is accepted:
 * the factory takes those legitimately.
 *
 * Usage: php tests/getclass-literals.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

/*
 * Below this and the scan is not scanning anything -- a broken tokenizer loop
 * or a bad file filter would otherwise report a clean pass.
 *
 * Anchored on DECLARED classes rather than on literals, which is the whole
 * point of invariant 1: the literals are supposed to trend to zero, so
 * counting them would make the gate weaker every time it succeeded. The
 * declarations are walked by the same loop over the same files, so an empty
 * count still proves the scan died.
 */
const MIN_DECLARED = 300;

$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "*.php"')),
    function ($f) {
        return '' !== $f
            && is_readable($f)
            && 0 !== strpos($f, 'packages/web/vendor/');
    }
);

$declared = [];   // exact name => true
$literals = [];   // literal => [file:line, ...]
$banned = [];     // [name, file:line] for a literal getClass() with no excuse

foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($file));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        // Declarations. Skip `X::class` and anonymous classes.
        if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            $back = $i;
            while (--$back >= 0
                && is_array($tokens[$back])
                && T_WHITESPACE === $tokens[$back][0]
            ) {
                continue;
            }
            if (isset($tokens[$back])
                && is_array($tokens[$back])
                && T_DOUBLE_COLON === $tokens[$back][0]
            ) {
                continue;
            }
            $j = $i + 1;
            while ($j < $count
                && is_array($tokens[$j])
                && T_WHITESPACE === $tokens[$j][0]
            ) {
                $j++;
            }
            if (isset($tokens[$j])
                && is_array($tokens[$j])
                && T_STRING === $tokens[$j][0]
            ) {
                $declared[$tokens[$j][1]] = true;
            }
            continue;
        }
        // getClass('X') / getManager('X') -- literal first argument only.
        if (T_STRING !== $token[0]
            || !in_array($token[1], ['getClass', 'getManager'], true)
        ) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count
            && is_array($tokens[$j])
            && T_WHITESPACE === $tokens[$j][0]
        ) {
            $j++;
        }
        if (!isset($tokens[$j]) || '(' !== $tokens[$j]) {
            continue;
        }
        $k = $j + 1;
        while ($k < $count
            && is_array($tokens[$k])
            && T_WHITESPACE === $tokens[$k][0]
        ) {
            $k++;
        }
        if (!isset($tokens[$k])
            || !is_array($tokens[$k])
            || T_CONSTANT_ENCAPSED_STRING !== $tokens[$k][0]
        ) {
            // A variable argument. Nothing to check statically.
            continue;
        }
        $name = trim($tokens[$k][1], "'\"");
        $where = $file . ':' . $tokens[$k][2];
        $literals[$name][] = $where;
        if ('getClass' !== $token[1]) {
            continue;
        }
        // Invariant 1: which of the two excused shapes is this, if either?
        // getClass() special-cases ReflectionClass, and the third argument
        // asks for getDefaultProperties() rather than an instance. Neither
        // has a `new` equivalent; everything else does.
        if ('reflectionclass' === strtolower($name)) {
            continue;
        }
        $depth = 0;
        $argc = 1;
        $third = '';
        for ($m = $j; $m < $count; $m++) {
            $tok = $tokens[$m];
            if (is_string($tok) && false !== strpos('([{', $tok)) {
                $depth++;
                continue;
            }
            if (is_string($tok) && false !== strpos(')]}', $tok)) {
                if (0 === --$depth) {
                    break;
                }
                continue;
            }
            if (1 === $depth && ',' === $tok) {
                $argc++;
                continue;
            }
            if (3 === $argc && is_array($tok) && T_WHITESPACE !== $tok[0]) {
                $third .= $tok[1];
            }
        }
        if (3 === $argc && 'true' === strtolower(trim($third))) {
            continue;
        }
        $banned[] = [$name, $where];
    }
}

$total = 0;
foreach ($literals as $sites) {
    $total += count($sites);
}

$fail = false;

if (count($declared) < MIN_DECLARED) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($declared) . ' class declaration(s) found, '
        . 'expected at least ' . MIN_DECLARED . ". The scan is not scanning.\n"
    );
    $fail = true;
}

// Invariant 1. Anything left here is a literal getClass() that is neither the
// props form nor ReflectionClass, so it has a plain `new` equivalent.
if ($banned !== []) {
    fwrite(
        STDERR,
        'FAIL: ' . count($banned) . " literal getClass() call(s); use new:\n"
    );
    foreach ($banned as list($name, $where)) {
        fwrite(STDERR, "  $where: getClass('$name') -- write new $name(...)\n");
    }
    $fail = true;
}

// Case-insensitive index of what is declared, so a mismatch can be reported
// with the spelling the caller should have used.
$byLower = [];
foreach (array_keys($declared) as $name) {
    $byLower[strtolower($name)] = $name;
}

$bad = [];
foreach ($literals as $name => $sites) {
    if (isset($declared[$name])) {
        continue;
    }
    $lower = strtolower($name);
    if (isset($byLower[$lower])) {
        $bad[] = [$name, $byLower[$lower], $sites];
        continue;
    }
    // Not a FOG class at all. A PHP built-in is legitimate here.
    if (class_exists($name, false) || interface_exists($name, false)) {
        continue;
    }
    $exists = false;
    try {
        $ref = new \ReflectionClass($name);
        $exists = $ref->isInternal();
    } catch (\Throwable $e) {
        $exists = false;
    }
    if (!$exists) {
        $bad[] = [$name, null, $sites];
    }
}

if (count($bad) > 0) {
    fwrite(STDERR, "FAIL: " . count($bad) . " getClass()/getManager() literal(s) "
        . "do not match a declared class name exactly:\n");
    foreach ($bad as $entry) {
        list($name, $want, $sites) = $entry;
        fwrite(
            STDERR,
            null === $want
                ? "  '$name' -- no such class\n"
                : "  '$name' -- declared as '$want'\n"
        );
        foreach ($sites as $site) {
            fwrite(STDERR, "      $site\n");
        }
    }
    $fail = true;
}

if ($fail) {
    exit(1);
}

printf(
    "ok: %d literal(s) in %d distinct name(s) all match a declared class, "
    . "%d declaration(s) indexed\n",
    $total,
    count($literals),
    count($declared)
);
exit(0);
