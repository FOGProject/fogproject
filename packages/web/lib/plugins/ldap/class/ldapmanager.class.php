<?php
/**
 * LDAPManager
 *
 * PHP version 5
 *
 * @category LDAPManager
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * LDAPManager
 *
 * @category LDAP
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'LDAPServers';
    /**
     * Install the plugin, creates the table for us.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'lsID',
                'lsName',
                'lsDesc',
                'lsCreatedBy',
                'lsAddress',
                'lsCreatedTime',
                'lsUserSearchDN',
                'lsPort',
                'lsUserNamAttr',
                'lsGrpMemberAttr',
                'lsAdminGroup',
                'lsUserGroup',
                'lsSearchScope',
                'lsBindDN',
                'lsBindPwd',
                'lsGrpSearchDN',
                'lsUseGroupMatch',
                'lsUserFilter'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(40)',
                'VARCHAR(255)',
                'TIMESTAMP',
                'LONGTEXT',
                'INTEGER',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1', '2')",
                'LONGTEXT',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1')",
                'VARCHAR(255)'
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
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
                'CURRENT_TIMESTAMP',
                false,
                false,
                false,
                false,
                false,
                false,
                '0',
                false,
                false,
                false,
                '0',
                false
            ],
            [
                'lsID',
                'lsName'
            ],
            'MyISAM',
            'utf8',
            'lsID',
            'lsID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        $sql = "INSERT INTO `globalSettings` "
            . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
            . "VALUES "
            . "('FOG_PLUGIN_LDAP_USER_FILTER',"
            . "'Insert the filter type codes comma separated. Default: 990,991',"
            . "'990,991','Plugin: LDAP'),"
            . "('FOG_PLUGIN_LDAP_PORTS',"
            . "'Allowed LDAP Ports as defined by user. Default: 389,636',"
            . "'389,636','Plugin: LDAP')";
        return self::$DB->query($sql);
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        $find = ['type' => LDAPPluginHook::LDAP_TYPES];
        Route::ids(
            'user',
            $find
        );
        $userIDs = json_decode(
            Route::getData(),
            true
        );
        self::getClass('Service')->destroy(
            ['category' => 'Plugin: LDAP']
        );
        if (count($userIDs ?: []) > 0) {
            self::getClass('UserManager')
                ->destroy(['id' => $userIDs]);
        }
        return parent::uninstall();
    }
}
