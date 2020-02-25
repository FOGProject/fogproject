<?php
/**
 * Deletes the Location the elements en-mass.
 *
 * PHP version 5
 *
 * @category LocationDeleteItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Deletes the Location the elements en-mass.
 *
 * @category LocationDeleteItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationDeleteMassItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'LocationDeleteItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Delete En-mass Route altering for Location';
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
            'DELETEMASS_API',
            [$this, 'deletemassitems']
        );
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
        switch($arguments['classname']) {
        case 'host':
            $arguments['removeItems']['locationassociation'] = [
                'hostID' => $arguments['itemIDs']
            ];
            break;
        case 'storagegroup':
            $arguments['removeItems']['locationassociation'] = [
                'storagegroupID' => $arguments['itemIDs']
            ];
            break;
        default:
            $arguments['removeItems']['locationassociation'] = [
                'locationID' => $arguments['itemIDs']
            ];
        }
    }
}
