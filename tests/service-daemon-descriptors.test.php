<?php
/**
 * The daemon descriptors still say what the four daemons used to say.
 *
 * ImageReplicator/SnapinReplicator were ~90% the same file and now share
 * FOGReplicator; ImageSize/SnapinHash likewise now share FOGItemScanner. The sequence moving into a base is safe; what is not
 * safe is the configuration that came with it, because it is now a table of
 * bare strings and a transcription error in one of them is silent:
 *
 *   - a wrong settings prefix reads as "unset" and the daemon falls back to
 *     a default, so it runs on the wrong tty writing the wrong log and
 *     ignores the admin's GLOBALENABLED switch entirely;
 *   - a wrong route or association field asks the API for the wrong class
 *     and replicates nothing, with no error;
 *   - a wrong default tty or log filename only shows up when someone goes
 *     looking for output that is not where it has been for a decade.
 *
 * Every expectation below was taken from the pre-refactor files (commit
 * b25193faf: imagereplicator.class.php and snapinreplicator.class.php as
 * they stood before FOGReplicator existed), so this is a characterization
 * test in the proper sense -- it describes what shipped, not what the new
 * code happens to do.
 *
 * The message assertions are the other half. They are checked as LITERAL
 * _() calls in the source, because gettext extracts msgids from source
 * text: the moment someone "tidies" these into _("There are no $noun
 * available!") the string stops being translated and stops appearing in
 * the .pot, silently and permanently. That is a live bug class in this
 * repo, not a hypothetical.
 *
 * Usage: php tests/service-daemon-descriptors.test.php
 * Exit status 0 = pass, 1 = fail.
 */

namespace FOG;

if (!function_exists('_')) {
    /**
     * Stands in for ext-gettext when it is absent.
     *
     * @param string $msgid The message.
     *
     * @return string
     */
    function _($msgid)
    {
        return $msgid;
    }
}

/**
 * Stand-in for the real base.
 *
 * FOGService reaches the database on construction and the descriptors do
 * not, so the chain is cut here and the tables are read directly.
 */
abstract class FOGService
{
    public static $sleeptime = '';
    public static $log = '';
    public static $dev = '';
    public static $zzz = 0;
}

$svcdir = dirname(__DIR__) . '/packages/web/src/Service';
require_once $svcdir . '/FOGReplicator.php';
require_once $svcdir . '/ImageReplicator.php';
require_once $svcdir . '/SnapinReplicator.php';
require_once $svcdir . '/FOGItemScanner.php';
require_once $svcdir . '/ImageSize.php';
require_once $svcdir . '/SnapinHash.php';

$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $label What is being asserted.
 * @param mixed  $got   The observed value.
 * @param mixed  $want  The required value.
 *
 * @return void
 */
function is($label, $got, $want)
{
    global $failures, $checks;
    $checks++;
    if ($got !== $want) {
        $failures[] = sprintf(
            '%s (got %s, wanted %s)',
            $label,
            var_export($got, true),
            var_export($want, true)
        );
    }
}

/**
 * Records one boolean assertion.
 *
 * @param string $label What is being asserted.
 * @param bool   $cond  Whether it held.
 *
 * @return void
 */
