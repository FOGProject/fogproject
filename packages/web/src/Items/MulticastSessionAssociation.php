<?php
/**
 * The multicast association class.
 *
 * PHP version 7.4+
 *
 * @category MulticastSessionAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
    protected $databaseFields = [
        'id' => 'msaID',
        'msID' => 'msID',
        'taskID' => 'tID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'msID',
        'taskID'
    ];
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\MulticastSessionAssociation', 'MulticastSessionAssociation');
