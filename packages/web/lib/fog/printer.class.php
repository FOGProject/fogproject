<?php
/**
 * The printer class
 *
 * PHP version 5
 *
 * @category Printer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The printer class
 *
 * @category Printer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Printer extends FOGController
{
    /**
     * The printer table
     *
     * @var string
     */
    protected $databaseTable = 'printers';
    /**
     * The Printer fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'pID',
        'name' => 'pAlias',
        'description' => 'pDesc',
        'port' => 'pPort',
        'file' => 'pDefFile',
        'model' => 'pModel',
        'config' => 'pConfig',
        'configFile' => 'pConfigFile',
        'ip' => 'pIP',
        'pAnon2' => 'pAnon2',
        'pAnon3' => 'pAnon3',
        'pAnon4' => 'pAnon4',
        'pAnon5' => 'pAnon5',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * The additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    /**
     * Removes the printer.
     *
     * @param string $key The key to match for removing.
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        self::getClass('PrinterAssociationManager')
            ->destroy(array('printerID'=>$this->get('id')));
        return parent::destroy($key);
    }
    /**
     * Stores/updates the printer
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('Printer', 'host')
            ->load();
    }
    /**
     * Adds the host to the printer.
     *
     * @param array $addArray the hosts to add.
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'hosts',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes hosts from the printer.
     *
     * @param array $removeArray the hosts to remove.
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'hosts',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Loads the hosts assigned
     *
     * @return void
     */
    protected function loadHosts()
    {
        $this->set(
            'hosts',
            self::getSubObjectIDs(
                'PrinterAssociation',
                array('printerID'=>$this->get('id')),
                'hostID'
            )
        );
    }
    /**
     * Loads the hosts not assigned to this object.
     *
     * @return void
     */
    protected function loadHostsnotinme()
    {
        $find = array('id'=>$this->get('hosts'));
        $this->set(
            'hostsnotinme',
            self::getSubObjectIDs(
                'Host',
                $find,
                'id',
                true
            )
        );
        unset($find);
        return $this;
    }
    /**
     * Update the default printer for the host.
     *
     * @param int  $hostid the host id to update for.
     * @param bool $onoff  if the printer is on or off.
     *
     * @return object
     */
    public function updateDefault($hostid, $onoff)
    {
        $AllHostsPrinter = self::getSubObjectIDs(
            'PrinterAssociation',
            array('printerID'=>$this->get('id'))
        );
        self::getClass('PrinterAssociationManager')
            ->update(
                array(
                    'id' => $AllHostsPrinter,
                    'isDefault' => 0
                )
            );
        self::getClass('PrinterAssociationManager')
            ->update(
                array(
                    'hostID' => $onoff,
                    'printerID' => $this->get('id')
                ),
                '',
                array('isDefault' => 1)
            );
        return $this;
    }
    /**
     * Returns if the printer is valid
     *
     * @return bool
     */
    public function isValid()
    {
        $validTypes = array(
            'iprint',
            'network',
            'local',
            'cups',
        );
        $curtype = $this->get('config');
        $curtype = trim($this->get('config'));
        $curtype = strtolower($curtype);
        if (!in_array($curtype, $validTypes)) {
            return false;
        }
        return parent::isValid();
    }
}
