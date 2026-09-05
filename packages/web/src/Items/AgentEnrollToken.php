<?php
/**
 * An admin's pre-approval for fog-agent enrollment.
 *
 * PHP version 7.4+
 *
 * @category AgentEnrollToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * An admin's pre-approval for fog-agent enrollment.
 *
 * Only the sha256 of the token is stored. Uses count down to zero; -1 means
 * unlimited until the expiry. See schema step 416.
 *
 * @category AgentEnrollToken
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AgentEnrollToken extends FOGController
{
    /**
     * The database table.
     *
     * @var string
     */
    protected $databaseTable = 'agentEnrollToken';
    /**
     * The database fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'atID',
        'name' => 'atName',
        'hash' => 'atHash',
        'uses' => 'atUses',
        'expires' => 'atExpires',
        'createdBy' => 'atCreatedBy',
        'created' => 'atCreated'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hash'
    ];
}
