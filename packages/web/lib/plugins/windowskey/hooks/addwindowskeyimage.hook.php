<?php
/**
 * Adds the windows keys choice to image.
 *
 * PHP version 5
 *
 * @category AddWindowsKeyImage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the windows keys choice to image.
 *
 * @category AddWindowsKeyImage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddWindowsKeyImage extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddWindowsKeyImage';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Windows Keys to images';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * THe node this hook enacts with.
     *
     * @var string
     */
    public $node = 'windowskey';
}
$AddWindowsKeyImage = new AddWindowsKeyImage();
/*
$HookManager
    ->register(
        'EMAIL_ITEMS',
        array(
            $AddLocationHost,
            'hostEmailHook'
        )
    );
$HookManager
    ->register(
        'HOST_INFO_EXPOSE',
        array(
            $AddLocationHost,
            'hostInfoExpose'
        )
    );
 */
