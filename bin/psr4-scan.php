<?php
/**
 * Where every FOG class belongs under PSR-4, and whether it is there yet.
 *
 * Commit 0 of docs/composer-psr4-plan.md. This tool owns two things the move
 * needs and nothing else does:
 *
 *   1. The MANIFEST -- current path => target path, derived from the class a
 *      file actually declares rather than from its filename. That derivation
 *      is the move: PSR-4 requires the basename to match the declared name
 *      exactly, and FOG's filenames match it only case-insensitively.
 *   2. The TAXONOMY -- which directory a class lands in. 151 of 202 fall out
 *      of the parent chain with no judgment (a model is `extends
 *      FOGController`, a manager is `extends FOGManagerController`); the
 *      remaining 51 are a hand-filed table below, and it is deliberately a
 *      table in code rather than a convention in prose, so that a class
 *      matching no rule and named in no table FAILS this check instead of
 *      landing somewhere plausible.
 *
 * Scans lib/ AND src/ together, so it stays meaningful before, during and
 * after the move: a class already at its target is reported as done, not as
 * missing. `--check` is therefore a standing invariant, not a one-shot
 * migration gate, and tests/psr4-layout.test.php wires it into the suite the
 * same way tests/namespaced-tree.test.php wires in bin/namespace-fog-classes.php.
 *
 * What --check refuses, and why each one is a silent failure otherwise:
 *
 *   - a file declaring zero or more than one type. PSR-4 cannot address the
 *     second type in a file, so it would resolve only as a side effect of
 *     loading the first.
 *   - a class matching no derivation rule and absent from the table. Left to
 *     a default it lands in a bucket nobody chose.
 *   - two classes whose targets collide case-insensitively. macOS and some
 *     Linux setups are case-insensitive, so one file would silently overwrite
 *     the other during the move.
 *   - two files claiming one basename. Under the multi-root map of Move 1 the
 *     first-listed root wins, which is the readdir-order failure class
 *     tests/autoload-core-wins.test.php exists for. It also has to hold for
 *     the reverse bridge's lowercase map to have a single answer.
 *
 * Usage:
 *   php bin/psr4-scan.php --check      # exit 1 if anything above is true
 *   php bin/psr4-scan.php --manifest   # current<TAB>target, files not yet moved
 *   php bin/psr4-scan.php --buckets    # the taxonomy, with counts
 *
 * @category Tooling
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Never moved, each for its own reason.
 *
 * The two AltoRouter files keep upstream's name, authorship and MIT license
 * (ADR 0013); they are a FORK rather than a vendored copy, so no Packagist
 * swap is coming and the exclusion is permanent.
 *
 * The 46 discovery-named files -- *.page.php, *.hook.php, *.report.php,
 * *.event.php -- are not listed here because they are selected out by
 * extension. Their filenames are a contract with code that walks directories
 * (FOGPageManager::loadPageClasses, EventManager::load) and PSR-4 does not do
 * discovery. They can still take a namespace; see the plan.
 */
const EXCLUDE = [
    'packages/web/lib/router/altorouter.class.php',
    'packages/web/lib/router/altotransformer.class.php',
];

/**
 * Has a home in the taxonomy, but is not moved by THIS work.
 *
 * Empty, and deliberately kept: a class parked here is reported by --check
 * rather than hidden, so a deferral stays a decision someone took rather than
 * a file that got missed.
 *
 * System was the only entry. Its path was hard-wired in 14 shell and git-hook
 * sites here and 3 in FOGProject/fog-workflows -- including fog-version.sh and
 * apply-fog-version.sh, which gate every commit and push in this repository --
 * so it moved after the fog-workflows change could find it either way, per ADR
 * 0009's release-ordering rule. The one consumer that could not be fixed ahead
 * of the move is the FOGUpdater already installed on a beta server, which
 * fetches the old path from raw.githubusercontent.com; see F-49 in
 * docs/refactor-facts.md for why that break was accepted rather than shimmed.
 */
const DEFERRED = [];

