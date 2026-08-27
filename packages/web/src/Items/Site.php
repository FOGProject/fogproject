<?php
/**
 * A site: a named group of hosts, users, groups and user groups.
 *
 * PHP version 7.4+
 *
 * @category Site
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Router\Route;

/**
 * A site: a named group of hosts, users, groups and user groups.
 *
 * The row. SiteScope is the policy that reads it -- kept apart because a
 * model is a row with accessors and a boundary decision is not, and
 * because SiteScope must answer during a request that never builds one of
 * these.
 *
 * Originally the Site Control plugin by Fernando Gietz; moved into core so
 * the boundary it enforces is owned by the same code that owns the
 * permission check it sits behind.
 *
 * @category Site
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Site extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'sites';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'siteID',
        'name' => 'siteName',
        'description' => 'siteDesc'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields.
     *
     * `catchall` is here rather than in $databaseFields, and that placement
     * is load bearing. FOGController::save() can only omit a column -- and
     * so store SQL NULL -- when the FRIENDLY key ends in "id"; for every
     * other key an unset value is coerced to '' and written. `siteCatchAll`
     * is TINYINT UNSIGNED with a CHECK of exactly 1, so '' is rejected
     * outright (MySQL 1366) and the whole INSERT fails.
     *
     * That is not theoretical: with it declared as a database field, every
     * "create site" failed while the page reported success. So the column
     * is not save()-managed at all. makeCatchAll() writes it in SQL, and
     * loadCatchall() reads it.
     *
     * @var array
     */
    protected $additionalFields = [
        'users',
        'hosts',
        'groups',
        'usergroups',
        // The grant side. `usergroups` above means "this user group is an
        // OBJECT in this site"; `grantusergroups` means "members of this
        // user group GET this site". Same pair of ids, opposite question,
        // which is why they are separate tables and separate fields --
        // see SiteUserGroupGrant.
        'grantroles',
        'grantusergroups',
        'catchall'
    ];
    /**
     * The list query.
     *
     * COUNT(DISTINCT ...) because the four LEFT OUTER JOINs multiply each
     * other's rows -- a site with 3 hosts and 2 users produces 6 rows, and
     * a plain COUNT would report 6 of each.
     *
     * @var string
     */
    protected $sqlQueryStr = "SELECT
        COUNT(DISTINCT `shmHostID`) `shmMembers`,
        COUNT(DISTINCT `sumUserID`) `sumMembers`,
        COUNT(DISTINCT `sgmGroupID`) `sgmMembers`,
        COUNT(DISTINCT `sugmUserGroupID`) `sugmMembers`, `%s`
        FROM `%s`
        LEFT OUTER JOIN `siteHostMembers`
        ON `sites`.`siteID` = `siteHostMembers`.`shmSiteID`
        LEFT OUTER JOIN `siteUserMembers`
        ON `sites`.`siteID` = `siteUserMembers`.`sumSiteID`
        LEFT OUTER JOIN `siteGroupMembers`
        ON `sites`.`siteID` = `siteGroupMembers`.`sgmSiteID`
        LEFT OUTER JOIN `siteUserGroupMembers`
        ON `sites`.`siteID` = `siteUserGroupMembers`.`sugmSiteID`
        %s
        GROUP BY `siteID`
        %s
        %s";
    /**
     * The filter query.
     *
     * @var string
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        %s";
    /**
     * The total query.
     *
     * @var string
     */
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`";
    /**
     * Add user to site.
     *
     * @param array $addArray The users to add.
     *
     * @return object
     */
    public function addUser($addArray)
    {
        return $this->addRemItem('users', (array)$addArray, 'merge');
    }
    /**
     * Remove user from site.
     *
     * @param array $removeArray The users to remove.
     *
     * @return object
     */
    public function removeUser($removeArray)
    {
        return $this->addRemItem('users', (array)$removeArray, 'diff');
    }
    /**
     * Add host to site.
     *
     * @param array $addArray The hosts to add.
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem('hosts', (array)$addArray, 'merge');
    }
    /**
     * Remove host from site.
     *
     * @param array $removeArray The hosts to remove.
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem('hosts', (array)$removeArray, 'diff');
    }
    /**
     * Add group to site.
     *
     * @param array $addArray The groups to add.
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addRemItem('groups', (array)$addArray, 'merge');
    }
    /**
     * Remove group from site.
     *
     * @param array $removeArray The groups to remove.
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->addRemItem('groups', (array)$removeArray, 'diff');
    }
    /**
     * Add user group to site.
     *
     * @param array $addArray The user groups to add.
     *
     * @return object
     */
    public function addUserGroup($addArray)
    {
        return $this->addRemItem('usergroups', (array)$addArray, 'merge');
    }
    /**
     * Remove user group from site.
     *
     * @param array $removeArray The user groups to remove.
     *
     * @return object
     */
    public function removeUserGroup($removeArray)
    {
        return $this->addRemItem('usergroups', (array)$removeArray, 'diff');
    }
    /**
     * Grant this site to a role.
     *
     * Everyone holding the role is in scope for this site, including
     * through a user group that holds it -- SiteScope resolves both paths.
     *
     * @param array $addArray The roles to grant to.
     *
     * @return object
     */
    public function addGrantRole($addArray)
    {
        return $this->addRemItem('grantroles', (array)$addArray, 'merge');
    }
    /**
     * Stop granting this site to a role.
     *
     * @param array $removeArray The roles to stop granting to.
     *
     * @return object
     */
    public function removeGrantRole($removeArray)
    {
        return $this->addRemItem('grantroles', (array)$removeArray, 'diff');
    }
    /**
     * Grant this site to a user group.
     *
     * Not the same act as adding the user group to the site: that makes it
     * an object the site contains, this puts its members in scope.
     *
     * @param array $addArray The user groups to grant to.
     *
     * @return object
     */
    public function addGrantUserGroup($addArray)
    {
        return $this->addRemItem('grantusergroups', (array)$addArray, 'merge');
    }
    /**
     * Stop granting this site to a user group.
     *
     * @param array $removeArray The user groups to stop granting to.
     *
     * @return object
     */
    public function removeGrantUserGroup($removeArray)
    {
        return $this->addRemItem(
            'grantusergroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Stores/updates the site.
     *
     * @return object
     */
    public function save()
    {
        // Propagated, not discarded. parent::save() returns false on
        // failure, and an override that returns $this regardless makes
        // every caller's `if (!$obj->save())` unreachable -- the create
        // page then reports "Site added!" over a row that was never
        // written, with the real error only in the history table.
        if (!parent::save()) {
            return false;
        }
        return $this
            ->assocSetter('SiteUserMember', 'user', true)
            ->assocSetter('SiteHostMember', 'host', true)
            ->assocSetter('SiteGroupMember', 'group', true)
            ->assocSetter('SiteUserGroupMember', 'usergroup', true)
            ->assocSetter('SiteRoleGrant', 'grantrole', true)
            ->assocSetter('SiteUserGroupGrant', 'grantusergroup', true)
            ->load();
    }
    /**
     * Is this the catch-all site?
     *
     * @return bool
     */
    public function isCatchAll()
    {
        return null !== $this->get('catchall')
            && '' !== $this->get('catchall');
    }
    /**
     * Makes this site the catch-all, or stops it being one.
     *
     * Not done through save(), and both halves are the reason:
     *
     * `siteCatchAll` is NULL or 1 and nothing else -- a UNIQUE index makes
     * "at most one" a property of the table rather than of this code, and a
     * CHECK constraint rejects 0. save() cannot write either state: it
     * coerces an empty non-"id" field to '' rather than NULL (see the
     * $additionalFields docblock), so turning the flag OFF through it
     * fails the CHECK, and so does creating a site that was never meant to
     * be the catch-all at all.
     *
     * Turning it ON has to clear the incumbent first or the UNIQUE index
     * rejects the write. Done as two statements in one transaction so a
     * failure cannot leave the install with no catch-all at all, which
     * would quietly narrow every unassigned user's view to nothing.
     *
     * @param bool $on true to make this the catch-all
     *
     * @return object
     */
    public function makeCatchAll($on = true)
    {
        $id = (int)$this->get('id');
        if ($id < 1) {
            throw new \Exception(_('Save the site before setting catch-all'));
        }
        if (!$on) {
            self::$DB->query(
                'UPDATE `sites` SET `siteCatchAll` = NULL '
                . 'WHERE `siteID` = :id',
                [],
                ['id' => $id]
            );
            return $this->set('catchall', null);
        }
        self::$DB->query('START TRANSACTION');
        try {
            self::$DB->query(
                'UPDATE `sites` SET `siteCatchAll` = NULL '
                . 'WHERE `siteCatchAll` IS NOT NULL AND `siteID` != :id',
                [],
                ['id' => $id]
            );
            self::$DB->query(
                'UPDATE `sites` SET `siteCatchAll` = 1 WHERE `siteID` = :id',
                [],
                ['id' => $id]
            );
            self::$DB->query('COMMIT');
        } catch (\Exception $e) {
            self::$DB->query('ROLLBACK');
            throw $e;
        }
        return $this->set('catchall', 1);
    }
    /**
     * Load users.
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['siteID' => $this->get('id')];
        $this->set(
            'users',
            (array)Route::getIds('siteusermember', $find, 'userID')
        );
    }
    /**
     * Load groups.
     *
     * @return void
     */
    protected function loadGroups()
    {
        $find = ['siteID' => $this->get('id')];
        $this->set(
            'groups',
            (array)Route::getIds('sitegroupmember', $find, 'groupID')
        );
    }
    /**
     * Load user groups.
     *
     * @return void
     */
    protected function loadUsergroups()
    {
        $find = ['siteID' => $this->get('id')];
        $this->set(
            'usergroups',
            (array)Route::getIds('siteusergroupmember', $find, 'usergroupID')
        );
    }
    /**
     * Load the roles this site is granted to.
     *
     * @return void
     */
    protected function loadGrantroles()
    {
        $find = ['siteID' => $this->get('id')];
        $this->set(
            'grantroles',
            (array)Route::getIds('siterolegrant', $find, 'grantroleID')
        );
    }
    /**
     * Load the user groups this site is granted to.
     *
     * @return void
     */
    protected function loadGrantusergroups()
    {
        $find = ['siteID' => $this->get('id')];
        $this->set(
            'grantusergroups',
            (array)Route::getIds(
                'siteusergroupgrant',
                $find,
                'grantusergroupID'
            )
        );
    }
    /**
     * Load the catch-all flag.
     *
     * Read in SQL because `catchall` is not a database field -- see the
     * $additionalFields docblock for why it cannot be one -- so load()
     * would otherwise never populate it and isCatchAll() would answer
     * "no" for the catch-all site itself.
     *
     * @return void
     */
    protected function loadCatchall()
    {
        $id = (int)$this->get('id');
        if ($id < 1) {
            $this->set('catchall', null);
            return;
        }
        $row = self::$DB
            ->query(
                'SELECT `siteCatchAll` AS `flag` FROM `sites` '
                . 'WHERE `siteID` = :id',
                [],
                ['id' => $id]
            )
            ->fetch(\PDO::FETCH_ASSOC)
            ->get();
        $flag = is_array($row) && isset($row['flag']) ? $row['flag'] : null;
        $this->set('catchall', ('' === $flag ? null : $flag));
    }
    /**
     * Load hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $find = ['siteID' => $this->get('id')];
        $this->set(
            'hosts',
            (array)Route::getIds('sitehostmember', $find, 'hostID')
        );
    }
    /**
     * The site ids a host belongs to.
     *
     * The inverse of loadHosts(), for the two callers that hold a host and
     * want its site rather than a site and its hosts.
     *
     * @param int $hostID the host id
     *
     * @return array int site ids
     */
    public static function hostSiteIDs($hostID)
    {
        $hostID = (int)$hostID;
        if ($hostID < 1) {
            return [];
        }
        return array_map(
            'intval',
            (array)Route::getIds('sitehostmember', ['hostID' => $hostID], 'siteID')
        );
    }
    /**
     * The names of the sites a host belongs to, for CSV export.
     *
     * Returns an array although a host has at most one site, because the
     * exporter formats every association label the same way and a bare
     * string would be iterated character by character.
     *
     * @param object $host the host being exported
     *
     * @return array the site names
     */
    public static function hostSiteNames($host)
    {
        $names = [];
        foreach (self::hostSiteIDs($host->get('id')) as $siteID) {
            $site = self::getClass('Site', $siteID);
            if ($site->isValid()) {
                $names[] = $site->get('name');
            }
        }
        return $names;
    }
    /**
     * Puts a host in one site on CSV import, replacing whatever it was in.
     *
     * A host has a single site, so this is a set rather than an add: any
     * existing membership is cleared and only the first resolved id is
     * used. Importing a row whose site column names two sites is a
     * malformed row, not a request for two sites, and taking the first is
     * what the retired Site plugin did.
     *
     * Written through the Site entity rather than by inserting rows so it
     * shares the deduplication and the cascade the Site page goes through.
     *
     * @param object $host the host being imported
     * @param array  $ids  the resolved site ids
     *
     * @return void
     */
    public static function applyHostSite($host, $ids)
    {
        $hostID = (int)$host->get('id');
        $ids = array_values(array_filter(array_map('intval', (array)$ids)));
        if ($hostID < 1 || count($ids) < 1) {
            return;
        }
        $wanted = $ids[0];
        foreach (self::hostSiteIDs($hostID) as $current) {
            if ($current === $wanted) {
                return;
            }
            self::getClass('Site', $current)->removeHost([$hostID])->save();
        }
        $site = self::getClass('Site', $wanted);
        if ($site->isValid()) {
            $site->addHost([$hostID])->save();
        }
    }
    /**
     * Destroy this particular object.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        // Funnel through the single cascade authority so a single-site
        // delete clears the same membership tables the REST delete does,
        // instead of keeping a second copy of the map here.
        Route::deletemass('site', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Site', 'Site');
