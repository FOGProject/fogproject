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
        'gpuproducts' => 'iGpuproducts',
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
     * sysuuid ends in "id" without being a foreign key.
     *
     * iSystemUUID is a VARCHAR(255) holding the SMBIOS system UUID, which is
     * a hyphenated hex string. Without this, save() read the key's name as a
     * foreign key, failed FILTER_VALIDATE_INT and wrote 0 -- so the UUID was
     * silently lost on every inventory write, with no error anywhere.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = array(
        'sysuuid',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'host'
    );
    /**
     * Return the associated host object.
     *
     * @return object
     */
    public function getHost()
    {
        if (!$this->isLoaded('host')) {
            $this->set('host', new Host($this->get('hostID')));
        }
        return $this->get('host');
    }
    /**
     * Cleanly represent the memory.
     *
     * @return float
     */
    public function getMem()
    {
        $memar = explode(' ', $this->get('mem'));
        $memar = isset($memar[1]) ? $memar[1] : 0;
        return self::formatByteSize(((int)$memar * 1024));
    }
}
