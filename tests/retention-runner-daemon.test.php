<?php
/**
 * FOGRetentionRunner is installed, enabled and non-root on every init system.
 *
 * The sweep used to live in FOGPluginRunner. That was cheap and it was wrong:
 * a daemon named for plugins is one a site with no plugins switches off, and
 * switching it off also stopped the audit trail, the administrative history,
 * the host login records and the task log from ever being pruned -- with
 * nothing anywhere to say so. Retention already has an off switch and it is
 * per table (0 days, keep forever); a second one, unrelated and named after
 * something else, is a breakage that comes back as a bug report against a
 * feature working exactly as written.
 *
 * Splitting it out fixes that only if the new daemon actually reaches the
 * disk on every supported host, and this is the half that fails silently.
 * FOG installs services from FOUR trees -- systemd, and OpenRC/sysv variants
 * for Alpine, Red Hat and Debian -- chosen by $systemctl and
 * $linuxReleaseName_lower in lib/common/config.sh. A unit added to three of
 * the four produces an install that reports every step OK and simply never
 * prunes anything on the fourth, which is the same class of failure #863 was
 * (an unmatched `case` arm exits 0, so errorStat prints OK for a step that
 * did not run).
 *
 * What is pinned:
 *
 *   A  a unit/init script exists in all four trees, and is executable in the
 *      three where the installer copies a script rather than a unit file
 *   B  $serviceList carries it, so enableInitScript() enrols it -- being in
 *      $initdsrc gets it COPIED, not started, and not started at boot
 *   C  it runs as the web user, via the FOGWEBUSER placeholder the installer
 *      rewrites. A missing placeholder means a daemon issuing DELETEs as root
 *   D  Alpine's copy names FOGPHPBIN and uses supervise-daemon, per #863
 *   E  the ExecStart/DAEMON/command_args path names the right daemon file
 *   F  the service wrapper and service class exist and agree on the class
 *   G  the log directory is created and chowned by the installer, and is in
 *      FOGLogPaths::FOG_SUBDIRS -- a log the viewer cannot enumerate is a
 *      daemon nobody can check on, which is the whole point of the split
 *
 * Textual and filesystem checks only: these live in functions that rewrite
 * /etc and enrol system services, so running them would mean writing to the
 * developer's own box. Same convention as
 * tests/alpine-openrc-services.test.sh.
 *
 * Usage: php tests/retention-runner-daemon.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

function readOrEmpty($path)
{
    return is_readable($path) ? (string) file_get_contents($path) : '';
}

$name = 'FOGRetentionRunner';
$daemonPath = '/opt/fog/service/' . $name . '/' . $name;

/*
 * A. Present in all four trees.
 *
 * The systemd unit is data read by systemd and ships 0644 like its eight
 * siblings; the other three are scripts an init system execs, and ship 0755.
 * core.fileMode is false in this repo, so a missing exec bit is not something
 * a checkout would show you -- it has to be asserted.
 */
// The third element is the DIRECTIVE that has to carry the placeholder, not
// merely the placeholder itself. Every one of these files also names
// FOGWEBUSER in a comment explaining the substitution, so searching for the
// bare word passes on a unit whose User= has been changed to root -- a gate
// that cannot fail. Each init system spells the directive differently, which
// is exactly why it has to be per tree.
$trees = [
    'systemd' => [
        $root . '/packages/systemd/' . $name . '.service',
        false,
        ['User=FOGWEBUSER', 'Group=FOGWEBUSER'],
    ],
    'alpine' => [
        $root . '/packages/init.d/alpine/' . $name,
        true,
        ['command_user="FOGWEBUSER"'],
    ],
    'redhat' => [
        $root . '/packages/init.d/redhat/' . $name,
        true,
        ['RUNASUSER=FOGWEBUSER'],
    ],
    'ubuntu' => [
        $root . '/packages/init.d/ubuntu/' . $name,
        true,
        ['RUNASUSER=FOGWEBUSER'],
    ],
];
$sources = [];
foreach ($trees as $tree => $spec) {
    list($path, $mustExec, $userDirectives) = $spec;
    $there = is_file($path);
    check(
        "the $tree tree ships $name",
        $there,
        $failures,
        $checks
    );
    if (!$there) {
        continue;
    }
    $sources[$tree] = readOrEmpty($path);
    if ($mustExec) {
        check(
            "the $tree copy is executable",
            is_executable($path),
            $failures,
            $checks
        );
    }
    /*
     * C. Non-root, through the placeholder installInitScript() rewrites. The
     *    literal FOGWEBUSER is what has to be here: the substitution happens
     *    on the INSTALLED copy, and systemd refusing to start a unit whose
     *    User= does not resolve is the intended loud failure.
     * E. And pointed at the right file. Every one of these hard-codes
     *    /opt/fog/service, which installInitScript() rewrites when
     *    $servicedst differs.
     */
    foreach ($userDirectives as $directive) {
        check(
            "the $tree copy carries $directive, so it is not root",
            false !== strpos($sources[$tree], $directive),
            $failures,
            $checks
        );
    }
    check(
        "the $tree copy points at $daemonPath",
        false !== strpos($sources[$tree], $daemonPath),
        $failures,
        $checks
    );
}

