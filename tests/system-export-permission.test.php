<?php
/**
 * The whole-database dump takes its own permission, and leaves no
 * predictable file behind while it runs.
 *
 * Three routes hand over every row of all 70 tables -- GET /system/export,
 * the Export button on FOG Configuration, and maintenance/backup_db.php,
 * which the installer calls before an upgrade. That means users.uPass and
 * uAPIToken, apiTokens.atHash, userAuths' hashes, every host's hostSecToken
 * and hostADPass, nfsGroupMembers' credentials, and every globalSettings
 * value including FOG_NODE_API_KEY and the LDAP bind password.
 *
 * The first two used to resolve to settings.edit, which SIX page nodes
 * already map onto -- so "may edit the OUI table" and "may take a copy of
 * every secret in the estate" were the same grant. It matters more here
 * than anywhere else in the API, because this is the one route that
 * bypasses the data protection the rest of the router applies:
 * unfilterableFields() refuses token/password filters,
 * maskSensitiveSetting() strips values by name, and Route::$sensitiveSettings
 * says of FOG_NODE_API_KEY that "it is a shared HMAC secret, so it must not
 * be readable over REST". The dump returns it in the clear.
 *
 * The permission half is EXECUTED, not grepped: resolveApiPermission() is
 * the same call the router makes, so a map entry edited back, or a registry
 * node dropped, fails here. A grep for the string would pass on a route
 * rewired around it.
 *
 * The temp-file half cannot be executed without a database, so it is
 * asserted as the absence of the dangerous literal anywhere under
 * packages/web. A fixed name in the system temp dir was three defects at
 * once: the dump is world-readable under the default umask for as long as
 * it takes to write, two concurrent exports clobber each other, and
 * fopen() follows symlinks, so a guessable path is a write-as-web-user
 * primitive. That third path was found by this test, not by the issue.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/1410
 *
 * No FOG boot: the composer autoloader is enough, because every call below
 * reads constant tables. Same DB-free stance as
 * permission-actions-declared.test.php, one step lighter.
 *
 * Usage: php tests/system-export-permission.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$autoload = $webroot . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    fwrite(STDERR, "FAIL: cannot read $autoload\n");
    exit(1);
}
require_once $autoload;

$results = [];

/**
 * Record one assertion and echo its result.
 *
 * Named uniquely rather than check(): tests/ is analyzed as one program by
 * phpstan-tests.neon, and a global check() collides with the dozens of
 * other files that declare their own.
 *
 * @param string $label what is being asserted
 * @param bool   $ok    whether it held
 * @param string $extra detail printed on failure
 *
 * @return bool the value of $ok, for the caller to collect
 */
function exportCheck($label, $ok, $extra = '')
{
    $suffix = ('' !== $extra ? " ($extra)" : '');
    echo ($ok ? 'ok   - ' : 'FAIL - ') . $label . ($ok ? '' : $suffix) . "\n";
    return $ok;
}

/*
 * 1. The router's own resolution, executed.
 */
$resolved = FOG\Auth\Authorization::resolveApiPermission('export');
$results[] = exportCheck(
    'GET /system/export resolves to system.export',
    'system.export' === $resolved,
    'got ' . var_export($resolved, true)
);
$results[] = exportCheck(
    'it is not back on settings.edit, which six page nodes share',
    'settings.edit' !== $resolved,
    'got ' . var_export($resolved, true)
);

/*
 * 2. The permission has to be grantable, or the only holder is '*' forever
 *    and assertCanGrant() refuses to delegate it.
 */
$registry = FOG\Auth\Authorization::coreRegistry();
$results[] = exportCheck(
    "the registry declares a 'system' node",
    array_key_exists('system', $registry),
    'nodes: ' . implode(',', array_keys($registry))
);
$results[] = exportCheck(
    "'system' offers the export action",
    in_array('export', (array) ($registry['system'] ?? []), true),
    'actions: ' . implode(',', (array) ($registry['system'] ?? []))
);

/*
 * 3. No predictable dump path, on any of the three export routes.
 *
 * Matches the literal shape rather than one filename, so reintroducing it
 * under any name is still a failure. tempnam() is what all three use
 * instead; it creates the file 0600 and unguessably named.
 */
$offenders = [];
$it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($webroot, \FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    $path = $f->getPathname();
    if (substr($path, -4) !== '.php' || false !== strpos($path, '/vendor/')) {
        continue;
    }
    $body = (string) file_get_contents($path);
    if (preg_match("#'/tmp/' *\\.|'/tmp/[A-Za-z0-9_.-]*'#", $body, $m)) {
        $offenders[] = str_replace($root . '/', '', $path) . ': ' . $m[0];
    }
}
$results[] = exportCheck(
    'no export path builds a dump filename under a fixed /tmp name',
    [] === $offenders,
    implode(' | ', array_slice($offenders, 0, 4))
);

/*
 * 4. The UI export is the same authority and takes the same gate. It is
 *    checked inside configPost() rather than by the node/sub map because
 *    that one POST endpoint serves both export and import, and `about`
 *    aliases onto `settings` -- so the map would put both on settings.edit.
 */
$src = (string) file_get_contents(
    $webroot . '/src/Pages/FOGConfigurationPage.php'
);
$results[] = exportCheck(
    'the UI export branch checks system.export before dumping',
    1 === preg_match(
        "#isset\\(\\\$_POST\\['toExport'\\]\\).*?"
        . "Authorization::can\\('system\\.export'\\).*?"
        . "Mysqldump#s",
        $src
    ),
    'the guard is missing, or no longer precedes the dump'
);

/*
 * 5. The document describes what the server sends. Source-level for the
 *    same reason openapi-route-coverage.test.php is: building the document
 *    needs a boot this test deliberately does not do.
 */
$openapi = (string) file_get_contents($webroot . '/src/Router/OpenAPI.php');
$exportBlock = '';
if (preg_match("#'/system/export' => \\[.*?\\n            \\],#s", $openapi, $m)) {
    $exportBlock = $m[0];
}
$results[] = exportCheck(
    'the /system/export block was located in the OpenAPI source',
    '' !== $exportBlock
);
$results[] = exportCheck(
    'the document declares text/plain, which is what exportdb() sends',
    false !== strpos($exportBlock, "'text/plain' => ["),
    'block did not name text/plain'
);
$results[] = exportCheck(
    'and no longer declares application/sql, which it never sent',
    false === strpos($exportBlock, "'application/sql' => ["),
    'application/sql is still declared'
);

$failed = count(
    array_filter(
        $results,
        function ($ok) {
            return !$ok;
        }
    )
);

echo "\n";
if ($failed > 0) {
    echo "$failed of " . count($results) . " check(s) failed\n";
    exit(1);
}
echo 'All ' . count($results) . " checks passed\n";
exit(0);
