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
     * THe node this hook enacts with.
     *
     * @var string
     */
    public $node = 'location';
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
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
            $Locations = self::getClass('LocationAssociationManager')->find(
                array(
                    'hostID' => $vals['id']
                )
            );
            if (count($Locations) < 1) {
                $arguments['data'][$index]['location'] = '';
            }
            foreach ((array)$Locations as &$Location) {
                if (!$Location->isValid()) {
                    continue;
                }
                $arguments['data'][$index]['location'] = $Location
                    ->getLocation()
                    ->get('name');
                unset($Location);
            }
            unset($vals);
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        $Locations = self::getClass('LocationAssociationManager')->find(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        $locID = 0;
        foreach ((array)$Locations as &$Location) {
            if (!$Location->isValid()) {
                continue;
            }
            $locID = $Location->getLocation()->get('id');
            unset($Location);
            break;
        }
        unset($Locations);
        $this->arrayInsertAfter(
            _('Host Product Key'),
            $arguments['fields'],
            _('Host Location'),
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        global $node;
        global $sub;
        global $tab;
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
        if (str_replace('_', '-', $tab) != 'host-general') {
            return;
        }
        if (!($_REQUEST['location']
            && is_numeric($_REQUEST['location'])
            && $_REQUEST['location'] > 0)
        ) {
            self::getClass('LocationAssociationManager')->destroy(
                array(
                    'hostID' => $arguments['Host']->get('id')
                )
            );
        }
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->load('hostID')
            ->set('locationID', $_REQUEST['location'])
            ->save();
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $Locations = self::getClass('LocationAssociationManager')->find(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        foreach ((array)$Locations as &$Location) {
            if (!$Location->isValid()) {
                continue;
            }
            $arguments['report']->addCSVCell(
                $Location->getLocation()->get('id')
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $Locations = self::getClass('LocationAssociationManager')->find(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        foreach ((array)$Locations as $Location) {
            if (!$Location->isValid()) {
                continue;
            }
            $locName = $Location->getLocation()->get('id');
            unset($Location);
            break;
        }
        $this->arrayInsertAfter(
            "\nSnapin Used: ",
            $arguments['email'],
            "\nImaged From (Location): ",
            $locName
        );
        $this->arrayInsertAfter(
            "\nImaged From (Location): ",
            $arguments['email'],
            "\nImageLocation=",
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $Locations = self::getClass('LocationAssociationManager')->find(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        foreach ((array)$Locations as &$Location) {
            if (!$Location->isValid()) {
                continue;
            }
            $arguments['repFields']['location'] = $Location
                ->getLocation()
                ->get('name');
            unset($Location);
        }
    }
}
$AddLocationHost = new AddLocationHost();
$HookManager
    ->register(
        'HOST_HEADER_DATA',
        array(
            $AddLocationHost,
            'hostTableHeader'
        )
    );
$HookManager
    ->register(
        'HOST_DATA',
        array(
            $AddLocationHost,
            'hostData'
        )
    );
$HookManager
    ->register(
        'HOST_FIELDS',
        array(
            $AddLocationHost,
            'hostFields'
        )
    );
$HookManager
    ->register(
        'HOST_ADD_SUCCESS',
        array(
            $AddLocationHost,
            'hostAddLocation'
        )
    );
$HookManager
    ->register(
        'HOST_EDIT_SUCCESS',
        array(
            $AddLocationHost,
            'hostAddLocation'
        )
    );
$HookManager
    ->register(
        'HOST_REGISTER',
        array(
            $AddLocationHost,
            'hostRegister'
        )
    );
$HookManager
    ->register(
        'HOST_IMPORT',
        array(
            $AddLocationHost,
            'hostImport'
        )
    );
$HookManager
    ->register(
        'HOST_EXPORT_REPORT',
        array(
            $AddLocationHost,
            'hostExport'
        )
    );
$HookManager
    ->register(
        'DESTROY_HOST',
        array(
            $AddLocationHost,
            'hostDestroy'
        )
    );
$HookManager
    ->register(
        'EMAIL_ITEMS',
        array(
            $AddLocationHost,
            'hostEmailHook'
        )
    );
$HookManager
    ->register(
        'HOST_INFO_EXPOSE',
        array(
            $AddLocationHost,
            'hostInfoExpose'
        )
    );
