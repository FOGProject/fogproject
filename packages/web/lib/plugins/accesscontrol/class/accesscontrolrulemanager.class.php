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
            array(
                'ruleID',
                'ruleName',
                'ruleType',
                'ruleValue',
                'ruleParent',
                'ruleCreatedBy',
                'ruleCreatedTime',
                'ruleNode'
            ),
            array(
                'INTEGER',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(40)'
            ),
            array(
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
                'CURRENT_TIMESTAMP',
                false
            ),
            array(),
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
            . '(2, "MAIN_MENU-user", "MAIN_MENU", "user", '
            . '"main", "fog", NOW(), NULL), '
            . '(3, "MAIN_MENU-host", "MAIN_MENU", "host", '
            . '"main", "fog", NOW(), NULL), '
            . '(4, "MAIN_MENU-group", "MAIN_MENU", "group", '
            . '"main", "fog", NOW(), NULL), '
            . '(5, "MAIN_MENU-image", "MAIN_MENU", "image", '
            . '"main", "fog", NOW(), NULL), '
            . '(6, "MAIN_MENU-storage", "MAIN_MENU", "storage", '
            . '"main", "fog", NOW(), NULL), '
            . '(7, "MAIN_MENU-snapin", "MAIN_MENU", "snapin", '
            . '"main", "fog", NOW(), NULL), '
            . '(8, "MAIN_MENU-printer", "MAIN_MENU", "printer", '
            . '"main", "fog", NOW(), NULL), '
            . '(9, "MAIN_MENU-service", "MAIN_MENU", "service", '
            . '"main", "fog", NOW(), NULL), '
            . '(10, "MAIN_MENU-task", "MAIN_MENU", "task", '
            . '"main", "fog", NOW(), NULL), '
            . '(11, "MAIN_MENU-report", "MAIN_MENU", "report", '
            . '"main", "fog", NOW(), NULL), '
            . '(12, "MAIN_MENU-plugin", "MAIN_MENU", "plugin", '
            . '"main", "fog", NOW(), NULL), '
            . '(13, "MAIN_MENU-about", "MAIN_MENU", "about", '
            . '"main", "fog", NOW(), NULL), '
            . '(14, "SUB_MENULINK-list", "SUB_MENULINK", "list", '
            . '"menu", "fog", NOW(), NULL), '
            . '(15, "SUB_MENULINK-search", "SUB_MENULINK", "search", '
            . '"menu", "fog", NOW(), NULL), '
            . '(16, "SUB_MENULINK-import", "SUB_MENULINK", "import", '
            . '"menu", "fog", NOW(), NULL), '
            . '(17, "SUB_MENULINK-export", "SUB_MENULINK", "export", '
            . '"menu", "fog", NOW(), NULL), '
            . '(18, "SUB_MENULINK-add", "SUB_MENULINK", "add", '
            . '"menu", "fog", NOW(), NULL), '
            . '(19, "SUB_MENULINK-multicast", "SUB_MENULINK", "multicast", '
            . '"menu", "fog", NOW(), "image"), '
            . '(20, "SUB_MENULINK-storageGroup", "SUB_MENULINK", "storageGroup", '
            . '"menu", "fog", NOW(), "storage"), '
            . '(21, "SUB_MENULINK-addStorageNode", "SUB_MENULINK", '
            . '"addStorageNode", "menu", "fog", NOW(), "storage"), '
            . '(22, "SUB_MENULINK-addStorageGroup", "SUB_MENULINK", '
            . '"addStorageGroup", "menu", "fog", NOW(), "storage"), '
            . '(23, "SUB_MENULINK-actice", "SUB_MENULINK", '
            . '"active", "menu", "fog", NOW(), "task"), '
            . '(24, "SUB_MENULINK-listhosts", "SUB_MENULINK", "listhosts", '
            . '"menu", "fog", NOW(), "task"), '
            . '(25, "SUB_MENULINK-listgroups", "SUB_MENULINK", '
            . '"listgroups", "menu", "fog", NOW(), "task"), '
            . '(26, "SUB_MENULINK-activemulticast", "SUB_MENULINK", '
            . '"activemulticast", "menu", "fog", NOW(), "task"), '
            . '(27, "SUB_MENULINK-activesnapins", "SUB_MENULINK", '
            . '"activesnapins", "menu", "fog", NOW(), "task"), '
            . '(28, "SUB_MENULINK-activescheduled", "SUB_MENULINK", '
            . '"activescheduled", "menu", "fog", NOW(), "task"), '
            . '(29, "SUB_MENULINK-home", "SUB_MENULINK", "home", '
            . '"menu", "fog", NOW(), "about"), '
            . '(30, "SUB_MENULINK-license", "SUB_MENULINK", '
            . '"license", "menu", "fog", NOW(), "about"), '
            . '(31, "SUB_MENULINK-kernelUpdate", "SUB_MENULINK", '
            . '"kernelUpdate", "menu", "fog", NOW(), "about"), '
            . '(32, "SUB_MENULINK-pxemenu", "SUB_MENULINK", '
            . '"pxemenu", "menu", "fog", NOW(), "about"), '
            . '(33, "SUB_MENULINK-customizepxe", "SUB_MENULINK", '
            . '"customizepxe", "menu", "fog", NOW(), "about"), '
            . '(34,"SUB_MENULINK-newmenu","SUB_MENULINK", '
            . '"newmenu", "menu", "fog", NOW(), "about"), '
            . '(35, "SUB_MENULINK-clientupdater", "SUB_MENULINK", '
            . '"clientupdater", "menu", "fog", NOW(), "about"), '
            . '(36, "SUB_MENULINK-maclist", "SUB_MENULINK", '
            . '"maclist", "menu", "fog", NOW(), "about"), '
            . '(37, "SUB_MENULINK-settings", "SUB_MENULINK", '
            . '"settings", "menu", "fog", NOW(), "about"), '
            . '(38, "SUB_MENULINK-logviewer", "SUB_MENULINK", '
            . '"logviewer", "menu", "fog", NOW(), "about"), '
            . '(39, "SUB_MENULINK-config", "SUB_MENULINK", '
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
