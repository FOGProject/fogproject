<?php
/**
 * Modifies Site filter searches.
 *
 * PHP version 5
 *
 * @category AddSiteFilterSearch
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Modifies Site filter searches.
 *
 * @category AddSiteFilterSearch
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteFilterSearch extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteFilterSearch';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add search filters by site';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook works with.
     *
     * @var string
     */
    public $node = 'site';
    /**
     * Initializes object
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'HOST_DATA',
            [$this, 'hostData']
        )->register(
            'GROUP_DATA',
            [$this, 'groupData']
        );
    }
    /**
     * This function modifies the data of the host page.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        global $node;
        global $sub;
        if ($sub == 'pending') {
            return;
        }
        if (!in_array('accesscontrol', (array)self::$pluginsinstalled)) {
            $isLocation = false;
        } else {
            $isLocation = true;
        }
        if (!$this->isRestricted(self::$FOGUser->get('id'))) {
            return;
        }
        $siteIDbyUser = $this->getSiteIDbyUser(self::$FOGUser->get('id'));
        $siteHosts = $this->getHostIDbySite($siteIDbyUser);
    }

    /**
     * Groups data.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function groupData($arguments)
    {
        global $node;
        global $sub;
        if ($node != 'group') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }

        if (!$this->isRestricted(self::$FOGUser->get('id'))) {
            return;
        }
        $siteIDbyUser = $this->getSiteIDbyUser(self::$FOGUser->get('id'));
        $siteGroups = $this->getGroupIDbySite($siteIDbyUser);
    }

    /**
     * Return if the user have search restrictions by site.
     *
     * @param int $userid The id to check.
     *
     * @return bool.
     */
    public function isRestricted($userid)
    {
        $userRestrictions = self::getSubObjectIDs(
            'SiteUserRestriction',
            ['userID' => $userid],
            'isRestricted'
        );
        return $userRestrictions[0];
    }
    /**
     * Get site IDs where the user is associated.
     *
     * @param int $userID Gets the user id.
     *
     * @return array
     */
    public function getSiteIDbyUser($userID)
    {
        $find = ['userID' => $userID];
        return self::getSubObjectIDs(
            'SiteUserAssociation',
            $find,
            'siteID'
        );
    }

    /**
     * Get host IDs of the sites where the user is associated
     *
     * @param mixed $siteIDs The site ids.
     *
     * @return array
     */
    public function getHostIDbySite($siteIDs)
    {
        $find = ['siteID' => $siteIDs];
        return self::getSubObjectIDs(
            'SiteHostAssociation',
            $find,
            'hostID'
        );
    }
    /**
     * Get the group IDs which have one or more hosts of the user locations.
     *
     * @param mixed $siteIDbyUser The site by user.
     *
     * @return array
     */
    public function getGroupIDbySite($siteIDbyUser)
    {
        $siteHosts = $this->getHostIDbySite($siteIDbyUser);
        return self::getSubObjectIDs(
            'GroupAssociation',
            ['hostID' => $siteHosts],
            'groupID'
        );
    }
}
