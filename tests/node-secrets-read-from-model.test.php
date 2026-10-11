<?php
/**
 * Server-side code reads a storage node's password from the model, never
 * from a Route payload.
 *
 * Route::stripNodeSecrets() removes `pass` and `key` from every storagenode
 * payload Route builds. That includes the in-process ones: Route::listem()
 * and Route::indiv() serve the replicators and Image::deleteFile() exactly
 * as they serve the REST API. So `$StorageNode->pass` on a json_decode()d
 * payload is always undefined. PHP only warns, the FTP login goes out with
 * an empty password, and the node refuses it.
 *
 * Observed in the field on 1.5.10.2482: image and snapin replication failed
 * on every node with "FTP login rejected by <node> for user fogproject",
 * after 'Undefined property: stdClass::$pass' in fogservice.class.php.
 *
 * The gate: no property read of `->pass` on anything but $this, and none
 * of `->key` on a node variable, in packages/web. A payload has no other
 * way to carry them, so any such read is a read of a field that is not
 * there.
 *
 * Usage: php tests/node-secrets-read-from-model.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = __DIR__ . '/../packages/web';
$fails = [];

// The premise: the strip is still in place. If it goes, this test's reason
// goes with it, and whoever removes it should see this file.
$route = file_get_contents($root . '/lib/router/route.class.php');
if (false === $route
    || !preg_match("/unset\\(\\\$data\\['pass'\\], \\\$data\\['key'\\]\\);/", $route)
) {
    $fails[] = 'Route::stripNodeSecrets() no longer strips pass and key;'
        . ' revisit this test';
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $lines = file($file->getPathname());
    foreach ($lines as $n => $line) {
        // ->pass on any payload; ->key only on a node, because other
        // classes (capone) carry a legitimate `key` field. A property read,
        // not a method call like ->key($k).
        if (preg_match('/\$(?!this\b)\w+->pass\b(?!\s*\()/', $line)
            || preg_match('/\$\w*[Nn]ode\w*->key\b(?!\s*\()/', $line)
        ) {
            $fails[] = sprintf(
                '%s:%d reads a stripped node secret off a payload: %s',
                substr($file->getPathname(), strlen($root) + 1),
                $n + 1,
                trim($line)
            );
        }
    }
}

if ($fails) {
    fwrite(STDERR, "FAIL\n  " . implode("\n  ", $fails) . "\n");
    exit(1);
}
echo "PASS: no storage node secret is read off a Route payload\n";
exit(0);
