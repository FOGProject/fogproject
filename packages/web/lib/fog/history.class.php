<?php
/**
 * Stores any actions to the database.
 *
 * PHP version 7.4+
 *
 * @category History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
    protected $databaseFields = [
        'id' => 'hID',
        'info' => 'hText',
        'createdBy' => 'hUser',
        'createdTime' => 'hTime',
        'ip' => 'hIP'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\History', 'History');
