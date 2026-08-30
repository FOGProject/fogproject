<?php
/**
 * Saved grid filter manager class.
 *
 * PHP version 7.4+
 *
 * @category SavedFilterManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Items\SavedFilter;

/**
 * Saved grid filter manager class.
 *
 * Access control is structural, the same shape UserPrefManager uses. Every
 * mutating method takes the acting user's id and will only touch a row whose
 * sfUserID matches it. Reaching a GLOBAL row (sfUserID IS NULL) additionally
 * requires an explicit $mayManageGlobal, which ONLY the route layer sets, and
 * only after asking Authorization. So no call in this class can be talked
 * into editing somebody else's private filter whatever it is handed, and the
 * privileged case cannot be reached by accident.
 *
 * There are three ways to SEE a filter and only one way to CHANGE it. A user
 * sees their own, every global one, and any shared with them directly, with
 * a group they are in, or with a role they hold. Being shared with confers
 * no write access at all: rename, re-share and delete stay with the owner.
 *
 * @category SavedFilterManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SavedFilterManager extends FOGManagerController
{
    /**
     * Everything the given user may SEE for one grid.
     *
     * Their own filters plus every global one. Ordered globals last so the
     * picker can group them without the client having to sort, and by name
     * within each group.
     *
     * @param int    $userID The user asking.
     * @param string $table  The grid key.
     *
     * @return array
     */
    public function listFor($userID, $table)
    {
        $userID = (int)$userID;
        $table = (string)$table;
        if ($userID < 1 || '' === $table) {
            return [];
        }
        $rows = self::$DB
            ->query(
                'SELECT f.`sfID`, f.`sfUserID`, f.`sfName`, f.`sfValue`,'
                . ' f.`sfModifiedTime`, c.`uName` AS `creatorName`,'
                // How this row REACHED the caller, so the picker can label it.
                // Computed here rather than inferred client-side because only
                // the server knows the caller's groups and roles. A row can
                // arrive by several paths at once -- that is the normal case
                // for a manager who is also in the group they shared to -- so
                // each path is reported and the precedence is applied below.
                . ' EXISTS (SELECT 1 FROM `savedFilterUserAssoc`'
                . ' WHERE `sfuaFilterID` = f.`sfID`'
                . ' AND `sfuaUserID` = :uid6) AS `viaUser`,'
                . ' EXISTS (SELECT 1 FROM `savedFilterGroupAssoc` g'
                . ' JOIN `userGroupMembers` m'
                . ' ON m.`ugmGroupID` = g.`sfgaUserGroupID`'
                . ' WHERE g.`sfgaFilterID` = f.`sfID`'
                . ' AND m.`ugmUserID` = :uid7) AS `viaGroup`,'
                . ' EXISTS (SELECT 1 FROM `savedFilterRoleAssoc` o'
                . ' JOIN `roleUserAssoc` r'
                . ' ON r.`ruaRoleID` = o.`sfraRoleID`'
                . ' WHERE o.`sfraFilterID` = f.`sfID`'
                . ' AND r.`ruaUserID` = :uid8) AS `viaRole`'
                . ' FROM `savedFilters` f'
                . ' LEFT JOIN `users` c ON c.`uID` = f.`sfCreatorID`'
                . ' WHERE f.`sfTable` = :table AND ('
                // Mine.
                . ' f.`sfUserID` = :uid'
                // Everybody's.
                . ' OR f.`sfUserID` IS NULL'
                // Shared with me by name.
                . ' OR f.`sfID` IN ('
                . ' SELECT `sfuaFilterID` FROM `savedFilterUserAssoc`'
                . ' WHERE `sfuaUserID` = :uid2)'
                // Shared with a group I am in.
                . ' OR f.`sfID` IN ('
                . ' SELECT a.`sfgaFilterID` FROM `savedFilterGroupAssoc` a'
                . ' JOIN `userGroupMembers` m'
                . ' ON m.`ugmGroupID` = a.`sfgaUserGroupID`'
                . ' WHERE m.`ugmUserID` = :uid3)'
                // Shared with a role I hold.
                . ' OR f.`sfID` IN ('
                . ' SELECT a.`sfraFilterID` FROM `savedFilterRoleAssoc` a'
                . ' JOIN `roleUserAssoc` r'
                . ' ON r.`ruaRoleID` = a.`sfraRoleID`'
                . ' WHERE r.`ruaUserID` = :uid4)'
                . ' )'
                . ' ORDER BY (f.`sfUserID` = :uid5) DESC,'
                . ' (f.`sfUserID` IS NULL) ASC, f.`sfName` ASC',
                [],
                [
                    ':table' => $table,
                    ':uid' => $userID,
                    ':uid2' => $userID,
                    ':uid3' => $userID,
                    ':uid4' => $userID,
                    ':uid5' => $userID,
                    ':uid6' => $userID,
                    ':uid7' => $userID,
                    ':uid8' => $userID
                ]
            )
            ->fetch('', 'fetch_all')
            ->get();

        $filters = [];
        foreach ((array)$rows as $row) {
            $mine = null !== $row['sfUserID']
                && (int)$row['sfUserID'] === $userID;
            // Most deliberate grant first. Owning it beats being named
            // beats being in a group beats holding a role beats being
            // everybody -- so the label a user sees always describes the
            // most specific reason they can see it, and one filter reaching
            // them three ways still reads as one row with one explanation.
            // The row itself is already unique: every arm above is an OR
            // inside a single SELECT, not a UNION.
            if ($mine) {
                $source = 'mine';
            } elseif ($row['viaUser']) {
                $source = 'user';
            } elseif ($row['viaGroup']) {
                $source = 'group';
            } elseif ($row['viaRole']) {
                $source = 'role';
            } else {
                $source = 'global';
            }
            $filters[] = [
                'id' => (int)$row['sfID'],
                'name' => (string)$row['sfName'],
                // Two flags rather than the owner's id. The client needs to
                // know which rows it may rename or delete (mine), and which
                // are the site-wide ones (global) so it can label them. The
                // owner's id would be a gratuitous disclosure and the client
                // has no use for it.
                'mine' => $mine,
                'global' => null === $row['sfUserID'],
                'source' => $source,
                // Who made it, so "Shared with you" can say by whom. Only
                // for rows the caller did not make: on their own rows it is
                // themselves, and on a global one the useful fact is that it
                // is global. Absent when the creator's account is gone --
                // sfCreatorID is SET NULL, deliberately, so a filter survives
                // the person who wrote it.
                'sharedBy' => $mine
                    ? ''
                    : (string)($row['creatorName'] ?? ''),
                'value' => (string)($row['sfValue'] ?? ''),
                'modified' => (string)($row['sfModifiedTime'] ?? '')
            ];
        }

        return $filters;
    }

    /**
     * Who one filter is shared with.
     *
     * Owner-only: being shared WITH a filter does not entitle you to see who
     * else it went to.
     *
     * @param int  $id              The filter.
     * @param int  $userID          The acting user.
     * @param bool $mayManageGlobal Whether the caller holds the permission.
     *
     * @return array|null users/groups/roles as id lists, or null if not yours
     */
    public function shares($id, $userID, $mayManageGlobal = false)
    {
        list($ok) = $this->_reachable($id, $userID, $mayManageGlobal);
        if (!$ok) {
            return null;
        }
        $id = (int)$id;
        $out = [];
        $sets = [
            'users' => ['savedFilterUserAssoc', 'sfuaFilterID', 'sfuaUserID'],
            'groups' => [
                'savedFilterGroupAssoc', 'sfgaFilterID', 'sfgaUserGroupID'
            ],
            'roles' => ['savedFilterRoleAssoc', 'sfraFilterID', 'sfraRoleID']
        ];
        foreach ($sets as $key => $spec) {
            list($table, $filterCol, $targetCol) = $spec;
            $rows = self::$DB
                ->query(
                    sprintf(
                        'SELECT `%s` FROM `%s` WHERE `%s` = :id',
                        $targetCol,
                        $table,
                        $filterCol
                    ),
                    [],
                    [':id' => $id]
                )
                ->fetch('', 'fetch_all')
                ->get();
            $out[$key] = array_map(
                'intval',
                array_column((array)$rows, $targetCol)
            );
        }

        return $out;
    }

    /**
     * Replaces who a filter is shared with.
     *
     * The whole list is sent and the whole list is replaced, rather than
     * add/remove calls: the share editor shows a set of checkboxes, and
     * "what is ticked now" is the only state either side can agree on
     * without a per-row conflict story.
     *
     * Deleting first and inserting after is safe here because both statements
     * are scoped to one filter that only this caller may reach.
     *
     * @param int   $id              The filter.
     * @param int   $userID          The acting user.
     * @param array $targets         users/groups/roles as id lists.
     * @param bool  $mayManageGlobal Whether the caller holds the permission.
     *
     * @return array [bool ok, string error]
     */
    public function setShares(
        $id,
        $userID,
        array $targets,
        $mayManageGlobal = false
    ) {
        list($ok, $error) = $this->_reachable($id, $userID, $mayManageGlobal);
        if (!$ok) {
            return [false, $error];
        }
        $id = (int)$id;
        $sets = [
            'users' => [
                'savedFilterUserAssoc', 'sfuaFilterID', 'sfuaUserID',
                'users', 'uId'
            ],
            'groups' => [
                'savedFilterGroupAssoc', 'sfgaFilterID', 'sfgaUserGroupID',
                'userGroups', 'ugID'
            ],
            'roles' => [
                'savedFilterRoleAssoc', 'sfraFilterID', 'sfraRoleID',
                'roles', 'rID'
            ]
        ];
        foreach ($sets as $key => $spec) {
            list($table, $filterCol, $targetCol, $parent, $parentCol) = $spec;
            $wanted = array_values(array_unique(array_filter(array_map(
                'intval',
                (array)($targets[$key] ?? [])
            ))));
            self::$DB->query(
                sprintf('DELETE FROM `%s` WHERE `%s` = :id', $table, $filterCol),
                [],
                [':id' => $id]
            );
            if (!$wanted) {
                continue;
            }
            // Filtered against the parent table before inserting. The foreign
            // key added at schema 395 would refuse an unknown id anyway, but
            // it refuses the whole statement -- so one stale checkbox in a
            // stale browser tab would lose every other share in the same
            // save. Dropping the unknown ids keeps the rest of the intent.
            $placeholders = [];
            $binds = [];
            foreach ($wanted as $index => $value) {
                $placeholders[] = ':t' . $index;
                $binds[':t' . $index] = $value;
            }
            $live = self::$DB
                ->query(
                    sprintf(
                        'SELECT `%s` FROM `%s` WHERE `%s` IN (%s)',
                        $parentCol,
                        $parent,
                        $parentCol,
                        implode(',', $placeholders)
                    ),
                    [],
                    $binds
                )
                ->fetch('', 'fetch_all')
                ->get();
            $live = array_map('intval', array_column((array)$live, $parentCol));
            foreach ($live as $value) {
                self::$DB->query(
                    sprintf(
                        'INSERT IGNORE INTO `%s` (`%s`, `%s`)'
                        . ' VALUES (:id, :target)',
                        $table,
                        $filterCol,
                        $targetCol
                    ),
                    [],
                    [':id' => $id, ':target' => $value]
                );
            }
        }

        return [true, ''];
    }

    /**
     * Reads one filter, if this user may see it.
     *
     * @param int $id     The filter.
     * @param int $userID The user asking.
     *
     * @return array|null
     */
    public function fetch($id, $userID)
    {
        $id = (int)$id;
        $userID = (int)$userID;
        if ($id < 1 || $userID < 1) {
            return null;
        }
        $row = self::$DB
            ->query(
                'SELECT `sfID`, `sfUserID`, `sfTable`, `sfName`, `sfValue`'
                . ' FROM `savedFilters`'
                . ' WHERE `sfID` = :id'
                . ' AND (`sfUserID` = :uid OR `sfUserID` IS NULL)',
                [],
                [':id' => $id, ':uid' => $userID]
            )
            ->fetch()
            ->get();

        return $row ? (array)$row : null;
    }

    /**
     * Creates a filter, or replaces the value of one with the same name.
     *
     * Saving over a name you already used is the operation people expect from
     * a Save button, so this is an upsert rather than a create -- but only
     * within the owner the caller is allowed to write to. Saving a private
     * filter never touches a global of the same name, and vice versa.
     *
     * @param int    $userID          The acting user.
     * @param string $table           The grid key.
     * @param string $name            The filter's name.
     * @param string $value           The opaque filter state.
     * @param bool   $global          Save it for everyone.
     * @param bool   $mayManageGlobal Whether the caller holds the permission.
     *
     * @return array [bool ok, string error]
     */
    public function store(
        $userID,
        $table,
        $name,
        $value,
        $global = false,
        $mayManageGlobal = false
    ) {
        $userID = (int)$userID;
        $table = trim((string)$table);
        $name = trim((string)$name);
        $value = (string)$value;
        if ($userID < 1) {
            return [false, _('Not signed in')];
        }
        if ('' === $table || '' === $name) {
            return [false, _('A filter needs a grid and a name')];
        }
        if (strlen($name) > SavedFilter::MAX_NAME_BYTES) {
            return [false, _('That name is too long')];
        }
        if (strlen($table) > SavedFilter::MAX_TABLE_BYTES) {
            return [false, _('That grid name is too long')];
        }
        if ('' === $value) {
            return [false, _('There is no filter to save')];
        }
        if (strlen($value) > SavedFilter::MAX_VALUE_BYTES) {
            return [false, _('That filter is too large to save')];
        }
        if ($global && !$mayManageGlobal) {
            return [false, _('You may not save a filter for everyone')];
        }

        // The UNIQUE index covers the private case. It does NOT cover the
        // global one: MySQL does not treat two NULLs as equal in a unique
        // index, so (NULL, 'hosts', 'Broken') can be inserted twice and the
        // picker would then show two identically named entries. Find the
        // existing row explicitly instead of relying on an upsert that only
        // half works.
        $existing = self::$DB
            ->query(
                'SELECT `sfID` FROM `savedFilters`'
                . ' WHERE `sfTable` = :table AND `sfName` = :name'
                . ($global ? ' AND `sfUserID` IS NULL' : ' AND `sfUserID` = :uid'),
                [],
                $global
                    ? [':table' => $table, ':name' => $name]
                    : [':table' => $table, ':name' => $name, ':uid' => $userID]
            )
            ->fetch()
            ->get();

        if ($existing && !empty($existing['sfID'])) {
            return [
                (bool)self::$DB->query(
                    'UPDATE `savedFilters`'
                    . ' SET `sfValue` = :val, `sfModifiedTime` = NOW()'
                    . ' WHERE `sfID` = :id',
                    [],
                    [':val' => $value, ':id' => (int)$existing['sfID']]
                ),
                ''
            ];
        }

        return [
            (bool)self::$DB->query(
                'INSERT INTO `savedFilters`'
                . ' (`sfUserID`, `sfCreatorID`, `sfTable`, `sfName`,'
                . ' `sfValue`, `sfModifiedTime`)'
                . ' VALUES (:owner, :creator, :table, :name, :val, NOW())',
                [],
                [
                    // NULL, not 0: 0 has no row in users, so the foreign key
                    // added at schema 394 would refuse it.
                    ':owner' => $global ? null : $userID,
                    ':creator' => $userID,
                    ':table' => $table,
                    ':name' => $name,
                    ':val' => $value
                ]
            ),
            ''
        ];
    }

    /**
     * Renames a filter.
     *
     * @param int    $id              The filter.
     * @param int    $userID          The acting user.
     * @param string $name            The new name.
     * @param bool   $mayManageGlobal Whether the caller holds the permission.
     *
     * @return array [bool ok, string error]
     */
    public function rename($id, $userID, $name, $mayManageGlobal = false)
    {
        $name = trim((string)$name);
        if ('' === $name) {
            return [false, _('A filter needs a name')];
        }
        if (strlen($name) > SavedFilter::MAX_NAME_BYTES) {
            return [false, _('That name is too long')];
        }
        list($ok, $error) = $this->_reachable($id, $userID, $mayManageGlobal);
        if (!$ok) {
            return [false, $error];
        }

        return [
            (bool)self::$DB->query(
                'UPDATE `savedFilters`'
                . ' SET `sfName` = :name, `sfModifiedTime` = NOW()'
                . ' WHERE `sfID` = :id',
                [],
                [':name' => $name, ':id' => (int)$id]
            ),
            ''
        ];
    }

    /**
     * Deletes a filter.
     *
     * @param int  $id              The filter.
     * @param int  $userID          The acting user.
     * @param bool $mayManageGlobal Whether the caller holds the permission.
     *
     * @return array [bool ok, string error]
     */
    public function remove($id, $userID, $mayManageGlobal = false)
    {
        list($ok, $error) = $this->_reachable($id, $userID, $mayManageGlobal);
        if (!$ok) {
            return [false, $error];
        }

        return [
            (bool)self::$DB->query(
                'DELETE FROM `savedFilters` WHERE `sfID` = :id',
                [],
                [':id' => (int)$id]
            ),
            ''
        ];
    }

    /**
     * Whether this user may MUTATE this filter.
     *
     * The one place the two ownership rules are written down, so rename and
     * remove cannot drift apart. A row that does not exist and a row that
     * belongs to somebody else are answered identically: the alternative
     * turns the id into an oracle for which filters other people have.
     *
     * @param int  $id              The filter.
     * @param int  $userID          The acting user.
     * @param bool $mayManageGlobal Whether the caller holds the permission.
     *
     * @return array [bool ok, string error]
     */
    private function _reachable($id, $userID, $mayManageGlobal)
    {
        $id = (int)$id;
        $userID = (int)$userID;
        if ($id < 1 || $userID < 1) {
            return [false, _('No such filter')];
        }
        $row = self::$DB
            ->query(
                'SELECT `sfUserID` FROM `savedFilters` WHERE `sfID` = :id',
                [],
                [':id' => $id]
            )
            ->fetch()
            ->get();
        if (!$row) {
            return [false, _('No such filter')];
        }
        if (null === $row['sfUserID']) {
            return $mayManageGlobal
                ? [true, '']
                : [false, _('You may not change a filter shared with everyone')];
        }
        if ((int)$row['sfUserID'] !== $userID) {
            return [false, _('No such filter')];
        }

        return [true, ''];
    }
}
