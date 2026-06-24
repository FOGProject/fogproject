<?php
/**
 * Adds site support to the CSV import/export associations column.
 *
 * PHP version 5
 *
 * @category AddSiteImport
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds site support to the CSV import/export associations column.
 *
 * Registers a "site" association type for hosts so the trailing
 * associations column can carry a host's site, e.g.
 * "groups:1|2;site:Building A". A host has a single site, so only the
 * first resolved reference is used.
 *
 * @category AddSiteImport
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteImport extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteImport';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Site to CSV import/export';
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
    public $node = 'site';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['IMPORT_ASSOCIATIONS', 'addSiteAssociation'],
        ]);
    }
    /**
     * Registers the "site" association type for hosts. The same entry
     * serves both import (apply) and export (get).
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addSiteAssociation($arguments)
    {
        if ($arguments['childClass'] !== 'Host') {
            return;
        }
        $arguments['config']['site'] = [
            'class' => 'Site',
            'namefield' => 'name',
            'get' => [$this, 'getSiteNames'],
            'apply' => [$this, 'applySite'],
        ];
    }
    /**
     * Returns the (single) site name a host is associated with, for export.
     * Returns an array for consistency with the importer.
     *
     * @param object $host The host being exported.
     *
     * @return array
     */
    public function getSiteNames($host)
    {
        $siteIDs = Route::getIds(
            'sitehostassociation',
            ['hostID' => $host->get('id')],
            'siteID'
        );
        $names = [];
        foreach ((array)$siteIDs as $siteID) {
            $Site = self::getClass('Site', $siteID);
            if ($Site->isValid()) {
                $names[] = $Site->get('name');
            }
        }
        return $names;
    }
    /**
     * Applies a site association to a host on import. A host has a single
     * site, so any existing association is cleared and only the first
     * resolved id is used.
     *
     * @param object $host The host being imported.
     * @param array  $ids  The resolved site ids.
     *
     * @return void
     */
    public function applySite($host, $ids)
    {
        $hostID = $host->get('id');
        if (empty($hostID) || count($ids) < 1) {
            return;
        }
        Route::deletemass(
            'sitehostassociation',
            ['hostID' => $hostID]
        );
        $siteID = array_shift($ids);
        if (self::getClass('Site', $siteID)->isValid()) {
            self::getClass('SiteHostAssociationManager')
                ->insertBatch(
                    ['hostID', 'siteID'],
                    [[$hostID, $siteID]]
                );
        }
    }
}
