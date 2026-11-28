<?php
/**
 * The global settings class.
 *
 * PHP version 7.4+
 *
 * @category Setting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Util\DebugSshKeys;

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
     * Saves the setting. FOG_DEBUG_SSH_KEYS is validated and audited first.
     *
     * The REST create and edit routes save through here. The FOG Settings
     * page writes with insertBatch() and does the same two steps itself.
     * The setting gives root SSH on every machine in a debug task (#2193).
     *
     * @throws \InvalidArgumentException code 400 when the keys do not parse
     *
     * @return bool|object false, or $this as parent::save() returns it
     */
    public function save()
    {
        if (DebugSshKeys::SETTING !== trim((string)$this->get('name'))) {
            return parent::save();
        }
        $this->set('value', DebugSshKeys::normalize((string)$this->get('value')));
        $id = (int)$this->get('id');
        // The value before, read from the table: this object already holds
        // the new one.
        $old = $id > 0 ? (string)(new self($id))->get('value') : '';
        $saved = parent::save();
        if (!$saved) {
            return false;
        }
        DebugSshKeys::recordChange(
            DebugSshKeys::normalizeStored($old),
            (string)$this->get('value'),
            $id > 0 ? 'settings.edit' : 'settings.create'
        );
        return $saved;
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
                \Initiator::e($value),
                (
                    strtolower($selected) == $value ?
                    ' selected' :
                    ''
                ),
                \Initiator::e($show)
            );
            unset($viewop);
        }
        unset($viewop);
        return $options.'</select>';
    }
}
