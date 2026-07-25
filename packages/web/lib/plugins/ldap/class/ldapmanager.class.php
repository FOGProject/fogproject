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
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
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
                'lsGroupNamAttr',
                'lsGrpMemberAttr',
                'lsAdminGroup',
                'lsUserGroup',
                'lsSearchScope',
                'lsBindDN',
                'lsBindPwd',
                'lsGrpSearchDN',
                'lsUseGroupMatch',
                'lsUserFilter',
                'lsDisplayNameEnabled',
                'lsDisplayNameAttr',
                'lsIsLDAPs',
                'lsAllowAPI'
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
                'VARCHAR(255)',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1', '2')",
                'LONGTEXT',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1')",
                'VARCHAR(255)',
                "ENUM('0','1')",
                'VARCHAR(255)',
                "ENUM('0', '1')",
                "ENUM('0', '1')"
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
                false,
                '0',
                false,
                false,
                false,
                '0',
                false,
                false,
                false,
                false,
                false
            ],
            [
                'lsID',
                'lsName'
            ],
            'InnoDB',
            'utf8',
            'lsID',
            'lsID'
        );
    }
    /**
     * Seeds this plugin's global settings, but only the ones that are
     * missing, so an admin's existing values are never overwritten on a
     * re-run/upgrade. Used as a step in schema().
     *
     * @return bool
     */
    public function seedSettings()
    {
        $category = 'Plugin: LDAP';
        $fields = [
            'name',
            'description',
            'value',
            'category'
        ];
        $settings = [
            [
                'FOG_PLUGIN_LDAP_USER_FILTER',
                'Insert the filter type codes comma separated. Default: 990,991',
                '990,991',
                $category
            ],
            [
                'FOG_PLUGIN_LDAP_PORTS',
                'Allowed LDAP Ports as defined by user. Default: 389,636',
                '389,636',
                $category
            ],
            [
                'FOG_PLUGIN_LDAP_ADMIN_ROLE',
                'Role granted to a user found in an LDAP server\'s admin '
                . 'group. Blank grants nothing.',
                self::_defaultRoleId('Administrator'),
                $category
            ],
            [
                'FOG_PLUGIN_LDAP_USER_ROLE',
                'Role granted to a user found in an LDAP server\'s user '
                . 'group. Blank grants nothing.',
                self::_defaultRoleId('Technician'),
                $category
            ],
            [
                // Group matching is per-server and off by default, and with
                // it off the directory cannot tell an admin from anyone who
                // can bind -- so this is the role EVERY bindable account
                // gets on such a server. It used to be hardcoded to full
                // administrator, which made every account in the directory
                // a FOG superuser on a stock config. Defaulting it to the
                // technician tier keeps that from being the shipped
                // behaviour while leaving the choice with the admin.
                'FOG_PLUGIN_LDAP_NOMATCH_ROLE',
                'Role granted to every account that can bind to an LDAP '
                . 'server which has group matching disabled. Blank grants '
                . 'nothing.',
                self::_defaultRoleId('Technician'),
                $category
            ]
        ];
        $SettingManager = self::getClass('SettingManager');
        $toInsert = [];
        foreach ($settings as $setting) {
            if (!$SettingManager->exists($setting[0], '', 'name')) {
                $toInsert[] = $setting;
            }
        }
        if (count($toInsert)) {
            $SettingManager->insertBatch($fields, $toInsert);
        }
        return true;
    }
    /**
     * The id of a seeded role, looked up by name.
     *
     * Matched by name rather than by the ids the core schema seeds (1 and
     * 2), following the precedent core itself sets when it back-grants the
     * technician permission set: a role can be renamed or renumbered, and a
     * blank default is a safe answer here because a blank role setting
     * grants nothing.
     *
     * @param string $name the role name to resolve
     *
     * @return string
     */
    private static function _defaultRoleId($name)
    {
        $ids = Route::getIds('role', ['name' => $name], 'id');
        return (string)(array_shift($ids) ?: '');
    }
    /**
     * The plugin's ordered, append-only schema migration list. Append new
     * steps (e.g. "ALTER TABLE `LDAPServers` ADD COLUMN ...") to the END.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0
            $this->createSql(),
            // 1
            function () {
                return $this->seedSettings();
            },
            // 2
            // seedSettings() again, deliberately. It only inserts settings
            // that are missing, so re-running it is how an already-installed
            // plugin picks up the role mapping keys added above without
            // disturbing values an admin has already chosen. Step 1 does not
            // re-run on an existing install; a new step does.
            function () {
                return $this->seedSettings();
            },
        ];
    }
    /**
     * Installs the plugin database non-destructively (create-if-absent +
     * seed any missing settings). Does not drop existing data or values.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        $find = ['type' => LDAPPluginHook::LDAP_TYPES];
        $userIDs = Route::getIds(
            'user',
            $find
        );
        Route::deletemass(
            'setting',
            ['category' => 'Plugin: LDAP']
        );
        if (count($userIDs ?: [])) {
            Route::deletemass(
                'user',
                ['id' => $userIDs]
            );
        }
        return parent::uninstall();
    }
}
