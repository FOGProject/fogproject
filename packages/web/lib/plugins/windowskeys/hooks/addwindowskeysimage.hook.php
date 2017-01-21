<?php
/**
 * Adds the windows keys choice to image.
 *
 * PHP version 5
 *
 * @category AddWindowsKeysImage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the windows keys choice to image.
 *
 * @category AddWindowsKeysImage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddWindowsKeysImage extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddWindowsKeysImage';
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
    public $node = 'windowskeys';
}
$AddWindowsKeysImage = new AddWindowsKeysImage();
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
