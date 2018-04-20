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
        'hosts'
    ];
    protected $sqlQueryStr = "SELECT
        COUNT(`shaHostID`) `shaMembers`,COUNT(`suaUserID`) `suaMembers`, `%s`
        FROM `%s`
        LEFT OUTER JOIN `siteHostAssoc`
        ON `site`.`sID` = `siteHostAssoc`.`shaSiteID`
        LEFT OUTER JOIN `siteUserAssoc`
        ON `site`.`sID` = `siteUserAssoc`.`suaSiteID`
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
        $find = ['siteID' => $this->get('id')];
        Route::ids(
            'siteuserassociation',
            $find,
            'userID'
        );
        $siteuserassocs = json_decode(
            Route::getData(),
            true
        );
        $this->set('users', (array)$siteuserassocs);
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
        $find = ['siteID' => $siteIDs];
        Route::ids(
            'sitehostassociation',
            $find,
            'hostID'
        );
        $sitehostassocs = json_decode(
            Route::getData(),
            true
        );
        $this->set('hosts', (array)$sitehostassocs);
    }
}
