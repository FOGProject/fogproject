<?php
/**
 * Deletes the Accesscontrol the elements en-mass.
 *
 * PHP version 5
 *
 * @category AccessControlDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Deletes the accesscontrol the elements en-mass.
 *
 * @category AccessControlDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlDeleteMassItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AccessControlDeleteMassItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Delete En-mass Route altering for Windows Key';
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
    public $node = 'accesscontrol';
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
            case 'user':
                $arguments['removeItems']['accesscontrolassociation'] = [
                    'userID' => $arguments['itemIDs']
                ];
                break;
            case 'accesscontrolrule':
                $arguments['removeItems']['accesscontrolruleassociation'] = [
                    'accesscontrolruleID' => $arguments['itemIDs']
                ];
                // no break
            default:
                $arguments['removeItems']['accesscontrolassociation'] = [
                    'accesscontrolID' => $arguments['itemIDs']
                ];
                $arguments['removeItems']['accesscontrolruleassociation'] = [
                    'accesscontrolID' => $arguments['itemIDs']
                ];
        }
    }
}
