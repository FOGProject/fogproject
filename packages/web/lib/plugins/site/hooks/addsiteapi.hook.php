<?php
/**
 * Injects access control stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddSiteAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects access control stuff into the api system.
 *
 * @category AddSiteAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddSiteAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Site stuff into the api system.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'API_VALID_CLASSES',
            [$this, 'injectAPIElements']
        )->register(
            'CUSTOMIZE_DT_COLUMNS',
            [$this, 'customizeDT']
        );
    }
    /**
     * This adjusts our DT columns for display.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function customizeDT($arguments)
    {
        if ($arguments['classname'] != $this->node) {
            return;
        }
        $arguments['columns'][] = [
            'db' => 'shaMembers',
            'dt' => 'hostcount',
            'removeFromQuery' => true
        ];
        $arguments['columns'][] = [
            'db' => 'suaMembers',
            'dt' => 'usercount',
            'removeFromQuery' => true
        ];
    }
    /**
     * This function injects site elements for
     * api access.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectAPIElements($arguments)
    {

        array_push(
            $arguments['validClasses'],
            [
                $this->node,
                'sitehostassociation',
                'siteuserassociation'
            ]
        );
    }
}
