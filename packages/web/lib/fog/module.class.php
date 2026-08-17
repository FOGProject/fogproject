<?php
/**
 * The module class.
 *
 * PHP version 7.4+
 *
 * @category Module
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * The module class.
 *
 * @category Module
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Module extends FOGController
{
    /**
     * The module table name.
     *
     * @var string
     */
    protected $databaseTable = 'modules';
    /**
     * The module fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'id',
        'name' => 'name',
        'shortName' => 'short_name',
        'description' => 'description',
        'isDefault' => 'default'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'shortName'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts'
    ];
    /**
     * Alters valid method.
     *
     * @return bool
     */
    public function isValid()
    {
        return (bool)parent::isValid()
            && $this->get('shortName');
    }
    /**
     * Destroys the object.
     *
     * @param string $key the key to match for removal.
     *
     * @return bool|mixed
     */
    public function destroy($key = 'id')
    {
        // Funnel cleanup through the single cascade authority (the module case in
        // Route::deletemass removes moduleassociation rows and fires
        // DELETEMASS_API for plugins). deletemass also deletes the module row; the
        // trailing parent::destroy() is a harmless no-op preserving the history.
        Route::deletemass('module', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
    /**
     * Loads any hosts this module has
     *
     * @return void
     */
    protected function loadHosts()
    {
        $this->_loadHostIds(
            'moduleassociation',
            ['moduleID' => $this->get('id')],
            'hostID'
        );
    }
    /**
     * Add host to the group.
     *
     * @param array $addArray the host to add
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
     * Remove host from the group.
     *
     * @param array $removeArray the host to remove
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
     * Saves the group elements.
     *
     * @return object
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        return $this
            ->assocSetter('Module', 'host')
            ->load();
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Module', 'Module');
