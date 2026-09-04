<?php
/**
 * The retention registry describes tables that exist, and 0 means forever.
 *
 * Retention deletes rows nothing else deletes, so both halves of this are
 * failures nobody would see:
 *
 *   - A REGISTRY ENTRY NAMING A COLUMN THAT IS NOT THERE. The sweep composes
 *     its SQL from the entry, so a typo produces an error inside a daemon's
 *     hourly cycle and the table never ages out. Every core entry is checked
 *     against commons/schema-expected.php, which is generated from the real
 *     schema. Plugin entries cannot be: the manifest holds core's 67 tables
 *     and nothing a plugin ships.
 *   - A SETTING KEY WITH NO SCHEMA STEP. getSetting() on an absent key
 *     returns '', which casts to 0, which means keep forever -- so the sweep
 *     reads "disabled" and does nothing at all, on every install, silently.
 *     That is the same failure mode as forgetting the FOG_SCHEMA bump and it
 *     looks identical from outside: retention configured, retention not
 *     happening.
 *
 * And the arithmetic, which is the part that inverts quietly: 0 means KEEP
 * FOREVER, so it is larger than any number of days. A plain integer
 * comparison gets "forever, now delete after a year" backwards, calls the
 * sharpest shrink there is a growth, and lets it through without the audit
 * row that ADR 0021 Decision 10 requires before it.
 *
 * Mostly DB-free: Initiator registers the autoloader, Retention::registry()
 * skips its hook when there is no HookManager, and isShrink()/_ident() are
 * pure. The sweep's own ordering is checked textually, because running it
 * would need a database and rows to destroy.
 *
 * Usage: php tests/retention-registry.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Audit\Retention;

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-retention-test-' . getmypid();
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

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

if (!class_exists(\FOG\Audit\Retention::class, true)) {
    fwrite(STDERR, "FAIL: Retention did not resolve\n");
    exit(1);
}

$core = Retention::coreRegistry();
check(
    'coreRegistry() lists the five tables ADR 0021, 0022, 0023 and design '
    . '0008 name',
    count($core) === 5,
    $failures,
    $checks
);
// hostUserSession is the fifth, added with schema 422. It is pinned for the
// same reason taskLog is below: a table that grows a row per logon per host
// is exactly the shape that went wrong before -- unbounded growth nobody had
// registered a sweep for.
check(
    'hostUserSession, which grows per logon, is registered',
    isset($core['hostUserSession']),
    $failures,
    $checks
);
// Was four, then three, and is four again. imagingLog was retired by ADR 0022
// decision 3 -- taskLog carries the image name now -- so its entry went with
// the table, and schema 357 put taskLog itself in. Both halves are pinned,
// because the shape that went wrong in between was the successor table
// growing unbounded while a setting named after the dead one still rendered.
check(
    'the retired imagingLog is not still registered',
    !isset($core['imagingLog']),
    $failures,
    $checks
);
check(
    'taskLog, which inherited imaging history, is registered',
    isset($core['taskLog']),
    $failures,
    $checks
);

/*
 * 1. Every core entry names a real table, a real date column and a real id.
 */
$manifest = include $webroot . '/commons/schema-expected.php';
$tables = $manifest['tables'] ?? [];
$byLower = [];
foreach ($tables as $name => $def) {
    $byLower[strtolower($name)] = $def;
}

foreach ($core as $table => $entry) {
    $def = $byLower[strtolower($table)] ?? null;
    check(
        "retention table `$table` is in the schema manifest",
        null !== $def,
        $failures,
        $checks
    );
    if (null === $def) {
        continue;
    }
    $columns = array_map('strtolower', array_keys($def['columns'] ?? []));
    foreach (['date', 'id'] as $which) {
        $col = $entry[$which] ?? '';
        check(
            "`$table`.`$col` ($which column) exists",
            in_array(strtolower($col), $columns, true),
            $failures,
            $checks
        );
    }
    foreach ((array)($entry['children'] ?? []) as $child) {
        $cdef = $byLower[strtolower($child['table'])] ?? null;
        check(
            "child table `{$child['table']}` is in the schema manifest",
            null !== $cdef,
            $failures,
            $checks
        );
        if (null === $cdef) {
            continue;
        }
        $ccols = array_map('strtolower', array_keys($cdef['columns'] ?? []));
        check(
            "`{$child['table']}`.`{$child['key']}` exists",
            in_array(strtolower($child['key']), $ccols, true),
            $failures,
            $checks
        );
    }
}

