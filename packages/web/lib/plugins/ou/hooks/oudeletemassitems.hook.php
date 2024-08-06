<?php
/**
 * Deletes the OU the elements en-mass.
 *
 * PHP version 5
 *
 * @category OUDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Deletes the OU the elements en-mass.
 *
 * @category OUDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OUDeleteMassItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'OUDeleteMassItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Delete En-mass Route altering for OU';
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
    public $node = 'ou';
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
        switch ($arguments['classname']) {
            case 'host':
                $arguments['removeItems']['ouassociation'] = [
                    'hostID' => $arguments['itemIDs']
                ];
                break;
            default:
                $arguments['removeItems']['ouassociation'] = [
                    'ouID' => $arguments['itemIDs']
                ];
        }
    }
}
