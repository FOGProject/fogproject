<?php
/**
 * PowerManagement class handler
 *
 * PHP version 5
 *
 * @category PowerManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * PowerManagement class handler
 *
 * @category PowerManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PowerManagement extends FOGController
{
    /**
     * The database table to work from
     *
     * @var string
     */
    protected $databaseTable = 'powerManagement';
    /**
     * The fields and common name associations
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'pmID',
        'hostID' => 'pmHostID',
        'min' => 'pmMin',
        'hour' => 'pmHour',
        'dom' => 'pmDom',
        'month' => 'pmMonth',
        'dow' => 'pmDow',
        'onDemand' => 'pmOndemand',
        'action' => 'pmAction',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'min',
        'hour',
        'dom',
        'month',
        'dow',
        'action',
    );
    /**
     * Any additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'hosts',
    );
    /**
     * Add new hosts to the powermanagement system
     *
     * @param array $addArray the hosts to add
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem('hosts', (array)$addArray, 'merge');
    }
    /**
     * Removes hosts from the powermanagement system
     *
     * @param array $removeArray the hosts to remove
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem('hosts', (array)$removeArray, 'diff');
    }
    /**
     * Loads an hosts for this pm tasking
     *
     * @return void
     */
    protected function loadHosts()
    {
        $this->set(
            'hosts',
            (array)self::getSubObjectIDs(
                'PowerManagement',
                array('id' => $this->get('id')),
                'hostID'
            )
        );
    }
    /**
     * Saves the item to the db
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('PowerManagement', 'host', true)
            ->load();
    }
    /**
     * Gets the action as defined
     *
     * @return string
     */
    public function getActionSelect()
    {
        return $this->getManager()->getActionSelect(
            $this->get('action'),
            true
        );
    }
    /**
     * Gets the current timer for this pm task
     *
     * @return object
     */
    public function getTimer()
    {
        $min = trim($this->get('min'));
        $hour = trim($this->get('hour'));
        $dom = trim($this->get('dom'));
        $month = trim($this->get('month'));
        $dow = trim($this->get('dow'));
        return new Timer($min, $hour, $dom, $month, $dow);
    }
    /**
     * Returns the host associated to this pm task
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * Wakes the host mac
     *
     * @return void
     */
    public function wakeOnLAN()
    {
        $this->getHost()->wakeOnLAN();
    }
}
