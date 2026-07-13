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
        $this->registerInstalled([
            ['API_VALID_CLASSES', 'injectAPIElements'],
            ['API_GETTER', 'adjustGetter'],
            ['API_PLUGIN_ITEMS', 'addPluginItems'],
            ['CUSTOMIZE_DT_COLUMNS', 'customizeDT'],
        ]);
    }
    /**
     * Customize our new columns.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function customizeDT($arguments)
    {
        if ($arguments['classname'] != $this->node) {
            return;
        }
        $arguments['columns'][] = [
            'db' => 'ngmMemberName',
            'dt' => 'storagenodename'
        ];
        $arguments['columns'][] = [
            'db' => 'ngName',
            'dt' => 'storagegroupname'
        ];
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
        array_push(
            $arguments['validClasses'],
            $this->node,
            'locationassociation'
        );
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
        switch ($arguments['classname']) {
            case 'location':
                $arguments['data'] = FOGCore::fastmerge(
                    $arguments['class']->get(),
                    [
                        'storagenode' => Route::getter(
                            'storagenode',
                            $arguments['class']->get('storagenode')
                        ),
                        'storagegroup' => Route::getter(
                            'storagegroup',
                            $arguments['class']->get('storagegroup')
                        )
                    ]
                );
                break;
            case 'locationassociation':
                $arguments['data'] = FOGCore::fastmerge(
                    $arguments['class']->get(),
                    [
                        'host' => Route::getter(
                            'host',
                            $arguments['class']->get('host')
                        ),
                        'location' => $arguments['class']
                        ->get('location')
                        ->get()
                    ]
                );
        }
    }
    /**
     * Injects the location association into another class' API output under
     * the namespaced `pluginItems` envelope (so core fields are never
     * clobbered). Bidirectional:
     *   host     -> pluginItems.location  (link, or full object when expanded)
     *   location -> pluginItems.hostCount (+ full hosts array when expanded)
     *
     * @param mixed $arguments The event arguments (data/pluginItems/class...).
     *
     * @return void
     */
    public function addPluginItems($arguments)
    {
        switch ($arguments['classname']) {
            case 'host':
                $hostID = (int)$arguments['class']->get('id');
                if ($hostID < 1) {
                    break;
                }
                $locIDs = Route::getIds(
                    'locationassociation',
                    ['hostID' => $hostID],
                    'locationID'
                );
                $locID = (int)current((array)$locIDs);
                if ($locID < 1) {
                    break;
                }
                $location = Route::getClass('location', $locID);
                if (!$location->isValid()) {
                    break;
                }
                if (Route::wantsExpand('location')) {
                    $arguments['pluginItems']['location'] = Route::getter(
                        'location',
                        $location
                    );
                    break;
                }
                $arguments['pluginItems']['location'] = [
                    'id' => $locID,
                    'name' => $location->get('name'),
                    'link' => '../management/index.php?node=location'
                        . '&sub=edit&id=' . $locID,
                ];
                break;
            case 'location':
                $locID = (int)$arguments['class']->get('id');
                if ($locID < 1) {
                    break;
                }
                $hostIDs = Route::positiveIntIds(
                    (array)Route::getIds(
                        'locationassociation',
                        ['locationID' => $locID],
                        'hostID'
                    )
                );
                $arguments['pluginItems']['hostCount'] = count($hostIDs);
                if (!Route::wantsExpand('hosts')) {
                    break;
                }
                $truncated = false;
                if (count($hostIDs) > Route::EXPAND_MAX_ITEMS) {
                    $hostIDs = array_slice($hostIDs, 0, Route::EXPAND_MAX_ITEMS);
                    $truncated = true;
                }
                $hosts = [];
                foreach ($hostIDs as $hid) {
                    $host = Route::getClass('host', $hid);
                    if (!$host->isValid()) {
                        continue;
                    }
                    $g = Route::getter('host', $host);
                    if (is_array($g)) {
                        $hosts[] = Route::stripSensitive('host', $g);
                    }
                }
                $arguments['pluginItems']['hosts'] = $hosts;
                $arguments['pluginItems']['hosts_truncated'] = $truncated;
                break;
        }
    }
}
