<?php
/**
 * The global settings class.
 *
 * PHP version 5
 *
 * @category Setting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The global settings class.
 *
 * @category Setting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Setting extends FOGController
{
    /**
     * The setting table name.
     *
     * @var string
     */
    protected $databaseTable = 'globalSettings';
    /**
     * The setting fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'settingID',
        'name' => 'settingKey',
        'description' => 'settingDesc',
        'value' => 'settingValue',
        'category' => 'settingCategory'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Set the display settings.
     *
     * @param int $x The width of the screen.
     * @param int $y The height of the screen.
     * @param int $r The refresh rate.
     *
     * @return void
     */
    public function setDisplay(
        $x,
        $y,
        $r
    ) {
        $keySettings = [
            'FOG_CLIENT_DISPLAYMANAGER_X' => $x,
            'FOG_CLIENT_DISPLAYMANAGER_Y' => $y,
            'FOG_CLIENT_DISPLAYMANAGER_R' => $r,
        ];
        foreach ($keySettings as $name => &$value) {
            self::setSetting($name, $value);
            unset($value);
        }
    }
    /**
     * Builds the exit type selectors for us.
     *
     * @param string $name      What to call the form selector (name=)
     * @param string $selected  Which is the selected item.
     * @param bool   $nullField Is there going to be a null starter.
     * @param string $id        ID name to give.
     *
     * @return string
     */
    public static function buildExitSelector(
        $name = '',
        $selected = '',
        $nullField = false,
        $id = ''
    ) {
        if (empty($name)) {
            $name = $this->get('name');
        }
        $types = [
            'sanboot',
            'grub',
            'grub_first_hdd',
            'grub_first_cdrom',
            'grub_first_found_windows',
            'refind_efi',
            'exit',
        ];
        if ($nullField) {
            array_unshift(
                $types,
                sprintf(
                    ' - %s -',
                    _('Please Select an option')
                )
            );
        }
        $options = sprintf(
            '<select name="%s" autocomplete="off" class="form-control fog-select2"%s>',
            $name,
            (
                $id ? ' id="'
                . $id
                . '"' :
                ''
            )
        );
        foreach ($types as $i => &$viewop) {
            $show = strtoupper($viewop);
            $value = $viewop;
            if ($nullField
                && $i == 0
            ) {
                $show = $viewop;
                $value = '';
            }
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $value,
                (
                    strtolower($selected) == $value ?
                    ' selected' :
                    ''
                ),
                $show
            );
            unset($viewop);
        }
        unset($viewop);
        return $options.'</select>';
    }
}
