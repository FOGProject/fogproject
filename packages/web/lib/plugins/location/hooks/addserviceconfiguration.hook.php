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
        self::$HookManager
            ->register(
                'SNAPIN_CLIENT_SERVICE',
                array(
                    $this,
                    'addServiceCheckbox'
                )
            )
            ->register(
                'SNAPIN_CLIENT_SERVICE_POST',
                array(
                    $this,
                    'updateGlobalSetting'
                )
            )
            ->register(
                'SERVICE_NAMES',
                array(
                    $this,
                    'addServiceNames'
                )
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'service') {
            return;
        }
        printf('<h2>%s</h2>', _('Snapin Locations'));
        printf(
            '%s %s. %s %s.',
            _('This area will allow the host checking in to tell'),
            _('where to download the snapin'),
            _('This is useful in the case of slow links between'),
            _('the main and the host')
        );
        echo '<br/><br/>';
        $fields = array(
            _('Enable location Sending') => sprintf(
                '<input type="checkbox" name="snapinsend" id="snapsend"%s/>'
                . '<label for="snapsend"></label>',
                (
                    isset($_REQUEST['snapinsend']) ?
                    ' checked' :
                    (
                        self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') ?
                        ' checked' :
                        ''
                    )
                )
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" name="updateglobal" value="%s"/>',
                _('Update')
            ),
        );
        unset(
            $arguments['page']->headerData,
            $arguments['page']->data
        );
        $arguments['page']->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $arguments['page']->templates = array(
            '${field}',
            '${input}',
        );
        foreach ($fields as $field => &$input) {
            $arguments['page']->data[] = array(
                'field' => $field,
                'input' => $input,
            );
            unset($input);
        }
        printf(
            '<form method="post" action="?node=service&sub=edit&tab=%s">',
            'snapinclient'
        );
        $arguments['page']->render();
        echo '</form>';
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'service') {
            return;
        }
        $Service = self::getClass('Service')
            ->set('name', 'FOG_SNAPIN_LOCATION_SEND_ENABLED')
            ->load('name');
        if (!$Service->isValid()) {
            return;
        }
        $Service
            ->set('value', isset($_REQUEST['snapinsend']))
            ->save();
        return true;
    }
    /**
     * Adds service names.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addServiceNames($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'about') {
            return;
        }
        if ($sub != 'settings') {
            return;
        }
        $arguments['ServiceNames'][] = 'FOG_SNAPIN_LOCATION_SEND_ENABLED';
    }
}
