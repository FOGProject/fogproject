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
        self::$HookManager
            ->register(
                'HOST_DATA',
                [$this, 'hostData']
            )
            ->register(
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
        if (empty($siteIDbyUser)) {
            $arguments['data']=[];
            return;
        }
        $siteHosts = $this->getHostIDbySite($siteIDbyUser);
        switch ($node) {
        case 'host':
            switch ($sub) {
            case 'search':
                $hostsID = self::getClass('HostManager')->search('');
                $hosts = self::getSubObjectIDs(
                    'SiteHostAssociation',
                    ['hostID' => $hostsID,'siteID'=>$siteIDbyUser],
                    'hostID'
                );
                break;
            case 'list':
                $hosts = self::getSubObjectIDs(
                    'Host',
                    ['id'=>$siteHosts],
                    'id'
                );
                break;
            }
            $arguments['data'] = [];
            foreach ($hosts as $HostID) {
                $HostSiteID = self::getSubObjectIDs(
                    'SiteHostAssociation',
                    ['hostID' => $HostID],
                    'siteID'
                );
                if ($isLocation) {
                    $locationID = self::getSubObjectIDs(
                        'LocationAssociation',
                        ['hostID' => $HostID],
                        'locationID'
                    );
                    $Location = new Location($locationID);
                    $locationName = $Location->get('name');
                } else {
                    $locationName = '';
                }
                $Site = self::getClass('SiteManager')->find(
                    ['id'=>$HostSiteID]
                );
                $Host = new Host($HostID);
                $arguments['data'][] = [
                    'id' => $Host->get('id'),
                    'deployed' => self::formatTime(
                        $Host->get('deployed'),
                        'Y-m-d H:i:s'
                    ),
                    'host_name' => $Host->get('name'),
                    'host_mac' => $Host->get('mac')->__toString(),
                    'host_desc' => $Host->get('description'),
                    'site' => $Site[0]->get('name'),
                    'location' => $locationName,
                    'image_id' => $Host->get('imageID'),
                    'image_name' => $Host->getImageName(),
                    'pingstatus' => $Host->getPingCodeStr(),
                ];
                unset($Host, $HostID);
                unset($HostSiteID, $Site);
            }
            break;
        case 'task':
            foreach ((array)$arguments['data'] as $index => &$data) {
                if (!in_array($data['host_id'], $siteHosts)) {
                    unset($arguments['data'][$index]);
                }
                unset($data);
            }
            break;
        default:
            return ;
        }
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
        if (empty($siteIDbyUser)) {
            $arguments['data']=[];
            return;
        }
        $siteGroups = $this->getGroupIDbySite($siteIDbyUser);
        switch ($sub) {
        case 'search':
            $groups = self::getClass('GroupManager')->search('', true);
            break;
        case 'list':
            $groups = self::getClass('GroupManager')->find(['id'=>$siteGroups]);
            break;
        }
        $arguments['data'] = [];
        foreach ($groups as $Group) {
            if (in_array($Group->get('id'), $siteGroups)) {
                $arguments['data'][] = [
                    'id' => $Group->get('id'),
                    'name' => $Group->get('name'),
                    'description' => $Group->get('description'),
                    'count' => $Group->getHostCount(),
                ];
            }
            unset($Group);
        }
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
