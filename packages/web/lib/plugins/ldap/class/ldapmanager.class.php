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
        /**
         * Idempotence guard. This runs on every plugin install because
         * applyUpdates() replays the whole list from zero, and a seed that
         * re-ran would resurrect mappings an admin had deliberately
         * deleted. Any existing row means the migration has already
         * happened.
         */
        if ($this->_ldapGroupCount() > 0) {
            return true;
        }
        /**
         * LDAPGroupMap was the first shape of this feature: one row per
         * (group name, target) pair. It shipped only briefly, and an
         * install that has it already folded lsAdminGroup/lsUserGroup into
         * it, so it is the better source when present.
         */
        if ($this->_tableExists('LDAPGroupMap')) {
            return $this->_migrateFromGroupMap();
        }
        return $this->_migrateFromServerColumns();
    }
    /**
     * How many directory groups are defined.
     *
     * @return int
     */
    private function _ldapGroupCount()
    {
        try {
            $rows = self::$DB
                ->query('SELECT COUNT(`lgID`) AS `cnt` FROM `LDAPGroups`')
                ->fetch('', 'fetch_all')
                ->get();
        } catch (Exception $e) {
            return 0;
        }
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }
        return (int)($rows[0]['cnt'] ?? 0);
    }
    /**
     * Whether a table is present in the current database.
     *
     * @param string $table the table name
     *
     * @return bool
     */
    private function _tableExists($table)
    {
        try {
            $rows = self::$DB
                ->query('SHOW TABLES LIKE :table', [], ['table' => $table])
                ->fetch('', 'fetch_all')
                ->get();
        } catch (Exception $e) {
            return false;
        }
        return is_array($rows) && !empty($rows);
    }
    /**
     * Ensures a directory group row exists and returns its id.
     *
     * INSERT IGNORE then SELECT rather than lastInsertId, because the row
     * may already exist -- the unique index on (server, name) is what makes
     * the whole migration safe to replay.
     *
     * @param int    $serverId the LDAP server the group belongs to
     * @param string $name     the directory group name
     *
     * @return int the group id, 0 when it could not be created
     */
    private function _ensureGroup($serverId, $name)
    {
        self::$DB->query(
            'INSERT IGNORE INTO `LDAPGroups` (`lgServerID`, `lgName`) '
            . 'VALUES (:server, :name)',
            [],
            ['server' => (int)$serverId, 'name' => $name]
        );
        $rows = self::$DB
            ->query(
                'SELECT `lgID` FROM `LDAPGroups` '
                . 'WHERE `lgServerID` = :server AND `lgName` = :name',
                [],
                ['server' => (int)$serverId, 'name' => $name]
            )
            ->fetch('', 'fetch_all')
            ->get();
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }
        return (int)($rows[0]['lgID'] ?? 0);
    }
    /**
     * Links a directory group to a role or a user group.
     *
     * @param int    $groupId  the LDAPGroups row id
     * @param string $type     'role' or 'usergroup'
     * @param int    $targetId the role or user group id
     *
     * @return void
     */
    private function _linkGroup($groupId, $type, $targetId)
    {
        if ($groupId < 1 || $targetId < 1) {
            return;
        }
        if ('usergroup' === $type) {
            $sql = 'INSERT IGNORE INTO `ldapGroupUserGroupAssoc` '
                . '(`lgugGroupID`, `lgugUserGroupID`) '
                . 'VALUES (:group, :target)';
        } else {
            $sql = 'INSERT IGNORE INTO `ldapGroupRoleAssoc` '
                . '(`lgraGroupID`, `lgraRoleID`) '
                . 'VALUES (:group, :target)';
        }
        self::$DB->query(
            $sql,
            [],
            ['group' => (int)$groupId, 'target' => (int)$targetId]
        );
    }
    /**
     * Moves LDAPGroupMap rows into the group + association tables.
     *
     * @return bool
     */
    private function _migrateFromGroupMap()
    {
        $rows = self::$DB
            ->query(
                'SELECT `lgmServerID`, `lgmGroup`, `lgmTargetType`, '
                . '`lgmTargetID` FROM `LDAPGroupMap`'
            )
            ->fetch('', 'fetch_all')
            ->get();
        if (!is_array($rows)) {
            return true;
        }
        foreach ($rows as $row) {
            $name = trim((string)($row['lgmGroup'] ?? ''));
            if ('' === $name) {
                continue;
            }
            $groupId = $this->_ensureGroup(
                (int)($row['lgmServerID'] ?? 0),
                $name
            );
            $this->_linkGroup(
                $groupId,
                (string)($row['lgmTargetType'] ?? 'role'),
                (int)($row['lgmTargetID'] ?? 0)
            );
        }
        return true;
    }
    /**
     * Seeds the group + association tables from the original two buckets.
     *
     * lsAdminGroup and lsUserGroup are comma-separated name lists, each
     * pointing at one globally configured role. Both columns and both
     * settings are left in place: they are the migration's only input, and
     * an install that re-runs before an admin has authored anything must
     * still be able to reconstruct the same mappings.
     *
     * @return bool
     */
    private function _migrateFromServerColumns()
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
            ->fetch('', 'fetch_all')
            ->get();
        if (!is_array($servers)) {
            return true;
        }
        foreach ($servers as $server) {
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
                    $groupId = $this->_ensureGroup(
                        (int)$server['lsID'],
                        $group
                    );
                    $this->_linkGroup($groupId, 'role', (int)$roleId);
                }
            }
        }
        return true;
    }
    /**
     * Seeds the grant record for users who signed in before it existed.
     *
     * Without this, an existing LDAP user's first sign in after the upgrade
     * finds nothing recorded, so nothing is revocable, and it then records
     * only what they earn now -- which means a role they should have lost
     * would survive that login and every one after it. Seeding from the
     * current state closes that window.
     *
     * The seed deliberately reproduces the OLD managed-set rule: a grant is
     * assumed to be this plugin's if the target is currently a mapping
     * target (or is the no-match role). That is exactly what the sync would
     * have considered its own a moment before the upgrade, so nothing an
     * admin attached by hand is captured, and nothing the plugin granted is
     * missed -- except a target whose last mapping was already deleted,
     * which the old rule had already given away for good.
     *
     * Scoped to uAuthSource = 'ldap' so a local account that happens to
     * hold a mapped role is never recorded as a plugin grant.
     *
     * @return bool
     */
    public function seedUserGrants()
    {
        $nomatch = trim(
            (string)self::getSetting('FOG_PLUGIN_LDAP_NOMATCH_ROLE')
        );
        $roleFilter = '`ruaRoleID` IN (SELECT `lgraRoleID` '
            . 'FROM `ldapGroupRoleAssoc`)';
        $binds = ['source' => LDAPPluginHook::AUTH_SOURCE];
        if ('' !== $nomatch) {
            $roleFilter = '(' . $roleFilter . ' OR `ruaRoleID` = :nomatch)';
            $binds['nomatch'] = (int)$nomatch;
        }
        try {
            self::$DB->query(
                'INSERT IGNORE INTO `ldapUserGrant` '
                . '(`lugUserID`, `lugTargetType`, `lugTargetID`) '
                . "SELECT `ruaUserID`, 'role', `ruaRoleID` "
                . 'FROM `roleUserAssoc` '
                . 'INNER JOIN `users` ON `uID` = `ruaUserID` '
                . 'WHERE `uAuthSource` = :source AND ' . $roleFilter,
                [],
                $binds
            );
            self::$DB->query(
                'INSERT IGNORE INTO `ldapUserGrant` '
                . '(`lugUserID`, `lugTargetType`, `lugTargetID`) '
                . "SELECT `ugmUserID`, 'usergroup', `ugmGroupID` "
                . 'FROM `userGroupMembers` '
                . 'INNER JOIN `users` ON `uID` = `ugmUserID` '
                . 'WHERE `uAuthSource` = :source '
                . 'AND `ugmGroupID` IN (SELECT `lgugUserGroupID` '
                . 'FROM `ldapGroupUserGroupAssoc`)',
                [],
                ['source' => LDAPPluginHook::AUTH_SOURCE]
            );
        } catch (Exception $e) {
            error_log(
                sprintf(
                    '%s %s() %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Could not seed the granted targets'),
                    $e->getMessage()
                )
            );
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
            // Steps 5 and 6 used to create LDAPGroupMap and migrate the two
            // buckets into it. That table is superseded by LDAPGroups plus
            // two association tables, and these two slots were rewritten in
            // place to build the new ones instead.
            //
            // That rewrite was a mistake, and steps 10-16 exist to repair
            // it. Plugin::installdb() passes the plugin's stored pSchema as
            // $applied, so applyUpdates() SKIPS the first $applied steps --
            // it does not replay from zero. An install that had already
            // recorded 7 applied steps therefore never ran the rewritten 5,
            // 6 and 7, and ended up with the migration and the DROP running
            // against tables that were never created.
            //
            // Rewriting a step that anyone has already applied is invisible
            // to them. Only ever append.
            function () {
                return self::getClass('LDAPGroupManager')->install();
            },
            // 6
            function () {
                return self::getClass('LDAPGroupRoleAssociationManager')
                    ->install();
            },
            // 7
            function () {
                return self::getClass('LDAPGroupUserGroupAssociationManager')
                    ->install();
            },
            // 8
            function () {
                return $this->migrateGroupMappings();
            },
            // 9
            // Dropped only after step 8 has copied it out. Ordering within
            // the list is what makes that safe.
            'DROP TABLE IF EXISTS `LDAPGroupMap`',
            // 10-16: repair for the in-place rewrite of steps 5-7 described
            // above. Every one of these is idempotent, so an install that
            // ran 5-9 correctly passes straight through them, while an
            // install that skipped them gets the tables, the migration and
            // the drop it missed -- in that order, so the drop still cannot
            // outrun the copy.
            // 10
            function () {
                return self::getClass('LDAPGroupManager')->install();
            },
            // 11
            function () {
                return self::getClass('LDAPGroupRoleAssociationManager')
                    ->install();
            },
            // 12
            function () {
                return self::getClass('LDAPGroupUserGroupAssociationManager')
                    ->install();
            },
            // 13
            // CREATE TABLE IF NOT EXISTS cannot repair a table that already
            // exists, so a partial run that created an association table
            // before the name column was added leaves it without one --
            // and Route::ids() orders by name, so every lookup against it
            // fails. ALTER explicitly; applyUpdates() tolerates 1060 when
            // the column is already there.
            "ALTER TABLE `ldapGroupRoleAssoc` "
            . "ADD COLUMN `lgraName` VARCHAR(60) NOT NULL DEFAULT ''",
            // 14
            "ALTER TABLE `ldapGroupUserGroupAssoc` "
            . "ADD COLUMN `lgugName` VARCHAR(60) NOT NULL DEFAULT ''",
            // 15
            function () {
                return $this->migrateGroupMappings();
            },
            // 16
            'DROP TABLE IF EXISTS `LDAPGroupMap`',
            // 17
            function () {
                return self::getClass('LDAPUserGrantManager')->install();
            },
            // 18
            function () {
                return $this->seedUserGrants();
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
        // Associations first, then the groups they point at: dropping the
        // groups first would leave association rows referencing ids that no
        // longer exist if either drop failed part way.
        self::getClass('LDAPUserGrantManager')->uninstall();
        self::getClass('LDAPGroupRoleAssociationManager')->uninstall();
        self::getClass('LDAPGroupUserGroupAssociationManager')->uninstall();
        self::getClass('LDAPGroupManager')->uninstall();
        return parent::uninstall();
    }
}