/*
 * 2. Every setting key has a schema step that inserts it.
 */
$schemaSrc = (string) file_get_contents($webroot . '/commons/schema.php');
foreach ($core as $table => $entry) {
    $key = $entry['setting'] ?? '';
    check(
        "setting $key is inserted by commons/schema.php",
        '' !== $key && false !== strpos($schemaSrc, "'" . $key . "'"),
        $failures,
        $checks
    );
}

/*
 * 2b. And no setting key names a table that is gone.
 *
 * FOG_IMAGINGLOG_RETENTION_DAYS is the worked example and the reason this
 * check exists: step 347 inserted it, ADR 0022 decision 3 dropped the table
 * from under it, and the row stayed -- rendering on the settings page,
 * accepting a number, aging out nothing. It is not enough to check that the
 * registry no longer names imagingLog (above); the failure was a setting with
 * NO registry entry, which nothing else here would notice. Schema 357 deletes
 * the row and carries its value to FOG_TASKLOG_RETENTION_DAYS.
 *
 * Textual, deliberately: the INSERT in 347 is still in the file and always
 * will be -- replaying the schema on a fresh install runs it -- so what is
 * asserted is that a later step removes it again.
 */
// Concatenation collapsed first. The statements in schema.php are written as
// "..." . "..." across several lines, and the WHERE clause below appears
// TWICE in the step -- once in the INSERT ... SELECT that copies the value
// out, once in the DELETE that removes the row. Searching the raw source for
// the WHERE alone therefore still matches after the DELETE is gone, which is
// a gate that cannot fail. Joining the halves lets the whole statement be
// pinned, so removing the DELETE removes the only thing that matches.
$schemaSql = preg_replace('#"\s*\.\s*"#', '', $schemaSrc);
check(
    'schema.php deletes the orphaned FOG_IMAGINGLOG_RETENTION_DAYS row',
    false !== strpos(
        $schemaSql,
        "DELETE FROM `globalSettings` "
        . "WHERE `settingKey` = 'FOG_IMAGINGLOG_RETENTION_DAYS'"
    ),
    $failures,
    $checks
);
check(
    'and carries its value to the successor key',
    false !== strpos($schemaSrc, "'FOG_TASKLOG_RETENTION_DAYS'"),
    $failures,
    $checks
);

/*
 * 3. The arithmetic. 0 is forever, and forever is the largest window.
 */
$shrinks = [
    // [old, new, isShrink]
    [0, 365, true],    // forever -> a year. The sharpest shrink there is.
    [365, 0, false],   // a year -> forever. Growth.
    [365, 30, true],   // shorter.
    [30, 365, false],  // longer.
    [30, 30, false],   // unchanged.
    [0, 0, false],     // unchanged.
    ['0', '30', true], // the settings page hands these over as strings.
    ['30', '0', false],
];
foreach ($shrinks as $case) {
    list($old, $new, $want) = $case;
    $got = Retention::isShrink($old, $new);
    check(
        sprintf(
            'isShrink(%s, %s) is %s',
            var_export($old, true),
            var_export($new, true),
            $want ? 'true' : 'false'
        ),
        $got === $want,
        $failures,
        $checks
    );
}

/*
 * 4. Identifiers are validated, not interpolated on trust. The registry is
 *    extensible by a plugin hook, and a table name is not bindable.
 */
$ident = new \ReflectionMethod('FOG\\Audit\\Retention', '_ident');
$ident->setAccessible(true);
$bad = ['audit`Log', 'auditLog; DROP TABLE hosts', 'audit Log', '', 'a-b'];
foreach ($bad as $name) {
    $threw = false;
    try {
        $ident->invoke(null, $name);
    } catch (\Exception $e) {
        $threw = true;
    }
    check(
        '_ident() rejects ' . var_export($name, true),
        $threw,
        $failures,
        $checks
    );
}
check(
    '_ident() accepts a plain identifier',
    $ident->invoke(null, 'auditLog') === 'auditLog',
    $failures,
    $checks
);

/*
 * 5. The refusal, textually: the audit row is written BEFORE the delete and
 *    a table whose row did not store is skipped rather than swept.
 */
$src = (string) file_get_contents($webroot . '/src/Audit/Retention.php');
// Scoped to sweep()'s own body. Searching the whole file compares the FIRST
// Audit::record() in it -- which belongs to permitSettingChange() and is
// declared earlier -- against the delete, so the ordering would read as
// correct however sweep() was written.
$sweepStart = strpos($src, 'public static function sweep()');
$sweepEnd = $sweepStart === false
    ? false
    : strpos($src, "\n    /**", $sweepStart);
