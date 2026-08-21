<?php
/**
 * Every model field that looks like a credential must be classified.
 *
 * ADR 0021 Decision 6, layer 3. Redaction::isSensitive() already defaults
 * closed -- a friendly key matching CREDENTIAL_PATTERN is withheld whether or
 * not anyone declared it -- so this test is not what stops a leak at runtime.
 * It is what stops the classification from being implicit, which is the part
 * that has failed twice:
 *
 *   - 58483d6: storagenode.pass was never declared, and the storage GROUP
 *     grid embeds the whole master node object, so the FTP password reached
 *     anyone holding storagegroup.view.
 *   - #1261/#1262: the SQL fault log, added days earlier to record failures,
 *     wrote the failed statement's bound values -- passwords included -- into
 *     a 0755 file.
 *
 * Both had an opt-in registry. Both were forgotten. So a new column named
 * `apiToken` fails CI here until somebody says which it is: a credential
 * (Route's registry) or not (Redaction::$patternExempt). The pattern is the
 * backstop; this is the forcing function.
 *
 * WHAT IS CHECKED, and why each direction is a real failure:
 *
 *   1. A model key matching the pattern that is in NEITHER the registry nor
 *      the exempt list -> unclassified. Redaction withholds it, so the effect
 *      is safe, but nobody has decided.
 *   2. A registry entry naming a key no model declares -> a rename silently
 *      disarmed the redaction. This is 58483d6's shape exactly: the entry
 *      looks present and protects nothing.
 *   3. An exempt entry naming a key no model declares -> a stale exemption,
 *      which is worse than a stale registry entry: it sits there ready to
 *      un-redact a future column that happens to reuse the name.
 *
 * WALKS EVERY MODEL, not Route::$validClasses. The ADR said validClasses
 * because that is what the API emits, but audit is not the API -- it records
 * whatever the ORM saves. userauth is the live difference: it is not a route
 * class, and `userauth.password` is the remember-me validator hash.
 *
 * Plugin models are checked too, and a plugin may satisfy (1) by declaring
 * the field through the API_SENSITIVE_FIELDS hook, which is the only way it
 * can. That declaration is read from the plugin's own source: the hook cannot
 * be fired here, because processEvent() reaches Route::getIds('hookevent')
 * and there is no database.
 *
 * DB-free: models are parsed from source, the registries are public statics
 * on a class that loads standalone.
 *
 * Usage: php tests/audit-redaction-coverage.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
chdir($root);

$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-redaction-cov-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        if (!is_dir($tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }
);
if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');
}

require_once $init;
new Initiator();

foreach (['Redaction', 'Route'] as $needed) {
    if (!class_exists($needed, true)) {
        fwrite(STDERR, "FAIL: $needed did not resolve\n");
        exit(1);
    }
}

/*
 * Floor, so a broken model scan cannot report a clean pass by finding
 * nothing. Well below the count at the time of writing (7 classes carry a
 * pattern-matching key, across 60-odd models).
 */
const MIN_MODELS = 40;

/**
 * class key => [friendly keys], parsed from source.
 *
 * Source rather than reflection: $databaseFields is protected, and
 * instantiating every model would need a database.
 *
 * @param string $webroot path to packages/web
 *
 * @return array
 */
function models($webroot)
{
    $files = array_merge(
        (array) glob($webroot . '/lib/fog/*.class.php'),
        (array) glob($webroot . '/lib/plugins/*/*.class.php'),
        (array) glob($webroot . '/lib/plugins/*/*/*.class.php')
    );
    $out = [];
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);
        if (!preg_match('#\$databaseFields\s*=\s*\[(.*?)\];#s', $src, $m)) {
            continue;
        }
        preg_match_all('#[\'"](\w+)[\'"]\s*=>#', $m[1], $keys);
        if (!count($keys[1])) {
            continue;
        }
        $class = strtolower(basename($file, '.class.php'));
        $out[$class] = array_map('strtolower', $keys[1]);
    }

    return $out;
}

/**
 * Fields a plugin declares through the API_SENSITIVE_FIELDS hook.
 *
 * Read from source because the hook cannot be fired without a database.
 * Matches the shape every declaring hook uses:
 *   $arguments['always'][$this->node][] = 'clientSecret';
 *
 * @param string $webroot path to packages/web
 *
 * @return array lowercased field names, class-agnostic
 */
function pluginDeclared($webroot)
{
    $out = [];
    foreach ((array) glob($webroot . '/lib/plugins/*/hooks/*.php') as $file) {
        $src = (string) file_get_contents($file);
        if (false === strpos($src, 'API_SENSITIVE_FIELDS')) {
            continue;
        }
        if (preg_match_all(
            '#\[\s*[\'"](?:fields|always)[\'"]\s*\][^;]*?[\'"](\w+)[\'"]\s*;#',
            $src,
            $m
        )) {
            foreach ($m[1] as $field) {
                $out[strtolower($field)] = true;
            }
        }
    }

    return $out;
}

$models = models($webroot);
if (count($models) < MIN_MODELS) {
    fwrite(
        STDERR,
        'FAIL: parsed only ' . count($models) . " model(s), expected at least "
        . MIN_MODELS . ". The scan is broken, not the tree.\n"
    );
    exit(1);
}

$declaredPlugin = pluginDeclared($webroot);

// Route's core tiers, read directly rather than through sensitiveFieldMap():
// that accessor fires a hook, which reaches Route::getIds('hookevent'), and
// there is no database here. The plugin half is covered by pluginDeclared().
$registry = [];
foreach ([Route::$sensitiveFields, Route::$sensitiveAlwaysFields] as $tier) {
    foreach ((array) $tier as $class => $keys) {
        foreach ((array) $keys as $key) {
            $registry[strtolower($class)][strtolower($key)] = true;
        }
    }
}

