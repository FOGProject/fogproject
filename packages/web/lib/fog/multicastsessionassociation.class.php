<?php
/**
 * The multicast association class.
 *
 * PHP version 5
 *
 * @category MulticastSessionAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The multicast association class.
 *
 * @category MulticastSessionAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSessionAssociation extends FOGController
{
    /**
     * The association table name.
     *
     * @var string
     */
    protected $databaseTable = 'multicastSessionsAssoc';
    /**
     * The association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'msaID',
        'msID' => 'msID',
        'taskID' => 'tID',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'msID',
        'taskID',
    );
    /**
     * Return the multicast session object.
     *
     * @return object
     */
    public function getMulticastSession()
    {
        return new MulticastSession($this->get('msID'));
    }
    /**
     * Return the task object.
     *
     * @return object
     */
    public function getTask()
    {
        return new Task($this->get('taskID'));
    }
}
