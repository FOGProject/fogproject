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
                'lsUseGroupMatch'
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
                '0'
            ],
            [
                'lsID',
                [
                    'lsAddress',
                    'lsPort'
                ],
                'lsName'
            ],
            'MyISAM',
            'utf8',
            'lsID',
            'lsID'
        );
        return self::$DB->query($sql);
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        $userIDs = self::getSubObjectIDs(
            'User',
            ['type' => LDAPPluginHook::LDAP_TYPES]
        );
        if (count($userIDs) > 0) {
            self::getClass('UserManager')
                ->destroy(['id' => $userIDs]);
        }
        return parent::uninstall();
    }
}
