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
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'pmID',
                'pmHostID',
                'pmMin',
                'pmHour',
                'pmDom',
                'pmMonth',
                'pmDow',
                'pmAction',
                'pmOndemand'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                "ENUM('shutdown', 'reboot', 'wol')",
                "ENUM('0', '1')"
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                'pmID',
            ),
            'InnoDB',
            'utf8',
            'pmID',
            'pmID'
        );
        return self::$DB->query($sql);
    }
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
        $types = array(
            'shutdown' => _('Shutdown'),
            'reboot' => _('Reboot'),
            'wol' => _('Wake On Lan'),
        );
        self::$HookManager->processEvent(
            'PM_ACTION_TYPES',
            array('types' => &$types)
        );
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
            '<select class="form-control" name="action%s"%s>%s%s</select>',
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
