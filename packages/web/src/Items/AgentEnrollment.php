<?php
/**
 * One fog-agent's request to be issued a certificate.
 *
 * PHP version 7.4+
 *
 * @category AgentEnrollment
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * One fog-agent's request to be issued a certificate.
 *
 * Keyed by the key's fingerprint, one row per agent key, refreshed on every
 * repeat of the request while it waits. See schema step 416 for the fields.
 *
 * @category AgentEnrollment
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AgentEnrollment extends FOGController
{
    const STATE_PENDING = 'pending';
    const STATE_ISSUED = 'issued';
    const STATE_DENIED = 'denied';

    /**
     * The database table.
     *
     * @var string
     */
    protected $databaseTable = 'agentEnrollment';
    /**
     * The database fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'aeID',
        'hostID' => 'aeHostID',
        'fingerprint' => 'aeFingerprint',
        'csr' => 'aeCSR',
        'identity' => 'aeIdentity',
        'hostname' => 'aeHostname',
        'os' => 'aeOS',
        'arch' => 'aeArch',
        'agentVersion' => 'aeAgentVersion',
        'remoteIP' => 'aeRemoteIP',
        'reason' => 'aeReason',
        'state' => 'aeState',
        'cert' => 'aeCert',
        'created' => 'aeCreated',
        'updated' => 'aeUpdated',
        'decided' => 'aeDecided',
        'decidedBy' => 'aeDecidedBy',
        'decidedVia' => 'aeDecidedVia'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'fingerprint',
        'csr'
    ];
}
