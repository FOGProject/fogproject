<?php
/**
 * Tells the client if there's a task waiting for the host
 *
 * PHP version 7.4+
 *
 * @category Jobs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

/**
 * Tells the client if there's a task waiting for the host
 *
 * @category Jobs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Jobs extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'taskreboot';
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        $Task = self::$Host->get('task');
        $script = strtolower(self::$scriptname);
        $script = trim($script);
        $script = basename($script);
        if ($script === 'jobs.php') {
            $field = 'error';
        } else {
            $field = 'job';
        }
        if ($Task->isInitNeededTasking()) {
            $field = 'job';
            if ($script === 'jobs.php') {
                $answer = 'ok';
            } else {
                $answer = true;
            }
        } else {
            if ($script === 'jobs.php') {
                $answer = 'nj';
            } else {
                $answer = false;
            }
        }
        return [$field => $answer];
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Jobs', 'Jobs');
