<?php
/**
 * The inventory class.
 *
 * PHP version 5
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        'caseasset' => 'iCaseasset'
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
     * @return float
     */
    public function getMem($val = '')
    {
        if (!$val) {
            $val = $this->get('mem');
        }
        $memar = preg_split('/\s+/', $val);

        $memar = isset($memar[1]) ? $memar[1] : 0;
        
        return self::formatByteSize(((int)$memar * 1024));
    }
    /**
     * Cleanly represent the memory.
     *
     * @return float
     */
    public static function getMemory($val)
    {
        if (!$val) {
            return 0.00;
        }
        $memar = preg_split('/\s+/', $val);

        $memar = isset($memar[1]) ? $memar[1] : 0;

        return self::formatByteSize(((int)$memar * 1024));
    }
}
