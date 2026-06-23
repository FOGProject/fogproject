<?php
/**
 * Injects ntfy stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddNtfyAPI
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects ntfy stuff into the api system.
 *
 * @category AddNtfyAPI
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddNtfyAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddNtfyAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add ntfy stuff into the api system.';
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
    public $node = 'ntfy';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['API_VALID_CLASSES', 'injectAPIElements'],
        ]);
    }
    /**
     * This function injects ntfy elements for
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
