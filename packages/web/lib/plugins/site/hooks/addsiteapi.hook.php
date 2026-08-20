<?php
/**
 * Injects access control stuff into the api system.
 *
 * PHP version 7.4+
 *
 * @category AddSiteAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects access control stuff into the api system.
 *
 * @category AddSiteAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddSiteAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Site stuff into the api system.';
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
        self::$HookManager
            ->register(
                'API_VALID_CLASSES',
                array(
                    $this,
                    'injectAPIElements'
                )
            )
            ->register(
                'API_GETTER',
                array(
                    $this,
                    'adjustGetter'
                )
            )
            ->register(
                'API_INDIVDATA_MAPPING',
                array(
                    $this,
                    'adjustIndivInfoUpdate'
                )
            )
            ->register(
                'API_MASSDATA_MAPPING',
                array(
                    $this,
                    'adjustMassInfo'
                )
            )
            ->register(
                'API_SCOPE_IDS',
                array(
                    $this,
                    'scopeIDs'
                )
            )
            ->register(
                'API_SCOPE_WHERE',
                array(
                    $this,
                    'scopeWhere'
                )
            );
    }
    /**
     * This function injects site elements for
     * api access.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function injectAPIElements($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $arguments['validClasses'] = self::fastmerge(
            $arguments['validClasses'],
            array(
                'site',
                'sitehostassociation'
            )
        );
    }
    /**
     * This function changes the api data map as needed.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustIndivInfoUpdate($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        
        // is create or edit call
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
            $vars = json_decode(
                file_get_contents('php://input')
            );
            
            if (isset($vars->siteID)) {
                switch ($arguments['classname']) {
                    case 'host':
                        $this->addHostToUniqueSite($vars->siteID, $arguments['data']['id']);
                        break;
                    
                    case 'group':
                        $hostIDs = self::getSubObjectIDs(
                            'GroupAssociation',
                            array('groupID' => $arguments['data']['id']),
                            'hostID'
                        );
                        
                        foreach ($hostIDs as $id) {
                            $this->addHostToUniqueSite($vars->siteID, $id);
                        }
                        
                        break;
                }
            }
        }
        
        // add siteID to result object
        switch ($arguments['classname']) {
            case 'host':
                
                $ids = $this->getSubObjectIDs(
                    'SiteHostAssociation',
                    ['hostID' => $arguments['data']['id']],
                    'siteID'
                );

                $arguments['data']['siteID'] = isset($ids[0]) ? $ids[0] : null;
                break;
        }
    }
    /**
     * This function changes the api data map as needed.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustMassInfo($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        
        // add siteID to result object
        switch ($arguments['classname']) {
            case 'host':
                
                for ($i = 0; $i < $arguments['data']['count']; $i++) {
                    $ids = $this->getSubObjectIDs(
                        'SiteHostAssociation',
                        ['hostID' => $arguments['data']['hosts'][$i]['id']],
                        'siteID'
                    );

                    $arguments['data']['hosts'][$i]['siteID'] = isset($ids[0]) ? $ids[0] : null;
                }
                
                break;
        }
    }
    /**
     * Narrows an API read to the acting user's sites.
     *
     * Until this existed the plugin's boundary was a management-page
     * feature: the only filtering hook is AddSiteFilterSearch, registered
     * on HOST_DATA and GROUP_DATA, and both handlers switch on the global
     * $node/$sub the pages set. Nothing under api/ fires those events, so
     * a site-restricted user saw their site in the grid and every host on
     * the server through /fog/host/list -- on the same credentials, and
     * without an API token, because Route skips API auth entirely when a
     * management session is already valid.
     *
     * Sets $arguments['ids'] only when a boundary actually applies. Left
     * alone it stays null, which is the caller's "no narrowing" value; an
     * EMPTY array set here is a real answer meaning the user may see
     * nothing. See Site::scopedObjectIDs() for why those must not be
     * collapsed.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function scopeIDs($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        // No acting user means no boundary to apply -- the service daemons
        // and the status endpoints reach Route::ids()/names() with nobody
        // logged in, and narrowing those to a site would break imaging
        // rather than protect anything.
        if (!self::$FOGUser || !self::$FOGUser->isValid()) {
            return;
        }
        $scope = Site::scopedObjectIDs(
            $arguments['classname'],
            self::$FOGUser->get('id')
        );
        if (null === $scope) {
            return;
        }
        $arguments['ids'] = array_values(
            array_unique(
                array_map('intval', (array)$scope)
            )
        );
    }
    /**
     * The same boundary as scopeIDs(), as a SQL fragment.
     *
     * Answered in preference to the id list, and the reason is cost: scopeIDs()
     * reads every host the user may see into PHP on every request, which on a
     * server with thousands of them is the whole expense of the feature. A
     * fragment is one expression whatever the fleet size.
     *
     * Both handlers stay registered. Core tries this one and falls back to the
     * id list when nothing answers, so a third-party plugin that knows only
     * API_SCOPE_IDS keeps bounding reads exactly as it did.
     *
     * Sets $arguments['where'] only when a boundary applies. Left alone it
     * stays null, which is the caller's "nobody answered" value. Note this
     * tri-state is NOT the id list's: an empty string is read as silence, so
     * "you may see nothing" is the literal fragment '1=0'. See
     * Site::scopedObjectWhere().
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function scopeWhere($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        // No acting user means no boundary to apply -- the service daemons
        // and the status endpoints reach Route::ids()/names() with nobody
        // logged in, and narrowing those to a site would break imaging
        // rather than protect anything. Same guard as scopeIDs(), and it has
        // to be here too: this handler is consulted FIRST, so a boundary it
        // emitted would apply before the id list was ever asked.
        if (!self::$FOGUser || !self::$FOGUser->isValid()) {
            return;
        }
        $where = Site::scopedObjectWhere(
            $arguments['classname'],
            $arguments['idExpr'],
            self::$FOGUser->get('id')
        );
        if (null === $where) {
            return;
        }
        $arguments['where'] = $where;
    }
    /**
     * This function changes the getter to enact on this particular item.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function adjustGetter($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        switch ($arguments['classname']) {
            case 'sitehostassociation':
                $arguments['data'] = FOGCore::fastmerge(
                    $arguments['class']->get(),
                    array(
                        'site' => $arguments['class']->get('site')->get(),
                        'host' => $arguments['class']->get('host')->get()
                    )
                );
                break;
        }
    }
    
    /**
     * This function add site to a host, removing any other site association to host if exists
     *
     * @param int $siteID Site id to associate
     *
     * @param int $hostID Host id to associate
     *
     * @return void
     */
    public function addHostToUniqueSite($siteID, $hostID)
    {
        $ids = $this->getSubObjectIDs(
            'SiteHostAssociation',
            ['hostID' => $hostID],
            'id'
        );
        
        $count = count($ids);

        if ($count === 0) {
            $this->getClass('SiteHostAssociation')
                ->set('siteID', $siteID)
                ->set('hostID', $hostID)
                ->save();
        } else {
            for ($i = 1; $i < $count; $i++) {
                $this->getClass('SiteHostAssociation', $ids[$i])
                    ->destroy();
            }
            
            $this->getClass('SiteHostAssociation', $ids[0])
                ->set('siteID', $siteID)
                ->save();
        }
    }
}
