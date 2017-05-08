<?php
/**
 * Sub menu hook changer.
 *
 * PHP version 5
 *
 * @category SubMenuData
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sub menu hook changer.
 *
 * @category SubMenuData
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubMenuData extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'SubMenuData';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Example to show how to chagne SubMenu data.';
    /**
     * Is this hook active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * The node to interact with.
     *
     * @var string
     */
    public $node = 'host';
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'subMenu'
                )
            );
    }
    /**
     * The changer method.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function subMenu($arguments)
    {
        global $node;
        if ($node != $this->node) {
            return;
        }
        $arguments['menu']['http://www.google.com'] = 'Google';
        if (!$arguments['object']) {
            return;
        }
        $arguments['submenu']['http://www.google.com']
            = 'Google here';
        $arguments['notes'][_('Example Bolded Header')]
            = $arguments['object']->get('description');
        $arguments['notes']['Example Add Description']
            = $arguments['object']->get('description');
    }
}
