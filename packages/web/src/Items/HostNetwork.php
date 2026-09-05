<?php
/**
 * Which links a host is actually on (design 0011).
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
 * One address on one of a host's interfaces.
 *
 * The contrast to draw is with `hosts.hostIP`, which this does not replace:
 * hostIP is one address with no prefix and no interface behind it, resolved
 * whenever FOG last looked. These rows are what the machine reported about
 * its own links, prefix and all, which is the difference between knowing
 * where a host answered from and knowing which link it is ON.
 *
 * One row per host per interface ADDRESS. An interface with two addresses
 * is on two links and can broadcast on both, so it is two rows.
 *
 * @category WakeRelay
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostNetwork extends FOGController
{
    /**
     * The hostNetwork table.
     *
     * @var string
     */
    protected $databaseTable = 'hostNetwork';
    /**
     * The hostNetwork fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hnID',
        'hostID' => 'hnHostID',
        'name' => 'hnName',
        'mac' => 'hnMAC',
        'ipv4' => 'hnIPv4',
        'prefix' => 'hnPrefix',
        'network' => 'hnNetwork',
        'broadcast' => 'hnBroadcast',
        'up' => 'hnUp',
        'wireless' => 'hnWireless',
        'observedAt' => 'hnObservedAt'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'host'
    ];
    /**
     * Return the associated host object.
     *
     * @return object
     */
    public function getHost()
    {
        if (!array_key_exists('host', $this->data)) {
            $this->set('host', new Host($this->get('hostID')));
        }
        return $this->get('host');
    }
    /**
     * The link in CIDR notation, for a report an admin reads.
     *
     * @return string
     */
    public function link()
    {
        $network = trim((string)$this->get('network'));
        if ('' === $network) {
            return '';
        }
        return $network . '/' . (int)$this->get('prefix');
    }
    /**
     * Whether this row can carry a broadcast for a neighbor.
     *
     * Three things have to be true and each has bitten a real WoL
     * deployment: the interface has to be up (a configured NIC with the
     * cable out sends nothing), the link has to HAVE a broadcast address
     * (a /31 point-to-point pair and a /32 host route do not), and it must
     * not be wireless -- an access point will not bridge a broadcast to a
     * station that is asleep and therefore not associated, so a wireless
     * relay is a packet sent into a link the target has already left.
     *
     * @return bool
     */
    public function canRelay()
    {
        return (bool)$this->get('up')
            && '' !== trim((string)$this->get('broadcast'))
            && !(bool)$this->get('wireless');
    }
}
