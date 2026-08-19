<?php
/**
 * What the hook and event managers actually do today.
 *
 * The hook system is the plugin ABI: every bundled plugin and every plugin we
 * have never read registers through EventManager::register() and is dispatched
 * through HookManager::processEvent(). So the bar for changing it is not "the
 * tests pass", it is "no plugin needs editing" -- and the only way to hold that
 * bar is to write down what happens now, including the parts that are wrong, so
 * that a behaviour change shows up as a case somebody had to edit on purpose
 * rather than as a silent difference.
 *
 * Findings pinned here are recorded as F-11..F-26 in docs/refactor-facts.md and
 * argued in docs/hook-event-plan.md.
 *
 * No database. The managers and the fixture listeners are built with
 * newInstanceWithoutConstructor() -- Event::__construct() dereferences
 * self::$FOGUser, which no test has -- and HookManager::$knownEvents is seeded
 * by reflection so processEvent() never asks the hookevent table whether it has
 * seen the name before.
 *
 * Usage: php tests/hook-event-contract.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$tmp = sys_get_temp_dir() . '/fog-hook-contract-' . getmypid();
@mkdir($tmp . '/cache', 0777, true);
@mkdir($tmp . '/log', 0777, true);
@mkdir($tmp . '/plugins/demo/hooks', 0777, true);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');

ini_set('error_log', $tmp . '/php-error.log');

require $web . '/commons/init.php';
new Initiator();

$fails = [];

/**
 * A hook that records what it was handed, so dispatch can be observed.
 */
class CharHook extends \FOG\Hook
{
    public $name = 'CharHook';
    public $node = 'demo';
    public $active = true;
    public $seen = [];

    public function fire($arguments)
    {
        $this->seen[] = $arguments;
    }
}

/**
 * An event listener that records the event it was notified of.
 */
class CharEvent extends \FOG\Event
{
    public $name = 'CharEvent';
    public $active = true;
    public $seen = [];

    public function onEvent($event, $data)
    {
        $this->seen[] = $event;
    }
}

/**
 * Builds an object without running any constructor in its chain.
 *
 * @param string $class Fully qualified class name.
 *
 * @return object
 */
$bare = function ($class) {
    return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
};

/**
 * Registers a listener and reports what came back out.
 *
 * Returns the class of whatever escaped register(), or '' when it returned
 * normally. Today that distinction is the whole point: some failures are
 * swallowed and logged, and some take the process with them.
 *
 * @param object $manager  The manager to register against.
 * @param string $event    Event name.
 * @param mixed  $listener The listener, in whatever shape is being tested.
 *
 * @return string
 */
$escapes = function ($manager, $event, $listener) {
    try {
        $manager->register($event, $listener);
    } catch (\Throwable $t) {
        return get_class($t);
    }
    return '';
};

/**
 * Captures whatever register() logs for a failure.
 *
 * FOGBase::log() echoes when the manager's logLevel reaches the message level,
 * and logHistory() returns before touching the database when there is no valid
 * user -- which is every test. So raising logLevel is enough to read the one
 * diagnostic a swallowed registration failure produces.
 *
 * @param object $manager  The manager to register against.
 * @param string $event    Event name.
 * @param mixed  $listener The listener.
 *
 * @return string
 */
$logged = function ($manager, $event, $listener) {
    $was = $manager->logLevel;
    $manager->logLevel = 9;
    ob_start();
    try {
        $manager->register($event, $listener);
    } catch (\Throwable $t) {
        // The fatal cases are asserted separately; here we only want the text.
    }
    $out = ob_get_clean();
    $manager->logLevel = $was;
    return $out;
};

// ------------------------------------------------- registering against hooks

$hm = $bare('FOG\HookManager');
$hook = $bare('CharHook');

if ('' !== $escapes($hm, 'CHAR_OK', [$hook, 'fire'])) {
    $fails[] = 'the supported listener shape [Hook, method] no longer registers';
}
if (!$hm->hasListeners('CHAR_OK')) {
    $fails[] = 'hasListeners() does not see a listener that register() accepted';
}

