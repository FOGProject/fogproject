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
        'id',
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
     * @param bool   $implicitCall call class implicitely instead of appending
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

        // Don't work on item that isn't loaded yet.
        if (!$this->isLoaded($plural)) {
            return $this;
        }

        // Get the current items.
        $items = $this->get($plural);
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
}
