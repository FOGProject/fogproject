<?php
/**
 * The Local files tab: what it shows, and what its actions refuse.
 *
 * The Kernel Update and Initrd Update pages could only say what was
 * available to download. "What do I already have, and what is it for" had
 * nowhere to be asked -- which is also why nothing could offer to point a
 * default back at the kernel that worked, or to keep one against a future
 * prune.
 *
 * Most of this asserts on the page class's source rather than by rendering,
 * because rendering the tab needs a boot directory, a settings table and an
 * authenticated session. What the source can be held to is the wiring that
 * is easy to get wrong and invisible when wrong:
 *
 *   - the release table has to stay tab ONE, because its DataTable computes
 *     column widths at page load and cannot do that inside a hidden pane;
 *   - tabFields() must be passed false, not its default -1, or it asks
 *     getClass() to resolve an entity for node 'about' where there is none;
 *   - every action endpoint has to check CSRF, check a permission, and
 *     resolve the posted filename against the directory rather than trusting
 *     it;
 *   - and the permissions have to be node-scoped, NOT global.
 *
 * The role-to-setting map IS exercised directly, because it is the thing
 * that stops a boot payload being installed as a boot kernel through a
 * hand-made request.
 *
 * Usage: php tests/boot-file-tab.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('boot-file-tab');
FogTestHarness::fakeDb();

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$page = file_get_contents($web . '/src/Pages/FOGConfigurationPage.php');
$auth = file_get_contents($web . '/src/Auth/Authorization.php');
$js = file_get_contents($web . '/management/js/fog/fog.common.js');

// --- the tabs -------------------------------------------------------------

$t->check(
    'the download view renders tabs',
    false !== strpos($page, "'name' => _('Available downloads')")
    && false !== strpos($page, "'name' => _('Local files')")
);
$t->check(
    'the release table is the first tab, so its DataTable is not built hidden',
    strpos($page, "_('Available downloads')")
    < strpos($page, "_('Local files')")
);
$t->check(
    'tabFields is passed false, not the default -1',
    false !== strpos($page, 'self::tabFields($tabData, false)')
);
$t->check(
    'the local files pane is not a DataTable',
    false === strpos($page, "fog-bootfile-table' data-datatable")
    && false !== strpos($page, 'fog-bootfile-table')
);

// --- the three action endpoints ------------------------------------------

$subs = ['bootfilekeep', 'bootfiledefault', 'bootfiledelete'];
foreach ($subs as $sub) {
    $t->check(
        $sub . ' has a dispatch anchor that refuses a GET',
        1 === preg_match(
            '/function ' . $sub . '\(\)\s*\{\s*\$this->_postOnly\(\);/',
            $page
        )
    );
    $t->check(
        $sub . ' has a POST handler',
        false !== strpos($page, 'function ' . $sub . 'Post()')
    );
    /**
     * Node-scoped, not global. The download subs are in
     * GLOBAL_SUB_OVERRIDES only because their JS posts with no node at all,
     * so they dispatch under the exempt 'home' node -- a shape worth not
     * repeating.
     */
    $t->check(
        $sub . ' declares its permission node-scoped, not globally',
        false !== strpos($auth, "'" . $sub . "' => 'settings.edit'")
    );
}
$globalStart = strpos($auth, 'const GLOBAL_SUB_OVERRIDES');
$globalBlock = false === $globalStart
    ? ''
    : substr($auth, $globalStart, 2000);
foreach ($subs as $sub) {
    $t->check(
        $sub . ' is not in GLOBAL_SUB_OVERRIDES',
        false === strpos($globalBlock, $sub)
    );
}

// Each handler, in its own slice of the file, has to do all three things.
foreach ($subs as $sub) {
    $at = strpos($page, 'function ' . $sub . 'Post()');
    $body = false === $at ? '' : substr($page, $at, 2600);
    $t->check(
        $sub . 'Post checks auth and CSRF',
        false !== strpos($body, 'self::checkAuthAndCSRF();')
    );
    $t->check(
        $sub . 'Post checks a permission explicitly',
        false !== strpos($body, "Authorization::can('settings.edit')")
    );
    $t->check(
        $sub . 'Post resolves the posted name against the directory',
        false !== strpos($body, '_bootFileNamed(')
    );
    $t->check(
        $sub . 'Post writes an audit row',
        false !== strpos($body, 'Audit::record(')
    );
}

