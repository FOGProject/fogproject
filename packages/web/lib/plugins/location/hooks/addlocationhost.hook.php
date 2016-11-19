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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        if ($_REQUEST['sub'] == 'pending') {
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        if ($_REQUEST['sub'] == 'pending') {
            return;
        }
        $arguments['templates'][4] = '${location}<br/><small>${deployed}</small>';
        foreach ((array)$arguments['data'] as $index => &$vals) {
            $locationID = self::getSubObjectIDs(
                'LocationAssociation',
                array(
                    'hostID' => $arguments['data'][$index]['id']
                ),
                'locationID'
            );
            $locID = array_shift($locationID);
            $arguments['data'][$index]['location'] = self::getClass(
                'Location',
                $locID
            )->get('name');
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($_REQUEST['node'] != 'host') {
            return;
        }
        $locationID = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'locationID'
        );
        $locID = array_shift($locationID);
        $this->arrayInsertAfter(
            _('Host Product Key'),
            $arguments['fields'],
            _('Host Location'),
            self::getClass('LocationManager')->buildSelectBox($locID)
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
        if (str_replace('_', '-', $_REQUEST['tab']) != 'host-general') {
            return;
        }
        self::getClass('LocationAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        self::getClass('LocationAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
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
        $locationID = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'locationID'
        );
        $locID = array_shift($locationID);
        $arguments['report']->addCSVCell($locID > 0 ? $locID : null);
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
        $locationID = self::getSubObjectIDs(
            'LocationAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'locationID'
        );
        $locID = array_shift($locationID);
        if (!self::getClass('Location', $locID)->isValid()) {
            return;
        }
        $this->arrayInsertAfter(
            "\nSnapin Used: ",
            $arguments['email'],
            "\nImaged From (Location): ",
            self::getClass('Location', $locID)->get('name')
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
        $locationID = self::getSubObjectIDs(
            'Location',
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        $locationID = @min($locationID);
        $arguments['repFields']['location'] = self::getClass(
            'Location',
            $locationID
        )->get('name');
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
