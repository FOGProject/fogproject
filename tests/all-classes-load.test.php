<?php
/**
 * Every class FOG declares must actually load.
 *
 * Sounds tautological. It is not, and the tree has already proved it: a
 * `protected function log()` added to PluginTask collided with the
 * `public static function log()` it inherits from FOGBase, and PHP refuses
 * that at class-declaration time. So PluginTask became unloadable, and
 * because PluginRunner reaches every plugin task through
 * `is_subclass_of($class, 'PluginTask')`, the whole plugin task runner went
 * with it. Nothing caught it: the file lints, php-cs-fixer is happy, every
 * source-scanning test in this directory passes, and the daemon is the only
 * thing that ever loads the class.
 *
 * That is the general shape of what this covers -- errors PHP raises when it
 * *declares* a class, which no amount of reading the file will show you:
 *
 *   - an instance method overriding an inherited static one, or vice versa
 *   - an incompatible signature against an abstract parent or interface
 *   - a missing parent, interface or trait
 *   - two files declaring the same name
 *   - a trait/property conflict
 *
 * Each class is loaded on its own so a failure names the culprit rather than
 * stopping at the first one. A declaration error is a FATAL, uncatchable in
 * process, so the loading runs in a CHILD php and the parent resumes past
 * whatever killed it. That costs one extra process per broken class and none
 * at all when the tree is clean.
 *
 * Usage: php tests/all-classes-load.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

// Child mode. Loads from $argv[2] onward, logging each name BEFORE trying it
// so the parent can tell which one killed the process.
if (isset($argv[1]) && '--load' === $argv[1]) {
    _childLoad((int) $argv[2], $argv[3], $argv[4]);
    exit(0);
}

$classes = _declaredClasses();

if (count($classes) < 200) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($classes) . " class(es) found, expected the "
        . "whole tree. Is this a git checkout?\n"
    );
    exit(1);
}

$listFile = tempnam(sys_get_temp_dir(), 'fog-load-list');
$progressFile = tempnam(sys_get_temp_dir(), 'fog-load-progress');
file_put_contents($listFile, json_encode($classes));
register_shutdown_function(
    function () use ($listFile, $progressFile) {
        @unlink($listFile);
        @unlink($progressFile);
    }
);

$broken = [];
$start = 0;
$total = count($classes);
$guard = 0;

while ($start < $total) {
    // A runaway loop here would fork forever; the tree cannot have more
    // broken classes than it has classes.
    if (++$guard > $total + 1) {
        fwrite(STDERR, "FAIL: resume loop did not converge\n");
        exit(1);
    }
    file_put_contents($progressFile, '');
    $cmd = sprintf(
        '%s %s --load %d %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__FILE__),
        $start,
        escapeshellarg($listFile),
        escapeshellarg($progressFile)
    );
    exec($cmd, $output, $status);
    if (0 === $status) {
        break;
    }
    $progress = trim((string) file_get_contents($progressFile));
    if ('' === $progress) {
        fwrite(
            STDERR,
            "FAIL: child died before reporting progress:\n  "
            . implode("\n  ", array_slice($output, 0, 10)) . "\n"
        );
        exit(1);
    }
    list($index, $name) = explode(' ', $progress, 2);
    $broken[] = [
        'name' => $name,
        'file' => $classes[(int) $index]['file'],
        'why' => _firstError($output)
    ];
    $start = (int) $index + 1;
    $output = [];
}

if (count($broken) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($broken) . " class(es) do not load:\n");
    foreach ($broken as $b) {
        fwrite(STDERR, sprintf("  %s (%s)\n      %s\n", $b['name'], $b['file'], $b['why']));
    }
    exit(1);
}

printf("ok: all %d declared class(es) load\n", $total);
exit(0);

/**
 * Load every class from $start onward, in this process.
 *
 * @param int    $start        index to resume at
 * @param string $listFile     JSON list of ['name' => , 'file' => ]
 * @param string $progressFile written before each attempt
 *
 * @return void
 */
function _childLoad($start, $listFile, $progressFile)
{
    $classes = json_decode((string) file_get_contents($listFile), true);

    $tmp = sys_get_temp_dir() . '/fog-load-test-' . getmypid();
    foreach (['cache', 'log', 'plugins'] as $sub) {
        @mkdir($tmp . '/' . $sub, 0700, true);
    }
    // The child is resumed after every fatal, so one run of this test forks
    // several of these and each got a directory named after a pid that is
    // never reused. Left behind they accumulate one tree per run, forever --
    // and sys_get_temp_dir() is a tmpfs on a normal Linux box, so the leak is
    // resident memory rather than disk. Registered before init.php loads
    // anything, because a declaration error is fatal and shutdown functions
    // are the only cleanup that still runs on that path.
    register_shutdown_function(
        function () use ($tmp) {
            if (!is_dir($tmp)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $tmp,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($tmp);
        }
    );
    define('FOG_CACHE_DIR', $tmp . '/cache');
    define('FOG_LOG_DIR', $tmp . '/log');
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');

    require_once dirname(__DIR__) . '/packages/web/commons/init.php';
    new Initiator();

    for ($i = $start, $n = count($classes); $i < $n; $i++) {
        $name = $classes[$i]['name'];
        file_put_contents($progressFile, $i . ' ' . $name);
        // Autoload on. A declaration error is fatal and takes the process;
        // that is what the parent's resume loop is for.
        if (!class_exists($name)
            && !interface_exists($name)
            && !trait_exists($name)
        ) {
            fwrite(STDERR, "not declared by its own file: $name\n");
            exit(1);
        }
    }
}

/**
 * The first line of child output that reads like the reason it died.
 *
 * @param array $output child stdout+stderr
 *
 * @return string
 */
function _firstError(array $output)
{
    foreach ($output as $line) {
        if (false !== stripos($line, 'error')
            || false !== stripos($line, 'not declared')
        ) {
            return trim($line);
        }
    }
    return trim((string) end($output));
}

/**
 * Every class/interface/trait declared by a tracked, non-vendored file.
 *
 * @return array of ['name' => string, 'file' => string]
 */
function _declaredClasses()
{
    $files = array_filter(
        explode("\n", (string) shell_exec('git ls-files "*.php"')),
        function ($f) {
            return '' !== $f
                && is_readable($f)
                && 0 !== strpos($f, 'packages/web/vendor/')
                && 0 !== strpos($f, 'tests/')
                // Analysis tooling, not product. build/ now holds only
                // constants.stub.php, which PHPStan loads as a bootstrap
                // file and which declares no class at all -- FOG's own
                // autoloader has nothing to find there and is not meant to.
                && 0 !== strpos($f, 'build/');
        }
    );

    $out = [];
    foreach ($files as $file) {
        $tokens = token_get_all(file_get_contents($file));
        $count = count($tokens);
        // The namespace the file declares, so the name collected below is the
        // one the file actually produces. Core no longer re-exports itself
        // globally (ADR 0013 §2), so a bare `Host` collected from
        // src/Items/Host.php would be a class this tree does not declare and
        // the check would fail for all 202 of them -- while a genuinely broken
        // file would be indistinguishable from the rest.
        $ns = '';
        if (preg_match('/^\s*namespace\s+([^;{]+)/m', file_get_contents($file), $nm)) {
            $ns = trim($nm[1]) . '\\';
        }
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])
                || !in_array(
                    $tokens[$i][0],
                    [T_CLASS, T_INTERFACE, T_TRAIT],
                    true
                )
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
                $out[] = ['name' => $ns . $tokens[$j][1], 'file' => $file];
            }
        }
    }
    return $out;
}
