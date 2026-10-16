<?php
/**
 * Route's secret and server-owned maps see a listener that registers late.
 *
 * Boot builds the secret map before it registers a single plugin hook:
 * LoadGlobals runs FOGCore::setEnv(), whose getActivePlugins() lists the
 * plugins through Route, and the hook-event lookup inside that list asks
 * unfilterableFields(), which builds sensitiveFieldMap(). HookManager::load()
 * runs after. The map was memoized at that first build and kept for the
 * request, so no plugin's API_SENSITIVE_FIELDS listener ever reached it, and
 * the API emitted every plugin-declared secret: the ldap and oidc plugins'
 * own, and Directory Groups' bind and join passwords.
 *
 * This test builds each map with no listener, registers one, and asks again
 * WITHOUT resetting the memo -- the step production never takes.
 *
 * Requires no database.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('sensitive-map-sees-late-listeners');
FogTestHarness::fakeDb();

use FOG\Base\Hook;
use FOG\Base\HookManager;
use FOG\Router\Route;

$t = new FogChecks();

/**
 * A plugin's hook, registered after the maps were first built.
 */
class LatePluginHook extends Hook
{
    public $name = 'LatePluginHook';
    public $description = 'test double';
    public $active = true;

    /**
     * Declares a secret the API must never emit.
     *
     * @param mixed $arguments the tier maps
     *
     * @return void
     */
    public function declareSecrets($arguments)
    {
        $arguments['always']['widget'][] = 'secret';
    }
    /**
     * Declares a field only the server writes.
     *
     * @param mixed $arguments the field map
     *
     * @return void
     */
    public function declareOwned($arguments)
    {
        $arguments['fields']['widget'][] = 'ownerID';
    }
}

$hooks = new HookManager();
FogTestHarness::setStatic('FOGBase', 'HookManager', $hooks);
FogTestHarness::setStatic('Route', '_sensitiveMap', null);
FogTestHarness::setStatic('Route', '_serverOwnedMap', null);

// Boot: the maps are built while no plugin hook is registered yet.
$early = Route::sensitiveFieldMap();
$t->check(
    'the map built at boot holds no plugin class',
    !isset($early['always']['widget'])
);
$t->check(
    'the server-owned map built at boot holds no plugin class',
    [] === Route::serverOwnedFields('widget')
);

// HookManager::load(): the plugin's hook registers.
$hook = new LatePluginHook();
$hooks->register('API_SENSITIVE_FIELDS', [$hook, 'declareSecrets']);
$hooks->register('API_SERVER_OWNED_FIELDS', [$hook, 'declareOwned']);

$t->check(
    'the secret map sees the plugin\'s secret once its hook registers',
    in_array('secret', (array)(Route::sensitiveFieldMap()['always']['widget'] ?? []), true)
);
$t->check(
    'so the emitter strips it',
    !array_key_exists(
        'secret',
        Route::stripSensitivePayload(
            ['_lang' => 'widget', 'name' => 'w1', 'secret' => 'value']
        )
    )
);
$t->check(
    'the server-owned map sees the plugin\'s field once its hook registers',
    ['ownerID'] === Route::serverOwnedFields('widget')
);
$t->check(
    'core tiers survive the rebuild',
    isset(Route::sensitiveFieldMap()['always']['storagenode'])
);

$t->finish();
