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
class ServiceModule extends FOGClient implements FOGClientSend
{
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $mods = self::getGlobalModuleStatus(
            false,
            true
        );
        $mod = strtolower(
            Initiator::sanitizeItems(
                $_REQUEST['moduleid']
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
        $remArr = array(
            'dircleanup',
            'usercleanup',
            'clientupdater'
        );
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
        foreach ((array)$globalInfo as $key => &$en) {
            if (self::$newService && in_array($key, $remArr)) {
                continue;
            }
            if (!$en) {
                $globalDisabled[] = $key;
            }
            unset($en);
        }
        $hostModules = self::getSubObjectIDs(
            'Module',
            array('id' => $this->Host->get('modules')),
            'shortName'
        );
        $hostEnabled = (
            self::$newService ?
            array_diff(
                (array)$hostModules,
                $remArr
            ) :
            $hostModules
        );
        $hostDisabled = array_diff(
            (array)$globalModules,
            $hostEnabled
        );
        if (in_array(
            $mod,
            self::fastmerge(
                (array)$globalDisabled,
                (array)$hostDisabled
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