// A pair whose first element is not a Hook, and a pair naming a method that
// does not exist: both are swallowed. The caller cannot tell, which is why the
// log line these produce is asserted below.
if ('' !== $escapes($hm, 'CHAR_BAD_OBJ', [new \stdClass(), 'fire'])) {
    $fails[] = 'a non-Hook listener now escapes register() instead of being logged';
}
if ($hm->hasListeners('CHAR_BAD_OBJ')) {
    $fails[] = 'a non-Hook listener was registered';
}
if ('' !== $escapes($hm, 'CHAR_BAD_METHOD', [$hook, 'noSuchMethod'])) {
    $fails[] = 'a missing method now escapes register() instead of being logged';
}
if ($hm->hasListeners('CHAR_BAD_METHOD')) {
    $fails[] = 'a listener naming a method that does not exist was registered';
}

// F-13, fixed. The catch used to interpolate $listener[0]; a Closure is an
// object and not an array, so the handler that exists to swallow the failure
// raised an \Error -- which catch (\Exception) does not catch. Registration
// runs in a hook constructor during LoadGlobals, so that was HTTP 500 with an
// empty body on every entry point until the file was deleted from disk.
//
// An error handler must not be able to fail harder than the error it reports,
// so this asserts that nothing escapes, whatever shape arrives.
foreach ([
    'plain object' => new \stdClass(),
    'string' => 'strlen',
    'integer' => 7,
    'null' => null,
    'three-element array' => [$hook, 'fire', 'extra'],
] as $what => $listener) {
    if ('' !== $escapes($hm, 'CHAR_JUNK', $listener)) {
        $fails[] = "a $what listener escapes register() instead of being logged";
    }
    if ($hm->hasListeners('CHAR_JUNK')) {
        $fails[] = "a $what listener was registered";
    }
}

// F-19 in miniature: the one diagnostic a swallowed failure produces does not
// name the class that failed. The format string carries a literal $s where a
// specifier was meant, and supplies seven arguments for six specifiers, so
// $listener[0] -- the only field identifying the culprit -- is dropped.
$msg = $logged($hm, 'CHAR_LOGGED', [new \stdClass(), 'fire']);
if (false === strpos($msg, 'Could not register')) {
    $fails[] = 'a swallowed registration failure no longer logs anything';
}
if (false !== strpos($msg, '$s:')) {
    $fails[] = 'the failure message still carries the literal $s typo';
}
if (false === strpos($msg, 'stdClass')) {
    $fails[] = 'the failure message does not name the class that failed, which'
        . ' is the only field that says which plugin to go and look at';
}
if (false === strpos($msg, 'CHAR_LOGGED')) {
    $fails[] = 'the failure message does not name the event';
}
// A shape that is still refused has to name itself too, or the fix has traded
// a crash for an unattributable log line.
$msg = $logged($hm, 'CHAR_LOGGED_STR', 'strlen');
if (false === strpos($msg, 'string')) {
    $fails[] = 'a listener of an unusable type is logged without being named';
}

// ------------------------------------------------ registering against events

$em = $bare('FOG\EventManager');
$event = $bare('CharEvent');

if ('' !== $escapes($em, 'CHAR_EVT', $event)) {
    $fails[] = 'the supported event listener shape (an Event object) no longer registers';
}

// Same defect as the Closure case, and fixed with it.
if ('' !== $escapes($em, 'CHAR_EVT_BAD', new \stdClass())) {
    $fails[] = 'a non-Event object escapes register() instead of being logged';
}
if ($em->hasListeners('CHAR_EVT_BAD')) {
    $fails[] = 'a non-Event object was registered as an event listener';
}

// F-18. Hook extends Event, so the instanceof guard that is supposed to keep
// the two apart accepts a hook as an event listener.
if ('' !== $escapes($em, 'CHAR_EVT_HOOK', $hook)) {
    $fails[] = 'EventManager::register() now refuses a Hook; that closes F-18,'
        . ' and this case has to be rewritten to assert the refusal';
}

