<?php
/**
 * Adds the subnet group to host.
 *
 * PHP Version 5
 *
 * @category AddSubnetGroupHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the subnet group to host.
 *
 * @category AddSubnetGroupHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSubnetGroupHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSubnetGroupHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Subnet Group to Hosts.';
    /**
     * For posterity
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'subnetgroup';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'REQUEST_CLIENT_INFO',
            [$this, 'addSubnetGroupHost']
        )->register(
            'BOOT_ITEM_NEW_SETTINGS',
            [$this, 'addSubnetGroupHost']
        );
    }
    /**
     * Adds host to group based on subnet.
     *
     * @param mixed $arguments The arguments to evaluate.
     *
     * @return void
     */
    public function addSubnetGroupHost($arguments)
    {
        $Host = $arguments['Host'];
        $mac = $Host->get('mac');

        if (!isset($mac)) {
            return;
        }

        // Setup for tests
        $name = $ipn = $Host->get('name');
        $ip = $Host->get('ip');
        $ipr = self::resolveHostname($name);

        // Perform all tests.
        $ip1t = filter_var($ip, FILTER_VALIDATE_IP);
        $ip2t = filter_var($ipn, FILTER_VALIDATE_IP);
        $ip3t = filter_var($ipr, FILTER_VALIDATE_IP);

        // If resolve hostname returns a valid IP, set IP appropriately.
        // Otherwise, if the name is valid, use it.
        // Otherwise, return if base $ip is false.
        if (false !== $ip3t) {
            $ip = $ipr;
        } elseif (false !== $ip2t) {
            $ip = $ipn;
        } elseif (false === $ip1t) {
            return;
        }

        // Now list our subnet groups.
        Route::listem('subnetgroup');
        $SNGroups = json_decode(Route::getData());
        $hostChanged = false;
        foreach ($SNGroups->data as &$SNGroup) {
            if (in_array($SNGroup->groupID, $Host->get('groups'))) {
                $Host->removeGroup($SNGroup->groupID);
                $hostChanged = true;
            }
            $subnetList = str_replace(' ', '', $SNGroup->subnets);
            $subnets = explode(',', $subnetList);

            foreach ($subnets as &$subnet) {
                if ($this->_ipCIDRCheck($ip, $subnet)) {
                    $Host->addGroup($SNGroup->groupID);
                    $hostChange = true;
                    unset($subnet);
                    continue 2;
                }
                unset($subnet);
            }
            unset($SNGroup);
        }

        if ($hostChanged) {
            $Host->save();
        }
    }
    /**
     * Check if an IP Address complies with a CIDR subnet
     * @credits http://php.net/manual/en/ref.network.php#121090
     *
     * @param string $ip   IP Address
     * @param string $cidr CIDR subnet
     *
     * @return bool
     */
    private function _ipCIDRCheck($ip, $cidr)
    {
        list ($net, $mask) = explode('/', $cidr);
        $ip_net = ip2long($net);
		$ip_mask = ~((1 << (32 - $mask)) - 1);
        $ip_ip = ip2long($ip);
		return (($ip_ip & $ip_mask) == ($ip_net & $ip_mask));
    }
}
