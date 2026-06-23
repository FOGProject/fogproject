<?php
/**
 * Lists only hosts related to the Sites of this user
 *
 * PHP version 5
 *
 * @category List only hosts related to the Site of this user
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects access control stuff into the api system.
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
    public $description = 'Only show hosts related to the site the user is in.';
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'AJAX_DATA_DISPLAY_CHANGE',
            [$this, 'filterHostsSite']
        );
    }
    /**
     * This adjusts our list hosts to only show those associated to the site
     * the user is defined to. If user isn't assigned to a host nothing happens.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function filterHostsSite($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $find = ['userID' => self::$FOGUser->get('id')];
        $sites = Route::getIds(
            'siteuserassociation',
            $find,
            'siteID'
        );
        if (!$sites) {
            return;
        }
        $find = ['siteID' => $sites];
        $hosts = Route::getIds(
            'sitehostassociation',
            $find,
            'hostID'
        );
        if (!$hosts) {
            return;
        }
        Route::listem(
            'host',
            ['id' => $hosts]
        );
        $arguments['data'] = Route::getData();
    }
}
