<?php
/**
 * Powermanagement manager mass management class.
 *
 * PHP version 5
 *
 * @category PowerManagementManager
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
/**
 * Powermanagement manager mass management class.
 *
 * @category PowerManagementManager
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
class PowerManagementManager extends FOGManagerController
{
    /**
     * Gets the predefined actions.
     *
     * @param string $selected the item that is selected
     * @param bool   $array    the item is an array
     *
     * @return string
     */
    public function getActionSelect(
        $selected = '',
        $array = false
    ) {
        $types = array(
            'shutdown' => _('Shutdown'),
            'reboot' => _('Reboot'),
            'wol' => _('Wake On Lan'),
        );
        self::$HookManager->processEvent('PM_ACTION_TYPES', array('types' => &$types));
        ob_start();
        foreach ((array) $types as $val => &$text) {
            printf(
                '<option value="%s"%s>%s</option>',
                trim($val),
                (
                    $template !== false
                    && trim($template) === trim($val) ?
                    ' selected' :
                    (
                        trim($selected) === trim($val) ?
                        ' selected' :
                        ''
                    )
                ),
                $text
            );
        }

        return sprintf(
            '<select name="action%s">%s%s</select>',
            (
                $array !== false ?
                '[]' :
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
