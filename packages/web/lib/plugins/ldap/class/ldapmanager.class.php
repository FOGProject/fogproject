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
     * Brings shadow rows created before role mapping into the new model.
     *
     * Rows this plugin created previously carry uType 990/991, no
     * provenance stamp and no role, which under native RBAC resolves to
     * implicit administrator. Two things have to happen to them, and
     * neither can wait for the user to log in again: the stamp is what
     * stops core accepting a local password against the row, and the role
     * is what stops the zero-role fallback granting everything.
     *
     * 990 is deliberately mapped to the admin role even though it also
     * covers users of a server with group matching disabled -- the old code
     * stored both as 990. Mapping them all to the admin role preserves the
     * access these accounts have today rather than cutting anyone off mid
     * upgrade, and the first login afterwards re-syncs each of them to the
     * tier they actually belong to, because role sync is authoritative over
     * the mapped roles. An account that never logs in again holds a visible,
     * revocable role instead of silent implicit administrator, which is the
     * improvement either way.
     *
     * @return mixed true, or an error string to halt the update
     */
    public function backfillIdentities()
    {
        // uAuthSource arrives in core schema step 314. A plugin update can
        // be run before the core update has happened, and PDODB does not
        // throw on query errors, so stamping a column that is not there yet
        // would fail silently and leave these rows unprotected.
        $column = array_filter(
            (array)DatabaseManager::getColumns('users', 'uAuthSource')
        );
        if (count($column) < 1) {
            return _(
                'The FOG schema update must be run before this plugin can '
                . 'be updated: users.uAuthSource does not exist yet.'
            );
        }
        $roleByType = [
            LDAPPluginHook::LDAP_ADMIN => self::getSetting(
                'FOG_PLUGIN_LDAP_ADMIN_ROLE'
            ),
            LDAPPluginHook::LDAP_MOBILE => self::getSetting(
                'FOG_PLUGIN_LDAP_USER_ROLE'
            )
        ];
        foreach ($roleByType as $uType => $roleId) {
            $roleId = trim((string)$roleId);
            if ('' === $roleId) {
                // No mapping configured: stamp only. The row ends up with a
                // provenance and no role, which denies rather than grants.
                continue;
            }
            // INSERT IGNORE leans on the unique (ruaRoleID, ruaUserID) key
            // so re-running the update cannot duplicate a grant.
            self::$DB->query(
                'INSERT IGNORE INTO `roleUserAssoc` '
                . '(`ruaRoleID`, `ruaUserID`) '
                . 'SELECT :roleid, `uId` FROM `users` '
                . 'WHERE `uType` = :utype',
                [],
                ['roleid' => $roleId, 'utype' => $uType]
            );
        }
        // Stamped last, so a failure part way through leaves rows without
        // provenance rather than with provenance and no role -- the former
        // is the pre-existing state, the latter would lock the account out.
        self::$DB->query(
            'UPDATE `users` SET `uAuthSource` = :source '
            . 'WHERE `uType` IN (:admintype, :mobiletype) '
            . "AND `uAuthSource` = ''",
            [],
            [
                'source' => LDAPPluginHook::AUTH_SOURCE,
                'admintype' => LDAPPluginHook::LDAP_ADMIN,
                'mobiletype' => LDAPPluginHook::LDAP_MOBILE
            ]
        );
        return true;
    }
    /**
     * Turns each server's two group buckets into LDAPGroupMap rows.
     *
     * lsAdminGroup and lsUserGroup are already comma-separated lists of
     * group names; what they lacked was any way to point different names
     * at different roles. Every name in the admin list becomes a mapping
     * to whatever FOG_PLUGIN_LDAP_ADMIN_ROLE names, and every name in the
     * user list a mapping to FOG_PLUGIN_LDAP_USER_ROLE -- so this reshapes
     * the data without changing anyone's access.
     *
     * Servers with group matching off are migrated too. Their group lists
     * are inert while it stays off (nothing queries groups), but they are
     * the admin's configuration and dropping them would lose work the
     * moment they enabled matching.
     *
     * Idempotent: INSERT IGNORE against the unique index means re-running
     * cannot duplicate a mapping, and an admin who has since deleted a
     * mapping does not get it resurrected -- because the source columns
     * are read as they are, and a second run inserts the same rows the
     * first one did.
     *
     * Deliberately raw SQL rather than Route::getIds(): a directory group
     * name can legitimately contain '+' (multi-valued RDNs) or '*', and
     * Route's query builder rewrites both into a SQL wildcard, turning an
     * exact lookup into a LIKE that matches far too much.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     *
     * @return bool
     */
    public function migrateGroupMappings()
    {
        $roleByColumn = [
            'lsAdminGroup' => trim(
                (string)self::getSetting('FOG_PLUGIN_LDAP_ADMIN_ROLE')
            ),
            'lsUserGroup' => trim(
                (string)self::getSetting('FOG_PLUGIN_LDAP_USER_ROLE')
            )
        ];
        $servers = self::$DB
            ->query(
                'SELECT `lsID`, `lsAdminGroup`, `lsUserGroup` '
                . 'FROM `LDAPServers`'
            )
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$servers as $server) {
            foreach ($roleByColumn as $column => $roleId) {
                if ('' === $roleId) {
                    // No role configured for this bucket, so there is
                    // nothing for the group names to map to. Leaving the
                    // source column alone means the admin can set the
                    // mapping up by hand later without having lost it.
                    continue;
                }
                $groups = array_filter(
                    array_map(
                        'trim',
                        explode(',', (string)($server[$column] ?? ''))
                    ),
                    'strlen'
                );
                foreach ($groups as $group) {
                    self::$DB->query(
                        'INSERT IGNORE INTO `LDAPGroupMap` '
                        . '(`lgmServerID`, `lgmGroup`, `lgmTargetType`, '
                        . '`lgmTargetID`) '
                        . 'VALUES (:server, :group, :type, :target)',
                        [],
                        [
                            'server' => (int)$server['lsID'],
                            'group' => $group,
                            'type' => LDAPGroupMap::TARGET_ROLE,
                            'target' => (int)$roleId
                        ]
                    );
                }
            }
        }
        return true;
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
            // 3
            function () {
                return $this->backfillIdentities();
            },
            // 4
            // FOG_PLUGIN_LDAP_USER_FILTER answered "which user rows does
            // this plugin own?" with a list of uType sentinels. uAuthSource
            // answers it directly, so the setting is gone from the UI and
            // from seedSettings(); drop the stored row too rather than
            // leave an orphan an admin can still find and edit to no
            // effect.
            "DELETE FROM `globalSettings` "
            . "WHERE `settingKey` = 'FOG_PLUGIN_LDAP_USER_FILTER'",
            // 5
            function () {
                return self::getClass('LDAPGroupMapManager')->install();
            },
            // 6
            function () {
                return $this->migrateGroupMappings();
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
        // Which rows this plugin owns is the provenance stamp, not the old
        // uType sentinels. Keying on uType meant uninstalling could only
        // find rows written by this plugin's own past versions, and would
        // happily delete a local account that had somehow been given a 990
        // -- uType is a shared column anyone can write, including over the
        // API and CSV import.
        $find = ['authsource' => LDAPPluginHook::AUTH_SOURCE];
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
        self::getClass('LDAPGroupMapManager')->uninstall();
        return parent::uninstall();
    }
}