/*
 * D. Alpine's two #863 properties. Neither is cosmetic: Alpine ships no
 *    unversioned "php" so a bare interpreter cannot work, and OpenRC has no
 *    supervision by default, so a daemon that starts before mariadb is
 *    accepting connections dies within a second and stays dead.
 */
if (isset($sources['alpine'])) {
    check(
        'the Alpine copy names FOGPHPBIN rather than a bare php',
        false !== strpos($sources['alpine'], 'FOGPHPBIN'),
        $failures,
        $checks
    );
    check(
        'the Alpine copy uses supervise-daemon',
        false !== strpos($sources['alpine'], 'supervisor=supervise-daemon'),
        $failures,
        $checks
    );
}

/*
 * B. Enrolled, not merely copied.
 *
 * installInitScript() copies everything in $initdsrc; enableInitScript()
 * walks $serviceList. A unit present in the tree and absent from the list
 * installs, never starts, and never comes back after a reboot -- and the
 * install still reports OK throughout.
 */
$config = readOrEmpty($root . '/lib/common/config.sh');
check(
    'config.sh names the unit in the systemd branch',
    false !== strpos($config, 'initdRTfullname="' . $name . '.service"'),
    $failures,
    $checks
);
check(
    'config.sh names the script in the sysv/OpenRC branch',
    false !== strpos($config, 'initdRTfullname="' . $name . '"'),
    $failures,
    $checks
);
if (preg_match('#^serviceList=.*$#m', $config, $m)) {
    check(
        '$serviceList carries the retention runner',
        false !== strpos($m[0], '$initdRTfullname'),
        $failures,
        $checks
    );
} else {
    check('$serviceList is assigned in config.sh', false, $failures, $checks);
}

/*
 * G. The log directory. Created and chowned by the installer because this
 *    daemon runs as the web user and cannot create it as root the way the
 *    seven root daemons could -- and listed in FOGLogPaths so the log viewer
 *    can enumerate it. A daemon whose log nobody can reach is a daemon nobody
 *    can check on, which is the question the split exists to make answerable.
 */
$functions = readOrEmpty($root . '/lib/common/functions.sh');
// Anchored on a word boundary, not strpos. 'retention' is a prefix of
// 'retentionX', so a plain substring search passes on a typo'd path -- and a
// typo'd path is precisely the failure this checks for, since the daemon's
// own @mkdir fallback would then quietly create the real directory with the
// wrong owner on some hosts and not at all on others.
check(
    'the installer creates $servicelogs/retention',
    1 === preg_match(
        '#mkdir -p \$servicelogs/retention(?![A-Za-z0-9_])#',
        $functions
    ),
    $failures,
    $checks
);
check(
    'and chowns it to the web user',
    1 === preg_match(
        '#chown \$\{apacheuser\}:\$\{apacheuser\} '
        . '\$servicelogs/retention(?![A-Za-z0-9_])#',
        $functions
    ),
    $failures,
    $checks
);
$logPaths = readOrEmpty(
    $root . '/packages/web/src/Util/FOGLogPaths.php'
);
check(
    "FOGLogPaths::FOG_SUBDIRS carries 'retention'",
    1 === preg_match(
        '#const FOG_SUBDIRS = \[[^\]]*\'retention\'#s',
        $logPaths
    ),
    $failures,
    $checks
);

/*
 * F. The wrapper, the class, and the settings the two share.
 *
 * The wrapper resolves its class by NAME through getClass(), so a rename on
 * either side is a runtime "Class not found" inside a daemon rather than
 * anything a linter would catch.
 */
