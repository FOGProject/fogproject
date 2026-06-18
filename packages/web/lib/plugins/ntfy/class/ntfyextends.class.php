<?php
/**
 * The base class of ntfy elements
 *
 * Extends the ntfy elements into the event class.
 *
 * PHP version 5
 *
 * @category NtfyExtends
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The base class of ntfy elements
 *
 * Extends the ntfy elements into the event class.
 *
 * @category NtfyExtends
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class NtfyExtends extends Event
{
    /**
     * The name
     *
     * @var string
     */
    protected $name;
    /**
     * The description
     *
     * @var string
     */
    protected $description;
    /**
     * The event loop
     *
     * @var mixed
     */
    protected static $eventloop;
    /**
     * The elements to use
     *
     * @var mixed
     */
    protected static $elements;
    /**
     * The short description
     *
     * @var mixed
     */
    protected static $shortdesc;
    /**
     * The message
     *
     * @var mixed
     */
    protected static $message;
    /**
     * The item is active
     *
     * @var bool
     */
    public $active;
    /**
     * Initialize the class item
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$eventloop = function (&$Ntfy) {
            self::getClass(
                'NtfyHandler',
                $Ntfy->serverURL,
                $Ntfy->topicEndpoint,
                $Ntfy->credentials
            )->pushNote(
                sprintf(
                    '%s %s',
                    self::$elements['HostName'],
                    _(self::$shortdesc)
                ),
                _(self::$message)
            );
        };
    }
    /**
     * Perform action
     *
     * @param string $event the event to enact
     * @param mixed  $data  the data
     *
     * @return void
     */
    public function onEvent($event, $data)
    {
        self::$elements = $data;
        Route::listem('ntfy');
        $Ntfys = json_decode(
            Route::getData()
        );
        foreach ($Ntfys->data as &$Ntfy) {
            self::$eventloop($Ntfy);
            unset($Ntfy);
        }
    }
}
