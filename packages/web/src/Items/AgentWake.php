<?php
/**
 * One machine asked to wake another (design 0011).
 *
 * PHP version 7.4+
 *
 * @category WakeRelay
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * A pending or finished wake relay.
 *
 * One row per (machine to wake, machine asked to send it). A wake ordered
 * now cannot be relayed now -- the neighboring agent finds out when it
 * next polls -- so the request has to be written down, and FOG has had
 * nowhere to write it.
 *
 * @category WakeRelay
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AgentWake extends FOGController
{
    /**
     * The agentWake table.
     *
     * @var string
     */
    protected $databaseTable = 'agentWake';
    /**
     * The agentWake fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'awID',
        'targetID' => 'awTargetID',
        'senderID' => 'awSenderID',
        'requestedAt' => 'awRequestedAt',
        'expiresAt' => 'awExpiresAt',
        'status' => 'awStatus',
        'packets' => 'awPackets',
        'detail' => 'awDetail',
        'reportedAt' => 'awReportedAt',
        'requestedBy' => 'awRequestedBy'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'targetID',
        'senderID'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'target',
        'sender'
    ];
    /**
     * The host being woken.
     *
     * @return object
     */
    public function getTarget()
    {
        if (!array_key_exists('target', $this->data)) {
            $this->set('target', new Host($this->get('targetID')));
        }
        return $this->get('target');
    }
    /**
     * The host asked to send the packet.
     *
     * @return object
     */
    public function getSender()
    {
        if (!array_key_exists('sender', $this->data)) {
            $this->set('sender', new Host($this->get('senderID')));
        }
        return $this->get('sender');
    }
}
