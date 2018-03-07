<?php
/**
 * Injects fileintegrity stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddFileintegrityAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects fileintegrity stuff into the api system.
 *
 * @category AddFileintegrityAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddFileintegrityAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddFileintegrityAPI';
    /**
     * Description.
     *
     * @var string
     */
    public $description = 'Add Fileintegrity stuff into the api system.';
    /**
     * For posterity
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to interact upon.
     *
     * @var string
     */
    public $node = 'fileintegrity';
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
            ['fileintegrity']
        );
    }
}