/**
 * The 51 classes the parent chain cannot place.
 *
 * Everything else is derived (see RULES). This table is the taxonomy
 * decision itself, and the reason it is code: a class that appears here is a
 * call somebody made, and a class that appears nowhere fails the check.
 *
 * Two boundaries that get re-litigated, recorded so they are not re-argued
 * from scratch:
 *
 *   - Items holds the RECORD, TaskHandling holds the MACHINERY. Task,
 *     TaskLog, TaskState, TaskType, SnapinTask, SnapinJob, ScheduledTask and
 *     MulticastSession are rows and derive into Items on their own.
 *     TaskQueue, TaskError and TaskingElement drive a task and are not rows.
 *   - WakeOnLan is Boot, not Util. It exists to get a host to the point where
 *     the boot menu can answer it -- same subsystem, same lifecycle. Util is
 *     for things belonging to no subsystem at all.
 */
const TABLE = [
    // The class hierarchy itself, plus the constants surface everything reads.
    'FOGBase' => 'Base',
    'FOGCore' => 'Base',
    'FOGController' => 'Base',
    'FOGManagerController' => 'Base',
    'FOGPage' => 'Base',
    // Pure rules for identifying a booting machine by firmware (#198, ADR
    // 0039). Extends nothing on purpose, so RULES cannot place it.
    'SmbiosIdentity' => 'Base',
    'FOGPageManager' => 'Base',
    'FOGPagePost' => 'Base',
    'FOGPageRender' => 'Base',
    'Page' => 'Base',
    'Hook' => 'Base',
    'Event' => 'Base',
    'EventManager' => 'Base',
    'HookManager' => 'Base',
    'Listener' => 'Base',
    'PluginTask' => 'Base',
    'LoadGlobals' => 'Base',
    'System' => 'Base',
    // Base, not Db or Util: it answers a question FOGBase itself has to ask
    // before it can read or write any date at all -- which zone stored values
    // mean -- and it is consulted from storageTimeZone(), which is on the
    // boot path. It reads one table, but so does every model; what makes it
    // Base is who depends on it.
    'StorageEpoch' => 'Base',
    // Bases of their own derived buckets.
    'FOGClient' => 'Client',
    'FOGService' => 'Service',
    // An entity in everything but its parent -- it has a table, an id and a
    // manager, it just does not extend FOGController.
    'MACAddress' => 'Items',
    'PDODB' => 'Db',
    'DatabaseManager' => 'Db',
    'Mysqldump' => 'Db',
    'SchemaReconciler' => 'Db',
    'ConstraintViolation' => 'Db',
    'Route' => 'Router',
    'OpenAPI' => 'Router',
    'HTTPResponseCodes' => 'Router',
    'LongestFirstRouteParser' => 'Router',
    'Authorization' => 'Auth',
    'CSRF' => 'Auth',
    'SiteScope' => 'Auth',
    'Redaction' => 'Auth',
    // Auth, not Audit, even though its most load-bearing consumer is
    // Audit::_actor(): it decides WHICH IDENTITY a request is acting under,
    // which is the same question Authorization and SiteScope answer about
    // that identity's reach. The audit is a reader of the answer, not its
    // owner. See ADR 0033.
    'Identity' => 'Auth',
    // Assign, not Items: it is policy, not a row. Resolver decides WHAT A
    // HOST IS ASSIGNED out of its own associations plus what its groups
    // grant, and putting it next to the ORM models in Items would say it
    // was one of them. Same reasoning that puts SiteScope in Auth rather
    // than beside the sites row it reads. See ADR 0038 decision 8.
    'Resolver' => 'Assign',
    'Audit' => 'Audit',
    'ActivityWindow' => 'Audit',
    'AuditStats' => 'Audit',
    'FleetStats' => 'Audit',
    'ImagingStats' => 'Audit',
    'InventoryStats' => 'Audit',
    'ReportWindow' => 'Audit',
    'SnapinStats' => 'Audit',
    'StorageStats' => 'Audit',
    'WindowedStats' => 'Audit',
    'Retention' => 'Audit',
    'Blame' => 'Audit',
    'FOGLdap' => 'Net',
    'FOGFTP' => 'Net',
    'FOGSSH' => 'Net',
    'FOGURLRequests' => 'Net',
    'Ping' => 'Net',
    'FOGRollingURL' => 'Net',
    'BootMenuBase' => 'Boot',
    'IpxeBootMenu' => 'Boot',
    'UbootBootMenu' => 'Boot',
    // Extends \Exception, not FOGBase or FOGController -- a catchable
    // stand-in for the exit() UbootBootMenu's constructor used to call, so
    // no RULES ancestry rule can place it. Same subsystem, same file as the
    // class it exists for.
    'UbootRenderHalted' => 'Boot',
    // Extends FOGBase directly, not FOGController -- like MACAddress above,
    // an entity in everything but its parent. Boot, not Util, for the same
    // reason WakeOnLan is: it exists to get a host to the point where a
    // board's boot request can be answered, same subsystem as UbootBootMenu.
    'UbootTftpSync' => 'Boot',
    'Registration' => 'Boot',
    'WakeOnLan' => 'Boot',
    // Boot, not Util, for the same reason WakeOnLan is: it is the vocabulary
    // of what iPXE reports on a boot, and its only writers are the boot menu
    // and the task-completion report. Util is for things belonging to no
    // subsystem at all.
    'SecureBootState' => 'Boot',
    // Agent, not Boot: fog-agent is the management client, not the netboot
    // path. Both extend FOGBase directly -- Enrollment is the policy for who
    // gets a client certificate, Principal is the pure verifier that turns a
    // presented certificate back into a host -- so ancestry cannot place
    // them, and they are one subsystem the way the Boot classes are.
    'Enrollment' => 'Agent',
    'Principal' => 'Agent',
    'Token' => 'Agent',
    'State' => 'Agent',
    'Snapins' => 'Agent',
    'SoftwareSet' => 'Agent',
    // The two writers for what an agent reports about its own host
    // (design 0006). Named *Facts rather than Inventory and Software
    // because both of those already name an Items class -- these write
    // those rows, they are not those rows.
    'InventoryFacts' => 'Agent',
    'SoftwareFacts' => 'Agent',
    // The writer for what an agent reports about who is logged on (design
    // 0008). Named UserSessions rather than UserTracking because that name
    // is already an Items class for the legacy event table -- this writes
    // hostUserSession rows, it is not either of those rows.
    'DirectoryFacts' => 'Agent',
    'DirectoryPlacement' => 'Agent',
    // The join half of directory membership (design 0009 section 6): the
    // one class that decides whether a credential leaves this server.
    'DirectoryJoin' => 'Agent',
    // The writer for the links a host is on, and the class that asks an
    // awake agent to broadcast a wake for a sleeping neighbor (design
    // 0011). Network and Wake would both be far too general as Items
    // names; these write hostNetwork and agentWake rows.
    'NetworkFacts' => 'Agent',
    'WakeRelay' => 'Agent',
    // The writer for what an agent reports about its installed printers
    // (design 0010). Same naming reason: Printer is already an Items
    // class for the assignable printer -- this writes hostPrinter and
    // hostSpooler rows, it is not that row.
    'PrinterFacts' => 'Agent',
    'PrinterSet' => 'Agent',
    // The second writer for hosts.hostSbState (design 0012). Named
    // SecureBootFacts and not SecureBootState because THAT name is the Boot
    // class holding the six state names -- this reports observations into
    // that vocabulary, it does not define it.
    'SecureBootFacts' => 'Agent',
    'UserSessions' => 'Agent',
    // The version an agent should be running (design 0015). Extends FOGBase
    // directly, like Enrollment and State, so ancestry cannot place it.
    // Agent and not Util because it is only ever read through the agent's
    // desired state -- and not Items, because it is not a row: it reads a
    // host column and a global setting and decides which of the two wins.
    'Update' => 'Agent',
    'TaskingElement' => 'TaskHandling',
    'TaskQueue' => 'TaskHandling',
    'TaskError' => 'TaskHandling',
    'Timer' => 'Util',
    'FOGCron' => 'Util',
    'FOGLogPaths' => 'Util',
    // Util because it belongs to no subsystem: it answers "do these hosts
    // agree about this column", which the group page and any mass edit over
    // a selection both need and neither owns. Not Items -- it is not a row.
    'SharedHostValues' => 'Util',
    // Util for the same reason: reducing a submission to leave/set/clear
    // belongs to whichever form is doing the editing, and to none of them.
    'MassEdit' => 'Util',
    'SnapinSaveException' => 'Exception',
    'UploadException' => 'Exception',
];

