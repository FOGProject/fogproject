<?php
/**
 * Allows Events and defines how they operate.
 * Because of the similarities of use for events and hooks
 * the event class here is the hook base model as well.
 *
 * PHP version 7.4+
 *
 * @category Event
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

/**
 * Allows Events and defines how they operate.
 * Because of the similarities of use for events and hooks
 * the event class here is the hook base model as well.
 *
 * @category Event
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class Event extends FOGBase
{
    use Listener;

    /**
     * Simply adds the run method, though should be more defined.
     *
     * @param mixed $arguments the item to work from
     *
     * @return mixed
     */
    public function run($arguments)
    {
    }
    /**
     * What EventManager::notify() calls. Subclasses override it.
     *
     * Deliberately empty. It used to printf the event name into the response,
     * which meant an event class that had not overridden it wrote text into
     * whatever output was being produced -- a page, or a client protocol reply
     * that the fog-client parses positionally. Every bundled plugin event
     * overrides this; src/Events/HostList.php, the one core event, does not, and
     * it is only inactive that saved it.
     *
     * A listener that does nothing should do nothing.
     *
     * @param string $event the event to work off.
     * @param mixed  $data  the data, though unused.
     *
     * @return mixed
     */
    public function onEvent($event, $data)
    {
    }
}
