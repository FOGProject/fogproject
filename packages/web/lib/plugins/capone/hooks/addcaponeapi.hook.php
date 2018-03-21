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
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddCaponeAPI';
    /**
     * Description of the hook.
     *
     * @var string
     */
    public $description = 'Add Capone stuff into the api system.';
    /**
     * For posterity
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this plugin works with.
     *
     * @var string
     */
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
        $arguments['validClasses'][] = $this->node;
    }
}
