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
     * Host data example method.
     *
     * @param mixed $arguments The data to alter, work with.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        $this->log(print_r($arguments, 1));
    }
}
$HookManager
    ->register(
        'HOST_DATA',
        array(
            new Template(),
            'HostData'
        )
    );
