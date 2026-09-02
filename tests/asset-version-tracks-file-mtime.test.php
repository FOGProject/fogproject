<?php
/**
 * The ?ver= on every stylesheet and script tag follows the file.
 *
 * FOG_BCACHE_VER was bumped by hand when a script changed, and it was not
 * bumped for the search-box change in #1675 -- or for any change since it
 * reached 359 -- so with the 30-day max-age on static files a returning
 * browser ran the previous fog.common.js against the new server. The
 * version now carries the file's mtime, which a deploy cannot forget to
 * change. This pins the helper's resolution rules and that both tag
 * emitters and the redirect script actually call it: a grep for the bare
 * constant in either would pass with the helper written and unused.
 *
 * PHP version 7.4+
 */
require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('asset-version');
$t = new FogChecks();
// The harness does not run System::init(); production defines this there.
if (!defined('FOG_BCACHE_VER')) {
    define('FOG_BCACHE_VER', 359);
}

$web = realpath(__DIR__ . '/../packages/web');
$js = $web . '/management/js/fog/fog.common.js';
$css = $web . '/management/css/adminlte4.min.css';

$t->check(
    'a script path resolves to constant.mtime',
    FOG_BCACHE_VER . '.' . filemtime($js)
    === \FOG\Base\Page::assetVersion('js/fog/fog.common.js')
);
$t->check(
    'a stylesheet path written as ../management/... resolves too',
    FOG_BCACHE_VER . '.' . filemtime($css)
    === \FOG\Base\Page::assetVersion('../management/css/adminlte4.min.css')
);
$t->check(
    'a plugin asset path (../lib/...) is resolved against the same root',
    // No plugin tree in the repo checkout, so this exercises the fallback
    // branch for a relative path that resolves to nothing.
    (string)FOG_BCACHE_VER === \FOG\Base\Page::assetVersion('../lib/plugins/none/js/x.js')
);
$t->check(
    'an absolute URL gets the bare constant',
    (string)FOG_BCACHE_VER === \FOG\Base\Page::assetVersion('https://cdn.example/x.js')
);
$t->check(
    'a protocol-relative URL gets the bare constant',
    (string)FOG_BCACHE_VER === \FOG\Base\Page::assetVersion('//cdn.example/x.js')
);
$t->check(
    'a missing file gets the bare constant',
    (string)FOG_BCACHE_VER === \FOG\Base\Page::assetVersion('js/fog/does-not-exist.js')
);
$t->check(
    'a path cannot escape the web tree',
    (string)FOG_BCACHE_VER === \FOG\Base\Page::assetVersion('/etc/passwd')
);

// The emitters. Both loops in the page template, and the redirect script.
$tpl = (string)file_get_contents($web . '/management/other/index.php');
$t->check(
    'the stylesheet loop asks the helper',
    false !== strpos($tpl, "'?ver=' . Page::assetVersion(\$stylesheet) . '\" rel=\"stylesheet\"")
);
$t->check(
    'the script loop asks the helper',
    false !== strpos($tpl, "'?ver=' . Page::assetVersion(\$javascript) . '\" type=\"text/javascript\"")
);
$t->check(
    'no tag in the template carries the bare constant',
    false === strpos($tpl, "'?ver=' . FOG_BCACHE_VER")
);
$page = (string)file_get_contents($web . '/src/Base/Page.php');
$t->check(
    'the redirect script tag asks the helper',
    false !== strpos($page, "redirect.js?ver=' . self::assetVersion('js/fog/redirect.js')")
    && false === strpos($page, "redirect.js?ver=' . FOG_BCACHE_VER")
);

// The AJAX navigation headers carry the same per-file versions, so the
// client's loaded-vs-needed delta compares equal strings.
foreach (['X-FOG-Stylesheets', 'X-FOG-JavaScripts', 'X-FOG-Common-JavaScripts', 'X-FOG-Once-JavaScripts'] as $h) {
    $at = strpos($page, "'$h: '");
    $t->check(
        "$h is built from versioned paths",
        false !== $at
        && false !== strpos(substr($page, $at, 200), 'self::_versionedAssets(')
    );
}
$versioned = new \ReflectionMethod('FOG\Base\Page', '_versionedAssets');
$versioned->setAccessible(true);
$t->check(
    '_versionedAssets() appends ?ver= per path and drops empties',
    ['js/fog/fog.common.js?ver=' . FOG_BCACHE_VER . '.' . filemtime($js)]
    === $versioned->invoke(null, ['js/fog/fog.common.js', '', null])
);
// And the client does not suffix a path twice.
$jsSrc = (string)file_get_contents($js);
$t->check(
    'the stylesheet delta compares the already-versioned path',
    false === strpos($jsSrc, 'var style = styles[styleIndex] + "?ver=" + assetVersion;')
    && false !== strpos($jsSrc, 'var style = styles[styleIndex];')
);

$t->finish();
