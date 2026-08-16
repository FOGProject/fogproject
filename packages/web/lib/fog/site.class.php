<?php
/**
 * A site: a named group of hosts, users, groups and user groups.
 *
 * PHP version 5
 *
 * @category Site
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        'description' => 'siteDesc',
        'catchall' => 'siteCatchAll'
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
     * @var array
     */
    protected $additionalFields = [
        'users',
        'hosts',
        'groups',
        'usergroups'
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
     * Stores/updates the site.
     *
     * @return object
     */
    public function save()
    {
        // `siteDesc` is LONGTEXT NOT NULL with no default -- a TEXT column
        // cannot carry one before MySQL 8.0.13, so the empty case has to be
        // filled in here. save() drops null-valued fields before building
        // the column list, so leaving it unset omits the column entirely
        // and strict mode rejects the INSERT.
        if (!$this->isPopulated('description')
            || null === $this->get('description')
        ) {
            $this->set('description', '');
        }
        parent::save();
        return $this
            ->assocSetter('SiteUserMember', 'user', true)
            ->assocSetter('SiteHostMember', 'host', true)
            ->assocSetter('SiteGroupMember', 'group', true)
            ->assocSetter('SiteUserGroupMember', 'usergroup', true)
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
     * CHECK constraint rejects 0. So turning the flag OFF means writing
     * NULL, and save() DROPS null-valued fields before building its column
     * list, which would silently leave the site as the catch-all while
     * reporting success.
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
