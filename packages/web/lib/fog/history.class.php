<?php
/**
 * Stores any actions to the database.
 *
 * PHP version 5
 *
 * @category History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Stores any actions to the database.
 *
 * @category History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class History extends FOGController
{
    /**
     * History table name.
     *
     * @var string
     */
    protected $databaseTable = 'history';
    /**
     * History field and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'hID',
        'info' => 'hText',
        'createdBy' => 'hUser',
        'createdTime' => 'hTime',
        'ip' => 'hIP',
    );
}