$wrapper = readOrEmpty($root . '/packages/service/' . $name . '/' . $name);
check(
    "packages/service/$name/$name exists",
    '' !== $wrapper,
    $failures,
    $checks
);
check(
    'the wrapper is executable',
    is_executable($root . '/packages/service/' . $name . '/' . $name),
    $failures,
    $checks
);
check(
    "the wrapper instantiates RetentionRunner",
    false !== strpos($wrapper, "getClass('RetentionRunner')"),
    $failures,
    $checks
);
check(
    'and registers itself with service_persist under its own name',
    false !== strpos($wrapper, "\$service_name = '" . $name . "'"),
    $failures,
    $checks
);
$class = readOrEmpty(
    $root . '/packages/web/src/Service/RetentionRunner.php'
);
check(
    'the service class exists and extends FOGService',
    false !== strpos($class, 'class RetentionRunner extends FOGService'),
    $failures,
    $checks
);
check(
    'and declares itself in the FOG\\Service namespace, with no global alias',
    false !== strpos($class, 'namespace FOG\\Service;')
    && false === strpos($class, 'class_alias('),
    $failures,
    $checks
);

/*
 * The four settings, each in the category that puts it beside the other
 * daemons' equivalents rather than in a section of its own. A key the class
 * reads and no schema step inserts resolves to '' -- so the log filename
 * silently falls back, and the sleep time silently becomes the default,
 * with nothing logged. Same failure mode as a missing FOG_SCHEMA bump.
 */
$schema = readOrEmpty($root . '/packages/web/commons/schema.php');
$settings = [
    'RETENTIONGLOBALENABLED',
    'RETENTIONRUNNERSLEEPTIME',
    'RETENTIONRUNNERLOGFILENAME',
    'RETENTIONRUNNERDEVICEOUTPUT',
];
foreach ($settings as $key) {
    check(
        "schema.php inserts $key",
        false !== strpos($schema, "'" . $key . "'"),
        $failures,
        $checks
    );
    if ('RETENTIONGLOBALENABLED' === $key) {
        // The enable flag is read; the other three are read in the
        // constructor's getSetting() array, which this same loop covers.
        continue;
    }
    check(
        "and the runner reads $key",
        false !== strpos($class, "'" . $key . "'"),
        $failures,
        $checks
    );
}
check(
    'the runner reads RETENTIONGLOBALENABLED',
    false !== strpos($class, "getSetting('RETENTIONGLOBALENABLED')"),
    $failures,
    $checks
);

/*
 * The sleep time is the sweep interval, not a poll around a schedule held
 * separately. That is what makes lowering the setting actually raise the
 * catch-up rate, and what makes `systemctl status`, the log and the setting
 * agree. A reintroduced private interval constant would silently decouple
 * them again.
 */
check(
    'the runner holds no second schedule of its own',
    false === strpos($class, 'RETENTION_INTERVAL'),
    $failures,
    $checks
);
check(
    'and takes its cycle from RETENTIONRUNNERSLEEPTIME',
    false !== strpos(
        $class,
        "public static \$sleeptime = 'RETENTIONRUNNERSLEEPTIME';"
    ),
    $failures,
    $checks
);

/*
 * The mode bit, as GIT records it -- which is not the same question as
 * is_executable() above.
 *
 * core.fileMode is false in this repo, so a `chmod +x` made locally is
 * invisible to git: the file goes in 100644 and every clone gets a
 * non-executable init script, while it keeps working perfectly on the box it
 * was written on. That is exactly how FOGFileDeleter shipped unexecutable.
 * `git add` alone does not fix it either -- it wants
 * `git update-index --chmod=+x`.
 *
 * Untracked files are skipped rather than failed: the four files are checked
 * on disk above, and this half can only be asserted once they are staged.
 * tests/alpine-openrc-services.test.sh does the same check for
 * packages/init.d/alpine, but nothing covered packages/service or the other
 * two init trees.
 */
$mustBeExec = [
    'packages/service/' . $name . '/' . $name,
    'packages/init.d/alpine/' . $name,
    'packages/init.d/redhat/' . $name,
    'packages/init.d/ubuntu/' . $name,
];
$index = [];
$out = [];
$rc = 0;
exec(
    'git -C ' . escapeshellarg($root) . ' ls-files -s '
    . implode(' ', array_map('escapeshellarg', $mustBeExec)) . ' 2>/dev/null',
    $out,
    $rc
);
foreach ($out as $line) {
    if (preg_match('#^(\d{6})\s+\S+\s+\d+\s+(.+)$#', $line, $m)) {
        $index[$m[2]] = $m[1];
    }
}
foreach ($mustBeExec as $rel) {
    if (!isset($index[$rel])) {
        // Not tracked yet. Nothing to assert.
        continue;
    }
    check(
        "$rel is committed executable (100755), not 100644",
        '100755' === $index[$rel],
        $failures,
        $checks
    );
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "ok  $checks checks passed\n");
exit(0);
