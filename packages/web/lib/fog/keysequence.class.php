<?php
/**
 * The key sequence class.
 *
 * PHP version 5
 *
 * @category KeySequence
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The key sequence class.
 *
 * @category KeySequence
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class KeySequence extends FOGController
{
    /**
     * The keysequence table name.
     *
     * @var string
     */
    protected $databaseTable = 'keySequence';
    /**
     * The keysequence field and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'ksID',
        'name' => 'ksValue',
        'ascii' => 'ksAscii',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'ascii',
    );
}
