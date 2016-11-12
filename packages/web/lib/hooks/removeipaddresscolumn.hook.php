<?php
/**
 * Remove the ip column from host list.
 *
 * PHP version 5
 *
 * @category RemoveIPAddressColumn
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Remove the ip column from host list.
 *
 * @category RemoveIPAddressColumn
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RemoveIPAddressColumn extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'RemoveIPAddressColumn';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Removes the "IP Address" column from Host Lists';
    /**
     * Is this hook active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * Changes the table header.
     *
     * @param mixed $arguments The items to alter.
     *
     * @return void
     */
    public function hostTableHeader($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        unset($arguments['headerData'][4]);
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
        global $node;
        if ($node != 'host') {
            return;
        }
        unset($arguments['templates'][4]);
    }
}
$RemoveIPAddressColumn = new RemoveIPAddressColumn();
$HookManager
    ->register(
        'HOST_HEADER_DATA',
        array(
            $RemoveIPAddressColumn,
            'hostTableHeader'
        )
    );
$HookManager
    ->register(
        'HOST_DATA',
        array(
            $RemoveIPAddressColumn,
            'hostData'
        )
    );