$exempt = [];
foreach ((array) Redaction::$patternExempt as $class => $keys) {
    foreach ((array) $keys as $key) {
        $exempt[strtolower($class)][strtolower($key)] = true;
    }
}

$failures = [];
$checks = 0;
$matched = 0;

/*
 * 1. Every pattern-matching model key is classified somewhere.
 */
foreach ($models as $class => $keys) {
    foreach ($keys as $key) {
        if (1 !== preg_match(Redaction::CREDENTIAL_PATTERN, $key)) {
            continue;
        }
        $matched++;
        $checks++;
        if (isset($registry[$class][$key])
            || isset($exempt[$class][$key])
            || isset($declaredPlugin[$key])
        ) {
            continue;
        }
        $failures[] = "$class.$key matches CREDENTIAL_PATTERN and is "
            . 'classified nowhere. Add it to Route::$sensitiveAlwaysFields '
            . 'if it is a credential, or to Redaction::$patternExempt if it '
            . 'is not. It is redacted either way at runtime; this is asking '
            . 'for the decision to be written down.';
    }
}

if ($matched < 1) {
    fwrite(
        STDERR,
        "FAIL: no model key matched CREDENTIAL_PATTERN at all. The pattern or "
        . "the model scan is broken -- host.ADPass alone should match.\n"
    );
    exit(1);
}

/*
 * 2. Every registry entry still names a real column. A rename here disarms
 *    the redaction and leaves an entry that looks like protection.
 */
foreach ($registry as $class => $keys) {
    foreach (array_keys($keys) as $key) {
        $checks++;
        if (!isset($models[$class])) {
            // A registry class with no model file is a plugin's, declared in
            // core for a plugin that may not be installed. Not a failure.
            continue;
        }
        if (in_array($key, $models[$class], true)) {
            continue;
        }
        $failures[] = "Route's sensitive registry names $class.$key, which "
            . 'that model does not declare. Either the column was renamed -- '
            . 'in which case the redaction it was protecting is now off -- or '
            . 'the entry is stale.';
    }
}

/*
 * 3. Every exemption still names a real column, so it cannot outlive the
 *    field it was written for and pre-approve a future one of that name.
 */
foreach ($exempt as $class => $keys) {
    foreach (array_keys($keys) as $key) {
        $checks++;
        if (isset($models[$class]) && in_array($key, $models[$class], true)) {
            continue;
        }
        $failures[] = "Redaction::\$patternExempt names $class.$key, which "
            . 'that model does not declare. A stale exemption silently '
            . 'un-redacts the next column to reuse the name.';
    }
}

/*
 * 4. The resolver itself, exercised. The three sections above check the
 *    DATA; these check that isSensitive() reads it the way the audit writer
 *    will. Outside a booted FOG, declaredFor() falls back to Route's core
 *    tiers -- which is what makes this callable here at all.
 */
$behaviour = [
    // Declared in a core tier, and does not match the pattern on its own:
    // "tok" is not "token". This is the case the registry exists for.
    ['host', 'sec_tok', true],
    ['host', 'prev_sec_tok', true],
    // Declared, and matches too.
    ['host', 'ADPass', true],
    ['storagenode', 'pass', true],
    ['user', 'password', true],
    ['userauth', 'password', true],
    // Matches the pattern and is declared nowhere: withheld anyway. This is
    // the default-closed behaviour that makes a forgotten column safe.
    ['host', 'someNewApiToken', true],
    ['image', 'sharedSecret', true],
    // Exempt: matches the pattern, is not a credential.
    ['host', 'tokenlock', false],
    ['pxemenuoptions', 'hotkey', false],
    ['task', 'bypassbitlocker', false],
    // The exemption is per class, not per name.
    ['host', 'hotkey', true],
    // Ordinary fields.
    ['host', 'name', false],
    ['host', 'description', false],
    // Case and namespace forms callers actually arrive with.
    ['Host', 'adpass', true],
    ['FOG\\Host', 'ADPASS', true],
    // Nothing is not a secret.
    ['host', '', false],
];
foreach ($behaviour as $case) {
    list($class, $field, $want) = $case;
    $checks++;
    $got = Redaction::isSensitive($class, $field);
    if ($got === $want) {
        continue;
    }
    $failures[] = sprintf(
        'Redaction::isSensitive(%s, %s) returned %s, expected %s',
        var_export($class, true),
        var_export($field, true),
        var_export($got, true),
        var_export($want, true)
    );
}

/*
 * 5. A redacted field records NOTHING derived from the value -- not a mask,
 *    not a length, not a hash. HARD constraint from the ADR prompt.
 */
$red = Redaction::values('host', 'ADPass', 'oldsecret', 'newsecret');
$checks++;
if ($red !== ['old' => null, 'new' => null, 'redacted' => 1]) {
    $failures[] = 'Redaction::values() on a credential must return NULL for '
        . 'both values with redacted = 1, got ' . var_export($red, true);
}
$open = Redaction::values('host', 'name', 'before', 'after');
$checks++;
if ($open !== ['old' => 'before', 'new' => 'after', 'redacted' => 0]) {
    $failures[] = 'Redaction::values() on an ordinary field must pass both '
        . 'values through, got ' . var_export($open, true);
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

printf(
    "ok  %d checks over %d models; %d key(s) matched the credential pattern\n",
    $checks,
    count($models),
    $matched
);
exit(0);
