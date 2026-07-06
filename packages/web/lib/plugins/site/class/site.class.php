<?php
/**
 * Site Control plugin
 *
 * PHP version 5
 *
 * @category Site
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site Control plugin
 *
 * @category Site
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
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
    protected $databaseTable = 'site';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sID',
        'name' => 'sName',
        'description' => 'sDesc'
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
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'description',
        'users',
        'hosts',
        'groups',
        'usergroups'
    ];
    // COUNT(DISTINCT ...) so the four LEFT OUTER JOINs (which multiply rows
    // together) don't inflate each other's member counts.
    protected $sqlQueryStr = "SELECT
        COUNT(DISTINCT `shaHostID`) `shaMembers`,COUNT(DISTINCT `suaUserID`) `suaMembers`,COUNT(DISTINCT `sgaGroupID`) `sgaMembers`,COUNT(DISTINCT `sugaUserGroupID`) `sugaMembers`, `%s`
        FROM `%s`
        LEFT OUTER JOIN `siteHostAssoc`
        ON `site`.`sID` = `siteHostAssoc`.`shaSiteID`
        LEFT OUTER JOIN `siteUserAssoc`
        ON `site`.`sID` = `siteUserAssoc`.`suaSiteID`
        LEFT OUTER JOIN `siteGroupAssoc`
        ON `site`.`sID` = `siteGroupAssoc`.`sgaSiteID`
        LEFT OUTER JOIN `siteUserGroupAssoc`
        ON `site`.`sID` = `siteUserGroupAssoc`.`sugaSiteID`
        %s
        GROUP BY `sID`,`shaSiteID`
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        %s";
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
        return $this->addRemItem(
            'users',
            (array)$addArray,
            'merge'
        );
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
        return $this->addRemItem(
            'users',
            (array)$removeArray,
            'diff'
        );
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
        return $this->addRemItem(
            'hosts',
            (array)$addArray,
            'merge'
        );
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
        return $this->addRemItem(
            'hosts',
            (array)$removeArray,
            'diff'
        );
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
        return $this->addRemItem(
            'groups',
            (array)$addArray,
            'merge'
        );
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
        return $this->addRemItem(
            'groups',
            (array)$removeArray,
            'diff'
        );
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
        return $this->addRemItem(
            'usergroups',
            (array)$addArray,
            'merge'
        );
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
        return $this->addRemItem(
            'usergroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Stores/updates the site
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('SiteUserAssociation', 'user', true)
            ->assocSetter('SiteHostAssociation', 'host', true)
            ->assocSetter('SiteGroupAssociation', 'group', true)
            ->assocSetter('SiteUserGroupAssociation', 'usergroup', true)
            ->load();
    }
    /**
     * Load users
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['siteID' => $this->get('id')];
        $siteuserassocs = Route::getIds(
            'siteuserassociation',
            $find,
            'userID'
        );
        $this->set('users', (array)$siteuserassocs);
    }
    /**
     * Load groups
     *
     * @return void
     */
    protected function loadGroups()
    {
        $find = ['siteID' => $this->get('id')];
        $sitegroupassocs = Route::getIds(
            'sitegroupassociation',
            $find,
            'groupID'
        );
        $this->set('groups', (array)$sitegroupassocs);
    }
    /**
     * Load user groups
     *
     * @return void
     */
    protected function loadUsergroups()
    {
        $find = ['siteID' => $this->get('id')];
        $siteusergroupassocs = Route::getIds(
            'siteusergroupassociation',
            $find,
            'usergroupID'
        );
        $this->set('usergroups', (array)$siteusergroupassocs);
    }
    /**
     * Load hosts
     *
     * @param mixed $ids The ids to pass in.
     *
     * @return void
     */
    public function loadHosts($ids = null)
    {
        $this->_loadHostIds(
            'sitehostassociation',
            ['siteID' => is_null($ids) ? $this->get('id') : $ids],
            'hostID'
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
        // Funnel through the cascade authority so a single site delete also
        // clears its host/user links (the 'site' case in sitedeletemassitems).
        Route::deletemass('site', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
    /**
     * Per-request cache of a user's site ids (keyed by userID).
     *
     * @var array
     */
    private static $_userSiteCache = [];
    /**
     * Map a scoped node to its [association class, object-id field].
     * Returns null for a node the Site plugin does not scope.
     *
     * @param string $node the lowercased node
     *
     * @return array|null
     */
    private static function _assocFor($node)
    {
        switch (strtolower(trim((string)$node))) {
            case 'host':
                return ['sitehostassociation', 'hostID'];
            case 'user':
                return ['siteuserassociation', 'userID'];
            case 'group':
                return ['sitegroupassociation', 'groupID'];
            case 'usergroup':
                return ['siteusergroupassociation', 'usergroupID'];
        }
        return null;
    }
    /**
     * The set of site ids a user is assigned to (via siteUserAssoc). A user
     * with no assignment returns an empty set, which the scope checks treat
     * as deny-all.
     *
     * @param int $userID the user id
     *
     * @return array int site ids
     */
    public static function userSiteIDs($userID)
    {
        $userID = (int)$userID;
        if (array_key_exists($userID, self::$_userSiteCache)) {
            return self::$_userSiteCache[$userID];
        }
        $sites = Route::getIds(
            'siteuserassociation',
            ['userID' => $userID],
            'siteID'
        );
        return self::$_userSiteCache[$userID] = array_map(
            'intval',
            (array)$sites
        );
    }
    /**
     * Is a single object within a user's site scope? True only when the
     * object shares at least one site with the user. A user with no sites,
     * or an object with no site, is never in scope (strict deny-all). Nodes
     * the plugin does not scope are always in scope.
     *
     * @param string $node   the node (host|user|group|usergroup)
     * @param int    $id     the object id
     * @param int    $userID the acting user id
     *
     * @return bool
     */
    public static function inScope($node, $id, $userID)
    {
        $assoc = self::_assocFor($node);
        if (null === $assoc) {
            return true;
        }
        $userSites = self::userSiteIDs($userID);
        if (empty($userSites)) {
            return false;
        }
        $hits = Route::getIds(
            $assoc[0],
            [$assoc[1] => (int)$id, 'siteID' => $userSites],
            $assoc[1]
        );
        return count((array)$hits) > 0;
    }
    /**
     * Filter a list of object ids down to those within a user's site scope.
     * Single query: keeps ids linked to any of the user's sites. A user with
     * no sites gets an empty list (deny-all); an unscoped node is unchanged.
     *
     * @param string $node   the node (host|user|group|usergroup)
     * @param array  $ids    candidate object ids
     * @param int    $userID the acting user id
     *
     * @return array int ids in scope
     */
    public static function filterInScope($node, $ids, $userID)
    {
        $ids = array_map('intval', (array)$ids);
        $assoc = self::_assocFor($node);
        if (null === $assoc) {
            return $ids;
        }
        $userSites = self::userSiteIDs($userID);
        if (empty($userSites) || empty($ids)) {
            return [];
        }
        $inScope = Route::getIds(
            $assoc[0],
            [$assoc[1] => $ids, 'siteID' => $userSites],
            $assoc[1]
        );
        return array_values(
            array_intersect($ids, array_map('intval', (array)$inScope))
        );
    }
}
