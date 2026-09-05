<?php
/**
 * A printer a host reports having installed (design 0010).
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
 * A printer a host reports having installed (design 0010).
 *
 * The contrast to draw is with `printerAssoc`, which this does not replace:
 * that is INTENT, the printers an admin assigned. This is OBSERVATION, the
 * queues the machine says it actually has. FOG has recorded the first since
 * 1.x and has never recorded the second, which is why "did it install?" has
 * had no answer.
 *
 * A printer is a URI and a driver (design 0010 section 2), because that is
 * how both spoolers already describe one. `uri` is what makes a row portable
 * between platforms; FOG's `pConfig` never could, because it named a code
 * path rather than a device.
 *
 * One row per host per queue, and the set is replaced on each report. Not a
 * history: unlike `hostSoftware`, where "which hosts had log4j in March" is
 * the question the table exists for, a printer that is gone is simply gone,
 * and the removal itself is in the audit log.
 *
 * @category Printers
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostPrinter extends FOGController
{
    /**
     * The hostPrinter table.
     *
     * @var string
     */
    protected $databaseTable = 'hostPrinter';
    /**
     * The hostPrinter fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hpID',
        'hostID' => 'hpHostID',
        'name' => 'hpName',
        'uri' => 'hpURI',
        'driver' => 'hpDriver',
        'isDefault' => 'hpDefault',
        'shared' => 'hpShared',
        'observedAt' => 'hpObservedAt'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'name'
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
