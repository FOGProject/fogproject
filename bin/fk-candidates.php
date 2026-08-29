<?php
/**
 * Candidate foreign-key relationships across the FOG schema.
 *
 * SURVEY INPUT -- this file declares nothing to the database. It is the map
 * `bin/fk-orphan-scan.php` walks to count how much existing data would reject
 * each constraint, and the working list the phased proposal in
 * docs/development/foreign-keys.md is built from.
 *
 * Every entry names one child column that holds an id belonging to another
 * table. `class` is the decision axis, not a description:
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
 * `sentinel` names a value the column already uses to mean "no reference".
 * A foreign key accepts NULL and nothing else for that, so any entry with a
 * sentinel needs a data and column change before a constraint is possible.
 *
 * `poly` marks a column whose target table is chosen by a sibling column. No
 * constraint is possible at all; recorded here so the survey states why.
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
    ['child' => 'groupMembers', 'column' => 'gmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'groupMembers', 'column' => 'gmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'hostMAC', 'column' => 'hmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'snapinAssoc', 'column' => 'saHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'snapinAssoc', 'column' => 'saSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'snapinGroupAssoc', 'column' => 'sgaSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'snapinGroupAssoc', 'column' => 'sgaStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'imageGroupAssoc', 'column' => 'igaImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'imageGroupAssoc', 'column' => 'igaStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'printerAssoc', 'column' => 'paHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'printerAssoc', 'column' => 'paPrinterID', 'parent' => 'printers', 'pcolumn' => 'pID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'moduleStatusByHost', 'column' => 'msHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'moduleStatusByHost', 'column' => 'msModuleID', 'parent' => 'modules', 'pcolumn' => 'id', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'multicastSessionsAssoc', 'column' => 'msID', 'parent' => 'multicastSessions', 'pcolumn' => 'msID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'multicastSessionsAssoc', 'column' => 'tID', 'parent' => 'tasks', 'pcolumn' => 'taskID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteHostMembers', 'column' => 'shmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteHostMembers', 'column' => 'shmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteGroupMembers', 'column' => 'sgmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteGroupMembers', 'column' => 'sgmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteUserMembers', 'column' => 'sumSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteUserMembers', 'column' => 'sumUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteUserGroupMembers', 'column' => 'sugmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteUserGroupMembers', 'column' => 'sugmUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteRoleGrants', 'column' => 'srgSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteRoleGrants', 'column' => 'srgRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteUserGroupGrants', 'column' => 'suggSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'siteUserGroupGrants', 'column' => 'suggGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'roleUserAssoc', 'column' => 'ruaRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'roleUserAssoc', 'column' => 'ruaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'roleUserGroupAssoc', 'column' => 'rugRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'roleUserGroupAssoc', 'column' => 'rugGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'rolePermissions', 'column' => 'rpRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'userGroupMembers', 'column' => 'ugmGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'userGroupMembers', 'column' => 'ugmUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE'],

    // ---- satellite: rows wholly owned by one parent ----------------------
    ['child' => 'inventory', 'column' => 'iHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'hostScreenSettings', 'column' => 'hssHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'hostAutoLogOut', 'column' => 'haloHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'powerManagement', 'column' => 'pmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'greenFog', 'column' => 'gfHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'apiTokens', 'column' => 'atUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'userAuths', 'column' => 'uaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'nfsGroupMembers', 'column' => 'ngmGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'satellite', 'action' => 'CASCADE'],

    // ---- config: references to configuration with its own life ----------
    ['child' => 'hosts', 'column' => 'hostImage', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0],
    ['child' => 'hosts', 'column' => 'hostArchID', 'parent' => 'architectures', 'pcolumn' => 'archID', 'class' => 'config', 'action' => 'SET NULL'],
    ['child' => 'images', 'column' => 'imageOSID', 'parent' => 'os', 'pcolumn' => 'osID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'images', 'column' => 'imageTypeID', 'parent' => 'imageTypes', 'pcolumn' => 'imageTypeID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'images', 'column' => 'imagePartitionTypeID', 'parent' => 'imagePartitionTypes', 'pcolumn' => 'imagePartitionTypeID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'images', 'column' => 'imageArchID', 'parent' => 'architectures', 'pcolumn' => 'archID', 'class' => 'config', 'action' => 'SET NULL'],
    ['child' => 'scheduledTasks', 'column' => 'stTaskTypeID', 'parent' => 'taskTypes', 'pcolumn' => 'ttID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'scheduledTasks', 'column' => 'stImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'multicastSessions', 'column' => 'msNFSGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'multicastSessions', 'column' => 'msSenderNode', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'fileDeleteQueue', 'column' => 'fdqStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],

    // ---- work: tasks and jobs -------------------------------------------
    ['child' => 'tasks', 'column' => 'taskHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'work', 'action' => 'CASCADE'],
    ['child' => 'tasks', 'column' => 'taskImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'work', 'action' => 'SET NULL', 'sentinel' => 0],
    ['child' => 'tasks', 'column' => 'taskStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'tasks', 'column' => 'taskTypeID', 'parent' => 'taskTypes', 'pcolumn' => 'ttID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'tasks', 'column' => 'taskNFSGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'tasks', 'column' => 'taskNFSMemberID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'tasks', 'column' => 'taskLastMemberID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'snapinJobs', 'column' => 'sjHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'work', 'action' => 'CASCADE'],
    ['child' => 'snapinJobs', 'column' => 'sjStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'snapinTasks', 'column' => 'stJobID', 'parent' => 'snapinJobs', 'pcolumn' => 'sjID', 'class' => 'work', 'action' => 'CASCADE'],
    ['child' => 'snapinTasks', 'column' => 'stSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'work', 'action' => 'CASCADE'],
    ['child' => 'snapinTasks', 'column' => 'stState', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0],

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
    ['child' => 'locationAssoc', 'column' => 'laLocationID', 'parent' => 'location', 'pcolumn' => 'lID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'locationAssoc', 'column' => 'laHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'ouAssoc', 'column' => 'oaOUID', 'parent' => 'ou', 'pcolumn' => 'ouID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'ouAssoc', 'column' => 'oaHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'windowsKeysAssoc', 'column' => 'wkaImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'windowsKeysAssoc', 'column' => 'wkaKeyID', 'parent' => 'windowsKeys', 'pcolumn' => 'wkID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'LDAPGroups', 'column' => 'lgServerID', 'parent' => 'LDAPServers', 'pcolumn' => 'lsID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'ldapGroupRoleAssoc', 'column' => 'lgraGroupID', 'parent' => 'LDAPGroups', 'pcolumn' => 'lgID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'ldapGroupRoleAssoc', 'column' => 'lgraRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'ldapGroupUserGroupAssoc', 'column' => 'lgugGroupID', 'parent' => 'LDAPGroups', 'pcolumn' => 'lgID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'ldapGroupUserGroupAssoc', 'column' => 'lgugUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'ldapUserGrant', 'column' => 'lugUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'OIDCGroups', 'column' => 'ogProviderID', 'parent' => 'OIDCProviders', 'pcolumn' => 'opID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'oidcIdentity', 'column' => 'oiProviderID', 'parent' => 'OIDCProviders', 'pcolumn' => 'opID', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'oidcIdentity', 'column' => 'oiUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE'],
    ['child' => 'oidcGroupRoleAssoc', 'column' => 'ograGroupID', 'parent' => 'OIDCGroups', 'pcolumn' => 'ogID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'oidcGroupRoleAssoc', 'column' => 'ograRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'oidcGroupUserGroupAssoc', 'column' => 'ogugGroupID', 'parent' => 'OIDCGroups', 'pcolumn' => 'ogID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'oidcGroupUserGroupAssoc', 'column' => 'ogugUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'oidcUserGrant', 'column' => 'ougUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE'],
    ['child' => 'location', 'column' => 'lStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'location', 'column' => 'lStorageNodeID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0],
    ['child' => 'ldapUserGrant', 'column' => 'lugTargetID', 'parent' => '(lugTargetType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'oidcUserGrant', 'column' => 'ougTargetID', 'parent' => '(ougTargetType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
];