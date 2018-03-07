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
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddAccessControlAPI';
    /**
     * The description of the hook.
     *
     * @var string
     */
    public $description = 'Add AccessControl stuff into the api system.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook works from (plugin).
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
            'API_VALID_CLASSES',
            [$this, 'injectAPIElements']
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
        $arguments['validClasses'] = self::fastmerge(
            $arguments['validClasses'],
            [
                'accesscontrol',
                'accesscontrolassociation',
                'accesscontrolrule',
                'accesscontrolruleassociation'
            ]
        );
    }
}
