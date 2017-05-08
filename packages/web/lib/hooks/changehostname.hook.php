<?php
/**
 * Change host name hook.
 *
 * PHP version 5
 *
 * @category ChangeHostname
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Change host name hook.
 *
 * @category ChangeHostname
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ChangeHostname extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'ChangeHostname';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Appends "Chicken-" to all hostnames ';
    /**
     * Is this hook active or not.
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
        self::$HookManager
            ->register(
                'HOST_DATA',
                array(
                    $this,
                    'hostData'
                )
            );
    }
    /**
     * The data to alter.
     *
     * @param mixed $arguments The items to alter.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        foreach ($arguments['data'] as $i => &$data) {
            $arguments['data'][$i]['host_name'] = sprintf(
                '%s-%s',
                'Chicken',
                $data['host_name']
            );
            unset($data);
        }
    }
}
