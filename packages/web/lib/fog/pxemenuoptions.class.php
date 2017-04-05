<?php
/**
 * Pxe menu items class.
 *
 * PHP version 5
 *
 * @category PXEMenuOptions
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Pxe menu items class.
 *
 * @category PXEMenuOptions
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PXEMenuOptions extends FOGController
{
    /**
     * The PXE menu items table.
     *
     * @var string
     */
    protected $databaseTable = 'pxeMenu';
    /**
     * The PXE menu items fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'pxeID',
        'name' => 'pxeName',
        'description' => 'pxeDesc',
        'params' => 'pxeParams',
        'default' => 'pxeDefault',
        'regMenu' => 'pxeRegOnly',
        'args' => 'pxeArgs',
        'hotkey' => 'pxeHotKeyEnable',
        'keysequence' => 'pxeKeySequence',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
}
