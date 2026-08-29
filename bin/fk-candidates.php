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
    ['groupMembers', 'gmHostID', 'hosts', 'hostID', 'junction'],
    ['groupMembers', 'gmGroupID', 'groups', 'groupID', 'junction'],
    ['hostMAC', 'hmHostID', 'hosts', 'hostID', 'junction'],
    ['snapinAssoc', 'saHostID', 'hosts', 'hostID', 'junction'],
    ['snapinAssoc', 'saSnapinID', 'snapins', 'sID', 'junction'],
    ['snapinGroupAssoc', 'sgaSnapinID', 'snapins', 'sID', 'junction'],
    ['snapinGroupAssoc', 'sgaStorageGroupID', 'nfsGroups', 'ngID', 'junction'],
    ['imageGroupAssoc', 'igaImageID', 'images', 'imageID', 'junction'],
    ['imageGroupAssoc', 'igaStorageGroupID', 'nfsGroups', 'ngID', 'junction'],
    ['printerAssoc', 'paHostID', 'hosts', 'hostID', 'junction'],
    ['printerAssoc', 'paPrinterID', 'printers', 'pID', 'junction'],
    ['moduleStatusByHost', 'msHostID', 'hosts', 'hostID', 'junction'],
    ['moduleStatusByHost', 'msModuleID', 'modules', 'id', 'junction'],
    ['multicastSessionsAssoc', 'msID', 'multicastSessions', 'msID', 'junction'],
    ['multicastSessionsAssoc', 'tID', 'tasks', 'taskID', 'junction'],
    ['siteHostMembers', 'shmSiteID', 'sites', 'siteID', 'junction'],
    ['siteHostMembers', 'shmHostID', 'hosts', 'hostID', 'junction'],
    ['siteGroupMembers', 'sgmSiteID', 'sites', 'siteID', 'junction'],
    ['siteGroupMembers', 'sgmGroupID', 'groups', 'groupID', 'junction'],
    ['siteUserMembers', 'sumSiteID', 'sites', 'siteID', 'junction'],
    ['siteUserMembers', 'sumUserID', 'users', 'uId', 'junction'],
    ['siteUserGroupMembers', 'sugmSiteID', 'sites', 'siteID', 'junction'],
    ['siteUserGroupMembers', 'sugmUserGroupID', 'userGroups', 'ugID', 'junction'],
    ['siteRoleGrants', 'srgSiteID', 'sites', 'siteID', 'junction'],
    ['siteRoleGrants', 'srgRoleID', 'roles', 'rID', 'junction'],
    ['siteUserGroupGrants', 'suggSiteID', 'sites', 'siteID', 'junction'],
    ['siteUserGroupGrants', 'suggGroupID', 'userGroups', 'ugID', 'junction'],
    ['roleUserAssoc', 'ruaRoleID', 'roles', 'rID', 'junction'],
    ['roleUserAssoc', 'ruaUserID', 'users', 'uId', 'junction'],
    ['roleUserGroupAssoc', 'rugRoleID', 'roles', 'rID', 'junction'],
    ['roleUserGroupAssoc', 'rugGroupID', 'userGroups', 'ugID', 'junction'],
    ['rolePermissions', 'rpRoleID', 'roles', 'rID', 'junction'],
    ['userGroupMembers', 'ugmGroupID', 'userGroups', 'ugID', 'junction'],
    ['userGroupMembers', 'ugmUserID', 'users', 'uId', 'junction'],

    // ---- satellite: rows wholly owned by one parent ----------------------
    ['inventory', 'iHostID', 'hosts', 'hostID', 'satellite'],
    ['hostScreenSettings', 'hssHostID', 'hosts', 'hostID', 'satellite'],
    ['hostAutoLogOut', 'haloHostID', 'hosts', 'hostID', 'satellite'],
    ['powerManagement', 'pmHostID', 'hosts', 'hostID', 'satellite'],
    ['greenFog', 'gfHostID', 'hosts', 'hostID', 'satellite'],
    ['apiTokens', 'atUserID', 'users', 'uId', 'satellite'],
    ['userAuths', 'uaUserID', 'users', 'uId', 'satellite'],
    ['nfsGroupMembers', 'ngmGroupID', 'nfsGroups', 'ngID', 'satellite'],

    // ---- config: references to configuration with its own life ----------
    ['hosts', 'hostImage', 'images', 'imageID', 'config', 0],
    ['hosts', 'hostArchID', 'architectures', 'archID', 'config'],
    ['images', 'imageOSID', 'os', 'osID', 'config', 0],
    ['images', 'imageTypeID', 'imageTypes', 'imageTypeID', 'config', 0],
    ['images', 'imagePartitionTypeID', 'imagePartitionTypes', 'imagePartitionTypeID', 'config', 0],
    ['images', 'imageArchID', 'architectures', 'archID', 'config'],
    ['scheduledTasks', 'stTaskTypeID', 'taskTypes', 'ttID', 'config', 0],
    ['scheduledTasks', 'stImageID', 'images', 'imageID', 'config', 0],
    ['multicastSessions', 'msNFSGroupID', 'nfsGroups', 'ngID', 'config', 0],
    ['multicastSessions', 'msSenderNode', 'nfsGroupMembers', 'ngmID', 'config', 0],
    ['fileDeleteQueue', 'fdqStorageGroupID', 'nfsGroups', 'ngID', 'config', 0],

    // ---- work: tasks and jobs -------------------------------------------
    ['tasks', 'taskHostID', 'hosts', 'hostID', 'work'],
    ['tasks', 'taskImageID', 'images', 'imageID', 'work', 0],
    ['tasks', 'taskStateID', 'taskStates', 'tsID', 'work', 0],
    ['tasks', 'taskTypeID', 'taskTypes', 'ttID', 'work', 0],
    ['tasks', 'taskNFSGroupID', 'nfsGroups', 'ngID', 'work', 0],
    ['tasks', 'taskNFSMemberID', 'nfsGroupMembers', 'ngmID', 'work', 0],
    ['tasks', 'taskLastMemberID', 'nfsGroupMembers', 'ngmID', 'work', 0],
    ['snapinJobs', 'sjHostID', 'hosts', 'hostID', 'work'],
    ['snapinJobs', 'sjStateID', 'taskStates', 'tsID', 'work', 0],
    ['snapinTasks', 'stJobID', 'snapinJobs', 'sjID', 'work'],
    ['snapinTasks', 'stSnapinID', 'snapins', 'sID', 'work'],
    ['snapinTasks', 'stState', 'taskStates', 'tsID', 'work', 0],

    // ---- audit: records of what happened. NO constraint proposed --------
    ['taskLog', 'taskID', 'tasks', 'taskID', 'audit'],
    ['taskLog', 'taskStateID', 'taskStates', 'tsID', 'audit'],
    ['taskLog', 'logHostID', 'hosts', 'hostID', 'audit'],
    ['auditChange', 'acAuditID', 'auditLog', 'alID', 'audit'],
    ['userTracking', 'utHostID', 'hosts', 'hostID', 'audit'],
    ['nfsFailures', 'nfNodeID', 'nfsGroupMembers', 'ngmID', 'audit'],
    ['nfsFailures', 'nfTaskID', 'tasks', 'taskID', 'audit'],
    ['nfsFailures', 'nfHostID', 'hosts', 'hostID', 'audit'],
    ['nfsFailures', 'nfGroupID', 'nfsGroups', 'ngID', 'audit'],

    // ---- polymorphic: target table chosen by a sibling column -----------
    ['scheduledTasks', 'stGroupHostID', '(hosts|groups)', '-', 'poly'],
    ['auditLog', 'alSubjectID', '(alSubjectType)', '-', 'poly'],
    ['history', 'hSubjectID', '(hSubjectType)', '-', 'poly'],
    ['auditChange', 'acSubjectID', '(acSubjectType)', '-', 'poly'],
    ['virus', 'vHostMAC', 'hostMAC.hmMAC', '-', 'poly'],

    // ---- plugin tables (fog-plugins repo) -------------------------------
    ['locationAssoc', 'laLocationID', 'location', 'lID', 'junction'],
    ['locationAssoc', 'laHostID', 'hosts', 'hostID', 'junction'],
    ['ouAssoc', 'oaOUID', 'ou', 'ouID', 'junction'],
    ['ouAssoc', 'oaHostID', 'hosts', 'hostID', 'junction'],
    ['windowsKeysAssoc', 'wkaImageID', 'images', 'imageID', 'junction'],
    ['windowsKeysAssoc', 'wkaKeyID', 'windowsKeys', 'wkID', 'junction'],
    ['LDAPGroups', 'lgServerID', 'LDAPServers', 'lsID', 'satellite'],
    ['ldapGroupRoleAssoc', 'lgraGroupID', 'LDAPGroups', 'lgID', 'junction'],
    ['ldapGroupRoleAssoc', 'lgraRoleID', 'roles', 'rID', 'junction'],
    ['ldapGroupUserGroupAssoc', 'lgugGroupID', 'LDAPGroups', 'lgID', 'junction'],
    ['ldapGroupUserGroupAssoc', 'lgugUserGroupID', 'userGroups', 'ugID', 'junction'],
    ['ldapUserGrant', 'lugUserID', 'users', 'uId', 'junction'],
    ['OIDCGroups', 'ogProviderID', 'OIDCProviders', 'opID', 'satellite'],
    ['oidcIdentity', 'oiProviderID', 'OIDCProviders', 'opID', 'satellite'],
    ['oidcIdentity', 'oiUserID', 'users', 'uId', 'satellite'],
    ['oidcGroupRoleAssoc', 'ograGroupID', 'OIDCGroups', 'ogID', 'junction'],
    ['oidcGroupRoleAssoc', 'ograRoleID', 'roles', 'rID', 'junction'],
    ['oidcGroupUserGroupAssoc', 'ogugGroupID', 'OIDCGroups', 'ogID', 'junction'],
    ['oidcGroupUserGroupAssoc', 'ogugUserGroupID', 'userGroups', 'ugID', 'junction'],
    ['oidcUserGrant', 'ougUserID', 'users', 'uId', 'junction'],
    ['location', 'lStorageGroupID', 'nfsGroups', 'ngID', 'config', 0],
    ['location', 'lStorageNodeID', 'nfsGroupMembers', 'ngmID', 'config', 0],
    ['ldapUserGrant', 'lugTargetID', '(lugTargetType)', '-', 'poly'],
    ['oidcUserGrant', 'ougTargetID', '(ougTargetType)', '-', 'poly'],
];
