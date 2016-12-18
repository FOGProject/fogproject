<?php
/**
 * Allows Events and defines how they operate.
 * Because of the similarities of use for events and hooks
 * the event class here is the hook base model as well.
 *
 * PHP version 5
 *
 * @category Event
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
    /**
     * The name.
     *
     * @var string
     */
    protected $name;
    /**
     * A description.
     *
     * @var string
     */
    protected $description;
    /**
     * The active flag of this.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The log level.
     *
     * @var int
     */
    public $logLevel = 0;
    /**
     * Whether to store log to file
     *
     * @var bool
     */
    public $logToFile = false;
    /**
     * Whether to show log in browser
     *
     * @var bool
     */
    public $logToBrowser = true;
    /**
     * Initializes the base elements of the event or hook
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!self::$FOGUser->isValid()) {
            self::$FOGUser = new User($_SESSION['FOG_USER']);
        }
    }
    /**
     * How to log this file.
     *
     * @param string $txt   The text to log.
     * @param int    $level The basic log level.
     *
     * @return void
     */
    public function log($txt, $level = 1)
    {
        if (self::$ajax) {
            return;
        }
        $findArr = array(
            "#\r#",
            "#\n#",
            '#\s+#',
            '# ,#',
        );
        $repArr = array(
            '',
            ' ',
            ' ',
            ','
        );
        $txt = preg_replace($findArr, $repArr, $txt);
        $txt = trim($txt);
        if (empty($txt)) {
            return;
        }
        $txt = sprintf(
            '[%s] %s',
            self::niceDate()->format('Y-m-d H:i:s'),
            $txt
        );
        $msg = '%s<div class="debug-hook">%s</div>%s';
        if (!self::$post && $this->logToBrowser) {
            if ($this->logLevel >= $level) {
                printf(
                    $msg,
                    "\n",
                    $txt,
                    "\n"
                );
            }
        }
        if ($this instanceof Hook) {
            $typePath = 'hooks';
        } elseif ($this instanceof Event) {
            $typePath = 'events';
        }
        if ($this->logToFile) {
            $log = sprintf(
                '%s/lib/%s/%s.log',
                BASEPATH,
                $typePath,
                get_class($this)
            );
            $logtxt = sprintf(
                "[%s] %s\r\n",
                self::niceDate()->format('d-m-Y H:i:s'),
                $txt
            );
            file_put_contents(
                $log,
                $logtxt,
                FILE_APPEND | LOCK_EX
            );
        }
    }
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
     * This is a default function for events only.
     *
     * @param string $event the event to work off.
     * @param mixed  $data  the data, though unused.
     *
     * @return mixed
     */
    public function onEvent($event, $data)
    {
        printf(
            '%s %s',
            $event,
            _('Registered')
        );
    }
}
