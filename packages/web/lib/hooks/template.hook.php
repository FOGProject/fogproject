<?php
/**
 * Template for others to work from.
 *
 * PHP version 5
 *
 * @category Template
 * @package  FOGProject
 * @author   Hook Author <hookemail@email.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Template for others to work from.
 *
 * @category Template
 * @package  FOGProject
 * @author   Hook Author <hookemail@email.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Template extends Hook
{
    /**
     * The name for this hook.
     *
     * @var string
     */
    public $name = 'Hook Name';
    /**
     * The description for this hook.
     *
     * @var string
     */
    public $description = 'Hook Description';
    /**
     * If the hook is active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'HOST_DATA',
            [$this, 'hostData']
        );
    }
    /**
     * Host data example method.
     *
     * @param mixed $arguments The data to alter, work with.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        self::log(
            print_r($arguments, 1),
            0,
            false,
            true,
            $this
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Template', 'Template');