/**
 * Bucket by ancestry, most specific first.
 *
 * Order matters: a manager's chain reaches FOGController through
 * FOGManagerController on some models, so Managers has to be asked first.
 */
const RULES = [
    'FOGManagerController' => 'Managers',
    'FOGController' => 'Items',
    'FOGService' => 'Service',
    'FOGClient' => 'Client',
    // The four discovery kinds. Ancestry rather than 52 TABLE rows because
    // the parent IS the definition of the kind -- a class extending FOGPage
    // is a page, and there is no way to write one that belongs elsewhere.
    // ReportManagement before FOGPage: a report extends ReportManagement,
    // which extends FOGPage, so the more specific ancestor has to be asked
    // first or every report buckets as a page.
    'ReportManagement' => 'Reports',
    'FOGPage' => 'Pages',
    'Hook' => 'Hooks',
    'Event' => 'Events',
];

const SRC = 'packages/web/src/';

$mode = $argv[1] ?? '';
if (!in_array($mode, ['--check', '--manifest', '--buckets'], true)) {
    fwrite(STDERR, "usage: {$argv[0]} --check|--manifest|--buckets\n");
    exit(2);
}

$root = dirname(__DIR__);
chdir($root);

$files = array_filter(
    array_map('trim', explode("\n", (string) shell_exec('git ls-files "packages/web/*"'))),
    function ($f) {
        if ('' === $f || in_array($f, EXCLUDE, true)) {
            return false;
        }
        if (0 === strpos($f, 'packages/web/vendor/')) {
            return false;
        }
        // The movable population: legacy class files, plus anything already
        // under src/. Discovery-named files are excluded by not matching.
        return (bool) preg_match('#^packages/web/lib/.+\.class\.php$#', $f)
            || (0 === strpos($f, SRC) && '.php' === substr($f, -4));
    }
);

