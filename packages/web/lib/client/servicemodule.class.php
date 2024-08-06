<?php
/**
 * The service module checks
 *
 * PHP version 5
 *
 * @category ServiceModule
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
            Initiator::sanitizeItems(
                $moduleid
            )
        );
        switch ($mod) {
            case 'dircleaner':
                $mod = 'dircleanup';
                break;
            case 'snapin':
                $mod = 'snapinclient';
                break;
        }
        if (!in_array($mod, $mods)) {
            throw new Exception('#!um');
        }
        $remArr = [
            'dircleanup',
            'usercleanup',
            'clientupdater'
        ];
        $globalModules = (
            !self::$newService ?
            self::getGlobalModuleStatus(
                false,
                true
            ) :
            array_diff(
                self::getGlobalModuleStatus(
                    false,
                    true
                ),
                $remArr
            )
        );
        $globalInfo = self::getGlobalModuleStatus();
        $globalDisabled = array();
        foreach ($globalInfo as $key => $en) {
            if (self::$newService && in_array($key, $remArr)) {
                continue;
            }
            if (!$en) {
                $globalDisabled[] = $key;
            }
            unset($en);
        }
        Route::ids(
            'moduleassociation',
            ['id' => self::$Host->get('modules')],
            'shortName'
        );
        $hostModules = json_decode(Route::getData(), true);
        $hostEnabled = (
            self::$newService ?
            array_diff(
                (array)$hostModules,
                $remArr
            ) :
            $hostModules
        );
        $hostDisabled = array_diff(
            $globalModules,
            $hostEnabled
        );
        if (in_array(
            $mod,
            self::fastmerge(
                $globalDisabled,
                $hostDisabled
            )
        )
        ) {
            throw new Exception(
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
