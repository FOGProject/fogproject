<?php
/**
 * Every class a browser-less entry point instantiates by bare name must exist.
 *
 * The files under packages/web/service/ and packages/web/api/ are the entry
 * points FOS, the fog-client and iPXE hit. They boot FOG and then do exactly
 * one thing: `new SomeClass(...)`, using the BARE global name that each core
 * class re-exports through the class_alias at the foot of its file (ADR 0013).
 *
 * Nothing else references most of them, so a renamed or deleted class leaves
 * the entry point pointing at a name that no longer exists -- and PHP does not
 * notice until the request arrives, at which point it is a fatal error on a
 * machine-facing endpoint with no browser to show it. Two live examples:
 *
 *   - The IpxeBootMenu rename. `service/ipxe/boot.php` was the only production
 *     call site, and reverting it to the old name left the ENTIRE suite green.
 *     That is the hole this file closes.
 *   - KNOWN_MISSING below: four endpoints orphaned by 565caa40c "Remove legacy
 *     client stuff", which deleted the classes and left the callers behind.
 *     Every request to them has been an instant fatal ever since.
 *
 * Static: it parses sources and reads Composer's classmap. No DB, no server,
 * and no autoloading -- loading a core class for real needs the whole FOG
 * runtime, which is what tests/lib/bootmenu-harness.php exists to fake.
 */
$root = dirname(__DIR__);
$web  = $root . '/packages/web';
$pass = 0;
$fail = 0;

function ok(string $m): void
{
    global $pass;
    $pass++;
    echo "  ok    $m\n";
}

function bad(string $m): void
{
    global $fail;
    $fail++;
    echo "  FAIL  $m\n";
}

/**
 * Names that are not FOG classes. `Exception` and friends resolve from the
 * global namespace at runtime; they are not ours to place.
 */
const GLOBALS_OK = ['Exception', 'DateTime', 'DateTimeZone', 'PDO', 'stdClass'];

/**
 * Provided by a bundled plugin, so absent from a plugins-free checkout -- which
 * is what CI runs, because packages/web/lib/plugins is fetched rather than
 * tracked. Listed explicitly, with the plugin, so that an entry point taking a
 * NEW plugin dependency is a deliberate edit here rather than a silent pass.
 */
const PLUGIN_PROVIDED = [
    'CaponeTasking' => 'capone',
];

/**
 * Entry points that reference a class this tree does not contain.
 *
 * These four are orphans of 565caa40c "Remove legacy client stuff": the commit
 * removed UserCleaner, UpdateClient, GF and ALOBG and left their callers in
 * place, so each of these endpoints has been a guaranteed fatal error on every
 * request since. They are exempted rather than fixed because deciding between
 * deleting the endpoint and restoring the class is not this test's call.
 *
 * THIS LIST MUST ONLY EVER SHRINK. An addition means a live endpoint was just
 * broken; fix the endpoint instead.
 */
const KNOWN_MISSING = [
    'service/usercleanup-users.php' => 'UserCleaner',
    'service/updates.php'           => 'UpdateClient',
    'service/greenfog.php'          => 'GF',
    'service/alo-bg.php'            => 'ALOBG',
];

$classmapFile = $web . '/vendor/composer/autoload_classmap.php';
if (!is_file($classmapFile)) {
    echo "FAIL: no composer classmap at $classmapFile -- run composer dump-autoload\n";
    exit(1);
}
$classmap = require $classmapFile;

echo "1. every bare `new X(` in a service/api entry point names a real class\n";

$files = [];
foreach ([$web . '/service', $web . '/api'] as $dir) {
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
}
sort($files);

$seen = 0;
foreach ($files as $file) {
    $rel = ltrim(str_replace($web, '', $file), '/');
    $src = (string)file_get_contents($file);
    // Bare `new Foo(` only. A leading \ or FOG\ is already unambiguous and a
    // lowercase name is a variable, not a class.
    if (!preg_match_all('/(?<![\\\\\w$])new\s+([A-Z][A-Za-z0-9_]*)\s*\(/', $src, $m)) {
        continue;
    }
    foreach (array_unique($m[1]) as $name) {
        $seen++;
        if (in_array($name, GLOBALS_OK, true)) {
            continue;
        }
        if (isset(PLUGIN_PROVIDED[$name])) {
            ok("$rel -> $name (from the " . PLUGIN_PROVIDED[$name] . ' plugin)');
            continue;
        }
        if (isset(KNOWN_MISSING[$rel]) && KNOWN_MISSING[$rel] === $name) {
            ok("$rel -> $name (known missing, 565caa40c -- endpoint is dead)");
            continue;
        }
        if (isset($classmap['FOG\\' . $name])) {
            ok("$rel -> $name");
            continue;
        }
        bad("$rel instantiates $name, which no class in src/ declares");
    }
}

if ($seen === 0) {
    bad('no `new X(` found in any entry point -- the scanner matched nothing');
}

echo "\n2. the known-missing list has not grown\n";
foreach (KNOWN_MISSING as $rel => $name) {
    if (!is_file($web . '/' . $rel)) {
        ok("$rel is gone, so its exemption can go too");
        continue;
    }
    if (isset($classmap['FOG\\' . $name])) {
        bad("$rel: $name exists again -- drop it from KNOWN_MISSING");
        continue;
    }
    ok("$rel still exempt for $name");
}

echo "\n";
if ($fail > 0) {
    echo "FAIL ($fail of " . ($pass + $fail) . " assertions)\n";
    exit(1);
}
echo "PASS ($pass assertions)\n";
