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
    protected $databaseFields = array(
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
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
    );
    /**
     * Return the associated host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * Cleanly represent the memory.
     *
     * @return float
     */
    public function getMem()
    {
        $memar = explode(' ', $this->get('mem'));
        
        return self::formatByteSize(((int)$memar[1] * 1024));
    }
}
