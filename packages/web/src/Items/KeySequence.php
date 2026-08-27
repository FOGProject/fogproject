<?php
/**
 * The key sequence class.
 *
 * PHP version 7.4+
 *
 * @category KeySequence
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

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
    protected $databaseFields = [
        'id' => 'ksID',
        'name' => 'ksValue',
        'ascii' => 'ksAscii'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'ascii'
    ];
}
