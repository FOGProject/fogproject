<?php
/**
 * The ntfy database and object definer
 *
 * PHP version 5
 *
 * @category Ntfy
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The ntfy database and object definer
 *
 * @category Ntfy
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Ntfy extends FOGController
{
    /**
     * The ntfy table.
     *
     * @var string
     */
    protected $databaseTable = 'ntfy';
    /**
     * The database fields and commonized items.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'nID',
        'serverURL' => 'nServerURL',
        'topicEndpoint' => 'nTopicEndpoint',
        'credentials' => 'nCredentials',
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'serverURL',
        'topicEndpoint',
    ];
}
