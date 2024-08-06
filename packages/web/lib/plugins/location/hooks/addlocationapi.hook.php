<?php
/**
 * Injects location stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddLocationAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects location stuff into the api system.
 *
 * @category AddLocationAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddLocationAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Location stuff into the api system.';
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
            'API_VALID_CLASSES',
            [$this, 'injectAPIElements']
        )->register(
            'API_GETTER',
            [$this, 'adjustGetter']
        )->register(
            'CUSTOMIZE_DT_COLUMNS',
            [$this, 'customizeDT']
        );
    }
    /**
     * Customize our new columns.
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
            'db' => 'ngmMemberName',
            'dt' => 'storagenodename'
        ];
        $arguments['columns'][] = [
            'db' => 'ngName',
            'dt' => 'storagegroupname'
        ];
    }
    /**
     * This function injects location elements for
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
            $this->node,
            'locationassociation'
        );
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
        switch ($arguments['classname']) {
            case 'location':
                $arguments['data'] = FOGCore::fastmerge(
                    $arguments['class']->get(),
                    [
                        'storagenode' => Route::getter(
                            'storagenode',
                            $arguments['class']->get('storagenode')
                        ),
                        'storagegroup' => Route::getter(
                            $arguments['class']->get('storagegroup')
                        )
                    ]
                );
                break;
            case 'locationassociation':
                $arguments['data'] = FOGCore::fastmerge(
                    $arguments['class']->get(),
                    [
                        'host' => Route::getter(
                            'host',
                            $arguments['class']->get('host')
                        ),
                        'location' => $arguments['class']
                        ->get('location')
                        ->get()
                    ]
                );
        }
    }
}
