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
    const BACKENDS = [
        'choco' => 'Chocolatey',
        'flatpak' => 'Flatpak',
        'snap' => 'Snap'
    ];
    /**
     * The backends that cannot hold an exact version. These managers pin
     * by commit or revision, not by a version string, so an exact version
     * is refused.
     */
    const UNPINNABLE = ['flatpak', 'snap'];
    /**
     * The backend an empty value means: the swBackend column default.
     */
    const DEFAULT_BACKEND = 'choco';
    /**
     * The one message for a pinned version on an UNPINNABLE backend, so
     * the page and save() say the same thing.
     *
     * @return string
     */
    public static function pinRefusal()
    {
        return _(
            'Flatpak and Snap cannot hold an exact version. '
            . 'Choose any version or latest.'
        );
    }
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
        // The backend rules live here, not only on the page, because the
        // REST API sets fields directly and never sees the page's checks.
        // Empty means the column default, DEFAULT_BACKEND.
        $backend = (string)$this->get('backend') ?: self::DEFAULT_BACKEND;
        if (!array_key_exists($backend, self::BACKENDS)) {
            throw new \Exception(_('Please select a valid backend.'));
        }
        $version = (string)$this->get('version');
        if (in_array($backend, self::UNPINNABLE, true)
            && '' !== $version
            && 'latest' !== $version
        ) {
            throw new \Exception(self::pinRefusal());
        }
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
