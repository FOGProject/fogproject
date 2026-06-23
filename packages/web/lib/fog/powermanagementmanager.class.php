<?php
/**
 * Powermanagement manager mass management class.
 *
 * PHP version 5
 *
 * @category PowerManagementManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Powermanagement manager mass management class.
 *
 * @category PowerManagementManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PowerManagementManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'powerManagement';
    /**
     * Gets the predefined actions.
     *
     * @param string $selected the item that is selected
     * @param bool   $array    the item is an array
     * @param string $id       the id to set this with.
     *
     * @return string
     */
    public function getActionSelect(
        $selected = '',
        $array = false,
        $id = ''
    ) {
        $types = [
            'shutdown' => _('Shutdown'),
            'reboot' => _('Reboot'),
            'wol' => _('Wake On Lan'),
        ];
        self::$HookManager->processEvent(
            'PM_ACTION_TYPES',
            ['types' => &$types]
        );
        ob_start();
        foreach ((array) $types as $val => &$text) {
            printf(
                '<option value="%s"%s>%s</option>',
                Initiator::e(trim($val)),
                (
                    (isset($template) && $template !== false)
                    && trim($template) === trim($val) ?
                    ' selected' :
                    (
                        trim($selected) === trim($val) ?
                        ' selected' :
                        ''
                    )
                ),
                Initiator::e($text)
            );
        }

        return sprintf(
            '<select class="pmaction form-control" name="action%s"%s>%s%s</select>',
            (
                $array !== false ?
                '[]' :
                ''
            ),
            (
                $id ?
                ' id="'.$id.'"' :
                ''
            ),
            (
                $array === false ?
                sprintf(
                    '<option value="">- %s -</option>',
                    self::$foglang['PleaseSelect']
                ) :
                ''
            ),
            ob_get_clean()
        );
    }
}