$errors = [];
$declared = [];   // name => ['file' => path, 'parent' => ?string]

foreach ($files as $file) {
    $types = declaredIn($file);
    if (1 !== count($types)) {
        $errors[] = sprintf(
            '%s declares %d types (%s); PSR-4 addresses one class per file',
            $file,
            count($types),
            0 === count($types) ? 'none' : implode(', ', array_column($types, 'name'))
        );
        continue;
    }
    $name = $types[0]['name'];
    // Checked HERE and not against the target below, because $declared is
    // keyed by name: a second file declaring an existing name would simply
    // overwrite the first, the scan would report one fewer class than the
    // tree holds, and every downstream collision check would have nothing
    // left to see. Found by mutation -- renaming Timer to Ping left --check
    // green and moved the total from 202 to 201.
    if (isset($declared[$name])) {
        $errors[] = sprintf(
            'two files declare "%s": %s and %s. PHP resolves whichever loads '
            . 'first; under the multi-root map that is decided by root order.',
            $name,
            $declared[$name]['file'],
            $file
        );
        continue;
    }
    $declared[$name] = ['file' => $file, 'parent' => $types[0]['parent']];
}

$buckets = [];
$manifest = [];
$deferred = [];
$targets = [];
$basenames = [];

foreach ($declared as $name => $info) {
    $bucket = bucketOf($name, $declared);
    if (null === $bucket) {
        $errors[] = sprintf(
            '%s (%s) matches no rule in RULES and is absent from TABLE. '
            . 'Add it to TABLE in %s -- a class with no chosen home lands in '
            . 'one nobody picked.',
            $name,
            $info['file'],
            basename(__FILE__)
        );
        continue;
    }
    $buckets[$bucket][] = $name;
    $target = SRC . $bucket . '/' . $name . '.php';

    $key = strtolower($target);
    if (isset($targets[$key])) {
        $errors[] = sprintf(
            'target collision (case-insensitively): %s and %s both want %s',
            $info['file'],
            $declared[$targets[$key]]['file'],
            $target
        );
        continue;
    }
    $targets[$key] = $name;

    // Names differing only in case: distinct to PHP's declaration table but
    // one file on a case-insensitive filesystem, and one key in the reverse
    // bridge's lowercase map. Identical names are caught earlier, when
    // $declared is built.
    $bkey = strtolower($name);
    if (isset($basenames[$bkey])) {
        $errors[] = sprintf(
            'two classes fold to the basename "%s": %s (%s) and %s (%s). '
            . 'The reverse bridge keys its map on the lowercased name and '
            . 'would have two answers for one key.',
            $bkey,
            $name,
            $info['file'],
            $basenames[$bkey],
            $declared[$basenames[$bkey]]['file']
        );
        continue;
    }
    $basenames[$bkey] = $name;

    if (isset(DEFERRED[$name])) {
        $deferred[$name] = $info['file'];
        continue;
    }
    if ($info['file'] !== $target) {
        $manifest[$info['file']] = $target;
    }
}

