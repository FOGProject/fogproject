<?php
/**
 * The print subsystem a host reports running (design 0010).
 *
 * PHP version 7.4+
 *
 * @category Printers
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * The print subsystem a host reports running (design 0010).
 *
 * One row per host, replaced in place. It is the per-host anchor for the
 * printer report: a machine with CUPS and no queues has ANSWERED, and a
 * report that could only see `hostPrinter` rows would show that host as
 * never having reported -- the invisible-absence failure design 0010
 * section 6 exists to avoid.
 *
 * It also carries the fact FOG's `pConfig` column was trying to hold and
 * could not (design 0010 section 1.1). `pConfig` asked an ADMIN to pick
 * between "Local", "Network", "iPrint" and "Cups" -- four code paths, three
 * of which throw on whichever platform the machine is actually running --
 * for a device that has no opinion on the matter. The subsystem is a fact
 * about the machine, so the machine reports it.
 *
 * @category Printers
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostSpooler extends FOGController
{
    /**
     * The hostSpooler table.
     *
     * @var string
     */
    protected $databaseTable = 'hostSpooler';
    /**
     * The hostSpooler fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hspID',
        'hostID' => 'hspHostID',
        'subsystem' => 'hspSubsystem',
        'observedAt' => 'hspObservedAt'
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
}