// F-18, second half. Event::onEvent()'s default body prints into the response.
// Every bundled plugin event overrides it; lib/events/hostlist.event.php, the
// one shipped core event, does not.
if (!(new \ReflectionMethod('FOG\Event', 'onEvent'))->isPublic()) {
    $fails[] = 'Event::onEvent() is no longer the public default dispatch target';
}
ob_start();
(new \ReflectionMethod('FOG\Event', 'onEvent'))->invoke($hook, 'CHAR_PRINTED', []);
$printed = ob_get_clean();
if (false === strpos($printed, 'CHAR_PRINTED')) {
    $fails[] = 'Event::onEvent() no longer prints the event name into the'
        . ' response; that closes half of F-18, so rewrite this case';
}

// ------------------------------------------------------------- notify()

// F-17. HookManager inherits notify(), which iterates listeners as objects
// while HookManager stores them as [object, method] arrays -- so it invokes
// nothing and returns true. Pinned structurally rather than by calling it:
// notify() asks the notifyevents table a question before anything a test can
// intercept.
$declares = (new \ReflectionMethod('FOG\HookManager', 'notify'))
    ->getDeclaringClass()->getName();
if ('FOG\EventManager' !== $declares) {
    $fails[] = 'HookManager now declares its own notify(); that closes F-17,'
        . ' so rewrite this case to assert what the override does';
}

// ------------------------------------------------------- activation, at load

// F-15, fixed. Whether a non-plugin hook runs used to be decided by a regular
// expression over the file's source text, so it turned on whitespace and case
// and could not tell a comment from code. It now reads the declared default of
// $active off the class. Same six variants, and the property wins every time.
$decl = new \ReflectionMethod('FOG\EventManager', '_declaresActive');
$decl->setAccessible(true);

$variants = [
    ['V1', 'public $active = true;', true],
    ['V2', 'public $active  = true;', true],
    ['V3', 'public $active =  true;', true],
    ['V4', 'public $active=true;', true],
    ['V5', 'public $active = TRUE;', true],
    ['V6', 'public $active = false; // set $active = true; to enable', false],
    // Declares nothing: inherits Event's default of true, where the regex
    // found no literal and skipped the file.
    ['V7', '', true],
];
@mkdir($tmp . '/hooks', 0777, true);
foreach ($variants as list($tag, $line, $active)) {
    $class = 'CharActive' . $tag;
    $path = $tmp . '/hooks/' . strtolower($class) . '.hook.php';
    file_put_contents(
        $path,
        "<?php\nclass $class extends \\FOG\\Hook\n{\n"
        . "    public \$node = 'demo';\n"
        . ('' === $line ? '' : "    $line\n")
        . "    public function fire(\$a) {}\n}\n"
    );
    require $path;
    if ($decl->invoke(null, $path, -strlen('.hook.php')) !== $active) {
        $fails[] = sprintf(
            'activation verdict for %s is wrong; the property says %s',
            '' === $line ? 'a file declaring no $active' : var_export($line, true),
            $active ? 'true' : 'false'
        );
    }
}

// A file whose class cannot be resolved is skipped, not fatal. Reflecting on a
// name nothing declares throws, and load() runs inside LoadGlobals, so an
// unresolvable file here would be a 500 rather than one hook not starting.
try {
    if (false !== $decl->invoke(null, $tmp . '/hooks/charnosuch.hook.php', -strlen('.hook.php'))) {
        $fails[] = 'a hook file with no resolvable class is treated as active';
    }
} catch (\Throwable $t) {
    $fails[] = 'a hook file with no resolvable class throws ' . get_class($t)
        . ' out of activation, which during LoadGlobals is a 500';
}

