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

/**
 * kernelArgs, not kernel: the kernel and init fields are boot-file pickers
 * now, and a picker reads FOG_TFTP_PXE_KERNEL_DIR to know what to offer,
 * which this DB-free bootstrap cannot answer. Their kinds are pinned below
 * instead, at the spec level where no render is needed.
 */
$text = $call('massEditValueControl', ['kernelArgs', $core['kernelArgs']]);
$check(
    'a text value posts as value[<key>]',
    false !== strpos($text, 'name="value[kernelArgs]"')
);
$check(
    'a text value renders empty -- there is no read path',
    false !== strpos($text, 'value=""')
);

/**
 * Mass edit was left on a free-text box when the host form gained the
 * dropdowns, so the one place a typo reaches every selected host at once was
 * the only place offering no list to pick from.
 */
$check(
    'the kernel field is a boot-file picker, not free text',
    'kernel' === ($core['kernel']['kind'] ?? '')
);
$check(
    'the init field is a boot-file picker, not free text',
    'init' === ($core['init']['kind'] ?? '')
);
$check(
    'kernel arguments stay free text -- they are not a filename',
    'text' === ($core['kernelArgs']['kind'] ?? '')
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

// The row-backed hint asks its own table.
$check(
    'the auto-logout hint reads its own table',
    null !== $hintBody
        && false !== strpos((string)$hintBody, "'hostAutoLogOut'")
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

// -------------------------------------------------------------------------
// A failed form fetch must leave the modal SAYING SO, not sitting on
// "Loading, please wait...".
//
// The error handler used to call massEditModal.modal('hide'), and Bootstrap 5
// drops a hide() that lands inside the show transition:
//
//     hide() { this._isShown && !this._isTransitioning && (...) }
//
// A refusal comes back faster than the fade takes -- the page guard above
// answered before any handler ran -- so the hide was discarded and the box
// stayed open with only a toast beside it to say why. That is what the bug
// report showed. Fixing the guard removes today's cause; it does not stop
// the next refusal (a permission denial, a 500) from hanging the same modal,
// so the handler now writes the reason where the person is already looking.
//
// Both fetches on this page are checked, not just the one that was reported.
// Queue Task is the button beside Mass edit, over the same selection, and had
// the identical handler -- it would have been the next report.
//
// Sliced by position rather than grepped over the whole file: `modal('hide')`
// appears legitimately in each success path a few lines away, so a file-wide
// search for it would pass whatever the error handlers do. The slice is
// anchored on the FETCH URL, because there are two error handlers in this
// file and the first textual match is the other one.
$listJs = (string)@file_get_contents(
    $root . '/packages/web/management/js/fog/host/fog.host.list.js'
);

/**
 * The error callback belonging to one $.ajax() block.
 *
 * @param string $js     the file
 * @param string $anchor a string inside the block, before its error handler
 *
 * @return string
 */
$errorHandler = function ($js, $anchor) {
    $from = strpos($js, $anchor);
    if (false === $from) {
        return '';
    }
    $at = strpos($js, 'error: function(jqXHR, textStatus) {', $from);
    if (false === $at) {
        return '';
    }
    $end = strpos($js, "\n                }", $at);

    return substr($js, $at, (false === $end ? strlen($js) : $end) - $at);
};

$fetches = [
    'mass edit' => ['sub=masseditform', 'massEditHolder.html(', "massEditModal.modal('hide')"],
    'queue task' => ['sub=deployMulti', 'queueHolder', "queueTaskModal.modal('hide')"],
];
foreach ($fetches as $what => $spec) {
    list($anchor, $writes, $hides) = $spec;
    $errBody = $errorHandler($listJs, $anchor);
    $check(
        "the $what fetch still has an error handler",
        '' !== $errBody
    );
    $check(
        "the $what fetch writes the reason into the modal body",
        false !== strpos($errBody, $writes)
    );
    $check(
        "the $what fetch escapes it, the reason being server text in markup",
        false !== strpos($errBody, '$.escapeHtml(')
    );
    $check(
        "the $what fetch does not try to hide the modal,"
        . ' which Bootstrap drops mid-transition',
        false === strpos($errBody, $hides)
    );
}

// --- Tabs -----------------------------------------------------------------
//
// Seventeen core fields plus whatever the plugins add is a scroll, not a
// form, so the modal is split the way a single host's page is. The claim
// being gated is narrow and it is the one that matters: splitting the form
// must not lose a control. Every pane is in the DOM either way and a hidden
// pane's inputs serialize exactly like a visible one's, so a field that
// vanishes here vanishes from the POST, silently, on a form that is the only
// way to set these values in bulk.

$groups = $call('massEditTabGroups');
$check(
    'the tab groups are named and ordered',
    is_array($groups)
        && ['general', 'ad', 'client', 'plugins'] === array_keys($groups)
);

// Every field the form offers has to land on a tab that exists. A spec
// naming one that does not is a mistake, and the mistake must produce a
// control in the wrong place rather than no control at all.
$stray = [];
foreach (array_merge($core, $rows) as $key => $spec) {
    $tab = $call('massEditTabFor', [$spec]);
    if (!isset($groups[$tab])) {
        $stray[] = $key;
    }
}
$check(
    'every offered field lands on a tab that exists ('
    . implode(', ', $stray) . ')',
    0 === count($stray)
);
$check(
    'a spec with no tab falls back to General rather than disappearing',
    'general' === $call('massEditTabFor', [['kind' => 'text']])
);
$check(
    'a spec naming a tab that does not exist falls back the same way',
    'general' === $call('massEditTabFor', [['tab' => 'nonesuch']])
);

// The grouping mirrors a single host's own page. enforce is on General
// because that is where hostGeneral() draws it, not because it is unrelated
// to AD -- following the host page is the rule.
$where = [
    'image' => 'general',
    'kernel' => 'general',
    'enforce' => 'general',
    // Not General. The host page draws this on Printer Associations, and
    // there is no Printer Associations tab here -- an association is not a
    // value a mass edit sets -- so what is left is a statement about how the
    // client handles printers, which is what this tab holds.
    'printerLevel' => 'client',
    'useAD' => 'ad',
    'ADPass' => 'ad',
    'autologout' => 'client',
];
$wrong = [];
foreach ($where as $key => $tab) {
    $spec = $core[$key] ?? $rows[$key] ?? null;
    if (null === $spec || $tab !== $call('massEditTabFor', [$spec])) {
        $wrong[] = $key;
    }
}
$check(
    'the grouping follows the host page (' . implode(', ', $wrong) . ')',
    0 === count($wrong)
);

// Now render it for real. The DB-backed kinds are dropped for the same
// reason the value-control checks above drop them -- standing a schema up to
// watch ImageManager build a select would make this an install rehearsal --
// and the tab question is unaffected by which control a pane contains.
$needsDb = ['image' => 1, 'biosexit' => 1, 'efiexit' => 1];
$renderCore = [];
foreach ($core as $key => $spec) {
    if (!isset($needsDb[$spec['kind'] ?? 'text'])) {
        $renderCore[$key] = $spec;
    }
}
$check(
    'the render still covers a field from every non-plugin tab',
    isset($renderCore['kernel'], $renderCore['useAD'])
);

$html = $call(
    'massEditTabbedFields',
    [$renderCore, $rows, [], []]
);
$check('the form renders as tabs', false !== strpos($html, 'nav-tabs'));
foreach (['general', 'ad', 'client'] as $tab) {
    $check(
        "the $tab tab is drawn",
        false !== strpos($html, 'id="massedit-tab-' . $tab . '"')
            && false !== strpos($html, 'href="#massedit-tab-' . $tab . '"')
    );
}
$check(
    'the tabs are labeled as the host page labels them',
    false !== strpos($html, '>General<')
        && false !== strpos($html, '>Active Directory<')
        && false !== strpos($html, '>FOG Client<')
);
$check(
    'exactly one pane opens, so the modal is never blank on arrival',
    1 === substr_count($html, 'tab-pane fade show active')
);

// The one that actually matters: nothing was lost in the split.
$lost = [];
foreach (array_merge($renderCore, $rows) as $key => $spec) {
    if (false === strpos($html, 'name="action[' . $key . ']"')
        || false === strpos($html, 'data-massedit-key="' . $key . '"')
    ) {
        $lost[] = $key;
    }
}
$check(
    'every field survives the split into tabs (' . implode(', ', $lost) . ')',
    0 === count($lost)
);

// An empty tab in a modal reads as something that failed to load, and
// Plugins is empty on any server with neither location nor ou installed.
$check(
    'the Plugins tab is not drawn when no plugin contributed',
    false === strpos($html, 'massedit-tab-plugins')
);
$withPlugin = $call(
    'massEditTabbedFields',
    [
        $renderCore,
        $rows,
        [],
        [
            'location' => [
                'label' => 'Host Location',
                'input' => '<input name="value[location]"/>',
                'hint' => ''
            ]
        ]
    ]
);
$check(
    'a contributed field draws the Plugins tab',
    false !== strpos($withPlugin, 'id="massedit-tab-plugins"')
        && false !== strpos($withPlugin, '>Plugins<')
);
$check(
    'core still draws the action control for a plugin field',
    false !== strpos($withPlugin, 'name="action[location]"')
);
$check(
    'the plugin tab does not steal the open pane from General',
    1 === substr_count($withPlugin, 'tab-pane fade show active')
        && strpos($withPlugin, 'massedit-tab-general')
            < strpos($withPlugin, 'massedit-tab-plugins')
);

// tabFields() defaults to resolving the current node and id into an object
// and firing TABDATA_HOOK against it. There is no single host being edited
// here, and a plugin tab built for one host would be wrong for all of them.
$tabbed = $methodBody($source, 'private function massEditTabbedFields(');
$check(
    'the tab builder passes no object, so the per-host tab hooks stay quiet',
    null !== $tabbed
        && false !== strpos((string)$tabbed, 'self::tabFields($tabData, false)')
);
$check(
    'the form endpoint renders through the tab builder',
    false !== strpos($body, 'massEditTabbedFields(')
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
