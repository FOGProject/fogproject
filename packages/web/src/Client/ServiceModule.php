<?php
/**
 * The service module checks
 *
 * PHP version 7.4+
 *
 * @category ServiceModule
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Router\Route;

/**
 * The service module checks
 *
 * @category ServiceModule
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ServiceModule extends FOGClient
{
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     * @throws Exception
     */
    public function send()
    {
        $mods = self::getGlobalModuleStatus(
            false,
            true
        );
        $moduleid = filter_input(INPUT_POST, 'moduleid');
        if (!$moduleid) {
            $moduleid = filter_input(INPUT_GET, 'moduleid');
        }
        $mod = strtolower(
            \Initiator::sanitizeItems(
                $moduleid
            )
        );
        switch ($mod) {
            case 'snapin':
                $mod = 'snapinclient';
                break;
        }
        if (!in_array($mod, $mods)) {
            throw new \Exception('#!um');
        }
        $globalModules = self::getGlobalModuleStatus(
            false,
            true
        );
        $globalInfo = self::getGlobalModuleStatus();
        $globalDisabled = array();
        foreach ($globalInfo as $key => $en) {
            if (!$en) {
                $globalDisabled[] = $key;
            }
            unset($en);
        }
        // RESOLVED, not host-direct. ADR 0038: a group grants a module, so
        // what this host actually runs is its own ON rows plus every grant
        // from a group it is in, minus anything it has turned OFF.
        // get('modules') is the edit view and would silently ignore grants.
        $hostModules = Route::getIds(
            'module',
            ['id' => self::$Host->resolvedModules()],
            'shortName'
        );
        $hostDisabled = array_diff(
            $globalModules,
            (array)$hostModules
        );
        if (in_array(
            $mod,
            self::fastmerge(
                $globalDisabled,
                $hostDisabled
            )
        )
        ) {
            throw new \Exception(
                sprintf(
                    "#!n%s\n",
                    in_array($mod, $globalDisabled) ?
                    'g' :
                    'h'
                )
            );
        }
        $this->send = "#!ok\n";
    }
}