// The cases above drive the helper directly, so they say nothing about whether
// load() still uses it. Assert both: that load() asks the helper, and that the
// source-text regex has not come back beside it.
$loader = file_get_contents($web . '/lib/fog/eventmanager.class.php');
$loadBody = substr($loader, strpos($loader, 'public function load()'));
if (false === strpos($loadBody, '_declaresActive(')) {
    $fails[] = 'load() no longer decides activation through _declaresActive(),'
        . ' so the cases above are testing something nothing calls';
}
if (false !== strpos($loadBody, 'preg_match')) {
    $fails[] = 'load() is matching a pattern again; activation is a property,'
        . ' not a shape in the source text';
}
if (false !== strpos($loader, 'active' . chr(92) . 's?=')) {
    $fails[] = 'the source-text activation regex is back in EventManager';
}

// F-22. load() picks the extension with two sequential instanceof checks, and
// HookManager satisfies both. It reaches .hook.php only because the second
// assignment overwrites the first, so reordering the blocks silently makes
// every HookManager load .event.php.
$body = substr($loader, strpos($loader, 'public function load()'));
$evtAt = strpos($body, "'.event.php'");
$hookAt = strpos($body, "'.hook.php'");
if (false === $evtAt || false === $hookAt) {
    $fails[] = 'load() no longer chooses between .event.php and .hook.php by'
        . ' assignment; if the choice moved onto the classes, rewrite this case';
} elseif ($evtAt > $hookAt) {
    $fails[] = 'the two instanceof blocks in load() have been reordered, which'
        . ' makes HookManager load .event.php files';
}

// ------------------------------------------------------ activation, at dispatch

// F-16. processEvent() force-activates any listener whose file path contains
// the substring "plugins", so a plugin hook that declares $active = false runs
// anyway. Fixture lives under FOG_PLUGIN_DIR so its ReflectionClass filename
// carries the substring.
file_put_contents(
    $tmp . '/plugins/demo/hooks/charplugin.hook.php',
    "<?php\nclass CharPluginHook extends \\FOG\\Hook\n{\n"
    . "    public \$name = 'CharPluginHook';\n"
    . "    public \$node = 'demo';\n"
    . "    public \$active = false;\n"
    . "    public \$seen = [];\n"
    . "    public function fire(\$arguments) { \$this->seen[] = \$arguments; }\n"
    . "}\n"
);
require $tmp . '/plugins/demo/hooks/charplugin.hook.php';

$known = new \ReflectionProperty('FOG\HookManager', 'knownEvents');
$known->setAccessible(true);
$known->setValue(null, ['CHAR_DISPATCH' => true, 'CHAR_CORE_DISPATCH' => true]);

$pluginHook = $bare('CharPluginHook');
$hm2 = $bare('FOG\HookManager');
$hm2->register('CHAR_DISPATCH', [$pluginHook, 'fire']);
$hm2->processEvent('CHAR_DISPATCH', ['payload' => 1]);
if (count($pluginHook->seen) !== 1) {
    $fails[] = 'a plugin-path hook declaring $active = false no longer fires;'
        . ' that is the force-activation being removed, so rewrite this case';
}
if (true !== $pluginHook->active) {
    $fails[] = 'processEvent() no longer writes active = true onto the listener';
}

// The same hook off a plugin path is skipped, which is what makes the
// force-activation load-bearing rather than decorative.
$coreHook = $bare('CharHook');
$coreHook->active = false;
$hm2->register('CHAR_CORE_DISPATCH', [$coreHook, 'fire']);
$hm2->processEvent('CHAR_CORE_DISPATCH', ['payload' => 1]);
if (count($coreHook->seen) !== 0) {
    $fails[] = 'a non-plugin hook declaring $active = false was dispatched';
}

// The payload gains the event name, and the listener sees it.
$hm2->register('CHAR_DISPATCH', [$hook, 'fire']);
$hook->seen = [];
$hm2->processEvent('CHAR_DISPATCH', ['payload' => 2]);
if (!isset($hook->seen[0]['event']) || 'CHAR_DISPATCH' !== $hook->seen[0]['event']) {
    $fails[] = 'processEvent() no longer merges the event name into the payload';
}

// ------------------------------------------------------------------- closures

