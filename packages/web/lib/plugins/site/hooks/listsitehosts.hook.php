<?php
/**
 * Filters the host/user/group/usergroup lists to the user's Sites
 *
 * PHP version 5
 *
 * @category List only objects related to the Site of this user
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Filters the host/user/group/usergroup lists to the user's Sites
 *
 * @category ListSiteHosts
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ListSiteHosts extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'ListSiteHosts';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Only show objects related to the site the user is in.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
     *
     * @var string
     */
    public $node = 'site';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['AJAX_DATA_DISPLAY_CHANGE', 'filterListSite'],
        ]);
    }
    /**
     * Restricts the host/user/group/usergroup list to objects within the
     * acting user's site scope. Implicit admins / global '*' holders are
     * left untouched (they see everything); a restricted user with no site
     * sees an empty list (strict deny-all), matching the single-object
     * boundary enforced by SiteScopeCheck.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function filterListSite($arguments)
    {
        global $node;
        // node => [association class, object-id field]
        $map = [
            'host'      => ['sitehostassociation', 'hostID'],
            'user'      => ['siteuserassociation', 'userID'],
            'group'     => ['sitegroupassociation', 'groupID'],
            'usergroup' => ['siteusergroupassociation', 'usergroupID'],
        ];
        if (!isset($map[$node])) {
            return;
        }
        // Unrestricted users bypass site scoping entirely.
        if (Authorization::isUnrestricted()) {
            return;
        }
        $userID = (int)self::$FOGUser->get('id');
        $sites = Site::userSiteIDs($userID);
        // A restricted user with no site sees nothing; otherwise keep the
        // objects linked to any of their sites. Fall back to a sentinel id
        // that matches nothing so the re-listed payload stays empty.
        $ids = [0];
        if (!empty($sites)) {
            $found = Route::getIds(
                $map[$node][0],
                ['siteID' => $sites],
                $map[$node][1]
            );
            if (!empty($found)) {
                $ids = $found;
            }
        }
        Route::listem(
            $arguments['childClass'],
            ['id' => $ids]
        );
        $arguments['data'] = Route::getData();
    }
}
