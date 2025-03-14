<?php
/**
 * Injects access control stuff into the api system.
 *
 * PHP version 5
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
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT']))
        {
            $vars = json_decode(
                file_get_contents('php://input')
            );
            
            if (isset($vars->siteID))
            {
                switch ($arguments['classname'])
                {
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
        switch ($arguments['classname'])
        {
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
        switch ($arguments['classname'])
        {
            case 'host':
                
                for ($i = 0; $i < $arguments['data']['count']; $i++)
                {
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

        if ($count === 0)
        {
            $this->getClass('SiteHostAssociation')
                ->set('siteID', $siteID)
                ->set('hostID', $hostID)
                ->save();
        }
        else
        {
            for ($i = 1; $i < $count; $i++)
            {
                $this->getClass('SiteHostAssociation', $ids[$i])
                    ->destroy();
            }
            
            $this->getClass('SiteHostAssociation', $ids[0])
                ->set('siteID', $siteID)
                ->save();
        }
    }
}
