<?php
/**
 * Adds the location choice to host.
 *
 * PHP version 5
 *
 * @category AddLocationHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the location choice to host.
 *
 * @category AddLocationHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLocationHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Location to Hosts';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
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
                'HOST_HEADER_DATA',
                array(
                    $this,
                    'hostTableHeader'
                )
            )
            ->register(
                'HOST_DATA',
                array(
                    $this,
                    'hostData'
                )
            )
            ->register(
                'HOST_FIELDS',
                array(
                    $this,
                    'hostFields'
                )
            )
            ->register(
                'HOST_ADD_SUCCESS',
                array(
                    $this,
                    'hostAddLocation'
                )
            )
            ->register(
                'HOST_EDIT_SUCCESS',
                array(
                    $this,
                    'hostAddLocation'
                )
            )
            ->register(
                'HOST_REGISTER',
                array(
                    $this,
                    'hostRegister'
                )
            )
            ->register(
                'HOST_IMPORT',
                array(
                    $this,
                    'hostImport'
                )
            )
            ->register(
                'HOST_EXPORT_REPORT',
                array(
                    $this,
                    'hostExport'
                )
            )
            ->register(
                'DESTROY_HOST',
                array(
                    $this,
                    'hostDestroy'
                )
            )
            ->register(
                'EMAIL_ITEMS',
                array(
                    $this,
                    'hostEmailHook'
                )
            )
            ->register(
                'HOST_INFO_EXPOSE',
                array(
                    $this,
                    'hostInfoExpose'
                )
            );
    }
    /**
     * Adjusts the host header.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostTableHeader($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }
        $arguments['headerData'][4] = _('Location/Deployed');
    }
    /**
     * Adjusts the host data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostData($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }
        $arguments['templates'][4] = '${location}<br/><small>${deployed}</small>';
        foreach ((array)$arguments['data'] as $index => &$vals) {
            $find = array(
                'hostID' => $vals['id']
            );
            $Locations = self::getSubObjectIDs(
                'LocationAssociation',
                $find,
                'locationID'
            );
            $cnt = self::getClass('LocationManager')
                ->count(
                    array('id' => $Locations)
                );
            if ($cnt !== 1) {
                $arguments['data'][$index]['location'] = '';
                continue;
            }
            foreach ((array)self::getClass('LocationManager')
                ->find(array('id' => $Locations)) as &$Location
            ) {
                $arguments['data'][$index]['location'] = $Location
                    ->get('name');
                unset($Location);
            }
            unset($vals);
            unset($Locations);
        }
    }
    /**
     * Adjusts the host fields.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostFields($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'locationID'
        );
        $cnt = self::getClass('LocationManager')->count(
            array(
                'id' => $Locations
            )
        );
        if ($cnt !== 1) {
            $locID = 0;
        } else {
            $Locations = self::getSubObjectIDs(
                'Location',
                array('id' => $Locations)
            );
            $locID = array_shift($Locations);
        }
        self::arrayInsertAfter(
            '<label for="productKey">'
            . _('Host Product Key')
            . '</label>',
            $arguments['fields'],
            '<label for="location">'
            . _('Host Location')
            . '</label>',
            self::getClass('LocationManager')->buildSelectBox(
                $locID
            )
        );
    }
    /**
     * Adds the location selector to the host.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddLocation($arguments)
    {
        global $node;
        global $sub;
        global $tab;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $subs = array(
            'add',
            'edit',
            'addPost',
            'editPost'
        );
        if ($node != 'host') {
            return;
        }
        if (!in_array($sub, $subs)) {
            return;
        }
        if (str_replace('_', '-', $tab) != 'host-general' && $sub != 'add') {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        $location = (int)filter_input(INPUT_POST, 'location');
        if ($location) {
            $insert_fields = array(
                'locationID',
                'hostID'
            );
            $insert_values = array();
            $insert_values[] = array(
                $location,
                $arguments['Host']->get('id')
            );
            if (count($insert_values)) {
                self::getClass('LocationAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }
    }
    /**
     * Adds the location to import.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostImport($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->load('hostID')
            ->set('locationID', $arguments['data'][5])
            ->save();
    }
    /**
     * Adds the location to export.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostExport($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $find = array(
            'hostID' => $arguments['Host']->id
        );
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            $find,
            'locationID'
        );
        $cnt = self::getClass('LocationManager')->count(
            array('id' => $Locations)
        );
        if ($cnt !== 1) {
            $arguments['report']->addCSVCell('');
            return;
        }
        Route::listem(
            'location',
            'name',
            false,
            array('id' => $Locations)
        );
        $Locations = json_decode(
            Route::getData()
        );
        $Locations = $Locations->locations;
        foreach ((array)$Locations as &$Location) {
            $arguments['report']->addCSVCell(
                $Location->id
            );
            unset($Location);
        }
        unset($Locations);
    }
    /**
     * Removes location when host is destroyed.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostDestroy($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
    }
    /**
     * Adds the location to host email stuff.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostEmailHook($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'locationID'
        );
        $cnt = self::getClass('LocationManager')
            ->count(array('id' => $Locations));
        if ($cnt !== 1) {
            $locName = '';
        } else {
            foreach ((array)self::getClass('LocationManager')
                ->find(array('id' => $Locations)) as $Location
            ) {
                $locName = $Location->get('name');
                unset($Location);
                break;
            }
        }
        self::arrayInsertAfter(
            "\nSnapin Used: ",
            $arguments['email'],
            "\nImaged From (Location): ",
            $locName
        );
        self::arrayInsertAfter(
            "\nImaged From (Location): ",
            $arguments['email'],
            "\nImagingLocation=",
            $locName
        );
    }
    /**
     * Adds lcoation to host register.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostRegister($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $cnt = self::getClass('LocationManager')
            ->count(array('id' => $_REQUEST['location']));
        if ($cnt !== 1) {
            return;
        }
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->set('locationID', $_REQUEST['location'])
            ->save();
        self::$HookManager
            ->processEvent(
                'HOST_REGISTER_LOCATION',
                array(
                    'Host' => $Host,
                    'Location' => &$_REQUEST['location']
                )
            );
    }
    /**
     * Exposes location during host info request.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostInfoExpose($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $Locations = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'locationID'
        );
        $cnt = self::getClass('LocationManager')
            ->count(array('id' => $Locations));
        if ($cnt !== 1) {
            $arguments['repFields']['location'] = '';
            return;
        }
        foreach ((array)self::getClass('LocationManager')
            ->find(array('id' => $Locations)) as &$Location
        ) {
            $arguments['repFields']['location'] = $Location
                ->get('name');
            unset($Location);
        }
    }
}
