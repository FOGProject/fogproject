<?php
/**
 * Site Control plugin
 *
 * PHP version 7.4+
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
    protected $databaseFields = array(
        'id' => 'sID',
        'name' => 'sName',
        'description' => 'sDesc'
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'description',
        'users',
        'usersnotinme',
        'hosts',
        'hostsnotinme'
    );
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
            ->load();
    }
    /**
     * Load users
     *
     * @return void
     */
    protected function loadUsers()
    {
        $associds = self::getSubObjectIDs(
            'SiteUserAssociation',
            array('siteID' => $this->get('id')),
            'userID'
        );
        $userids = self::getSubObjectIDs(
            'User',
            array('id' => $associds)
        );
        $this->set('users', (array)$userids);
    }
    /**
     * Load items not with this object
     *
     * @return void
     */
    protected function loadUsersnotinme()
    {
        $userids = array_diff(
            self::getSubObjectIDs('User'),
            $this->get('users')
        );
        $types = array();
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            array('types' => &$types)
        );
        $users = array();
        foreach ((array)self::getClass('UserManager')
            ->find(array('id' => $userids)) as &$User
        ) {
            if (in_array($User->get('type'), $types)) {
                continue;
            }
            $users[] = $User->get('id');
            unset($User);
        }
        unset($userids, $types);
        $this->set('usersnotinme', (array)$users);
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
        if (is_null($ids)) {
            $siteIDs = $this->get('id');
        } else {
            $siteIDs = $ids;
        }
        $associds = self::getSubObjectIDs(
            'SiteHostAssociation',
            array('siteID' => $siteIDs),
            'hostID'
        );
        $hostids = self::getSubObjectIDs(
            'Host',
            array('id' => $associds)
        );
        $this->set('hosts', (array)$hostids);
    }
    /**
     * Load hosts not in this object.
     *
     * @param mixed $ids The ids to pass in.
     *
     * @return void
     */
    public function loadHostsnotinme($ids = null)
    {
        if (is_null($ids)) {
            $siteIDs = $this->get('id');
        } else {
            $siteIDs = $ids;
        }
        $associds = self::getSubObjectIDs(
            'SiteHostAssociation',
            array('siteID' => $siteIDs),
            'hostID'
        );
        $hostids = self::getSubObjectIDs(
            'Host',
            array('id' => $associds)
        );
        $hostids = array_diff(
            self::getSubObjectIDs('Host'),
            $hostids
        );
        $this->set('hostsnotinme', (array)$hostids);
    }
    /**
     * Set's values for associative fields.
     *
     * @param string $assocItem    the assoc item to work from/with
     * @param string $alterItem    the alternate item to work with
     * @param bool   $implicitCall call class implicitly instead of appending
     *                             with association
     *
     * @return object
     */
    public function assocSetter($assocItem, $alterItem = '', $implicitCall = false)
    {
        // Lower our item
        $alterItem = strtolower($alterItem ?: $assocItem);
        // Getter is pluralized
        $plural = "{$alterItem}s";
        // Class to call, if implicit leave off association.
        $classCall = ($implicitCall ? $assocItem : "{$assocItem}Association");
        // Main object and string setters.
        $obj = strtolower(get_class($this));
        $objstr = "{$obj}ID";
        $assocstr = "{$alterItem}ID";

        // Don't work on an association the caller didn't actually touch.
        // isDirty(), not isPopulated(): isPopulated() is also true when a
        // key was merely lazy-loaded for reading, which would otherwise
        // make this run a full DB diff -- for a no-op result -- on every
        // save() that happens to read this association first. isDirty()
        // only reports true for a real caller-driven write.
        if (!$this->isDirty($plural)) {
            return $this;
        }

        // Get the current items, normalized to positive integer ids -- this
        // copy had no guard at all, so a falsy get() fallback reached
        // array_diff() as a string and fatalled under PHP 8.
        $items = self::positiveIntIds($this->get($plural));
        Route::ids(
            $classCall,
            [$objstr => $this->get('id')],
            $assocstr
        );
        $cur = json_decode(Route::getData(), true);

        // Get the items differing between the current and what we have associated.
        // Remove the items if there's anything to remove.
        // Take in account that the array_diff function returns different values depending the order of the factors. In this way:
        // When we delete hosts or users from the webUI:
        $delItems = array_diff($cur, $items);
        // When we add hosts or users from the webUI:
        $addItems = array_diff($items, $cur);
        if (count($delItems)) {
            Route::deletemass(
                $classCall,
                [
                    $objstr => $this->get('id'),
                    $assocstr => $delItems,
                ]
            );
            return $this;
        }
        if (count($addItems)) {
            $items = $addItems;
            // Setup our insert.
            $insert_fields = [
                $objstr,
                $assocstr
            ];
            $insert_values = [];
            if ($assocstr == 'moduleID') {
                $insert_fields[] = 'state';
            }
            foreach ($items as &$id) {
                $insert_val = [
                    $this->get('id'),
                    $id
                ];
                if ($assocstr == 'moduleID') {
                    $insert_val[] = 1;
                }
                $insert_values[] = $insert_val;
                unset($insert_val, $id);
            }
            if (count($insert_values ?: []) > 0) {
                self::getClass("{$classCall}manager")->insertBatch(
                    $insert_fields,
                    $insert_values
                );
            }
        }
        return $this;
    }
    /**
     * Whether this user's view is bounded by site membership.
     *
     * @param int $userID The user to test.
     *
     * @return bool
     */
    public static function userIsRestricted($userID)
    {
        $userID = (int)$userID;
        if ($userID < 1) {
            return false;
        }
        $flags = self::getSubObjectIDs(
            'SiteUserRestriction',
            array('userID' => $userID),
            'isRestricted'
        );
        return (bool)(isset($flags[0]) ? $flags[0] : false);
    }
    /**
     * The sites this user belongs to.
     *
     * @param int $userID The user to look up.
     *
     * @return array
     */
    public static function userSiteIDs($userID)
    {
        return (array)self::getSubObjectIDs(
            'SiteUserAssociation',
            array('userID' => (int)$userID),
            'siteID'
        );
    }
    /**
     * The hosts belonging to any of these sites.
     *
     * @param array $siteIDs The sites.
     *
     * @return array
     */
    public static function hostIDsForSites($siteIDs)
    {
        $siteIDs = array_filter(
            array_map('intval', array_values((array)$siteIDs))
        );
        if (count($siteIDs) < 1) {
            return array();
        }
        // Read directly rather than through getSubObjectIDs().
        //
        // SiteHostAssociation declares a class relationship to Host, and
        // find() walks those relationships to build the joins -- including
        // Host's own MACAddressAssociation relationship, which carries the
        // filter array('primary' => 1). buildQuery() puts that in the WHERE
        // as `hostMAC`.`hmPrimary` = '1', which turns a LEFT OUTER JOIN into
        // an inner one and DROPS every host with no primary MAC row.
        //
        // The effect was silent and it under-returned: a site-restricted user
        // did not see hosts in their own site that had no primary MAC. In the
        // lab, 95 of 1000. It is not a disclosure -- nobody saw anything they
        // should not -- but it is wrong, and it made the SQL boundary in
        // scopedObjectWhere() disagree with this one, which is worse: which
        // hosts you could see depended on which of the two answered.
        //
        // A plain membership lookup has no business joining Host at all. The
        // table and column names are the same ones scopedObjectWhere() writes
        // and are justified there.
        $rows = self::$DB
            ->query(
                sprintf(
                    'SELECT DISTINCT `siteHostAssoc`.`shaHostID`'
                    . ' FROM `siteHostAssoc`'
                    . ' WHERE `siteHostAssoc`.`shaSiteID` IN (%s)',
                    implode(',', $siteIDs)
                )
            )
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get('shaHostID');
        return array_values(
            array_unique(
                array_map('intval', (array)$rows)
            )
        );
    }
    /**
     * The groups holding one or more hosts of these sites.
     *
     * @param array $siteIDs The sites.
     *
     * @return array
     */
    public static function groupIDsForSites($siteIDs)
    {
        $hostIDs = self::hostIDsForSites($siteIDs);
        if (count($hostIDs) < 1) {
            return array();
        }
        return (array)self::getSubObjectIDs(
            'GroupAssociation',
            array('hostID' => $hostIDs),
            'groupID'
        );
    }
    /**
     * The object ids $userID may see for $classname.
     *
     * THE RETURN IS A TRI-STATE and the distinction is the whole point:
     *
     *   null          no boundary applies -- leave the caller's set alone
     *   array(...)    narrow to exactly these ids
     *   array()       a real answer meaning "nothing", NOT "no boundary"
     *
     * null is the only value that means "unbounded". Treating an empty
     * array as unbounded -- which is what any `if (!$ids)` test does -- is
     * how a user entitled to nothing ends up seeing everything, so callers
     * must test `null ===` and nothing looser.
     *
     * This is the single statement of the membership rule. The management
     * pages reach it through AddSiteFilterSearch and the API reaches it
     * through AddSiteAPI; if the two ever disagree about who may see what,
     * the boundary is decorative.
     *
     * @param string $classname The class being listed or fetched.
     * @param int    $userID    The acting user.
     *
     * @return array|null
     */
    /**
     * Per-request memo of _boundedSiteIDs(), keyed by user id.
     *
     * Core consults the SQL fragment first and falls back to the id list, so
     * on a server where only one of the two is answered -- which is every
     * server, since the fragment always wins here -- an UNRESTRICTED user
     * pays for the restriction lookup TWICE per read: once for the fragment
     * that declines, once for the id list that declines. Measured at 2 -> 3
     * statements for an administrator on `names(host)` before this existed,
     * which is a cost the boundary imposes on exactly the people it does not
     * apply to.
     *
     * Deliberately NOT a memo on userIsRestricted() or userSiteIDs(). Those
     * are public and the management pages call them directly, including on
     * requests that have just written the rows they read; a memo there would
     * serve a stale answer to the page that changed it. Scoped to this
     * private ladder, the only callers are the two read-side entry points,
     * neither of which writes.
     *
     * @var array
     */
    private static $_boundedSites = array();
    /**
     * The same boundary as scopedObjectIDs(), expressed as SQL.
     *
     * THE RETURN IS A TRI-STATE, and it is not the id list's:
     *
     *   null      no boundary applies -- the caller must fall back
     *   '<sql>'   narrow with this expression
     *   '1=0'     a real answer meaning "nothing"
     *
     * There is no empty-string state, because the caller reads '' as "no
     * listener answered" -- see Route::_scopeWhere(). Deny-all therefore has
     * to be said in SQL, and '1=0' is how it is said here.
     *
     * Why a fragment at all: scopedObjectIDs() answers by reading every
     * object the user may see into PHP, on every request, and the caller then
     * either splices thousands of ids into an IN list or compares each row
     * against them. This costs one expression whatever the fleet size. The
     * membership rule is still stated once -- the two functions share the
     * ladder below and differ only in what they return -- so the API and the
     * management pages cannot drift into two different answers.
     *
     * Nothing here interpolates user input. $idExpr is built by the caller
     * from the model's own $databaseFields, and $userID is cast to int; there
     * is no path from a request parameter into this string. A future edit
     * that wants to inline anything else needs a parameter, not a cast.
     *
     * @param string $classname The class being listed or fetched.
     * @param string $idExpr    The object-id column, quoted and qualified.
     * @param int    $userID    The acting user.
     *
     * @return string|null
     */
    public static function scopedObjectWhere($classname, $idExpr, $userID)
    {
        $siteIDs = self::_boundedSiteIDs($classname, $userID);
        if (null === $siteIDs) {
            return null;
        }
        if (count($siteIDs) < 1) {
            return '1=0';
        }
        $sites = implode(
            ',',
            array_map('intval', array_values($siteIDs))
        );
        $hostsInSites = sprintf(
            'SELECT `siteHostAssoc`.`shaHostID` FROM `siteHostAssoc`'
            . ' WHERE `siteHostAssoc`.`shaSiteID` IN (%s)',
            $sites
        );
        if ('group' === strtolower((string)$classname)) {
            // A group is in scope when it holds at least one host that is.
            // Same rule as groupIDsForSites(), which is the point.
            return sprintf(
                'EXISTS (SELECT 1 FROM `groupMembers`'
                . ' WHERE `groupMembers`.`gmGroupID` = %s'
                . ' AND `groupMembers`.`gmHostID` IN (%s))',
                $idExpr,
                $hostsInSites
            );
        }
        return sprintf(
            'EXISTS (SELECT 1 FROM `siteHostAssoc`'
            . ' WHERE `siteHostAssoc`.`shaHostID` = %s'
            . ' AND `siteHostAssoc`.`shaSiteID` IN (%s))',
            $idExpr,
            $sites
        );
    }
    /**
     * The sites bounding this user for this class, or null for no boundary.
     *
     * The shared front half of scopedObjectIDs() and scopedObjectWhere():
     * everything up to the membership lookup itself. Two copies of this
     * ladder would be two chances to answer "is this user bounded?"
     * differently, in the one place where the two answers must agree.
     *
     * Returns an EMPTY ARRAY for a restricted user belonging to no site --
     * a real answer meaning "nothing" -- and null when no boundary applies.
     *
     * @param string $classname The class being listed or fetched.
     * @param int    $userID    The acting user.
     *
     * @return array|null
     */
    private static function _boundedSiteIDs($classname, $userID)
    {
        $classname = strtolower((string)$classname);
        // Only what the plugin actually associates. Everything else --
        // images, snapins, storage nodes, the association tables -- has no
        // site boundary to apply, and narrowing one the plugin knows nothing
        // about would break lookups rather than protect anything.
        if (!in_array($classname, array('host', 'group'), true)) {
            return null;
        }
        $userID = (int)$userID;
        if (array_key_exists($userID, self::$_boundedSites)) {
            return self::$_boundedSites[$userID];
        }
        self::$_boundedSites[$userID] = self::userIsRestricted($userID)
            ? self::userSiteIDs($userID)
            : null;
        return self::$_boundedSites[$userID];
    }
    public static function scopedObjectIDs($classname, $userID)
    {
        // Same ladder as scopedObjectWhere(), deliberately: these two answer
        // the same question in two shapes, and a server where they disagree
        // has a boundary that depends on which route you came in through.
        $siteIDs = self::_boundedSiteIDs($classname, $userID);
        if (null === $siteIDs) {
            return null;
        }
        if (count($siteIDs) < 1) {
            return array();
        }
        return 'group' === strtolower((string)$classname)
            ? self::groupIDsForSites($siteIDs)
            : self::hostIDsForSites($siteIDs);
    }
}
