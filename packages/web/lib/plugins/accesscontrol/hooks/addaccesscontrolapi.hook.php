<?php
/**
 * Injects access control stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddAccessControlAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects access control stuff into the api system.
 *
 * @category AddAccessControlAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddAccessControlAPI extends Hook
{
    public $name = 'AddAccessControlAPI';
    public $description = 'Add AccessControl stuff into the api system.';
    public $active = true;
    public $node = 'accesscontrol';
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
     * This function injects access control elements for
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
                'accesscontrol',
                'accesscontrolassociation',
                'accesscontrolrule',
                'accesscontrolruleassociation'
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
        switch ($arguments['classname']) {
        case 'accesscontrol':
            $arguments['data'] = $arguments['class']->get();
            break;
        case 'accesscontrolassociation':
            $arguments['data'] = $arguments['class']->get();
            break;
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
        foreach ($arguments['classman']->find() as &$class) {
            switch ($arguments['classname']) {
            case 'accesscontrol':
                $arguments['data'][$arguments['classname'].'s'] = array();
                $arguments['data'][$arguments['classname'].'s'][] = $class->get();
                $arguments['data']['count'] = count(
                    $arguments['data'][$arguments['classname'].'s']
                );
                break;
            case 'accesscontrolassociation':
                $arguments['data'][$arguments['classname'].'s'] = array();
                $arguments['data'][$arguments['classname'].'s'][] = $class->get();
                $arguments['data']['count'] = count(
                    $arguments['data'][$arguments['classname'].'s']
                );
                break;
            }
        }
    }
}
