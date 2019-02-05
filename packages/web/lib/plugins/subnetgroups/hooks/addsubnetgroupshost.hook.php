<?php
/**
 * Adds the SubnetGroups host to group.
 *
 * PHP version 5
 *
 * @category AddSubnetGroupsHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the SubnetGroups host to group.
 *
 * @category AddSubnetGroupsHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSubnetgroupsHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSubnetgroupsHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add SubnetGroups to Hosts';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'subnetgroups';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'REQUEST_CLIENT_INFO',
                array(
                    $this,
                    'addSubnetgroupsHost'
                )
            );
    }
    /**
     * Adds subnetgroups host to group.
     *
     * @param mixed $arguments The arguments to evaluate.
     *
     * @return void
     */
    public function addSubnetgroupsHost($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }

        $Host = $arguments['Host'];
        $mac = $Host->get('mac');
        if (!isset($mac)) {
            return;
        }

        $name = $Host->get('name');
        $ip = $Host->get('ip');

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = $name;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = self::resolveHostname($name);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $subnetGroups = self::getSubObjectIDs(
            'SubnetGroups',
            array(),
            array('subnets', 'groupID')
        );

        $hostChanged = false;

        foreach ($Host->get('groups') as $hostGroup) {
            if (in_array($hostGroup, $subnetGroups['groupID'])) {
                $Host->removeGroup($hostGroup);
                $hostChanged = true;
            }
        }

        foreach ($subnetGroups['subnets'] as $index => $subnetList) {

            $group = $subnetGroups['groupID'][$index];
            $subnetList = str_replace(' ', '', $subnetList);
            $subnets = explode(',', $subnetList);

            foreach ($subnets as $subnet) {
                if ($this->ipCIDRCheck($ip, $subnet)) {
                    $Host->addGroup($group);
                    $hostChanged = true;
                    continue 2;
                }
            }
        }

        if ($hostChanged) {
            $Host->save();
        }

    }
    /**
     * Check if an IP Address complies with a CIDR subnet
     *
     * @credits http://php.net/manual/en/ref.network.php#121090
     *
     * @param string  $IP       IP Address
     * @param string $CIDR      CIDR Subnet
     *
     * @return bool
     */
    private function ipCIDRCheck ($IP, $CIDR)
    {
        list ($net, $mask) = explode ('/', $CIDR);
        $ip_net = ip2long ($net);
        $ip_mask = ~((1 << (32 - $mask)) - 1);
        $ip_ip = ip2long ($IP);
        return (($ip_ip & $ip_mask) == ($ip_net & $ip_mask));
    }
}