// docs/plugin-development.md has documented the closure form for the three
// Phase 2 authentication seams since ADR 0014, and until now handing register()
// one took the server down. Both listener shapes are supported; the owner is
// what carries $active, so admitting closures needed no new activation rule.
$closureSaw = [];
$hm3 = $bare('FOG\HookManager');
$hm3->register('CHAR_CLOSURE_OK', function ($arguments) use (&$closureSaw) {
    $closureSaw[] = $arguments;
});
if (!$hm3->hasListeners('CHAR_CLOSURE_OK')) {
    $fails[] = 'a Closure listener does not register';
}
$known->setValue(null, [
    'CHAR_DISPATCH' => true,
    'CHAR_CORE_DISPATCH' => true,
    'CHAR_CLOSURE_OK' => true,
    'CHAR_CLOSURE_OWNED' => true,
    'CHAR_CLOSURE_REF' => true,
]);
$hm3->processEvent('CHAR_CLOSURE_OK', ['payload' => 3]);
if (count($closureSaw) !== 1) {
    $fails[] = 'a Closure listener does not fire';
} elseif (!isset($closureSaw[0]['event'])
    || 'CHAR_CLOSURE_OK' !== $closureSaw[0]['event']
) {
    $fails[] = 'a Closure listener is not handed the merged payload';
}

// A closure written inside a hook is bound to that hook, so the hook's $active
// governs it -- the same rule as [$this, 'method'], not a second one.
class CharClosureHook extends \FOG\Hook
{
    public $name = 'CharClosureHook';
    public $node = 'demo';
    public $active = false;
    public $seen = 0;

    public function listener()
    {
        return function ($arguments) {
            $this->seen++;
        };
    }
}
$owned = $bare('CharClosureHook');
$hm3->register('CHAR_CLOSURE_OWNED', $owned->listener());
$hm3->processEvent('CHAR_CLOSURE_OWNED', []);
if (0 !== $owned->seen) {
    $fails[] = 'a closure owned by an inactive hook fired anyway; $active has'
        . ' to mean the same thing for both listener shapes';
}
$owned->active = true;
$hm3->processEvent('CHAR_CLOSURE_OWNED', []);
if (1 !== $owned->seen) {
    $fails[] = 'a closure owned by an active hook did not fire';
}

// A listener is free to declare its parameter by reference, so the payload has
// to reach it as a variable and not as the return value of the merge. PHP does
// not refuse the call when it is not -- it emits "Only variables should be
// passed by reference" and silently drops the binding -- so the diagnostic is
// the only thing there is to assert on.
$hm3->register('CHAR_CLOSURE_REF', function (&$arguments) {
    $arguments['touched'] = true;
});
$diagnostics = [];
set_error_handler(function ($no, $str) use (&$diagnostics) {
    $diagnostics[] = $str;
    return true;
});
$hm3->processEvent('CHAR_CLOSURE_REF', []);
restore_error_handler();
foreach ($diagnostics as $d) {
    if (false !== stripos($d, 'passed by reference')) {
        $fails[] = 'the merged payload no longer reaches a listener as a'
            . ' variable, so a listener declaring its parameter by reference'
            . ' is silently handed a copy';
    }
}

// ---------------------------------------------------------------- hasListeners

if ($hm2->hasListeners('CHAR_NOBODY')) {
    $fails[] = 'hasListeners() reports listeners for an event nobody registered';
}
// Deliberately blind to active: answering "could anyone care" is all it is for,
// and Route::deletemass() uses it to skip building payloads nothing would read.
if (!$hm2->hasListeners('CHAR_CORE_DISPATCH')) {
    $fails[] = 'hasListeners() now tests the listener active flag; that changes'
        . ' what Route::deletemass() skips';
}

// ------------------------------------------------------------------- teardown

$rmrf = function ($dir) use (&$rmrf) {
    if (!is_dir($dir)) {
        return;
    }
    foreach (array_diff((array) scandir($dir), ['.', '..']) as $e) {
        $p = "$dir/$e";
        is_dir($p) ? $rmrf($p) : unlink($p);
    }
    rmdir($dir);
};
$rmrf($tmp);

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: hook and event registration, activation and dispatch are as recorded\n";
exit(0);