// --- delete refuses what it must ----------------------------------------

$at = strpos($page, 'function bootfiledeletePost()');
$deleteBody = false === $at ? '' : substr($page, $at, 3000);
$t->check(
    'delete refuses a file that is marked kept',
    false !== strpos($deleteBody, "\$info['pinned']")
);
$t->check(
    'delete refuses a file a default setting points at',
    false !== strpos($deleteBody, '_defaultPointers()')
);
/**
 * The row hides those buttons already. The handler refuses anyway, because
 * a hidden button is a courtesy and a request is not obliged to come from
 * one.
 */
$t->check(
    'the refusals are server side, not only in the rendered row',
    false !== strpos($deleteBody, 'HTTP_BAD_REQUEST')
);

// --- path traversal ------------------------------------------------------

$at = strpos($page, 'function _bootFileNamed(');
$namedBody = false === $at ? '' : substr($page, $at, 900);
$t->check(
    'a posted filename is reduced to a basename',
    false !== strpos($namedBody, 'basename(')
);
$t->check(
    'and then required to be a real file in the boot directory',
    false !== strpos($namedBody, 'is_file(')
    && false !== strpos($namedBody, 'FOG_TFTP_PXE_KERNEL_DIR')
);

// --- the role to setting map, exercised ---------------------------------

$keysFor = new \ReflectionMethod(
    'FOG\Pages\FOGConfigurationPage',
    '_defaultKeysFor'
);
$keysFor->setAccessible(true);
$kernelKeys = array_keys($keysFor->invoke(null, 'kernel'));
$initKeys = array_keys($keysFor->invoke(null, 'init'));
$payloadKeys = array_keys($keysFor->invoke(null, 'payload'));

$t->check(
    'a kernel may be set as any of the three default kernels',
    [
        'FOG_TFTP_PXE_KERNEL',
        'FOG_TFTP_PXE_KERNEL_32',
        'FOG_TFTP_PXE_KERNEL_ARM'
    ] === $kernelKeys
);
$t->check(
    'an init may be set as any of the three default inits',
    [
        'FOG_PXE_BOOT_IMAGE',
        'FOG_PXE_BOOT_IMAGE_32',
        'FOG_PXE_BOOT_IMAGE_ARM'
    ] === $initKeys
);
/**
 * The collision this whole change set is about: FOG_MEMTEST_KERNEL is named
 * for a kernel and points at a payload. A payload must be offerable as that
 * and as nothing else.
 */
$t->check(
    'a payload may be set as the memtest pointer and nothing else',
    ['FOG_MEMTEST_KERNEL'] === $payloadKeys
);
$t->check(
    'no kernel setting is offered to a payload',
    !in_array('FOG_TFTP_PXE_KERNEL', $payloadKeys, true)
);
$t->check(
    'no payload setting is offered to a kernel',
    !in_array('FOG_MEMTEST_KERNEL', $kernelKeys, true)
);
$t->check(
    'an unclassified file may be set as nothing at all',
    [] === $keysFor->invoke(null, 'unclassified')
);

// --- the client side -----------------------------------------------------

$t->check(
    'the actions post with an explicit node, so they resolve node-scoped',
    false !== strpos($js, "'?node=about&sub=' + sub")
);
$t->check(
    'delete asks first',
    false !== strpos($js, 'window.confirm(')
);
$t->check(
    'the handlers are delegated and namespaced, so an AJAX page swap is safe',
    false !== strpos($js, "off('click.fogBootFileAct')")
);
$t->check(
    'a successful action re-reads the page rather than patching the row',
    false !== strpos($js, 'location.reload();')
);

$t->finish();