$sweep = ($sweepStart === false || $sweepEnd === false)
    ? ''
    : substr($src, $sweepStart, $sweepEnd - $sweepStart);
$recordPos = strpos($sweep, 'Audit::record(');
$deletePos = strpos($sweep, 'self::_delete(');
check(
    'sweep() records the audit row before it deletes',
    '' !== $sweep
    && false !== $recordPos
    && false !== $deletePos
    && $recordPos < $deletePos,
    $failures,
    $checks
);
check(
    'sweep() skips a table whose audit row did not store',
    false !== strpos($src, 'if (!$audit) {'),
    $failures,
    $checks
);
check(
    'permitSettingChange() refuses an unrecorded shrink',
    false !== strpos($src, 'if ($shrink && !$stored) {'),
    $failures,
    $checks
);

/*
 * 5b. The audit rows name the SETTING, by its own id.
 *
 * Scoped to permitSettingChange()'s body. It used to store the audit row's
 * own id as the subject id -- a number from a different table that fits the
 * column and so points at whatever setting happens to hold it, which is
 * wrong in a way nothing can detect after the fact. The subject id must come
 * from the lookup, and the lookup must not be able to throw: this runs inside
 * Decision 10, where an exception raised while decorating the record would
 * turn a settings save into a 500 from the code protecting the record.
 */
$permitStart = strpos($src, 'public static function permitSettingChange(');
$permitEnd = $permitStart === false
    ? false
    : strpos($src, "\n    /**", $permitStart);
$permit = ($permitStart === false || $permitEnd === false)
    ? ''
    : substr($src, $permitStart, $permitEnd - $permitStart);
check(
    'permitSettingChange() reads the setting id from the key',
    '' !== $permit
    && false !== strpos($permit, '$settingID = self::_settingID($key)'),
    $failures,
    $checks
);
check(
    'permitSettingChange() never uses the audit row id as the subject id',
    '' !== $permit && false === strpos($permit, "\$audit->get('id')"),
    $failures,
    $checks
);
check(
    '_settingID() cannot throw',
    false !== strpos($src, 'private static function _settingID($key)')
    && false !== strpos($src, '} catch (\\Exception $e) {
            return 0;
        }'),
    $failures,
    $checks
);

/*
 * 6. The settings page gates the windows on audit.manage, both ways: the
 *    field is not rendered without it and a post is refused without it.
 */
$page = (string) file_get_contents(
    $webroot . '/src/Pages/FOGConfigurationPage.php'
);
check(
    'the settings page hides retention windows without audit.manage',
    false !== strpos($page, 'Retention::settingKeys()')
    && false !== strpos($page, "Authorization::can('audit.manage')"),
    $failures,
    $checks
);
check(
    'the settings page runs a retention post through permitSettingChange()',
    false !== strpos($page, 'Retention::permitSettingChange('),
    $failures,
    $checks
);

/*
 * 7. The sweep has a caller, and it is a daemon named for retention.
 *
 * A registry and no daemon is a setting that silently does nothing, which is
 * the first half. The second half is WHICH daemon, and it is a property worth
 * pinning rather than a tidiness preference: the sweep lived in
 * FOGPluginRunner, so an administrator who ran no plugins and switched that
 * off silently stopped pruning the audit trail, the history, the host login
 * records and the task log. Putting it back there -- or adding a second
 * caller in a daemon that is about something else -- reopens exactly that.
 */
$runner = (string) file_get_contents(
    $webroot . '/src/Service/RetentionRunner.php'
);
check(
    'FOGRetentionRunner calls Retention::sweep()',
    false !== strpos($runner, 'Retention::sweep()'),
    $failures,
    $checks
);
// Its own enable flag, named for the feature rather than for the process. The
// only switches that stop retention are this one and the per-table windows.
check(
    'and gates on RETENTIONGLOBALENABLED, not another daemon\'s flag',
    false !== strpos($runner, "getSetting('RETENTIONGLOBALENABLED')"),
    $failures,
    $checks
);
$plugin = (string) file_get_contents(
    $webroot . '/src/Service/PluginRunner.php'
);
check(
    'the plugin runner no longer sweeps',
    false === strpos($plugin, 'Retention::sweep()'),
    $failures,
    $checks
);

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
