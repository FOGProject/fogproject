<?php
/**
 * Injects location stuff into the api system.
 *
 * PHP version 5
 *
 * @category AddLocationAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Injects location stuff into the api system.
 *
 * @category AddLocationAPI
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationAPI extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'AddLocationAPI';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Add Location stuff into the api system.';
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
    public $node = 'location';
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
     * This function injects location elements for
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
                'location',
                'locationassociation'
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
            
            if (isset($vars->locationID))
            {
                switch ($arguments['classname'])
                {
                    case 'host':
                        $this->addHostToUniqueLocation($vars->locationID, $arguments['data']['id']);                        
                        break;
                    
                    case 'group':
                        $hostIDs = self::getSubObjectIDs(
                            'GroupAssociation',
                            array('groupID' => $arguments['data']['id']),
                            'hostID'
                        );
                        
                        foreach ($hostIDs as $id) {
                            $this->addHostToUniqueLocation($vars->locationID, $id);
                        }
                        
                        break;
                }
            }
        }
        
        // add locationID to result object
        switch ($arguments['classname'])
        {
            case 'host':
                
                $ids = $this->getSubObjectIDs(
                    'LocationAssociation', 
                    ['hostID' => $arguments['data']['id']],
                    'locationID'
                );

                $arguments['data']['locationID'] = isset($ids[0]) ? $ids[0] : null;
                
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
        
        // add locationID to result objects
        switch ($arguments['classname'])
        {
            case 'host':
                
                for ($i = 0; $i < $arguments['data']['count']; $i++)
                {
                    $ids = $this->getSubObjectIDs(
                        'LocationAssociation', 
                        ['hostID' => $arguments['data']['hosts'][$i]['id']],
                        'locationID'
                    );

                    $arguments['data']['hosts'][$i]['locationID'] = isset($ids[0]) ? $ids[0] : null;
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
        case 'location':
            $arguments['data'] = FOGCore::fastmerge(
                $arguments['class']->get(),
                array(
                    'storagenode' => $arguments['class']
                    ->get('storagenode')
                    ->get(),
                    'storagegroup' => $arguments['class']
                    ->get('storagegroup')
                    ->get()
                )
            );
            break;
        case 'locationassociation':
            $arguments['data'] = FOGCore::fastmerge(
                $arguments['class']->get(),
                array(
                    'host' => Route::getter(
                        'host',
                        $arguments['class']->get('host')
                    ),
                    'location' => $arguments['class']
                    ->get('location')
                    ->get()
                )
            );
            break;
        }
    }
    
    /**
     * This function add location to a host, removing any other location association to host if exists
     * 
     * @param int $locationID Location id to associate
     * 
     * @param int $hostID Host id to associate
     * 
     * @return void
     */
    public function addHostToUniqueLocation($locationID, $hostID)
    {
        $ids = $this->getSubObjectIDs(
            'LocationAssociation', 
            ['hostID' => $hostID],
            'id'
        );
        
        $count = count($ids);

        if ($count === 0)
        {
            $this->getClass('LocationAssociation')
                ->set('locationID', $locationID)
                ->set('hostID', $hostID)
                ->save();
        }
        else
        {
            for ($i = 1; $i < $count; $i++)
            {
                $this->getClass('LocationAssociation', $ids[$i])
                    ->destroy();
            }
            
            $this->getClass('LocationAssociation', $ids[0])
                ->set('locationID', $locationID)
                ->save();
        }
    }
}