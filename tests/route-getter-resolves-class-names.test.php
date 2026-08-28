<?php
/**
 * Route::getter() must resolve the bare class name before its type guard.
 *
 * getter() opens with a type assertion:
 *
 *     $fqcn = self::qualify($classname);
 *     if (!$class instanceof $fqcn) { return; }
 *
 * $classname is the BARE lowercase name ('storagenode') that the whole router
 * speaks, while the entity is FOG\Items\StorageNode. A class name held in a
 * string resolves from the GLOBAL namespace -- `use` is not consulted -- so
 * without the qualify() the test is false for every core class.
 *
 * That is not hypothetical. The guard was written in 2021 and worked only
 * because each file under src/ re-exported itself globally with class_alias().
 * Retiring those aliases (ADR 0013 s2) missed this one site, and from that
 * commit getter() returned null for EVERY class. indiv() assigns its result
 * straight to self::$data, so `GET /api/<class>/<id>` answered HTTP 200 with a
 * literal `null` body for every entity in the API -- and Route::getItem(),
 * embed() and expandRelations() went with it, which is why the storage node
 * panel on the About page rendered empty.
 *
 * It is silent in the worst way: null out of a serializer is indistinguishable
 * from a serializer that ran and found nothing, so nothing logs, nothing 500s,
 * and the status code stays 200.
 *
 * TWO ARMS, deliberately. Checking only that a matching object survives would
 * still pass if the guard were deleted outright, which would let getter() run
 * on an object of the wrong class:
 *
 *   A. matching   -- getter(<bare>, new <FQCN>) must NOT return null.
 *   B. mismatched -- getter(<other bare>, new <FQCN>) MUST return null.
 *
 * Arm A goes red if the qualify() is dropped; arm B goes red if the guard is
 * removed. Both were confirmed by mutation before this file was committed.
 *
 * A Throwable counts as passing arm A. This harness deliberately boots no
 * database -- it defines the handful of constants the autoloader needs and
 * nothing else -- so an entity that gets PAST the guard dies reaching for one.
 * What matters is that it got past; the guard is the only thing above the
 * switch that can return null.
 *
 * Usage: php tests/route-getter-resolves-class-names.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';

if (!is_file($web . '/vendor/autoload.php')) {
    fwrite(STDERR, "FAIL: composer dependencies are not installed\n");
    exit(1);
}

// The autoloader reads these three and nothing else on this path. FOG_CACHE_DIR
// only has to be writable -- srcFileList() memoises its scan there.
define('DS', DIRECTORY_SEPARATOR);
define('BASEPATH', $web . DS);
define('FOG_CACHE_DIR', sys_get_temp_dir());

require $web . '/vendor/autoload.php';
require_once $web . '/commons/init.php';

/**
 * 'NULL' when getter() short-circuited, 'past' when it did not.
 *
 * @param string $bare the bare class name to hand getter()
 * @param object $obj  the entity to serialize
 *
 * @return string
 */
function getterOutcome($bare, $obj)
{
    try {
        $got = \FOG\Router\Route::getter($bare, $obj);
    } catch (\Throwable $e) {
        return 'past';
    }
    return null === $got ? 'NULL' : 'past';
}

// One entity per shape of getter() case: a `default` arm, an arm with embeds,
// and one of the tasking arms. Constructed WITHOUT an id, so nothing loads.
$sample = ['image', 'user', 'host', 'storagenode', 'snapin'];

$fails = [];
$objects = [];

foreach ($sample as $bare) {
    $fqcn = \FOG\Base\FOGBase::qualify($bare);
    if ($fqcn === $bare || !class_exists($fqcn)) {
        $fails[] = sprintf(
            'qualify(%s) did not resolve to a class under src/ (got %s) -- '
            . 'the class map is not being read, so this gate proves nothing',
            $bare,
            $fqcn
        );
        continue;
    }
    $objects[$bare] = new $fqcn();
}

if (count($objects) < 2) {
    fwrite(STDERR, "FAIL: too few entities resolved to test both arms\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

// Arm A -- a matching name must get past the guard.
foreach ($objects as $bare => $obj) {
    if ('NULL' === getterOutcome($bare, $obj)) {
        $fails[] = sprintf(
            'getter(%s, %s) returned null. The guard is testing the bare name '
            . 'against the global namespace, so it can never match -- every '
            . 'single-entity GET serializes to `null`.',
            $bare,
            get_class($obj)
        );
    }
}

// Arm B -- a mismatched name must still be rejected.
$names = array_keys($objects);
foreach ($names as $i => $bare) {
    $other = $names[($i + 1) % count($names)];
    if ('NULL' !== getterOutcome($other, $objects[$bare])) {
        $fails[] = sprintf(
            'getter(%s, %s) did not return null. The type guard is gone, so '
            . 'getter() will serialize an object of the wrong class.',
            $other,
            get_class($objects[$bare])
        );
    }
}

if ($fails) {
    fwrite(STDERR, "FAIL: Route::getter() type guard is wrong\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo 'PASS: Route::getter() resolves bare class names (' . count($objects)
    . " classes, both arms)\n";
exit(0);
