<?php
/**
 * The base class of pushbullet elements
 *
 * Extends the pushbullet elements into the event class.
 *
 * PHP version 5
 *
 * @category PushbulletExtends
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/license/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The base class of pushbullet elements
 *
 * Extends the pushbullet elements into the event class.
 *
 * @category PushbulletExtends
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/license/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class PushbulletExtends extends Event
{
    /**
     * The name of the pushbullet
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
        self::$eventloop = function (&$Pushbullet) {
            self::getClass(
                'PushbulletHandler',
                $Pushbullet->token
            )->pushNote(
                '',
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
        Route::listem('pushbullet');
        $Pushbullets = json_decode(
            Route::getData()
        );
        foreach ($Pushbullets->data as &$Pushbullet) {
            self::$eventloop($Pushbullet);
            unset($Pushbullet);
        }
    }
}
