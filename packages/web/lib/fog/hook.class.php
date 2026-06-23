<?php
/**
 * Hooks allow customization between different aspects.
 *
 * While not everything is hookable, there is quite a lot
 * that is able to be customized.
 *
 * Most of the accessible elements are handled from the event class.
 *
 * PHP version 5
 *
 * @category Hook
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hooks allow customization between different aspects.
 *
 * While not everything is hookable, there is quite a lot
 * that is able to be customized.
 *
 * @category Hook
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class Hook extends Event
{
    /**
     * Function enables reportTypes
     * to allow plugins, and all hooks really, to tie into
     * report structures.
     *
     * @param mixed $arguments the item to tie into
     *
     * @return void
     */
    public function reportTypes($arguments)
    {
        $arguments['types'][$this->node] = 4;
    }
    /**
     * Registers a set of hook callbacks, but only when this hook's plugin
     * node is actually installed.
     *
     * Replaces the install-guard + register chain boilerplate repeated in
     * nearly every plugin hook constructor. Each registration is an ordered
     * [event, method] pair; pairs run in order, and the same event may
     * appear more than once (each adds another callback), so duplicates are
     * preserved exactly as a hand-written register() chain would be.
     *
     * @param array $registrations list of [eventName, methodName] pairs
     *
     * @return void
     */
    protected function registerInstalled(array $registrations)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        foreach ($registrations as $registration) {
            list($event, $method) = $registration;
            self::$HookManager->register($event, [$this, $method]);
        }
    }
}
