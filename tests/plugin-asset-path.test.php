<?php
/**
 * The plugin asset URL and the bundled plugin directory are the same place.
 *
 * Plugin JS is fetched by the browser from Plugin::bundledWebRoot(), and read
 * off disk from Plugin::bundledRoot(). Two spellings of one directory, and
 * only the filesystem one is exercised by anything else in the suite -- so a
 * move that updated bundledRoot() and missed the web root would 404 every
 * plugin's JS on every page while every other test stayed green. There is no
 * server-side symptom at all: Page::_setJSFiles() silently drops a path that
 * does not resolve, so the failure is a plugin whose UI quietly does nothing.
 *
 * Three properties:
 *
 *   1. The web root, resolved against the document root (management/), is the
 *      same directory as the filesystem root. This is the coupling.
 *   2. It is relative. FOG is routinely served from an alias (/fog/), so a
 *      leading slash would point outside the install.
 *   3. Hook::injectPluginJS() actually builds its paths from it, rather than
 *      carrying its own copy of the string. Asserted by running the method,
 *      not by grepping for the call -- a grep passes on a call whose result
 *      is discarded.
 *
 * Usage: php tests/plugin-asset-path.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$fails = [];
$check = function ($ok, $what) use (&$fails) {
    if (!$ok) {
        $fails[] = $what;
    }
};

// BASEPATH is what bundledRoot() is relative to; commons/init.php normally
// defines it. Nothing here boots, so set the two constants Plugin needs and
// load the one class -- it is PSR-4, so the path is its namespace tail.
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('BASEPATH')) {
    define('BASEPATH', $web);
}

// Composer's map claims FOG\ -> src/, so one require pulls Plugin and the
// FOGController/FOGBase chain it extends. Loading a class definition runs no
// FOG code, so this needs neither a database nor a booted Initiator.
if (!is_readable($web . '/vendor/autoload.php')) {
    echo "SKIP: no vendor/autoload.php in this tree\n";
    exit(0);
}
require_once $web . '/vendor/autoload.php';

$fsRoot = \FOG\Items\Plugin::bundledRoot();
$webRoot = \FOG\Items\Plugin::bundledWebRoot();

// ------------------------------------------------- 1. same directory

// The web root is relative to management/, which is the directory every page
// is served from and the CWD every other path in the JS list assumes.
$resolved = $web . '/management/' . $webRoot;

// realpath() would return false in a checkout with no lib/plugins (it is
// gitignored -- ADR 0009), so normalize the '..' textually instead. That also
// keeps the test meaningful in CI, where the directory is genuinely absent.
$normalize = function ($path) {
    $out = [];
    foreach (explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $path)) as $part) {
        if ('' === $part || '.' === $part) {
            continue;
        }
        if ('..' === $part) {
            array_pop($out);
            continue;
        }
        $out[] = $part;
    }
    return '/' . implode('/', $out);
};

$check(
    $normalize($resolved) === $normalize($fsRoot),
    sprintf(
        'bundledWebRoot() resolves to %s but bundledRoot() is %s',
        $normalize($resolved),
        $normalize($fsRoot)
    )
);

// ------------------------------------------------- 2. relative, trailing sep

$check(
    strpos($webRoot, '/') !== 0,
    'bundledWebRoot() must not start with "/" -- FOG is served from an alias'
);
$check(
    substr($webRoot, -1) === '/',
    'bundledWebRoot() must end with a separator'
);

// ------------------------------------------------- 3. the hook uses it

// A throwaway Hook subclass, because injectPluginJS() is protected and the
// point is to observe what it appends rather than to read the source.
$hook = new class extends \FOG\Base\Hook {
    public $name = 'AssetPathProbe';
    public $description = 'test';
    public $active = true;
    public $node = 'probeplugin';
    // Hook's constructor reaches for the globals a booted FOG has; nothing
    // here is booted, and the method under test touches none of them.
    public function __construct()
    {
    }
    public function call(&$arguments, array $cases)
    {
        $this->injectPluginJS($arguments, $cases);
    }
};

$GLOBALS['node'] = 'probeplugin';
$GLOBALS['sub'] = '';
$arguments = ['files' => []];
$hook->call($arguments, ['probeplugin' => []]);

$check(
    $arguments['files'] === [$webRoot . 'probeplugin/js/fog.probeplugin.js'],
    'injectPluginJS() did not build its path from bundledWebRoot(); got '
    . json_encode($arguments['files'])
);

// ------------------------------------------------- report

if ([] !== $fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}

echo "ok: plugin asset URL and directory agree (" . $webRoot . ")\n";
exit(0);
