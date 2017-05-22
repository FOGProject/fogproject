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
        $this->set('users', $userids);
    }
    /**
     * Load items not with this object
     *
     * @return void
     */
    protected function loadUsersnotinme()
    {
        $find = array('id' => $this->get('users'));
        $userids = self::getSubObjectIDs(
            'User',
            $find,
            'id',
            true
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
        $this->set('usersnotinme', $users);
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
        $this->set('hosts', $hostids);
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
        $hostids = self::getSubObjectIDs(
            'Host',
            array('id' => $hostids),
            'id',
            true
        );
        $this->set('hostsnotinme', $hostids);
    }
}
