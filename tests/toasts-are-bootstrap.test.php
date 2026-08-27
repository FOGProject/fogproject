<?php
/**
 * Toasts are Bootstrap's Toast component, and every page that raises one
 * loads it.
 *
 * FOG vendored PNotify 3.2.0 for notifications. It was dropped rather than
 * updated: upstream's last release was 2020-11-06, it ships no Bootstrap 5
 * styling, and its icon presets stop at Font Awesome 5 -- which is why the
 * toast icon names had to be hand-patched at FOG's call site after the FA7
 * migration. Bootstrap 5.3.3 is already vendored and carries Toast, so the
 * dependency went away instead of being refreshed.
 *
 * WHAT IS PINNED, and why each one is here:
 *
 *  1. PNotify is really gone -- no vendored file, no asset-list entry, no
 *     reference in FOG's own code. A half-removal leaves a 404 in the asset
 *     list, which fails silently: the browser skips the file and the next
 *     script runs anyway.
 *  2. $.notify() does not construct PNotify any more, and does build a
 *     bootstrap.Toast.
 *  3. **Every asset list that can raise a toast loads Bootstrap's JS before
 *     fog.common.js.** This is the one that actually broke. $loginJavascripts
 *     carried pnotify.min.js but NO Bootstrap bundle at all -- nothing on the
 *     login page had ever needed it -- so moving to bootstrap.Toast without
 *     adding it would kill the flash message on exactly the page that exists
 *     to show it. User::logout() preserves queued messages across the session
 *     rebuild specifically so "logged out due to inactivity" can toast after
 *     the redirect, and management/other/index.php emits that as a $.notify()
 *     call. It would have died on `bootstrap is not defined`, in an inline
 *     nonce'd script, leaving the user at a login screen that never says why
 *     they are there. Order matters as much as presence: fog.common.js reads
 *     the global at call time, but a missing bundle is only ever noticed by
 *     someone watching a console.
 *  4. Toast bodies are set as text, not HTML. PNotify defaulted
 *     title_escape/text_escape to FALSE, so every toast title and body was
 *     parsed as markup -- including $.notifyFromAPI()'s res.error, which can
 *     carry a value the user typed. Nothing passes markup, so this is free.
 *
 * Usage: php tests/toasts-are-bootstrap.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('toasts-are-bootstrap');

$t = new FogChecks();

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$common = file_get_contents($web . '/management/js/fog/fog.common.js');
$page = file_get_contents($web . '/src/Base/Page.php');

// ---------------------------------------------------------------------------
// 1. The dependency is gone.
// ---------------------------------------------------------------------------
$t->check(
    'no pnotify asset is vendored',
    [] === glob($web . '/management/js/pnotify*')
    && [] === glob($web . '/management/css/pnotify*')
);
$t->check(
    'no asset list still references pnotify',
    false === stripos($page, 'pnotify')
);

// Comments may still explain what was replaced; executable references must be
// gone. Stripping them keeps this from passing or failing on its own docs.
$stripped = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $common);
$t->check(
    'fog.common.js has no live PNotify reference left',
    false === strpos($stripped, 'PNotify')
);

// ---------------------------------------------------------------------------
// 2. It was replaced, not merely deleted.
// ---------------------------------------------------------------------------
$t->check(
    '$.notify builds a bootstrap.Toast',
    (bool)preg_match('/new bootstrap\.Toast\(/', $stripped)
);
$t->check(
    'and disposes it once hidden, so the DOM does not grow unbounded',
    false !== strpos($stripped, 'hidden.bs.toast')
    && (bool)preg_match('/\.dispose\(\)/', $stripped)
);

// ---------------------------------------------------------------------------
// 3. Every list that can raise a toast can actually show one.
// ---------------------------------------------------------------------------
$lists = [];
if (preg_match_all(
    '/protected static \$(\w*[Jj]avascripts) = \[(.*?)\];/s',
    $page,
    $lm,
    PREG_SET_ORDER
)) {
    foreach ($lm as $entry) {
        $lists[$entry[1]] = $entry[2];
    }
}
$t->check(
    sprintf('the javascript asset lists were found (%d)', count($lists)),
    count($lists) >= 2
);

foreach ($lists as $name => $body) {
    $fogCommon = strpos($body, 'js/fog/fog.common.js');
    if (false === $fogCommon) {
        // A list that never loads fog.common.js cannot raise a toast.
        continue;
    }
    $bs = strpos($body, 'js/bootstrap5.bundle.min.js');
    $t->check(
        sprintf('$%s loads bootstrap before fog.common.js', $name),
        false !== $bs && $bs < $fogCommon
    );
}

// ---------------------------------------------------------------------------
// 4. The toast is built as text.
// ---------------------------------------------------------------------------
$t->check(
    'toast title and body are set as text, not HTML',
    (bool)preg_match('/titleEl\.textContent = /', $stripped)
    && (bool)preg_match('/bodyEl\.textContent = /', $stripped)
    && !preg_match('/(titleEl|bodyEl)\.innerHTML/', $stripped)
);

$t->finish();
