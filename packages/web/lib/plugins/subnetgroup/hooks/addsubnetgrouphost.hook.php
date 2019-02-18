<?php
/**
 * Adds the subnetgroup host to group.
 *
 * PHP version 5
 *
 * @category AddSubnetGroupHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the subnetgroup host to group.
 *
 * @category AddSubnetGroupHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSubnetgroupHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSubnetgroupHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add SubnetGroup to Hosts';
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
    public $node = 'subnetgroup';
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
                    'addSubnetgroupHost'
                )
            )
            ->register(
                'BOOT_ITEM_NEW_SETTINGS',
                array(
                    $this,
                    'addSubnetgroupHost'
                )
            );
    }
    /**
     * Adds subnetgroup host to group.
     *
     * @param mixed $arguments The arguments to evaluate.
     *
     * @return void
     */
    public function addSubnetgroupHost($arguments)
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

        Route::listem('subnetgroup');
        $Subnetgroup = json_decode(
            Route::getData()
        );
        $Subnetgroup = $Subnetgroup->subnetgroups;
        $hostChanged = false;

        foreach ($Subnetgroup as $SG) {
            if (in_array($SG->groupID, $Host->get('groups'))) {
                $Host->removeGroup($SG->groupID);
                $hostChanged = true;
            }
        }

        foreach ($Subnetgroup as $SG) {
            $subnetList = str_replace(' ', '', $SG->subnets);
            $subnets = explode(',', $subnetList);

            foreach ($subnets as $subnet) {
                if ($this->ipCIDRCheck($ip, $subnet)) {
                    $Host->addGroup($SG->groupID);
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
     * @credits http://php.net/manual/en/ref.network.php#121090
     *
     * @param string  $IP       IP Address
     * @param string $CIDR      CIDR Subnet
     *
     * @return bool
     */
    private function ipCIDRCheck($IP, $CIDR)
    {
        list($net, $mask) = explode('/', $CIDR);
        $ip_net = ip2long($net);
        $ip_mask = ~((1 << (32 - $mask)) - 1);
        $ip_ip = ip2long($IP);
        return (($ip_ip & $ip_mask) == ($ip_net & $ip_mask));
    }
}