if ('--buckets' === $mode) {
    ksort($buckets);
    $total = 0;
    foreach ($buckets as $bucket => $names) {
        sort($names);
        printf("%-13s %3d\n", $bucket, count($names));
        $total += count($names);
    }
    printf("%-13s %3d\n", 'TOTAL', $total);
    exit(count($errors) > 0 ? 1 : 0);
}

if ('--manifest' === $mode) {
    ksort($manifest);
    foreach ($manifest as $from => $to) {
        printf("%s\t%s\n", $from, $to);
    }
    exit(count($errors) > 0 ? 1 : 0);
}

if (count($errors) > 0) {
    fwrite(STDERR, count($errors) . " problem(s):\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  $e\n");
    }
    exit(1);
}

$done = count($declared) - count($manifest) - count($deferred);
printf(
    "ok: %d class(es), %d bucket(s); %d already in place, %d to move",
    count($declared),
    count($buckets),
    $done,
    count($manifest)
);
if (count($deferred) > 0) {
    printf(", %d deferred (%s)", count($deferred), implode(', ', array_keys($deferred)));
}
printf("\n");
exit(0);

/**
 * The bucket a class belongs to, or null if nothing places it.
 *
 * Walks the ancestry inside the scanned set; a parent outside it (\Exception,
 * a Composer package) simply ends the walk. The hand-filed table is consulted
 * FIRST so an explicit decision always beats a derived one -- MACAddress is
 * the worked example, filed into Items despite deriving nowhere.
 *
 * @param string $name     the class to place
 * @param array  $declared name => ['file' => string, 'parent' => ?string]
 *
 * @return string|null
 */
function bucketOf($name, array $declared)
{
    if (isset(TABLE[$name])) {
        return TABLE[$name];
    }
    $seen = [];
    $cursor = $name;
    while (isset($declared[$cursor]['parent'])
        && null !== $declared[$cursor]['parent']
        && !isset($seen[$cursor])
    ) {
        $seen[$cursor] = true;
        $cursor = $declared[$cursor]['parent'];
        foreach (RULES as $ancestor => $bucket) {
            if ($cursor === $ancestor) {
                return $bucket;
            }
        }
        if (!isset($declared[$cursor])) {
            break;
        }
    }
    return null;
}

/**
 * The types a file declares, with each one's parent.
 *
 * Token-based rather than regex: `Foo::class` puts T_CLASS in the stream
 * after a T_DOUBLE_COLON, and an anonymous class puts one before a `(`.
 * Both are skipped. The parent is read from the T_EXTENDS that follows,
 * unqualified -- every FOG class extends a FOG class by short name.
 *
 * @param string $file path to read
 *
 * @return array[] each ['name' => string, 'parent' => ?string]
 */
function declaredIn($file)
{
    $tokens = token_get_all((string) file_get_contents($file));
    $count = count($tokens);
    $out = [];

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i])
            || !in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT], true)
        ) {
            continue;
        }
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
        $j = nextSignificant($tokens, $i, $count);
        if (null === $j || T_STRING !== $tokens[$j][0]) {
            continue;
        }
        $name = $tokens[$j][1];
        $parent = null;
        $k = nextSignificant($tokens, $j, $count);
        if (null !== $k && T_EXTENDS === $tokens[$k][0]) {
            $p = nextSignificant($tokens, $k, $count);
            if (null !== $p && T_STRING === $tokens[$p][0]) {
                $parent = $tokens[$p][1];
            }
        }
        $out[] = ['name' => $name, 'parent' => $parent];
    }
    return $out;
}

/**
 * Index of the next array token that is neither whitespace nor a comment.
 *
 * @param array $tokens the token stream
 * @param int   $from   index to search after
 * @param int   $count  total tokens
 *
 * @return int|null
 */
function nextSignificant(array $tokens, $from, $count)
{
    for ($j = $from + 1; $j < $count; $j++) {
        if (!is_array($tokens[$j])) {
            return null;
        }
        if (in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $j;
    }
    return null;
}