function check($label, $cond)
{
    global $failures, $checks;
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

/**
 * Reads a class' protected descriptor.
 *
 * @param string $class The class name.
 *
 * @return array
 */
function descriptorOf($class)
{
    $ref = new \ReflectionClass($class);
    $obj = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('descriptor');
    $method->setAccessible(true);
    return (array)$method->invoke($obj);
}

// --- the tables, as they stood before the base existed ---------------------

$expected = [
    'FOG\ImageReplicator' => [
        'sleeptime' => 'IMAGEREPSLEEPTIME',
        'prefix' => 'IMAGEREPLICATOR',
        'log' => 'fogreplicator.log',
        'dev' => '/dev/tty1',
        'route' => 'image',
        'assocRoute' => 'imageassociation',
        'assocField' => 'imageID',
        'model' => 'Image',
        'extraPaths' => ['postdownloadscripts', 'dev/postinitscripts'],
        'msg' => [
            'disabled' => ' * Image replication is globally disabled',
            'starting' => 'Starting Image Replication',
            'kind' => 'image replication',
            'none' => 'There are no images available!',
            'associate' => 'images to a storage group',
            'notSyncing' => 'Not syncing Image'
        ]
    ],
    'FOG\SnapinReplicator' => [
        'sleeptime' => 'SNAPINREPSLEEPTIME',
        'prefix' => 'SNAPINREPLICATOR',
        'log' => 'fogsnapinrep.log',
        'dev' => '/dev/tty4',
        'route' => 'snapin',
        'assocRoute' => 'snapingroupassociation',
        'assocField' => 'snapinID',
        'model' => 'Snapin',
        'extraPaths' => ['ssl/CA'],
        'msg' => [
            'disabled' => ' * Snapin replication is globally disabled',
            'starting' => 'Starting Snapin Replication',
            'kind' => 'snapin replication',
            'none' => 'There are no snapins available!',
            'associate' => 'snapins to a storage group',
            'notSyncing' => 'Not syncing Snapin'
        ]
    ],
    'FOG\ImageSize' => [
        'sleeptime' => 'IMAGESIZESLEEPTIME',
        'prefix' => 'IMAGESIZE',
        'log' => 'fogimagesize.log',
        'dev' => '/dev/tty3',
        'zzz' => 3600,
        'route' => 'image',
        'assocRoute' => 'imageassociation',
        'assocField' => 'imageID',
        'model' => 'Image',
        'nodePathField' => 'path',
        'itemFileField' => 'path',
        'msg' => [
            'disabled' => ' * Image size is globally disabled',
            'starting' => 'Starting Image Size Service',
            'finding' => 'Finding any images associated',
            'none' => 'No images associated with this group as master',
            'plural' => 'images',
            'singular' => 'image',
            'tail' => 'to update size values as needed',
            'trying' => 'Trying image size for',
            'getting' => 'Getting image size for'
        ]
    ],
    'FOG\SnapinHash' => [
        'sleeptime' => 'SNAPINHASHSLEEPTIME',
        'prefix' => 'SNAPINHASH',
        'log' => 'fogsnapinhash.log',
        'dev' => '/dev/tty6',
        'zzz' => 1800,
        'route' => 'snapin',
        'assocRoute' => 'snapingroupassociation',
        'assocField' => 'snapinID',
        'model' => 'Snapin',
        'nodePathField' => 'snapinpath',
        'itemFileField' => 'file',
        'msg' => [
            'disabled' => ' * Snapin hash is globally disabled',
            'starting' => 'Starting Snapin Hashing Service',
            'finding' => 'Finding any snapins associated',
            'none' => 'No snapins associated with this group as master',
            'plural' => 'snapins',
            'singular' => 'snapin',
            'tail' => 'to update hash values as needed',
            'trying' => 'Trying Snapin hash for',
            'getting' => 'Getting snapin hash and size for'
        ]
    ]
];

foreach ($expected as $class => $want) {
    $short = substr($class, strrpos($class, '\\') + 1);
    $desc = descriptorOf($class);

    is($short . ' reads its sleeptime from the same setting',
        $class::$sleeptime, $want['sleeptime']);

    foreach ($want as $key => $value) {
        if ('sleeptime' === $key || 'msg' === $key) {
            continue;
        }
        is(sprintf('%s %s', $short, $key), $desc[$key] ?? null, $value);
    }
    foreach ($want['msg'] as $key => $text) {
        is(sprintf('%s msg.%s', $short, $key), $desc['msg'][$key] ?? null, $text);
    }
}

// --- every key the base reads is present in every descriptor ---------------
//
// _d() throws on a missing key rather than returning null, which is the
// right failure but only fires on the code path that needed it -- and the
// replication path needs a database. Reading the base's own _d() calls out
// of its source and demanding each key exist covers all of them at once,
// and keeps covering a key added later.

$bases = [
    'FOG\ImageReplicator' => 'FOGReplicator',
    'FOG\SnapinReplicator' => 'FOGReplicator',
    'FOG\ImageSize' => 'FOGItemScanner',
    'FOG\SnapinHash' => 'FOGItemScanner'
];
$baseSrc = '';
foreach (array_unique($bases) as $file) {
    $src = file_get_contents($svcdir . '/' . $file . '.php');
    $baseSrc .= $src;
    preg_match_all(
        "/(?:\\\$this->_?d)\('([a-zA-Z]+)'(?:,\s*'([a-zA-Z]+)')?\)/",
        $src,
        $found,
        PREG_SET_ORDER
    );
    check($file . ' actually reads its descriptor', count($found) > 5);
}
foreach ($expected as $class => $want) {
    $short = substr($class, strrpos($class, '\\') + 1);
    $desc = descriptorOf($class);
    preg_match_all(
        "/(?:\\\$this->_?d)\('([a-zA-Z]+)'(?:,\s*'([a-zA-Z]+)')?\)/",
        file_get_contents($svcdir . '/' . $bases[$class] . '.php'),
        $m,
        PREG_SET_ORDER
    );
    foreach ($m as $use) {
        $key = $use[1];
        $sub = $use[2] ?? '';
        if ('' === $sub) {
            check(
                sprintf('%s supplies "%s", which the base reads', $short, $key),
                array_key_exists($key, $desc)
            );
            continue;
        }
        check(
            sprintf('%s supplies "%s.%s", which the base reads', $short, $key, $sub),
            array_key_exists($key, $desc)
            && array_key_exists($sub, (array)$desc[$key])
        );
    }
}

// --- the msgids stay literal ----------------------------------------------

foreach (['ImageReplicator', 'SnapinReplicator', 'ImageSize', 'SnapinHash'] as $file) {
    $src = file_get_contents($svcdir . '/' . $file . '.php');
    $short = $file;
    check(
        $short . ' builds no msgid from a variable',
        !preg_match('/_\(\s*[^\'")]*\$/', $src)
        && !preg_match('/_\(\s*sprintf/', $src)
        && !preg_match('/_\([^)]*\.\s*\$/', $src)
    );
    check(
        $short . ' still carries its messages as literal _() calls',
        substr_count($src, "_('") >= 6
    );
}

// The base must not translate a string it was handed: msgDisabled() and the
// rest arrive already translated, and _($alreadyTranslated) is the runtime
// msgid trap wearing a different hat -- it looks up the TRANSLATION as a
// msgid, misses, and is a no-op that only ever confuses whoever reads it.
check(
    'the base does not re-translate a message it was given',
    false === strpos($baseSrc, '_($e->getMessage())')
    && !preg_match('/_\(\s*\$this->_?d\(/', $baseSrc)
);

// --- report ----------------------------------------------------------------

if (count($failures)) {
    fwrite(STDERR, sprintf("FAIL (%d of %d)\n", count($failures), $checks));
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    exit(1);
}

printf("ok  %d checks passed\n", $checks);
exit(0);
