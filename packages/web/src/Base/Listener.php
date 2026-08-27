<?php
/**
 * What a hook and an event both are, and nothing else.
 *
 * Hook and Event are separate kinds of thing -- see the table in
 * docs/adr/0017-hook-dispatch-contract.md -- but they share the boilerplate
 * every listener carries: a name, whether it is switched on, and how it logs.
 * That shared part used to be shared by making Hook a subclass of Event,
 * which said the wrong thing about the two and made
 * EventManager::acceptListener()'s `instanceof Event` accept a hook (#1194,
 * #1203). Sharing it as a trait keeps the boilerplate in one place without
 * claiming a kinship that is not there.
 *
 * log() is here rather than on Event because it must keep working from a
 * hook. FOGBase declares a log() of its own with the identical signature and
 * a completely different job -- it writes a history row -- so a hook that
 * lost this one would not fail, it would quietly call that one instead. Two
 * core hooks call self::log(): hookdebugger and template.
 *
 * PHP version 7.4+
 *
 * @category Listener
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * What a hook and an event both are, and nothing else.
 *
 * @category Listener
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
trait Listener
{
    /**
     * The name.
     *
     * @var string
     */
    protected $name;
    /**
     * The description.
     *
     * @var string
     */
    protected $description;
    /**
     * Is this listener active?
     *
     * @var bool
     */
    public $active = true;
    /**
     * Items log level.
     *
     * @var int
     */
    public $logLevel = 0;
    /**
     * Log to file?
     *
     * @var bool
     */
    public $logToFile = false;
    /**
     * Log to browser?
     *
     * @var bool
     */
    public $logToBrowser = true;
    /**
     * Initializes the listener.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!self::$FOGUser->isValid()) {
            self::$FOGUser =& $GLOBALS['currentUser'];
        }
    }
    /**
     * How to log this file.
     *
     * @param string $txt     The text to log.
     * @param int    $curlog  The logLevel setting.
     * @param int    $logfile The logToFile setting.
     * @param int    $logbrow The logToBrowser setting.
     * @param object $obj     The object.
     * @param int    $level   The basic log level.
     *
     * @return void
     */
    public static function log(
        $txt,
        $curlog,
        $logfile,
        $logbrow,
        $obj,
        $level = 1
    ) {
        if (self::$ajax) {
            return;
        }
        $findArr = [
            "#\r#",
            "#\n#",
            '#\s+#',
            '# ,#',
        ];
        $repArr = [
            '',
            ' ',
            ' ',
            ','
        ];
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
        $msg = '%s<div class='
            . '"alert alert-info alert-dismissible fade show">'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '%s</div>%s';
        if (!self::$post && $logbrow) {
            if ($curlog >= $level) {
                printf(
                    $msg,
                    "\n",
                    $txt,
                    "\n"
                );
            }
        }
        $typePath = 'events';
        if ($obj instanceof Hook) {
            $typePath = 'hooks';
        }
        if ($logfile) {
            $log = sprintf(
                '%s%slib%s%s%s%s.log',
                BASEPATH,
                DS,
                DS,
                $typePath,
                DS,
                // Short name: this becomes a log filename.
                self::shortName($obj)
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
}

/*
 * Compatibility alias, for the same reason every other name in this tree has
 * one: a plugin writing `use Listener;` unqualified keeps working.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Listener', 'Listener');
