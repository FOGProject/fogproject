<?php
/**
 * Injects capone stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddCaponeAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects capone stuff into the api system.
 *
 * @category AddCaponeAPI
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddCaponeAPI extends Hook
{
    public $name = 'AddCaponeAPI';
    public $description = 'Add Capone stuff into the api system.';
    public $active = true;
    public $node = 'capone';
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
        $arguments['validClasses'] = self::fastmerge(
            $arguments['validClasses'],
            ['capone']
        );
    }
}
