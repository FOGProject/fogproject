<?php
/**
 * AdminLTE's card tools keep working after an AJAX page load.
 *
 * adminlte4 binds collapse, remove and maximize exactly once, inside its own
 * DOMContentLoaded:
 *
 *   document.querySelectorAll('[data-lte-toggle="card-collapse"]')
 *           .forEach(el => el.addEventListener('click', ...))
 *
 * It is not delegated, and doPageLoad() replaces #ajaxPageWrapper wholesale on
 * every sidebar click -- so a card that arrives by navigation has no handler
 * on its toggle. ClientManagement is the shipped page this bites: node=client
 * IS a sidebar link, so both its collapse buttons were dead. It went
 * unreported because a card that starts OPEN only degrades to "cannot be
 * collapsed"; a card rendered `collapsed-card` is the loud version, because
 * its body is display:none from AdminLTE's own CSS and the only thing that
 * removes the class is the listener that was never attached (GH-1600).
 *
 * fog.common.js takes the three tools over with one delegated listener in the
 * CAPTURE phase. Capture matters twice over: it runs before the element's own
 * listener whether or not AdminLTE attached one, and stopPropagation() there
 * keeps the event from reaching the target at all -- so a card that WAS
 * present at DOMContentLoaded does not get toggled twice by two handlers and
 * end up looking just as dead.
 *
 * WHAT THIS CAN AND CANNOT PIN. The behavior was proven in a browser against
 * the shadow tree: node=client reached through the sidebar, collapse clicked,
 * card collapses and re-expands; the same page with this block deleted, same
 * route, click does nothing; and the same page reached by a FULL load, where
 * AdminLTE's own listener also exists, still toggles once per click. None of
 * that is reachable from a PHP suite with no JS engine. What is pinned here is
 * the contract between FOG and the vendored library -- every tool AdminLTE
 * binds is a tool FOG takes over, and every method FOG names still exists on
 * the class it calls. Those are the parts an AdminLTE upgrade breaks silently,
 * and the expectations are read out of the vendored file rather than restated,
 * so a fourth tool or a renamed method fails this rather than passing it.
 *
 * Usage: php tests/card-tools-survive-ajax-nav.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('card-tools-survive-ajax-nav');

$t = new FogChecks();
$js = dirname(__DIR__) . '/packages/web/management/js';
$lte = (string) file_get_contents($js . '/adminlte4.min.js');
$fog = (string) file_get_contents($js . '/fog/fog.common.js');

/*
 * 1. The takeover exists and is delegated on the document in the capture
 *    phase. Both halves are load-bearing and neither is obvious to read, so
 *    both are named: without capture the handler runs after AdminLTE's, and
 *    without stopPropagation both run and the card toggles twice.
 */
$block = '';
if (preg_match('#/\*\*\s+\* AdminLTE\'s card tools.*?\n\}\)\(\);#s', $fog, $m)) {
    $block = $m[0];
}
$t->check(
    'fog.common.js takes the card tools over',
    '' !== $block
);
// Assert on CODE, never on the comment above it. Every string checked below
// -- stopPropagation, the capture flag, all three tool names -- is also
// written out in that docblock, so matching the block whole would pass
// against a takeover whose body had been deleted. Two of these checks did
// exactly that until a mutation run caught them.
$code = (string) preg_replace('#/\*.*?\*/#s', '', $block);
$code = (string) preg_replace('#^\s*//.*$#m', '', $code);
$t->check(
    'delegated on document, not per element',
    false !== strpos($code, 'document.addEventListener(\'click\'')
);
$t->check(
    'in the capture phase, so it beats the element\'s own listener',
    (bool) preg_match('#\},\s*true\s*\);#', $code)
);
$t->check(
    'and stops the event there, so AdminLTE\'s listener cannot also fire',
    false !== strpos($code, 'stopPropagation()')
);

/*
 * 2. Every card tool AdminLTE binds is one FOG takes over. Read out of the
 *    vendored file: an upgrade that adds a fourth fails this rather than
 *    shipping one more dead button.
 */
preg_match_all('/data-lte-toggle="(card-[a-z]+)"/', $lte, $m);
$bound = array_values(array_unique($m[1]));
$t->check(
    'the vendored AdminLTE still binds card tools by that attribute',
    count($bound) >= 3
);
foreach ($bound as $tool) {
    $t->check(
        'FOG takes over ' . $tool,
        false !== strpos($code, '"' . $tool . '"')
    );
}

/*
 * 3. Every CardWidget method FOG names still exists on the class it calls,
 *    and the class is still exported. FOG reaches it as
 *    window.adminlte.CardWidget; nothing else on the page does, so a renamed
 *    export would be a silent no-op on every card tool at once.
 */
$t->check(
    'AdminLTE still exports CardWidget',
    (bool) preg_match('/\be\.CardWidget=/', $lte)
);
preg_match_all('/\'(card-[a-z]+)\':\s*\'([A-Za-z]+)\'/', $code, $m);
$mapped = array_combine($m[1], $m[2]);
$t->check(
    'FOG maps every tool it handles to a method',
    count($mapped) === count($bound)
);
foreach ($mapped as $tool => $method) {
    $t->check(
        $tool . ' calls CardWidget.' . $method . '(), which still exists',
        (bool) preg_match('/\b' . preg_quote($method, '/') . '\(\)\{/', $lte)
    );
}

/*
 * 4. Changing shipped JS without moving the asset version leaves every
 *    browser that has already loaded the old file on the old file until a
 *    hard refresh. Hygiene rather than correctness, and cheap to hold.
 */
$sys = (string) file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Base/System.php'
);
preg_match('/FOG_BCACHE_VER\',\s*(\d+)/', $sys, $m);
$t->check(
    'FOG_BCACHE_VER is past the version this shipped against',
    isset($m[1]) && (int) $m[1] >= 358
);

$t->finish();
