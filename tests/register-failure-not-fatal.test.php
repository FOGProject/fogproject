<?php
/**
 * register() must never fail harder than the error it is reporting.
 *
 * The catch that exists to swallow a bad listener interpolated $listener[0].
 * Hand register() an object that is not an array -- a closure is the shape the
 * 1.6 plugin guide documents -- and that line raised "Cannot use object of
 * type X as array", an Error, which catch (Exception) does not catch.
 *
 * Registration runs inside a hook constructor during LoadGlobals, so the Error
 * escaped base.inc.php and the whole application answered HTTP 500 with a
 * zero-byte body, on every entry point, until the file was deleted from disk.
 * Indistinguishable in a browser from an autoloader collision.
 *
 * Ported from working-1.6, where the full contract is pinned by
 * tests/hook-event-contract.test.php and argued in
 * docs/adr/0017-hook-dispatch-contract.md. This is the safety half only: the
 * activation and dispatch changes made there are behaviour changes on a
 * released line and are a separate decision.
 *
 * No database. The managers are built without running any constructor, since
 * register() only touches $this->data.
 *
 * Usage: php tests/register-failure-not-fatal.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require $web . '/commons/init.php';
new Initiator();

$fails = array();

/**
 * Stands in for the signed-in user.
 *
 * FOGBase::logHistory() calls self::$FOGUser->isValid() with no null guard,
 * and LoadGlobals is what sets that in a real request. Returning false is the
 * "nobody is signed in" answer, which makes logHistory() return before it
 * would touch the database.
 */
class RegFailUser
{
    public function isValid()
    {
        return false;
    }
}
$userProp = new ReflectionProperty('FOGBase', 'FOGUser');
$userProp->setAccessible(true);
$userProp->setValue(null, new RegFailUser());

class RegFailHook extends Hook
{
    public $name = 'RegFailHook';
    public $active = true;

    public function fire($arguments)
    {
    }
}

$bare = function ($class) {
    $r = new ReflectionClass($class);
    return $r->newInstanceWithoutConstructor();
};

/**
 * Registers a listener and reports the class of whatever escaped, or ''.
 */
$escapes = function ($manager, $event, $listener) {
    try {
        $manager->register($event, $listener);
    } catch (Exception $e) {
        return get_class($e);
    } catch (Throwable $t) {
        return get_class($t);
    }
    return '';
};

/**
 * Captures what register() logs. FOGBase::log() echoes once the manager's
 * logLevel reaches the message level, and logHistory() returns before touching
 * the database when there is no valid user -- which is every test.
 */
$logged = function ($manager, $event, $listener) {
    $was = $manager->logLevel;
    $manager->logLevel = 9;
    ob_start();
    try {
        $manager->register($event, $listener);
    } catch (Exception $e) {
        // asserted separately
    } catch (Throwable $t) {
        // asserted separately
    }
    $out = ob_get_clean();
    $manager->logLevel = $was;
    return $out;
};

$hm = $bare('HookManager');
$em = $bare('EventManager');
$hook = $bare('RegFailHook');

// The supported shape still works.
if ('' !== $escapes($hm, 'REGFAIL_OK', array($hook, 'fire'))) {
    $fails[] = 'the supported listener shape array(Hook, method) no longer registers';
}
if (!isset($hm->data['REGFAIL_OK'])) {
    $fails[] = 'a valid listener was not stored';
}

// Nothing escapes, whatever arrives.
$junk = array(
    'closure' => function ($a) {
    },
    'plain object' => new stdClass(),
    'string' => 'strlen',
    'integer' => 7,
    'null' => null,
);
foreach ($junk as $what => $listener) {
    if ('' !== $escapes($hm, 'REGFAIL_JUNK', $listener)) {
        $fails[] = "a $what listener escapes register() instead of being logged";
    }
    if (isset($hm->data['REGFAIL_JUNK'])) {
        $fails[] = "a $what listener was registered";
    }
}
if ('' !== $escapes($em, 'REGFAIL_EVT', new stdClass())) {
    $fails[] = 'a non-Event object escapes register() instead of being logged';
}

// And the one line it does log has to say which class failed. The format
// carried a literal $s where a specifier was meant, and seven arguments for
// six specifiers, so the class was dropped before it was ever printed.
$msg = $logged($hm, 'REGFAIL_LOGGED', array(new stdClass(), 'fire'));
if (false === strpos($msg, 'Could not register')) {
    $fails[] = 'a swallowed registration failure logs nothing';
}
if (false !== strpos($msg, '$s:')) {
    $fails[] = 'the failure message still carries the literal $s typo';
}
if (false === strpos($msg, 'stdClass')) {
    $fails[] = 'the failure message does not name the class that failed, which'
        . ' is the only field saying which plugin to go and look at';
}
if (false === strpos($msg, 'REGFAIL_LOGGED')) {
    $fails[] = 'the failure message does not name the event';
}
$msg = $logged($hm, 'REGFAIL_CLOSURE', function ($a) {
});
if (false === strpos($msg, 'Closure')) {
    $fails[] = 'a closure listener is logged without being named';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: an unusable listener is logged and named, never thrown\n";
exit(0);
