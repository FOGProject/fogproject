<?php
/**
 * Tells the client if there's a task waiting for the host
 *
 * PHP version 5
 *
 * @category Jobs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Tells the client if there's a task waiting for the host
 *
 * @category Jobs
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Jobs extends FOGClient implements FOGClientSend
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
        $Task = $this->Host->get('task');
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
        return array($field => $answer);
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $Task = $this->Host->get('task');
        if ($Task->isInitNeededTasking()) {
            $this->send = '#!ok';
        } else {
            throw new Exception('#!nj');
        }
    }
}
