<?php
/**
 * The mass edit form renders three states, and never a credential.
 *
 * The apply endpoint fails closed no matter what arrives
 * (tests/mass-edit-fails-closed.test.php). This is about the other half of
 * ADR 0038 decision 11, which is a claim about what the FORM does:
 *
 *   - every field is paired with an ACTION control that core renders, so a
 *     plugin cannot ship a two-state field;
 *   - a boolean is offered LEAVE and SET only, because its value control is
 *     already a yes/no and a CLEAR beside it would be a second spelling of
 *     "set to No";
 *   - every value control is EMPTY -- there is no read path -- and its name
 *     is `value[<key>]` so MassEdit sees it;
 *   - the AD password in particular renders with no value and no 32-asterisk
 *     placeholder, and its hint reports agreement without reporting the
 *     secret.
 *
 * These are checked by RENDERING, not by grepping: the control builders are
 * pure string functions, so the object is constructed without its
 * constructor and the private methods are called through reflection. That is
 * real evidence about the markup a browser receives, which a source scan is
 * not.
 *
 * The kinds that need a database (`image`, and the two exit selectors, which
 * read settings) are deliberately not exercised here -- they are the shared
 * builders every other FOG form already uses, and standing a schema up to
 * watch them work would make this an install rehearsal.
 *
 * Usage: php tests/mass-edit-form.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$webroot = $root . '/packages/web';
$init = $webroot . '/commons/init.php';
if (!is_readable($init)) {
    fwrite(STDERR, "FAIL: cannot read $init\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/fog-mass-edit-form-test-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
register_shutdown_function(
    function () use ($tmp) {
        if (!is_dir($tmp)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }
);
if (!defined('FOG_CACHE_DIR')) {
    define('FOG_CACHE_DIR', $tmp . '/cache');
}
if (!defined('FOG_LOG_DIR')) {
    define('FOG_LOG_DIR', $tmp . '/log');
}
if (!defined('FOG_PLUGIN_DIR')) {
    define('FOG_PLUGIN_DIR', $tmp . '/plugins');
}

require_once $init;
new Initiator();

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

$class = \FOG\Pages\HostManagement::class;
if (!class_exists($class, true)) {
    fwrite(STDERR, "FAIL: HostManagement does not resolve\n");
    exit(1);
}

// No constructor: it wants a configured server. The control builders are
// pure, so an uninitialized instance is enough to call them, and calling
// them is the whole point -- this test asserts on rendered markup.
$page = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
$call = static function ($method, array $args = []) use ($class, $page) {
    $m = new \ReflectionMethod($class, $method);
    $m->setAccessible(true);
    return $m->invokeArgs($page, $args);
};

$core = $call('massEditCoreFields');
$rows = $call('massEditRowFields');
$check('the core field spec is reachable', is_array($core) && count($core) > 0);
$check('the row field spec is reachable', is_array($rows) && count($rows) > 0);

// --- The action control ---------------------------------------------------

$action = $call('massEditActionControl', ['kernel', $core['kernel']]);
$check(
    'the action control posts as action[<key>]',
    false !== strpos($action, 'name="action[kernel]"')
);
$check(
    'a text field is offered all three states',
    false !== strpos($action, 'value="leave"')
        && false !== strpos($action, 'value="set"')
        && false !== strpos($action, 'value="clear"')
);
$check(
    'leave is the first option, so it is what an untouched control submits',
    strpos($action, 'value="leave"') < strpos($action, 'value="set"')
);
$check(
    'the action control names its key for the script that disables the value',
    false !== strpos($action, 'data-massedit-key="kernel"')
);

$boolAction = $call('massEditActionControl', ['useAD', $core['useAD']]);
$check(
    'a boolean is offered leave and set only',
    false !== strpos($boolAction, 'value="leave"')
        && false !== strpos($boolAction, 'value="set"')
        && false === strpos($boolAction, 'value="clear"')
);

// Every field core and the row half offer must get an action control, and
// core must be the one drawing it. A plugin drawing its own could ship a
// two-state field, which is the defect decision 11 is entirely about.
$missing = [];
foreach (array_merge($core, $rows) as $key => $spec) {
    $html = $call('massEditActionControl', [$key, $spec]);
    if (false === strpos($html, 'name="action[' . $key . ']"')
        || false === strpos($html, 'value="leave"')
    ) {
        $missing[] = $key;
    }
}
$check(
    'every offered field gets a core-rendered action control ('
    . implode(', ', $missing) . ')',
    0 === count($missing)
);

// --- Value controls -------------------------------------------------------

$text = $call('massEditValueControl', ['kernel', $core['kernel']]);
$check(
    'a text value posts as value[<key>]',
    false !== strpos($text, 'name="value[kernel]"')
);
$check(
    'a text value renders empty -- there is no read path',
    false !== strpos($text, 'value=""')
);

$pass = $call('massEditValueControl', ['ADPass', $core['ADPass']]);
$check(
    'the AD password is a password input',
    false !== strpos($pass, 'type="password"')
);
$check(
    'the AD password renders empty',
    false !== strpos($pass, 'value=""')
);
$check(
    'the AD password carries no 32-asterisk placeholder',
    false === strpos($pass, '****')
);
$check(
    'the AD password does not invite the browser to fill it',
    false !== strpos($pass, 'autocomplete="off"')
);

$bool = $call('massEditValueControl', ['useAD', $core['useAD']]);
$check(
    'a boolean value control is a yes/no select',
    false !== strpos($bool, 'name="value[useAD]"')
        && false !== strpos($bool, 'value="1"')
        && false !== strpos($bool, 'value="0"')
);

$level = $call('massEditValueControl', ['printerLevel', $core['printerLevel']]);
$check(
    'the printer level offers all three levels',
    false !== strpos($level, 'value="0"')
        && false !== strpos($level, 'value="1"')
        && false !== strpos($level, 'value="2"')
);

// The composite. Its parts must arrive as an ARRAY under one key, which is
// what lets MassEdit::resolveComposite() read them without parsing anything
// -- and is why there is no 1024x768@60 string anywhere in this form.
$res = $call('massEditValueControl', ['resolution', $rows['resolution']]);
$check(
    'the resolution posts three parts under one key',
    false !== strpos($res, 'name="value[resolution][x]"')
        && false !== strpos($res, 'name="value[resolution][y]"')
        && false !== strpos($res, 'name="value[resolution][r]"')
);
$check(
    'the resolution is not encoded into one string field',
    false === strpos($res, 'name="value[resolution]"')
);

// --- Control ids ----------------------------------------------------------

$check(
    'a control id is a usable HTML id, whatever the key',
    'massedit-value-weirdkey' === $call(
        'massEditControlId',
        ['value', 'weird[key]']
    )
);

// --- What the whitelist promises the form ---------------------------------

$noKind = [];
foreach (array_merge($core, $rows) as $key => $spec) {
    if (!isset($spec['label'])) {
        $noKind[] = $key;
    }
}
$check(
    'every offered field carries a label to render ('
    . implode(', ', $noKind) . ')',
    0 === count($noKind)
);

// --- The endpoint's own wiring --------------------------------------------

$source = (string)@file_get_contents(
    $root . '/packages/web/src/Pages/HostManagement.php'
);
$methodBody = static function ($src, $signature) {
    $start = strpos($src, $signature);
    if (false === $start) {
        return null;
    }
    $open = strpos($src, '{', $start);
    if (false === $open) {
        return null;
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $open, $i - $open + 1);
            }
        }
    }
    return null;
};
$body = $methodBody($source, 'public function massEditFormPost()');
$check('massEditFormPost() is still findable', null !== $body);
$body = (string)$body;

// The form reports what the selection holds, so fetching it is a read of
// those hosts and takes the same boundary the write does.
$auth = strpos($body, 'checkAuthAndCSRF');
$scope = strpos($body, 'requirePageObjectScopeMass');
$hints = strpos($body, 'massEditHints(');
$check('the form endpoint checks auth and CSRF', false !== $auth);
$check('the form endpoint bounds the selection to the site scope', false !== $scope);
$check(
    'the scope check runs before anything is read about the hosts',
    false !== $scope && false !== $hints && $scope < $hints
);

// The form posts to the APPLY sub, not to itself.
$check(
    'the form submits to the apply endpoint',
    false !== strpos($body, 'sub=massedit')
        && false === strpos($body, 'sub=masseditform&')
);
$check(
    'the selection rides in the form as hidden host ids',
    false !== strpos($body, 'name="hosts[]"')
);

// The plugin half goes through the same call the apply path makes, so the
// two cannot see different field sets.
$check(
    'the form gets its plugin fields from massEditPluginFields()',
    false !== strpos($body, 'massEditPluginFields($hosts)')
);

$plugin = $methodBody($source, 'private function massEditPluginFields(');
$check(
    'HOST_MASSEDIT_FIELDS carries the selection to the plugin',
    null !== $plugin
        && false !== strpos((string)$plugin, "'hostIDs' => &\$hostIDs")
);

// The credential hint. A secret reports agreement, never the value.
$hintBody = $methodBody($source, 'private function massEditHints(');
$check('massEditHints() is still findable', null !== $hintBody);
$check(
    'a secret field gets a redacted hint',
    null !== $hintBody
        && 1 === preg_match(
            '/SharedHostValues::hint\(\s*\$shared\[\$key\],\s*'
            . '!empty\(\$spec\[.secret.\]\)/s',
            (string)$hintBody
        )
);

// The row-backed hints ask their own tables, and the resolution's three
// columns are combined into one answer rather than reported separately.
$check(
    'the auto-logout hint reads its own table',
    null !== $hintBody
        && false !== strpos((string)$hintBody, "'hostAutoLogOut'")
);
$check(
    'the resolution hint reads its own table',
    null !== $hintBody
        && false !== strpos((string)$hintBody, "'hostScreenSettings'")
);
$check(
    'the resolution is uniform only when all three parts agree',
    null !== $hintBody
        && 1 === preg_match(
            '/\$uniform = !empty\(\$disp\[.x.\]\[.uniform.\]\)\s*'
            . '&& !empty\(\$disp\[.y.\]\[.uniform.\]\)\s*'
            . '&& !empty\(\$disp\[.r.\]\[.uniform.\]\)/s',
            (string)$hintBody
        )
);

// --- Where the button sits ------------------------------------------------
//
// The toolbar is split on a real distinction: the LEFT half acts on the rows
// that are already ticked (Delete selected, Mass edit) and the RIGHT-hand
// btn-group brings something new into existence (Queue Task, Add to group,
// Add). Mass edit shipped in the right-hand group by mistake and was moved.
//
// Position is not something the rendering checks above can see -- they call
// massEditActions() and get a button back with no toolbar around it -- so
// this reads the emission order out of FOGPage::process(), which is what
// actually decides it. Two floated buttons stack in emission order, so
// "after deleteSelected and before the btn-group opens" IS "to the right of
// Delete".
$page = (string)@file_get_contents(
    $root . '/packages/web/src/Base/FOGPage.php'
);
$posDelete = strpos($page, "'deleteSelected',");
$posMass = strpos($page, "massEditActions')");
$posGroup = strpos($page, '<div class="btn-group float-end">');
$check(
    'FOGPage::process() still emits all three toolbar landmarks',
    false !== $posDelete && false !== $posMass && false !== $posGroup
);
$check(
    'Mass edit is emitted after Delete selected and before the right group',
    false !== $posDelete && false !== $posMass && false !== $posGroup
        && $posDelete < $posMass && $posMass < $posGroup
);
$check(
    'the Mass edit button floats left, so it lands beside Delete',
    1 === preg_match(
        "/'massEditSelected',\s*_\('Mass edit'\),\s*'[^']*\bfloat-start\b/",
        $source
    )
);

// -------------------------------------------------------------------------
// The page guard must not swallow the mass edit subs.
//
// FOGPage::__construct() refuses a sub that acts on one object when the URL
// carries no id. It asked `false !== stripos($sub, 'edit')`, and BOTH mass
// edit subs contain the word:
//
//     stripos('masseditform', 'edit') === 4
//     stripos('massedit', 'edit')     === 4
//
// so both were refused with 404 `No host exists with ID` -- no id after
// "ID", because there was never meant to be one -- and the modal that
// fetches the form sat on "Loading, please wait..." indefinitely, a 404
// being nothing its success handler reads. The feature could not work at
// all on a running server, in either half, and every other gate on it is
// DOWNSTREAM of this guard and so could not see it.
//
// EXECUTED, not grepped. The rule lives in one method precisely so a test
// can call it with the sub names that actually exist. A grep for the
// absence of stripos() would pass on the next spelling of the same mistake.
$needsID = new \ReflectionMethod('FOG\\Base\\FOGPage', 'subNeedsObjectID');
$needsID->setAccessible(true);
$check(
    "the object guard fires for sub=edit, which is the one object sub there is",
    true === $needsID->invoke(null, 'edit')
);
$check(
    'and is case-insensitive about it',
    true === $needsID->invoke(null, 'Edit')
);
foreach (['massedit', 'masseditform'] as $listSub) {
    $check(
        "the object guard does NOT fire for sub=$listSub,"
        . ' which acts on a POSTed selection and has no id',
        false === $needsID->invoke(null, $listSub)
    );
}
$check(
    'and does not fire for a sub that merely ends in the word',
    false === $needsID->invoke(null, 'bulkedit')
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the mass edit form is not three-state:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  mass edit form: %d checks\n", $checks);
exit(0);
