<?php
/**
 * Foreign-key constraints this release declares, and the ones it declines to.
 *
 * NORMATIVE. `SchemaReconciler::planConstraints()` reads this file at runtime
 * and adds any enabled constraint the live database is missing, which is why
 * it lives beside commons/schema-expected.php rather than in bin/ -- the repo
 * root is never deployed. bin/fk-orphan-scan.php and bin/fk-lab-fixture.php
 * read it too, so the survey and the migration cannot drift apart.
 *
 * Every entry names one child column that holds an id belonging to another
 * table. `class` is the decision axis:
 *
 *   junction   an association row that exists only to link two things. Its
 *              own existence is meaningless once either end is gone.
 *   satellite  a 1:1 or 1:N row wholly owned by its parent -- inventory for a
 *              host, an auth session for a user.
 *   config     a reference to a configuration object that has a life of its
 *              own. The child is the dependant; the parent must not vanish
 *              underneath it.
 *   work       a task or job. Deleting the parent while one is live is an
 *              operational question, not a data-integrity one.
 *   audit      a record of something that happened. MUST NOT constrain the
 *              thing it recorded -- see ADR 0021 and schema.php step 341.
 *
 * `action` is the ON DELETE action for THIS relationship, not for its class.
 * The two are not the same and cannot be collapsed: tasks.taskHostID is
 * CASCADE while tasks.taskStateID is RESTRICT, and hosts.hostImage is SET
 * NULL while scheduledTasks.stImageID is RESTRICT. `none` means no
 * constraint is declared at all, which is a decision rather than an omission.
 *
 * The rule behind the actions: where Route::deletemass() already implements a
 * behavior, the constraint pins it and nothing observable changes. CASCADE
 * where it deletes; SET NULL where it zeroes, as the image cascade does for
 * hosts.hostImage. RESTRICT only where PHP does nothing today and the
 * reference silently dangles.
 *
 * `enabled` is the phasing. A constraint lands when its step lands, so the
 * reconciler ignores anything still false. Flipping a group to true IS the
 * commit that adds it, which is what makes each step reviewable on its own.
 *
 * `sentinel` names a value the column already uses to mean "no reference".
 * A foreign key accepts NULL for that and nothing else, so an entry with a
 * sentinel cannot be enabled until the column is nullable and the value
 * converted.
 *
 * `poly` marks a column whose target table is chosen by a sibling column. No
 * constraint is possible at all; recorded so the survey states why rather
 * than leaving it looking overlooked.
 *
 * Constraints are named fk_<childTable>_<childColumn> -- not
 * fk_<child>_<parent>, because tasks.taskNFSMemberID and
 * tasks.taskLastMemberID both reference nfsGroupMembers and would collide.
 *
 * Full survey, per-relationship reasoning and sequencing:
 * docs/development/foreign-keys.md and ADR 0031.
 *
 * PHP version 7.4+
 *
 * @category Schema
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

return [
    // ---- junction: association rows -------------------------------------
    ['child' => 'groupMembers', 'column' => 'gmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'groupMembers', 'column' => 'gmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'hostMAC', 'column' => 'hmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'snapinAssoc', 'column' => 'saHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'snapinAssoc', 'column' => 'saSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'snapinGroupAssoc', 'column' => 'sgaSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'snapinGroupAssoc', 'column' => 'sgaStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'imageGroupAssoc', 'column' => 'igaImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'imageGroupAssoc', 'column' => 'igaStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'printerAssoc', 'column' => 'paHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'printerAssoc', 'column' => 'paPrinterID', 'parent' => 'printers', 'pcolumn' => 'pID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'moduleStatusByHost', 'column' => 'msHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'moduleStatusByHost', 'column' => 'msModuleID', 'parent' => 'modules', 'pcolumn' => 'id', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'multicastSessionsAssoc', 'column' => 'msID', 'parent' => 'multicastSessions', 'pcolumn' => 'msID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'multicastSessionsAssoc', 'column' => 'tID', 'parent' => 'tasks', 'pcolumn' => 'taskID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'siteHostMembers', 'column' => 'shmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteHostMembers', 'column' => 'shmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteGroupMembers', 'column' => 'sgmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteGroupMembers', 'column' => 'sgmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteUserMembers', 'column' => 'sumSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteUserMembers', 'column' => 'sumUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteUserGroupMembers', 'column' => 'sugmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteUserGroupMembers', 'column' => 'sugmUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteRoleGrants', 'column' => 'srgSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteRoleGrants', 'column' => 'srgRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteUserGroupGrants', 'column' => 'suggSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'siteUserGroupGrants', 'column' => 'suggGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'roleUserAssoc', 'column' => 'ruaRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'roleUserAssoc', 'column' => 'ruaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'roleUserGroupAssoc', 'column' => 'rugRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'roleUserGroupAssoc', 'column' => 'rugGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'rolePermissions', 'column' => 'rpRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'userGroupMembers', 'column' => 'ugmGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'userGroupMembers', 'column' => 'ugmUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true],

    // ---- satellite: rows wholly owned by one parent ----------------------
    ['child' => 'inventory', 'column' => 'iHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'hostScreenSettings', 'column' => 'hssHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'hostAutoLogOut', 'column' => 'haloHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'powerManagement', 'column' => 'pmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'greenFog', 'column' => 'gfHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'apiTokens', 'column' => 'atUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],
    ['child' => 'userAuths', 'column' => 'uaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true],

    // ---- config: references to configuration with its own life ----------
    //
    // nfsGroupMembers.ngmGroupID was classed satellite and shipped CASCADE
    // in schema step 384. That was wrong on both counts and is corrected
    // here. A storage node is not a satellite of its group: it carries its
    // own hostname, credentials, paths, bandwidth limit and enable flag, and
    // StorageGroup::removeNode() detaches one without deleting it -- so
    // "belongs to no group" is a state FOG itself creates and the Storage
    // Node list still shows. Under CASCADE, deleting a group would have
    // silently destroyed every node's configuration with it.
    //
    // It stays disabled until the sentinel conversion makes the column
    // nullable, because SET NULL cannot be declared on a NOT NULL column.
    ['child' => 'nfsGroupMembers', 'column' => 'ngmGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'hosts', 'column' => 'hostImage', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'hosts', 'column' => 'hostArchID', 'parent' => 'architectures', 'pcolumn' => 'archID', 'class' => 'config', 'action' => 'SET NULL', 'enabled' => false],
    ['child' => 'images', 'column' => 'imageOSID', 'parent' => 'os', 'pcolumn' => 'osID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'images', 'column' => 'imageTypeID', 'parent' => 'imageTypes', 'pcolumn' => 'imageTypeID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'images', 'column' => 'imagePartitionTypeID', 'parent' => 'imagePartitionTypes', 'pcolumn' => 'imagePartitionTypeID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'images', 'column' => 'imageArchID', 'parent' => 'architectures', 'pcolumn' => 'archID', 'class' => 'config', 'action' => 'SET NULL', 'enabled' => false],
    ['child' => 'scheduledTasks', 'column' => 'stTaskTypeID', 'parent' => 'taskTypes', 'pcolumn' => 'ttID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'scheduledTasks', 'column' => 'stImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'multicastSessions', 'column' => 'msNFSGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'multicastSessions', 'column' => 'msSenderNode', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'fileDeleteQueue', 'column' => 'fdqStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],

    // ---- work: tasks and jobs -------------------------------------------
    ['child' => 'tasks', 'column' => 'taskHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'tasks', 'column' => 'taskImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'work', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'tasks', 'column' => 'taskStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'tasks', 'column' => 'taskTypeID', 'parent' => 'taskTypes', 'pcolumn' => 'ttID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'tasks', 'column' => 'taskNFSGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'tasks', 'column' => 'taskNFSMemberID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'tasks', 'column' => 'taskLastMemberID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'snapinJobs', 'column' => 'sjHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'snapinJobs', 'column' => 'sjStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'snapinTasks', 'column' => 'stJobID', 'parent' => 'snapinJobs', 'pcolumn' => 'sjID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'snapinTasks', 'column' => 'stSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'snapinTasks', 'column' => 'stState', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],

    // ---- audit: records of what happened. NO constraint proposed --------
    ['child' => 'taskLog', 'column' => 'taskID', 'parent' => 'tasks', 'pcolumn' => 'taskID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'taskLog', 'column' => 'taskStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'taskLog', 'column' => 'logHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'auditChange', 'column' => 'acAuditID', 'parent' => 'auditLog', 'pcolumn' => 'alID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'userTracking', 'column' => 'utHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'nfsFailures', 'column' => 'nfNodeID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'nfsFailures', 'column' => 'nfTaskID', 'parent' => 'tasks', 'pcolumn' => 'taskID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'nfsFailures', 'column' => 'nfHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'audit', 'action' => 'none'],
    ['child' => 'nfsFailures', 'column' => 'nfGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'audit', 'action' => 'none'],

    // ---- polymorphic: target table chosen by a sibling column -----------
    ['child' => 'scheduledTasks', 'column' => 'stGroupHostID', 'parent' => '(hosts|groups)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'auditLog', 'column' => 'alSubjectID', 'parent' => '(alSubjectType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'history', 'column' => 'hSubjectID', 'parent' => '(hSubjectType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'auditChange', 'column' => 'acSubjectID', 'parent' => '(acSubjectType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'virus', 'column' => 'vHostMAC', 'parent' => 'hostMAC.hmMAC', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],

    // ---- plugin tables (fog-plugins repo) -------------------------------
    ['child' => 'locationAssoc', 'column' => 'laLocationID', 'parent' => 'location', 'pcolumn' => 'lID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'locationAssoc', 'column' => 'laHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ouAssoc', 'column' => 'oaOUID', 'parent' => 'ou', 'pcolumn' => 'ouID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ouAssoc', 'column' => 'oaHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'windowsKeysAssoc', 'column' => 'wkaImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'windowsKeysAssoc', 'column' => 'wkaKeyID', 'parent' => 'windowsKeys', 'pcolumn' => 'wkID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'LDAPGroups', 'column' => 'lgServerID', 'parent' => 'LDAPServers', 'pcolumn' => 'lsID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ldapGroupRoleAssoc', 'column' => 'lgraGroupID', 'parent' => 'LDAPGroups', 'pcolumn' => 'lgID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ldapGroupRoleAssoc', 'column' => 'lgraRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ldapGroupUserGroupAssoc', 'column' => 'lgugGroupID', 'parent' => 'LDAPGroups', 'pcolumn' => 'lgID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ldapGroupUserGroupAssoc', 'column' => 'lgugUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'ldapUserGrant', 'column' => 'lugUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'OIDCGroups', 'column' => 'ogProviderID', 'parent' => 'OIDCProviders', 'pcolumn' => 'opID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcIdentity', 'column' => 'oiProviderID', 'parent' => 'OIDCProviders', 'pcolumn' => 'opID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcIdentity', 'column' => 'oiUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcGroupRoleAssoc', 'column' => 'ograGroupID', 'parent' => 'OIDCGroups', 'pcolumn' => 'ogID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcGroupRoleAssoc', 'column' => 'ograRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcGroupUserGroupAssoc', 'column' => 'ogugGroupID', 'parent' => 'OIDCGroups', 'pcolumn' => 'ogID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcGroupUserGroupAssoc', 'column' => 'ogugUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'oidcUserGrant', 'column' => 'ougUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => false],
    ['child' => 'location', 'column' => 'lStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'location', 'column' => 'lStorageNodeID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => false],
    ['child' => 'ldapUserGrant', 'column' => 'lugTargetID', 'parent' => '(lugTargetType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'oidcUserGrant', 'column' => 'ougTargetID', 'parent' => '(ougTargetType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
];
