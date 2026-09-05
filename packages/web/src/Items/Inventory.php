<?php
/**
 * The inventory class.
 *
 * PHP version 7.4+
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * The inventory class.
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Inventory extends FOGController
{
    /**
     * The inventory table.
     *
     * @var string
     */
    protected $databaseTable = 'inventory';
    /**
     * The inventory field and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'iID',
        'hostID' => 'iHostID',
        'primaryUser' => 'iPrimaryUser',
        'other1' => 'iOtherTag',
        'other2' => 'iOtherTag1',
        'createdTime' => 'iCreateDate',
        'deleteDate' => 'iDeleteDate',
        'sysman' => 'iSysman',
        'sysproduct' => 'iSysproduct',
        'sysversion' => 'iSysversion',
        'sysserial' => 'iSysserial',
        'sysuuid' => 'iSystemUUID',
        'systype' => 'iSystype',
        'biosversion' => 'iBiosversion',
        'biosvendor' => 'iBiosvendor',
        'biosdate' => 'iBiosdate',
        'mbman' => 'iMbman',
        'mbproductname' => 'iMbproductname',
        'mbversion' => 'iMbversion',
        'mbserial' => 'iMbserial',
        'mbasset' => 'iMbasset',
        'cpuman' => 'iCpuman',
        'cpuversion' => 'iCpuversion',
        'cpucurrent' => 'iCpucurrent',
        'cpumax' => 'iCpumax',
        'mem' => 'iMem',
        'hdmodel' => 'iHdmodel',
        'hdserial' => 'iHdserial',
        'hdfirmware' => 'iHdfirmware',
        'caseman' => 'iCaseman',
        'casever' => 'iCasever',
        'caseserial' => 'iCaseserial',
        'caseasset' => 'iCaseasset',
        'gpuvendors' => 'iGpuvendors',
        'gpuproducts' => 'iGpuproducts'
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
     * Ends in "id" but is not one: iSystemUUID is varchar(255) holding the
     * SMBIOS UUID. Without this, save() read the name as a foreign key and
     * rewrote every UUID it stored to 0.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = [
        'sysuuid'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'host'
    ];
    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `inventory`.`iHostID` = `hosts`.`hostID`
        %s
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `inventory`.`iHostID` = `hosts`.`hostID`
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `inventory`.`iHostID` = `hosts`.`hostID`";
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
     * Cleanly represent the memory.
     *
     * @return string
     */
    public function getMem($val = '')
    {
        if (!$val) {
            $val = $this->get('mem');
        }
        return self::formatByteSize(self::memBytes($val));
    }
    /**
     * Cleanly represent the memory.
     *
     * Float only for the empty case, which predates the formatter and is
     * left alone deliberately: 0.00 echoes as "0" and '0.00' would echo as
     * "0.00", so narrowing it here would change what the Inventory tab
     * prints for a host that reported no memory.
     *
     * @return string|float
     */
    public static function getMemory($val)
    {
        if (!$val) {
            return 0.00;
        }
        return self::formatByteSize(self::memBytes($val));
    }
    /**
     * Total physical memory in bytes, from either the legacy client's
     * format or the fog-agent client's.
     *
     * The legacy base64 client posts a multi-token string (e.g. the tail of
     * a dmidecode/wmic line) with the size in KB at index 1 -- that shape is
     * what the original single-token parse below always assumed. The
     * fog-agent client (internal/inventory.Inventory.Mem, design 0006)
     * reports a bare decimal string in MB instead, so a single-token value
     * with no whitespace to split on used to fall through to 0 here every
     * time. Token count is what tells the two apart.
     *
     * @param string $val the raw `mem` field, either format
     *
     * @return int
     */
    private static function memBytes($val)
    {
        $memar = preg_split('/\s+/', trim((string)$val));
        if (count($memar) > 1) {
            $kb = isset($memar[1]) ? (int)$memar[1] : 0;
            return $kb * 1024;
        }
        $mb = (int)$memar[0];
        return $mb * 1024 * 1024;
    }
}
