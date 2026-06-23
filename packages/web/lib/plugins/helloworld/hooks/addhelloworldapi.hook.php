<?php
/**
 * Exposes Hello World through the REST API.
 *
 * Registering the node in API_VALID_CLASSES lets /fog/helloworld endpoints
 * resolve to this plugin's model/manager (list/get/create/update/delete),
 * reusing the same ORM the UI uses.
 *
 * PHP version 5
 *
 * @category AddHelloWorldAPI
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects Hello World into the API system.
 *
 * @category AddHelloWorldAPI
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddHelloWorldAPI extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddHelloWorldAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Hello World into the API system.';
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
    public $node = 'helloworld';
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
     * Marks this node as a valid API class.
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
