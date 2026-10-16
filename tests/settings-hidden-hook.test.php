<?php
/**
 * A plugin can take a setting off the FOG Settings page, and only while it
 * is installed (GH-1879).
 *
 * SETTINGS_HIDDEN lets a plugin that edits some settings on its own page
 * hide them here, so an admin is not shown two places to change one value.
 * The Directory Groups plugin uses it for the FOG_DIRECTORY_* connection
 * settings, which it replaces with a list of domains.
 *
 * What is pinned:
 * - with no listener, every setting renders, as before;
 * - a listener's key is not rendered, and its neighbors still are;
 * - the save path skips the same keys, so a stale form cannot overwrite a
 *   value the plugin now owns. That half reads php://input and checks CSRF,
 *   so it is pinned on the source, next to the render half it must match.
 *
 * Usage: php tests/settings-hidden-hook.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('settings-hidden-hook');
$db = FogTestHarness::fakeDb();

$t = new FogChecks();

// The settings page's one SELECT, answered with three rows in one category.
$db->responder = function ($sql) {
    if (false === strpos($sql, 'settingCategory')) {
        return null;
    }
    $rows = [];
    $n = 1;
    foreach (['FOG_DIRECTORY_BASE_DN', 'FOG_DIRECTORY_LDAP_URI', 'FOG_DIRECTORY_PLACEMENT_ENABLED'] as $key) {
        $rows[] = [
            'settingID' => $n++,
            'settingKey' => $key,
            'settingDesc' => 'test',
            'settingValue' => '',
            'settingCategory' => 'Directory'
        ];
    }
    return $rows;
};

/**
 * Renders the settings body the page would print.
 *
 * @return string
 */
function rendered()
{
    $page = (new ReflectionClass(\FOG\Pages\FOGConfigurationPage::class))
        ->newInstanceWithoutConstructor();
    $m = new ReflectionMethod($page, '_renderSettings');
    $m->setAccessible(true);
    return (string)$m->invoke($page);
}

$html = rendered();
$t->check(
    'with no listener, the directory settings render',
    false !== strpos($html, 'FOG_DIRECTORY_LDAP_URI')
        && false !== strpos($html, 'FOG_DIRECTORY_BASE_DN')
);

$hooks = new \FOG\Base\HookManager();
$hooks->register(
    'SETTINGS_HIDDEN',
    function (&$args) {
        $args['hidden']['FOG_DIRECTORY_LDAP_URI'] = true;
        $args['hidden']['FOG_DIRECTORY_BASE_DN'] = true;
    }
);
FogTestHarness::setStatic('FOGBase', 'HookManager', $hooks);

$html = rendered();
$t->check(
    'a key a listener hides is not rendered',
    false === strpos($html, 'FOG_DIRECTORY_LDAP_URI')
        && false === strpos($html, 'FOG_DIRECTORY_BASE_DN')
);
$t->check(
    'a key it does not name still is',
    false !== strpos($html, 'FOG_DIRECTORY_PLACEMENT_ENABLED')
);

$src = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Pages/FOGConfigurationPage.php'
);
$post = substr($src, (int)strpos($src, 'public function settingsPost()'));
$post = substr($post, 0, (int)strpos($post, "\n    public function ", 10));
$t->check(
    'the save path skips a hidden key before it can be written',
    1 === preg_match(
        '/\$name = trim\(\$Setting->name\);\s*(\/\/[^\n]*\n\s*)*if \(isset\(\$meta\[\'hidden\'\]\[\$name\]\)\) \{\s*continue;/',
        $post
    )
);

$t->finish();
