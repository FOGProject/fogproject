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
            'MyISAM',
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
            . '(2, "MAIN_MENU_DATA-user", "MAIN_MENU_DATA", "user", '
            . '"main", "fog", NOW(), NULL), '
            . '(3, "MAIN_MENU_DATA-host", "MAIN_MENU_DATA", "host", '
            . '"main", "fog", NOW(), NULL), '
            . '(4, "MAIN_MENU_DATA-group", "MAIN_MENU_DATA", "group", '
            . '"main", "fog", NOW(), NULL), '
            . '(5, "MAIN_MENU_DATA-image", "MAIN_MENU_DATA", "image", '
            . '"main", "fog", NOW(), NULL), '
            . '(6, "MAIN_MENU_DATA-storage", "MAIN_MENU_DATA", "storage", '
            . '"main", "fog", NOW(), NULL), '
            . '(7, "MAIN_MENU_DATA-snapin", "MAIN_MENU_DATA", "snapin", '
            . '"main", "fog", NOW(), NULL), '
            . '(8, "MAIN_MENU_DATA-printer", "MAIN_MENU_DATA", "printer", '
            . '"main", "fog", NOW(), NULL), '
            . '(9, "MAIN_MENU_DATA-service", "MAIN_MENU_DATA", "service", '
            . '"main", "fog", NOW(), NULL), '
            . '(10, "MAIN_MENU_DATA-task", "MAIN_MENU_DATA", "task", '
            . '"main", "fog", NOW(), NULL), '
            . '(11, "MAIN_MENU_DATA-report", "MAIN_MENU_DATA", "report", '
            . '"main", "fog", NOW(), NULL), '
            . '(12, "MAIN_MENU_DATA-plugin", "MAIN_MENU_DATA", "plugin", '
            . '"main", "fog", NOW(), NULL), '
            . '(13, "MAIN_MENU_DATA-about", "MAIN_MENU_DATA", "about", '
            . '"main", "fog", NOW(), NULL), '
            . '(14, "SUB_MENULINK_DATA-list", "SUB_MENULINK_DATA", "list", '
            . '"menu", "fog", NOW(), NULL), '
            . '(15, "SUB_MENULINK_DATA-search", "SUB_MENULINK_DATA", "search", '
            . '"menu", "fog", NOW(), NULL), '
            . '(16, "SUB_MENULINK_DATA-import", "SUB_MENULINK_DATA", "import", '
            . '"menu", "fog", NOW(), NULL), '
            . '(17, "SUB_MENULINK_DATA-export", "SUB_MENULINK_DATA", "export", '
            . '"menu", "fog", NOW(), NULL), '
            . '(18, "SUB_MENULINK_DATA-add", "SUB_MENULINK_DATA", "add", '
            . '"menu", "fog", NOW(), NULL), '
            . '(19, "SUB_MENULINK_DATA-multicast", "SUB_MENULINK_DATA", "multicast", '
            . '"menu", "fog", NOW(), "image"), '
            . '(20, "SUB_MENULINK_DATA-storageGroup", "SUB_MENULINK_DATA", "storageGroup", '
            . '"menu", "fog", NOW(), "storage"), '
            . '(21, "SUB_MENULINK_DATA-addStorageNode", "SUB_MENULINK_DATA", '
            . '"addStorageNode", "menu", "fog", NOW(), "storage"), '
            . '(22, "SUB_MENULINK_DATA-addStorageGroup", "SUB_MENULINK_DATA", '
            . '"addStorageGroup", "menu", "fog", NOW(), "storage"), '
            . '(23, "SUB_MENULINK_DATA-actice", "SUB_MENULINK_DATA", '
            . '"active", "menu", "fog", NOW(), "task"), '
            . '(24, "SUB_MENULINK_DATA-listhosts", "SUB_MENULINK_DATA", "listhosts", '
            . '"menu", "fog", NOW(), "task"), '
            . '(25, "SUB_MENULINK_DATA-listgroups", "SUB_MENULINK_DATA", '
            . '"listgroups", "menu", "fog", NOW(), "task"), '
            . '(26, "SUB_MENULINK_DATA-activemulticast", "SUB_MENULINK_DATA", '
            . '"activemulticast", "menu", "fog", NOW(), "task"), '
            . '(27, "SUB_MENULINK_DATA-activesnapins", "SUB_MENULINK_DATA", '
            . '"activesnapins", "menu", "fog", NOW(), "task"), '
            . '(28, "SUB_MENULINK_DATA-activescheduled", "SUB_MENULINK_DATA", '
            . '"activescheduled", "menu", "fog", NOW(), "task"), '
            . '(29, "SUB_MENULINK_DATA-home", "SUB_MENULINK_DATA", "home", '
            . '"menu", "fog", NOW(), "about"), '
            . '(30, "SUB_MENULINK_DATA-license", "SUB_MENULINK_DATA", '
            . '"license", "menu", "fog", NOW(), "about"), '
            . '(31, "SUB_MENULINK_DATA-kernel", "SUB_MENULINK_DATA", '
            . '"kernel", "menu", "fog", NOW(), "about"), '
            . '(32, "SUB_MENULINK_DATA-pxemenu", "SUB_MENULINK_DATA", '
            . '"pxemenu", "menu", "fog", NOW(), "about"), '
            . '(33, "SUB_MENULINK_DATA-customizepxe", "SUB_MENULINK_DATA", '
            . '"customizepxe", "menu", "fog", NOW(), "about"), '
            . '(34,"SUB_MENULINK_DATA-newmenu","SUB_MENULINK_DATA", '
            . '"newmenu", "menu", "fog", NOW(), "about"), '
            . '(35, "SUB_MENULINK_DATA-clientupdater", "SUB_MENULINK_DATA", '
            . '"clientupdater", "menu", "fog", NOW(), "about"), '
            . '(36, "SUB_MENULINK_DATA-maclist", "SUB_MENULINK_DATA", '
            . '"maclist", "menu", "fog", NOW(), "about"), '
            . '(37, "SUB_MENULINK_DATA-settings", "SUB_MENULINK_DATA", '
            . '"settings", "menu", "fog", NOW(), "about"), '
            . '(38, "SUB_MENULINK_DATA-logviewer", "SUB_MENULINK_DATA", '
            . '"logviewer", "menu", "fog", NOW(), "about"), '
            . '(39, "SUB_MENULINK_DATA-config", "SUB_MENULINK_DATA", '
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
