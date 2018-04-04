<?php
/**
 * Adds service configuration with locations.
 *
 * PHP version 5
 *
 * @category AddServiceConfiguration
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds service configuration with locations.
 *
 * @category AddServiceConfiguration
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddServiceConfiguration extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddServiceConfiguration';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Checkbox to service configuration.';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'location';
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
            'MODULE_SNAPINCLIENT_FIELDS',
            [$this, 'addServiceCheckbox']
        )->register(
            'MODULE_SNAPINCLIENT_POST',
            [$this, 'updateGlobalSetting']
        )->register(
            'NEEDSTOBECHECKBOX',
            [$this, 'addCheckbox']
        );
    }
    /**
     * Add the service checkbox.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addServiceCheckbox($arguments)
    {
        global $node;
        if ($node != 'service') {
            return;
        }
        $snapinsend = (
            isset($_POST['snapinsend']) ?
            'checked' :
            (
                self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') ?
                'checked' :
                ''
            )
        );

        $labelClass = 'col-sm-3 control-label';

        $arguments['fields'][
            FOGPage::makeLabel(
                $labelClass,
                'snapinsend',
                _('Enable Sending via Location')
            )
        ] = FOGPage::makeInput(
            '',
            'snapinsend',
            '',
            'checkbox',
            'snapinsend',
            '',
            false,
            false,
            -1,
            -1,
            $snapinsend
        );
    }
    /**
     * Updates the global setting for location sending.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function updateGlobalSetting($arguments)
    {
        global $node;
        if ($node != 'service') {
            return;
        }

        $snapinsend = (int)isset($_POST['snapinsend']);

        self::setSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED', $snapinsend);
    }
    /**
     * Adds service names.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addCheckbox($arguments)
    {
        global $node;
        global $sub;
        if ($node != 'about') {
            return;
        }
        if ($sub != 'settings') {
            return;
        }
        $arguments['needstobecheckbox']['FOG_SNAPIN_LOCATION_SEND_ENABLED'] = true;
    }
}
