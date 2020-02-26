<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlRuleManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRuleManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRuleManager extends FOGManagerController
{
    /**
     * Table name
     *
     * @var string
     */
    public $tablename = 'rules';
    /**
     * Installs the database for the plugin.
     *
     * @return bool
     */
    public function install()
    {
        /**
         * Add the information into the database.
         * This is commented out so we don't actually
         * create anything.
         */
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'ruleID',
                'ruleName',
                'ruleType',
                'ruleValue',
                'ruleParent',
                'ruleCreatedBy',
                'ruleCreatedTime',
                'ruleNode'
            ],
            [
                'INTEGER',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(40)'
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                false
            ],
            [],
            'InnoDB',
            'utf8',
            'ruleID',
            'ruleID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        $sql = 'INSERT INTO '
            . $this->tablename
            . ' VALUES '
            . '(2, "DELETE_MENU_DATA-user", "DELETE_MENU_DATA", "user", '
            . '"main", "fog", NOW(), NULL), '
            . '(3, "DELETE_MENU_DATA-host", "DELETE_MENU_DATA", "host", '
            . '"main", "fog", NOW(), NULL), '
            . '(4, "DELETE_MENU_DATA-group", "DELETE_MENU_DATA", "group", '
            . '"main", "fog", NOW(), NULL), '
            . '(5, "DELETE_MENU_DATA-image", "DELETE_MENU_DATA", "image", '
            . '"main", "fog", NOW(), NULL), '
            . '(6, "DELETE_MENU_DATA-storage", "DELETE_MENU_DATA", "storage", '
            . '"main", "fog", NOW(), NULL), '
            . '(7, "DELETE_MENU_DATA-snapin", "DELETE_MENU_DATA", "snapin", '
            . '"main", "fog", NOW(), NULL), '
            . '(8, "DELETE_MENU_DATA-printer", "DELETE_MENU_DATA", "printer", '
            . '"main", "fog", NOW(), NULL), '
            . '(9, "DELETE_MENU_DATA-service", "DELETE_MENU_DATA", "service", '
            . '"main", "fog", NOW(), NULL), '
            . '(10, "DELETE_MENU_DATA-task", "DELETE_MENU_DATA", "task", '
            . '"main", "fog", NOW(), NULL), '
            . '(11, "DELETE_MENU_DATA-report", "DELETE_MENU_DATA", "report", '
            . '"main", "fog", NOW(), NULL), '
            . '(12, "DELETE_MENU_DATA-plugin", "DELETE_MENU_DATA", "plugin", '
            . '"main", "fog", NOW(), NULL), '
            . '(13, "DELETE_MENU_DATA-about", "DELETE_MENU_DATA", "about", '
            . '"main", "fog", NOW(), NULL), '
            . '(14, "DELETE_MENULINK_DATA-list", "DELETE_MENULINK_DATA", "list", '
            . '"menu", "fog", NOW(), NULL), '
            . '(15, "DELETE_MENULINK_DATA-search", "DELETE_MENULINK_DATA", "search", '
            . '"menu", "fog", NOW(), NULL), '
            . '(16, "DELETE_MENULINK_DATA-import", "DELETE_MENULINK_DATA", "import", '
            . '"menu", "fog", NOW(), NULL), '
            . '(17, "DELETE_MENULINK_DATA-export", "DELETE_MENULINK_DATA", "export", '
            . '"menu", "fog", NOW(), NULL), '
            . '(18, "DELETE_MENULINK_DATA-add", "DELETE_MENULINK_DATA", "add", '
            . '"menu", "fog", NOW(), NULL), '
            . '(19, "DELETE_MENULINK_DATA-multicast", "DELETE_MENULINK_DATA", "multicast", '
            . '"menu", "fog", NOW(), "image"), '
            . '(20, "DELETE_MENULINK_DATA-storageGroup", "DELETE_MENULINK_DATA", "storageGroup", '
            . '"menu", "fog", NOW(), "storage"), '
            . '(21, "DELETE_MENULINK_DATA-addStorageNode", "DELETE_MENULINK_DATA", '
            . '"addStorageNode", "menu", "fog", NOW(), "storage"), '
            . '(22, "DELETE_MENULINK_DATA-addStorageGroup", "DELETE_MENULINK_DATA", '
            . '"addStorageGroup", "menu", "fog", NOW(), "storage"), '
            . '(23, "DELETE_MENULINK_DATA-active", "DELETE_MENULINK_DATA", '
            . '"active", "menu", "fog", NOW(), "task"), '
            . '(24, "DELETE_MENULINK_DATA-listhosts", "DELETE_MENULINK_DATA", "listhosts", '
            . '"menu", "fog", NOW(), "task"), '
            . '(25, "DELETE_MENULINK_DATA-listgroups", "DELETE_MENULINK_DATA", '
            . '"listgroups", "menu", "fog", NOW(), "task"), '
            . '(26, "DELETE_MENULINK_DATA-activemulticast", "DELETE_MENULINK_DATA", '
            . '"activemulticast", "menu", "fog", NOW(), "task"), '
            . '(27, "DELETE_MENULINK_DATA-activesnapins", "DELETE_MENULINK_DATA", '
            . '"activesnapins", "menu", "fog", NOW(), "task"), '
            . '(28, "DELETE_MENULINK_DATA-activescheduled", "DELETE_MENULINK_DATA", '
            . '"activescheduled", "menu", "fog", NOW(), "task"), '
            . '(29, "DELETE_MENULINK_DATA-home", "DELETE_MENULINK_DATA", "home", '
            . '"menu", "fog", NOW(), "about"), '
            . '(30, "DELETE_MENULINK_DATA-license", "DELETE_MENULINK_DATA", '
            . '"license", "menu", "fog", NOW(), "about"), '
            . '(31, "DELETE_MENULINK_DATA-kernel", "DELETE_MENULINK_DATA", '
            . '"kernel", "menu", "fog", NOW(), "about"), '
            . '(32, "DELETE_MENULINK_DATA-pxemenu", "DELETE_MENULINK_DATA", '
            . '"pxemenu", "menu", "fog", NOW(), "about"), '
            . '(33, "DELETE_MENULINK_DATA-customizepxe", "DELETE_MENULINK_DATA", '
            . '"customizepxe", "menu", "fog", NOW(), "about"), '
            . '(34,"DELETE_MENULINK_DATA-newmenu","DELETE_MENULINK_DATA", '
            . '"newmenu", "menu", "fog", NOW(), "about"), '
            . '(35, "DELETE_MENULINK_DATA-clientupdater", "DELETE_MENULINK_DATA", '
            . '"clientupdater", "menu", "fog", NOW(), "about"), '
            . '(36, "DELETE_MENULINK_DATA-maclist", "DELETE_MENULINK_DATA", '
            . '"maclist", "menu", "fog", NOW(), "about"), '
            . '(37, "DELETE_MENULINK_DATA-settings", "DELETE_MENULINK_DATA", '
            . '"settings", "menu", "fog", NOW(), "about"), '
            . '(38, "DELETE_MENULINK_DATA-logviewer", "DELETE_MENULINK_DATA", '
            . '"logviewer", "menu", "fog", NOW(), "about"), '
            . '(39, "DELETE_MENULINK_DATA-config", "DELETE_MENULINK_DATA", '
            . '"config", "menu", "fog", NOW(), "about")';
        if (self::$DB->query($sql)) {
            $sql = "CREATE UNIQUE INDEX `indexmul` "
                    . "`rules` (`ruleValue`, `ruleNode`)";
            self::$DB->query($sql);
            return self::getClass('AccessControlRuleAssociationManager')->install();
        } else {
            return true;
        }
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        self::getClass('AccessControlRuleAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
