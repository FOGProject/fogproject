<?php
/**
 * Adds location support to the CSV import/export associations column.
 *
 * PHP version 5
 *
 * @category AddLocationImport
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds location support to the CSV import/export associations column.
 *
 * Registers a "location" association type for hosts so the trailing
 * associations column can carry a host's location, e.g.
 * "groups:1|2;location:Building A". A host has a single location, so only
 * the first resolved reference is used.
 *
 * @category AddLocationImport
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationImport extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLocationImport';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Location to CSV import/export';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'location';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'IMPORT_ASSOCIATIONS',
            [$this, 'addLocationAssociation']
        );
    }
    /**
     * Registers the "location" association type for hosts. The same entry
     * serves both import (apply) and export (get).
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addLocationAssociation($arguments)
    {
        if ($arguments['childClass'] !== 'Host') {
            return;
        }
        $arguments['config']['location'] = [
            'class' => 'Location',
            'namefield' => 'name',
            'get' => [$this, 'getLocationNames'],
            'apply' => [$this, 'applyLocation'],
        ];
    }
    /**
     * Returns the (single) location name a host is associated with, for
     * export. Returns an array for consistency with the importer.
     *
     * @param object $host The host being exported.
     *
     * @return array
     */
    public function getLocationNames($host)
    {
        $locationIDs = Route::getIds(
            'locationassociation',
            ['hostID' => $host->get('id')],
            'locationID'
        );
        $names = [];
        foreach ((array)$locationIDs as $locationID) {
            $Location = self::getClass('Location', $locationID);
            if ($Location->isValid()) {
                $names[] = $Location->get('name');
            }
        }
        return $names;
    }
    /**
     * Applies a location association to a host on import. A host has a single
     * location, so any existing association is cleared and only the first
     * resolved id is used.
     *
     * @param object $host The host being imported.
     * @param array  $ids  The resolved location ids.
     *
     * @return void
     */
    public function applyLocation($host, $ids)
    {
        $hostID = $host->get('id');
        if (empty($hostID) || count($ids) < 1) {
            return;
        }
        Route::deletemass(
            'locationassociation',
            ['hostID' => $hostID]
        );
        $locationID = array_shift($ids);
        if (self::getClass('Location', $locationID)->isValid()) {
            self::getClass('LocationAssociationManager')
                ->insertBatch(
                    ['hostID', 'locationID'],
                    [[$hostID, $locationID]]
                );
        }
    }
}
