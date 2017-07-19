<?php
/**
 * Injects windows key stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddWindowskeyAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects windows key stuff into the api system.
 *
 * @category AddWindowskeyAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddWindowskeyAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddWindowskeyAPI';
    /**
     * The hooks description.
     *
     * @var string
     */
    public $description = 'Add windows key stuff into the api system.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the plugin works on.
     *
     * @var string
     */
    public $node = 'windowskey';
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
            );
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
                'windowskey',
                'windowskeyassociation'
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
        $find = Route::getsearchbody($arguments['classname']);
        switch ($arguments['classname']) {
        case 'windowskey':
            $arguments['data'][$arguments['classname'].'s'] = array();
            $arguments['data']['count'] = 0;
            foreach ((array)$arguments['classman']->find($find) as &$windowskey) {
                $arguments['data'][$arguments['classname'].'s'][]
                    = $windowskey->get();
                $arguments['data']['count']++;
                unset($windowskey);
            }
            break;
        case 'windowskeyassociation':
            $arguments['data'][$arguments['classname'].'s'] = array();
            $arguments['data']['count'] = 0;
            foreach ((array)$arguments['classman']
                ->find($find) as &$windowskeyassoc
            ) {
                $arguments['data'][$arguments['classname'].'s'][]
                    = $windowskeyassoc->get();
                $arguments['data']['count']++;
                unset($windowskeyassoc);
            }
            break;
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
        case 'windowskeyassoc':
            $arguments['data'] = FOGCore::fastmerge(
                $arguments['class']->get(),
                array(
                    'key' => $arguments['class']->get('key')->get(),
                    'image' => $arguments['class']->get('image')->get()
                )
            );
            break;
        }
    }
}
