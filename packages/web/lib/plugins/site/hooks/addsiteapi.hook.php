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
    public $name = 'AddSiteAPI';
    public $description = 'Add Site stuff into the api system.';
    public $active = true;
    public $node = 'site';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'API_VALID_CLASSES',
                array(
                    $this,
                    'injectAPIElements'
                )
            )
            ->register(
                'API_GETTER',
                array(
                    $this,
                    'adjustGetter'
                )
            )
            ->register(
                'API_INDIVDATA_MAPPING',
                array(
                    $this,
                    'adjustIndivInfoUpdate'
                )
            )
            ->register(
                'API_MASSDATA_MAPPING',
                array(
                    $this,
                    'adjustMassInfo'
                )
            )
            ->register(
                'CUSTOMIZE_DT_COLUMNS',
                array(
                    $this,
                    'customizeDT'
                )
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $arguments['validClasses'] = self::fastmerge(
            $arguments['validClasses'],
            array(
                'site',
                'sitehostassociation'
            )
        );
    }
    /**
     * This function changes the api data map as needed.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustIndivInfoUpdate($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
    }
    /**
     * This function changes the api data map as needed.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustMassInfo($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
    }
    /**
     * This function changes the getter to enact on this particular item.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustGetter($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        switch ($arguments['classname']) {
        case 'sitehostassociation':
            $arguments['data'] = FOGCore::fastmerge(
                $arguments['class']->get(),
                array(
                    'site' => $arguments['class']->get('site')->get(),
                    'host' => $arguments['class']->get('host')->get()
                )
            );
            break;
        }
    }
}
