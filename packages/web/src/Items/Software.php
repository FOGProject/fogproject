<?php
/**
 * A software entry: a package a host is held to (design 0003).
 *
 * PHP version 7.4+
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Router\Route;

/**
 * A software entry: a package a host is held to (design 0003).
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Software extends FOGController
{
    /**
     * The software table.
     *
     * @var string
     */
    protected $databaseTable = 'software';
    /**
     * The software fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'swID',
        'name' => 'swName',
        'description' => 'swDesc',
        'backend' => 'swBackend',
        'package' => 'swPackage',
        'version' => 'swVersion',
        'state' => 'swState',
        'source' => 'swSource',
        'args' => 'swArgs',
        'timeout' => 'swTimeout',
        'returnCodes' => 'swReturnCodes',
        'isEnabled' => 'swEnabled',
        'createdTime' => 'swCreateDate',
        'createdBy' => 'swCreator'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'package'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts'
    ];
    /**
     * The backends a server knows how to describe; the agent decides
     * which it can run.
     */
    const BACKENDS = ['choco' => 'Chocolatey'];
    /**
     * The states.
     */
    const STATES = ['present', 'absent'];
    /**
     * Removes the entry and everything that hangs off it.
     *
     * @param string $key the key to match on
     *
     * @return object
     */
    public function destroy($key = 'id')
    {
        $find = ['softwareID' => $this->get('id')];
        Route::deletemass('softwareassociation', $find);
        Route::deletemass('groupsoftwareassociation', $find);
        Route::deletemass('softwarestatus', $find);
        return parent::destroy($key);
    }
    /**
     * Loads the directly assigned hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $this->_loadHostIds(
            'softwareassociation',
            ['softwareID' => $this->get('id')],
            'hostID'
        );
    }
    /**
     * Assigns hosts directly.
     *
     * @param array $addArray the host ids
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
     * Unassigns hosts.
     *
     * @param array $removeArray the host ids
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
     * Saves, writing the host assignments as rows.
     *
     * @return object|bool false when the row itself did not save
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        $this->assocSetter('Software', 'host');
        return $this->load();
    }
}
