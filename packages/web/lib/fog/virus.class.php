<?php
/**
 * Virus handler class (informative).
 *
 * PHP version 5
 *
 * @category Virus
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Virus handler class (informative).
 *
 * @category Virus
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Virus extends FOGController
{
    /**
     * The virus table.
     *
     * @var string
     */
    protected $databaseTable = 'virus';
    /**
     * The virus fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'vID',
        'name' => 'vName',
        'mac' => 'vHostMAC',
        'file' => 'vOrigFile',
        'date' => 'vDateTime',
        'mode' => 'vMode',
        'anon2' => 'vAnon2',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'mac',
        'file',
        'date',
    );
}
