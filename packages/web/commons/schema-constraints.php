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
    ['child' => 'groupMembers', 'column' => 'gmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'groupMembers', 'column' => 'gmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'hostMAC', 'column' => 'hmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'snapinAssoc', 'column' => 'saHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'snapinAssoc', 'column' => 'saSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'snapinGroupAssoc', 'column' => 'sgaSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 3],
    ['child' => 'snapinGroupAssoc', 'column' => 'sgaStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 3],
    ['child' => 'imageGroupAssoc', 'column' => 'igaImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 3],
    ['child' => 'imageGroupAssoc', 'column' => 'igaStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 3],
    ['child' => 'printerAssoc', 'column' => 'paHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'printerAssoc', 'column' => 'paPrinterID', 'parent' => 'printers', 'pcolumn' => 'pID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'moduleStatusByHost', 'column' => 'msHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'moduleStatusByHost', 'column' => 'msModuleID', 'parent' => 'modules', 'pcolumn' => 'id', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'multicastSessionsAssoc', 'column' => 'msID', 'parent' => 'multicastSessions', 'pcolumn' => 'msID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 6],
    ['child' => 'multicastSessionsAssoc', 'column' => 'tID', 'parent' => 'tasks', 'pcolumn' => 'taskID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 6],
    ['child' => 'siteHostMembers', 'column' => 'shmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteHostMembers', 'column' => 'shmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteGroupMembers', 'column' => 'sgmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteGroupMembers', 'column' => 'sgmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteUserMembers', 'column' => 'sumSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteUserMembers', 'column' => 'sumUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteUserGroupMembers', 'column' => 'sugmSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteUserGroupMembers', 'column' => 'sugmUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteRoleGrants', 'column' => 'srgSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteRoleGrants', 'column' => 'srgRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteUserGroupGrants', 'column' => 'suggSiteID', 'parent' => 'sites', 'pcolumn' => 'siteID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'siteUserGroupGrants', 'column' => 'suggGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'roleUserAssoc', 'column' => 'ruaRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'roleUserAssoc', 'column' => 'ruaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'roleUserGroupAssoc', 'column' => 'rugRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'roleUserGroupAssoc', 'column' => 'rugGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'rolePermissions', 'column' => 'rpRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'userGroupMembers', 'column' => 'ugmGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'userGroupMembers', 'column' => 'ugmUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],

    // ---- satellite: rows wholly owned by one parent ----------------------
    ['child' => 'inventory', 'column' => 'iHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'hostAutoLogOut', 'column' => 'haloHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'powerManagement', 'column' => 'pmHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 1],
    ['child' => 'apiTokens', 'column' => 'atUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    ['child' => 'userAuths', 'column' => 'uaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 2],
    // Group 7, added with the table itself (schema 391/392). A NUMBER, not a
    // name: names are reserved for the plugin groups, whose steps live in the
    // plugin's own schema(), and tests/foreign-key-map enforces that split.
    // Unlike groups 1-6 there is nothing to migrate here -- a table created
    // empty one step earlier cannot hold an orphan -- so no sweep precedes it.
    // A preference means nothing without the user it belongs to: CASCADE, the
    // same call as apiTokens and userAuths above.
    ['child' => 'userPrefs', 'column' => 'upUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 7],

    // ---- config: references to configuration with its own life ----------
    //
    // nfsGroupMembers.ngmGroupID was classed satellite and shipped CASCADE
    // in schema step 384. Both were wrong.
    //
    // The invariant, from Tom: a storage node ALWAYS belongs to a group; a
    // group need not have any nodes. So the column stays NOT NULL and this
    // is not SET NULL either -- there is no legitimate "no group" state to
    // spell.
    //
    // RESTRICT rather than CASCADE because a node is not a satellite. It
    // carries its own hostname, credentials, root/FTP/snapin paths,
    // interface, bandwidth limit, max clients and enable flag, none of it
    // recoverable from the group. CASCADE would silently destroy all of
    // that when a group was deleted; RESTRICT refuses the delete until the
    // nodes have been moved, which keeps the invariant and destroys
    // nothing.
    //
    // A row still holding the `0` that StorageGroup::removeNode() writes is
    // a BROKEN row under this invariant, not a detached node, so the
    // sentinel conversion deliberately leaves it alone -- see schema step
    // 386. The constraint is then refused and named in the log until an
    // administrator assigns the node to a group. Only they know which one.
    ['child' => 'nfsGroupMembers', 'column' => 'ngmGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 5],
    ['child' => 'hosts', 'column' => 'hostImage', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 5],
    ['child' => 'hosts', 'column' => 'hostArchID', 'parent' => 'architectures', 'pcolumn' => 'archID', 'class' => 'config', 'action' => 'SET NULL', 'enabled' => true, 'group' => 5],
    ['child' => 'images', 'column' => 'imageOSID', 'parent' => 'os', 'pcolumn' => 'osID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => true, 'group' => 5],
    ['child' => 'images', 'column' => 'imageTypeID', 'parent' => 'imageTypes', 'pcolumn' => 'imageTypeID', 'class' => 'config', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 5],
    ['child' => 'images', 'column' => 'imagePartitionTypeID', 'parent' => 'imagePartitionTypes', 'pcolumn' => 'imagePartitionTypeID', 'class' => 'config', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 5],
    ['child' => 'images', 'column' => 'imageArchID', 'parent' => 'architectures', 'pcolumn' => 'archID', 'class' => 'config', 'action' => 'SET NULL', 'enabled' => true, 'group' => 5],
    ['child' => 'scheduledTasks', 'column' => 'stTaskTypeID', 'parent' => 'taskTypes', 'pcolumn' => 'ttID', 'class' => 'config', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 5],
    // SET NULL, not RESTRICT. FOG's house behavior for a deleted image is
    // to degrade what depends on it, not to block the delete: hosts are
    // unassigned and live tasks are canceled (Route::deletemass, case
    // 'image'). Nothing touches scheduledTasks today, so a schedule quietly
    // outlives its image and fails every time it fires. RESTRICT would
    // refuse the image delete on the strength of a schedule someone forgot
    // about; SET NULL leaves the schedule visible and editable, which is
    // where the administrator can actually fix it.
    ['child' => 'scheduledTasks', 'column' => 'stImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 5],
    // CASCADE, not RESTRICT. A multicast session is work performed BY a
    // storage group -- it carries no configuration of its own worth keeping
    // and cannot be re-pointed at another group. Under RESTRICT a single
    // completed session would pin its storage group forever, so a group
    // that had ever run a multicast could never be deleted. The imaging
    // record lives in taskLog, which takes no constraint at all (ADR 0021),
    // so nothing here is the history.
    ['child' => 'multicastSessions', 'column' => 'msNFSGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'CASCADE', 'enabled' => true, 'group' => 5],
    // SET NULL, not RESTRICT. This records WHICH node ran the session, not
    // what the session belongs to -- that is msNFSGroupID above. A node
    // being removed should not pin it, and should not take the session with
    // it either. Nullable as of schema step 386.
    ['child' => 'multicastSessions', 'column' => 'msSenderNode', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 5],
    ['child' => 'fileDeleteQueue', 'column' => 'fdqStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 5],

    // ---- work: tasks and jobs -------------------------------------------
    ['child' => 'tasks', 'column' => 'taskHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => true, 'group' => 6],
    ['child' => 'tasks', 'column' => 'taskImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'work', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 6],
    ['child' => 'tasks', 'column' => 'taskStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 6],
    ['child' => 'tasks', 'column' => 'taskTypeID', 'parent' => 'taskTypes', 'pcolumn' => 'ttID', 'class' => 'work', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 6],
    // SET NULL, not RESTRICT, for all three of the storage references
    // below. They record WHICH storage served a task, not what the task
    // belongs to -- that is taskHostID. Under RESTRICT a single finished
    // task would pin its storage group or node until retention pruned the
    // task, which can be months, so emptying a storage group would not be
    // enough to let you delete it. All three are nullable as of schema step
    // 386, and a task that has lost its storage reference is still a
    // complete record of what was imaged onto which host.
    ['child' => 'tasks', 'column' => 'taskNFSGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'work', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 6],
    ['child' => 'tasks', 'column' => 'taskNFSMemberID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'work', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 6],
    ['child' => 'tasks', 'column' => 'taskLastMemberID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'work', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 6],
    ['child' => 'snapinJobs', 'column' => 'sjHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => true, 'group' => 6],
    ['child' => 'snapinJobs', 'column' => 'sjStateID', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 6],
    ['child' => 'snapinTasks', 'column' => 'stJobID', 'parent' => 'snapinJobs', 'pcolumn' => 'sjID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => true, 'group' => 6],
    ['child' => 'snapinTasks', 'column' => 'stSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'work', 'action' => 'CASCADE', 'enabled' => true, 'group' => 6],
    ['child' => 'snapinTasks', 'column' => 'stState', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 6],
    // Two more references to taskStates that do not end in ID. They were
    // missing from this file until the coverage gate in
    // tests/foreign-key-map.test.php was widened past /ID$/ -- it could not
    // see them, so nothing said they were absent. Both are RESTRICT for the
    // same reason snapinTasks.stState is: a state row someone deleted while
    // work referenced it would leave that work unreadable.
    ['child' => 'multicastSessions', 'column' => 'msState', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => true, 'group' => 6],
    ['child' => 'fileDeleteQueue', 'column' => 'fdqState', 'parent' => 'taskStates', 'pcolumn' => 'tsID', 'class' => 'work', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 6],

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

    // ---- plugin tables (fog-plugins repo) -------------------------------
    //
    // These live here rather than in the plugin repo because the map is one
    // document: a plugin's child table references core parents (hosts,
    // images, users, roles, userGroups, nfsGroups), and a reader asking
    // "what points at hosts?" has to get the whole answer from one file.
    //
    // The direction rule holds in the constraints themselves: every arrow
    // below runs plugin -> core or plugin -> plugin. Nothing in core
    // references a plugin table, so uninstalling a plugin is still just
    // dropping its tables.
    //
    // Their `group` is the plugin's own name, a string, where core's groups
    // are ints. planConstraints() compares with ===, so the two spaces
    // cannot collide, and each plugin's schema step applies exactly its own
    // relationships by passing its name. A server without the plugin has no
    // child table, and planConstraints() skips a relationship whose table is
    // missing -- which is what lets the unfiltered reconcile after every
    // core update carry these safely on an install that has none of them.
    ['child' => 'locationAssoc', 'column' => 'laLocationID', 'parent' => 'location', 'pcolumn' => 'lID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'location'],
    ['child' => 'locationAssoc', 'column' => 'laHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'location'],
    ['child' => 'ouAssoc', 'column' => 'oaOUID', 'parent' => 'ou', 'pcolumn' => 'ouID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ou'],
    ['child' => 'ouAssoc', 'column' => 'oaHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ou'],
    ['child' => 'windowsKeysAssoc', 'column' => 'wkaImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'windowskey'],
    ['child' => 'windowsKeysAssoc', 'column' => 'wkaKeyID', 'parent' => 'windowsKeys', 'pcolumn' => 'wkID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'windowskey'],
    ['child' => 'LDAPGroups', 'column' => 'lgServerID', 'parent' => 'LDAPServers', 'pcolumn' => 'lsID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ldap'],
    ['child' => 'ldapGroupRoleAssoc', 'column' => 'lgraGroupID', 'parent' => 'LDAPGroups', 'pcolumn' => 'lgID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ldap'],
    ['child' => 'ldapGroupRoleAssoc', 'column' => 'lgraRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ldap'],
    ['child' => 'ldapGroupUserGroupAssoc', 'column' => 'lgugGroupID', 'parent' => 'LDAPGroups', 'pcolumn' => 'lgID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ldap'],
    ['child' => 'ldapGroupUserGroupAssoc', 'column' => 'lgugUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ldap'],
    ['child' => 'ldapUserGrant', 'column' => 'lugUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'ldap'],
    ['child' => 'OIDCGroups', 'column' => 'ogProviderID', 'parent' => 'OIDCProviders', 'pcolumn' => 'opID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcIdentity', 'column' => 'oiProviderID', 'parent' => 'OIDCProviders', 'pcolumn' => 'opID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcIdentity', 'column' => 'oiUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcGroupRoleAssoc', 'column' => 'ograGroupID', 'parent' => 'OIDCGroups', 'pcolumn' => 'ogID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcGroupRoleAssoc', 'column' => 'ograRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcGroupUserGroupAssoc', 'column' => 'ogugGroupID', 'parent' => 'OIDCGroups', 'pcolumn' => 'ogID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcGroupUserGroupAssoc', 'column' => 'ogugUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'oidcUserGrant', 'column' => 'ougUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'oidc'],
    ['child' => 'location', 'column' => 'lStorageGroupID', 'parent' => 'nfsGroups', 'pcolumn' => 'ngID', 'class' => 'config', 'action' => 'RESTRICT', 'enabled' => true, 'group' => 'location'],
    ['child' => 'location', 'column' => 'lStorageNodeID', 'parent' => 'nfsGroupMembers', 'pcolumn' => 'ngmID', 'class' => 'config', 'action' => 'SET NULL', 'sentinel' => 0, 'enabled' => true, 'group' => 'location'],
    ['child' => 'capone', 'column' => 'cImageID', 'parent' => 'images', 'pcolumn' => 'imageID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => true, 'group' => 'capone'],
    ['child' => 'capone', 'column' => 'cOSID', 'parent' => 'os', 'pcolumn' => 'osID', 'class' => 'config', 'action' => 'RESTRICT', 'sentinel' => 0, 'enabled' => true, 'group' => 'capone'],
    ['child' => 'subnetgroup', 'column' => 'sgGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 'subnetgroup'],
    // Group 8: saved grid filters and who they are shared with
    // (schema 393/394/395).
    //
    // Three different actions on purpose. A PRIVATE filter is a satellite of
    // the user who owns it, so CASCADE. The CREATOR reference is provenance
    // on a row that may be GLOBAL -- owned by the install rather than by a
    // person -- so SET NULL: a shared filter must outlive the account that
    // wrote it. Every grant is a junction and dies with either end.
    //
    // The grant tables are three junctions rather than one polymorphic
    // table precisely so these can exist: a single target column pointing at
    // users, userGroups or roles could carry no foreign key at all, which is
    // why ldapUserGrant and oidcUserGrant below are class 'poly', action
    // 'none'. ADR 0031 exists to shrink that set.
    ['child' => 'savedFilters', 'column' => 'sfUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilters', 'column' => 'sfCreatorID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'config', 'action' => 'SET NULL', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilterUserAssoc', 'column' => 'sfuaFilterID', 'parent' => 'savedFilters', 'pcolumn' => 'sfID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilterUserAssoc', 'column' => 'sfuaUserID', 'parent' => 'users', 'pcolumn' => 'uId', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilterGroupAssoc', 'column' => 'sfgaFilterID', 'parent' => 'savedFilters', 'pcolumn' => 'sfID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilterGroupAssoc', 'column' => 'sfgaUserGroupID', 'parent' => 'userGroups', 'pcolumn' => 'ugID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilterRoleAssoc', 'column' => 'sfraFilterID', 'parent' => 'savedFilters', 'pcolumn' => 'sfID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    ['child' => 'savedFilterRoleAssoc', 'column' => 'sfraRoleID', 'parent' => 'roles', 'pcolumn' => 'rID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 8],
    // ADR 0038 group grants. Group 9, created empty by step 403 so there is
    // nothing to sweep before the flip.
    //
    // All four CASCADE, same reasoning on each: a group grant is meaningless
    // once either end is gone. A deleted group has no grants; a deleted
    // snapin or printer cannot be granted. Leaving an orphan row would
    // silently offer a grant against an id that has since been reused, which
    // on the printer side means the resolver hands a machine somebody else's
    // printer.
    ['child' => 'groupSnapinAssoc', 'column' => 'gsaGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 9],
    ['child' => 'groupSnapinAssoc', 'column' => 'gsaSnapinID', 'parent' => 'snapins', 'pcolumn' => 'sID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 9],
    ['child' => 'groupPrinterAssoc', 'column' => 'gpaGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 9],
    ['child' => 'groupPrinterAssoc', 'column' => 'gpaPrinterID', 'parent' => 'printers', 'pcolumn' => 'pID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 9],
    // Group 13 -- fog-agent software (design 0003, schema 418). An
    // assignment or a status row is meaningless without both its host (or
    // group) and its software entry.
    ['child' => 'softwareAssoc', 'column' => 'swaHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 13],
    ['child' => 'softwareAssoc', 'column' => 'swaSoftwareID', 'parent' => 'software', 'pcolumn' => 'swID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 13],
    ['child' => 'groupSoftwareAssoc', 'column' => 'gswaGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 13],
    ['child' => 'groupSoftwareAssoc', 'column' => 'gswaSoftwareID', 'parent' => 'software', 'pcolumn' => 'swID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 13],
    ['child' => 'softwareStatus', 'column' => 'sstHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 13],
    ['child' => 'softwareStatus', 'column' => 'sstSoftwareID', 'parent' => 'software', 'pcolumn' => 'swID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 13],
    // ADR 0038 decision 3, revised. Group 10, created empty by step 407 so
    // there is nothing to sweep before the flip.
    //
    // Both CASCADE, for the reason group 9 gives: a grant is meaningless once
    // either end is gone, and an orphan row would offer a grant against an id
    // that has since been reused -- here, a host silently gaining whichever
    // module inherited the number.
    ['child' => 'groupModuleAssoc', 'column' => 'gmaGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 10],
    ['child' => 'groupModuleAssoc', 'column' => 'gmaModuleID', 'parent' => 'modules', 'pcolumn' => 'id', 'class' => 'junction', 'action' => 'CASCADE', 'enabled' => true, 'group' => 10],
    // ADR 0038: Power Management is the fourth grant. Group 11, created
    // empty by step 410 so there is nothing to sweep before the flip.
    //
    // `satellite` rather than `junction`, and it sits with the grants
    // above rather than in the satellite block, because it is one of
    // them: the class is about SHAPE and the placement is about what the
    // row is for. A schedule references only its group -- there is no
    // second end to link to -- which is the same shape as
    // powerManagement.pmHostID one level down. CASCADE for the reason
    // group 9 gives, with a sharper edge here: an orphan schedule left
    // against a reused group id would silently start shutting down every
    // host that inherited the number.
    ['child' => 'groupPowerManagement', 'column' => 'gpmGroupID', 'parent' => 'groups', 'pcolumn' => 'groupID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 11],
    // FOG Agent enrollment. Group 12, created empty by step 416 so there is
    // nothing to sweep before the flip.
    //
    // `satellite`: an enrollment row is the agent's standing with ONE host --
    // pending, issued or denied -- and means nothing once that host is gone.
    // Every row gets a host, because an unknown machine is given a pending
    // host at enrollment time, the same way iPXE registration does it.
    // CASCADE rather than RESTRICT because deleting the pending host IS how
    // an admin forgets a machine; the agent then comes back as unknown and
    // waits for a fresh decision. A denied row going with its host is the
    // same outcome, and the decision itself is in auditLog.
    ['child' => 'agentEnrollment', 'column' => 'aeHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 12],
    // The facts an agent reports about its own host (design 0006). Both are
    // satellites in the same sense inventory is: the rows describe one host
    // and mean nothing without it. CASCADE, so deleting a host takes its
    // reported software history with it -- the same call FOG already makes
    // for that host's inventory row, and the reason the fleet report reads
    // "which hosts have X" rather than "which machines ever did".
    ['child' => 'hostSoftware', 'column' => 'hsHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'hostUserSession', 'column' => 'husHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'hostFactState', 'column' => 'hfsHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'hostDirectory', 'column' => 'hdHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'hostPrinter', 'column' => 'hpHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'hostSpooler', 'column' => 'hspHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'hostNetwork', 'column' => 'hnHostID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    // Both ends of a wake relay are hosts, and BOTH cascade. A deleted
    // target has nothing left to wake; a deleted sender cannot be asked.
    // Leaving either behind would leave a row naming a host id that has
    // since been reused, which is how an admin ends up reading that a
    // machine relayed a wake it has never heard of.
    ['child' => 'agentWake', 'column' => 'awTargetID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'agentWake', 'column' => 'awSenderID', 'parent' => 'hosts', 'pcolumn' => 'hostID', 'class' => 'satellite', 'action' => 'CASCADE', 'enabled' => true, 'group' => 14],
    ['child' => 'ldapUserGrant', 'column' => 'lugTargetID', 'parent' => '(lugTargetType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
    ['child' => 'oidcUserGrant', 'column' => 'ougTargetID', 'parent' => '(ougTargetType)', 'pcolumn' => '-', 'class' => 'poly', 'action' => 'none'],
];
