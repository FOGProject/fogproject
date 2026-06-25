<?php
/**
 * Deletes the Site elements en-mass.
 *
 * PHP version 5
 *
 * @category SiteDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Deletes the Site elements en-mass.
 *
 * @category SiteDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteDeleteMassItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'SiteDeleteMassItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Delete En-mass Route altering for Site';
    /**
     * The active flag.
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
            ['DELETEMASS_API', 'deletemassitems'],
        ]);
    }
    /**
     * Prepares to clean up associations
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function deletemassitems($arguments)
    {
        switch ($arguments['classname']) {
            case 'host':
                $arguments['removeItems']['sitehostassociation'] = [
                    'hostID' => $arguments['itemIDs']
                ];
                break;
            case 'user':
                $arguments['removeItems']['siteuserassociation'] = [
                    'userID' => $arguments['itemIDs']
                ];
                $arguments['removeItems']['siteuserrestriction'] = [
                    'userID' => $arguments['itemIDs']
                ];
                break;
            case 'site':
                // Deleting a site clears its host and user links. The user
                // restriction table is keyed by userID only (no siteID), so it
                // is cleaned on the user case rather than here.
                $arguments['removeItems']['sitehostassociation'] = [
                    'siteID' => $arguments['itemIDs']
                ];
                $arguments['removeItems']['siteuserassociation'] = [
                    'siteID' => $arguments['itemIDs']
                ];
                break;
        }
    }
}
