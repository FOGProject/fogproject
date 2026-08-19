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

// F-13. The catch block interpolates $listener[0]; a Closure is an object and
// not an array, so the handler that exists to swallow the failure raises an
// \Error instead -- which catch (\Exception) does not catch. Registration runs
// in a hook constructor during LoadGlobals, so in production this is HTTP 500
// with an empty body on every entry point.
//
// F-14. docs/plugin-development.md documents exactly this shape three times,
// for the three Phase 2 authentication seams.
if ('Error' !== $escapes($hm, 'CHAR_CLOSURE', function ($a) {
})) {
    $fails[] = 'a Closure listener no longer raises \Error out of register();'
        . ' if that is deliberate, this case and F-13 both need rewriting';
}

// F-19 in miniature: the one diagnostic a swallowed failure produces does not
// name the class that failed. The format string carries a literal $s where a
// specifier was meant, and supplies seven arguments for six specifiers, so
// $listener[0] -- the only field identifying the culprit -- is dropped.
$msg = $logged($hm, 'CHAR_LOGGED', [new \stdClass(), 'fire']);
if (false === strpos($msg, 'Could not register')) {
    $fails[] = 'a swallowed registration failure no longer logs anything';
}
if (false === strpos($msg, '$s:')) {
    $fails[] = 'the literal $s typo is gone from the failure message; good, but'
        . ' this case pins it deliberately -- rewrite it alongside the fix';
}
if (false !== strpos($msg, 'stdClass')) {
    $fails[] = 'the failure message now names the offending class; good, but'
        . ' this case pins its absence deliberately -- rewrite it';
}

// ------------------------------------------------ registering against events

$em = $bare('FOG\EventManager');
$event = $bare('CharEvent');

if ('' !== $escapes($em, 'CHAR_EVT', $event)) {
    $fails[] = 'the supported event listener shape (an Event object) no longer registers';
}

// Same defect as the Closure case: a non-Event object reaches $listener[0].
if ('Error' !== $escapes($em, 'CHAR_EVT_BAD', new \stdClass())) {
    $fails[] = 'a non-Event object no longer raises \Error out of register()';
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

// F-15. Whether a non-plugin hook runs is decided by a regular expression over
// the file's source text, so it turns on whitespace and case and does not know
// a comment from code. The pattern is read out of the shipped source rather
// than copied, so replacing it fails this case instead of leaving it green
// against a regex nobody uses any more.
$loader = file_get_contents($web . '/lib/fog/eventmanager.class.php');
// Walk out from the regex's own text to the quotes around it, rather than
// writing a pattern that matches a pattern.
$at = strpos($loader, 'active' . chr(92) . 's?=' . chr(92) . 's?true;');
$open = false === $at ? false : strrpos(substr($loader, 0, $at), chr(39));
$close = false === $open ? false : strpos($loader, chr(39), $open + 1);
if (false === $close) {
    $fails[] = 'the activation regex is gone from EventManager; if it was'
        . ' replaced by reading the property, rewrite this whole section';
} else {
    $pattern = substr($loader, $open + 1, $close - $open - 1);
    $variants = [
        ['public $active = true;', true],
        ['public $active  = true;', false],
        ['public $active =  true;', false],
        ['public $active=true;', true],
        ['public $active = TRUE;', false],
        ['public $active = false; // set $active = true; to enable', true],
    ];
    foreach ($variants as list($line, $loads)) {
        if ((bool) preg_match($pattern, $line) !== $loads) {
            $fails[] = sprintf(
                'activation verdict changed for %s (was %s)',
                var_export($line, true),
                $loads ? 'loaded' : 'skipped'
            );
        }
    }
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
