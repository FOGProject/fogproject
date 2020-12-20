<?php
/**
 * Associate Hosts to a Site.
 *
 * PHP version 5
 *
 * @category AddSiteHost
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Associate Hosts to a Site.
 *
 * @category AddSiteHost
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Hosts to a Site';
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
    public $node = 'site';
    /**
     * Initializes object.
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
                    'hostAddSite'
                )
            )
            ->register(
                'HOST_EDIT_SUCCESS',
                array(
                    $this,
                    'hostAddSite'
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
                'HOST_INFO_EXPOSE',
                array(
                    $this,
                    'hostInfoExpose'
                )
            );
    }
    /**
     * This function modifies the header of the user page.
     * Add one column calls 'Associated Sites'
     *
     * @param mixed $arguments The arguments to modify.
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
        if (!in_array('accesscontrol', (array)self::$pluginsinstalled)) {
            $insertIndex = 4;
        } else {
            $insertIndex = 5;
        }
        foreach ((array)$arguments['headerData'] as $index => &$str) {
            if ($index == $insertIndex) {
                $arguments['headerData'][$index] = _('Associated Sites');
                $arguments['headerData'][] = $str;
            }
            unset($str);
        }
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
        if (!in_array('accesscontrol', (array)self::$pluginsinstalled)) {
            $insertIndex = 4;
        } else {
            $insertIndex = 5;
        }
        foreach ((array)$arguments['attributes'] as $index => &$str) {
            if ($index == $insertIndex) {
                $arguments['attributes'][$index] = array();
                $arguments['attributes'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['templates'] as $index => &$str) {
            if ($index == $insertIndex) {
                $arguments['templates'][$index] = '${site}';
                $arguments['templates'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['data'] as $index => &$vals) {
            $find = array(
                'hostID' => $vals['id']
            );
            $Sites = self::getSubObjectIDs(
                'SiteHostAssociation',
                $find,
                'siteID'
            );
            $cnt = count($Sites);
            if ($cnt !== 1) {
                $arguments['data'][$index]['site'] = '';
                continue;
            }
            $SiteNames = array_values(
                array_unique(
                    array_filter(
                        self::getSubObjectIDs(
                            'Site',
                            array('id' => $Sites),
                            'name'
                        )
                    )
                )
            );
            $arguments['data'][$index]['site'] = $SiteNames[0];
            unset($vals);
            unset($Sites, $SiteNames);
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
        $SiteIDs = self::getSubObjectIDs(
            'SiteHostAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'siteID'
        );
        $cnt = self::getClass('SiteManager')->count(
            array(
                'id' => $SiteIDs
            )
        );
        if ($cnt !== 1) {
            $sID = 0;
        } else {
            $Sites = self::getSubObjectIDs(
                'Site',
                array('id' => $SiteIDs)
            );
            $sID = array_shift($Sites);
        }
        $UserIsRestricted = self::getSubObjectIDs(
            'SiteUserRestriction',
            array('userID' => self::$FOGUser->get('id')),
            'isRestricted'
        )[0];
        if ($UserIsRestricted == 1) {
            $SitesFiltered = array_diff(
                self::getSubObjectIDs(
                    'Site',
                    '',
                    'id'
                ),
                self::getSubObjectIDs(
                    'SiteUserAssociation',
                    array('userID' => self::$FOGUser->get('id')),
                    'siteID'
                )
            );
        }
        self::arrayInsertAfter(
            '<label for="productKey">'
            . _('Host Product Key')
            . '</label>',
            $arguments['fields'],
            '<label for="site">'
            . _('Host Site')
            . '</label>',
            self::getClass('SiteManager')->buildSelectBox(
                $sID,
                '',
                'name',
                $SitesFiltered
            )
        );
    }
    /**
     * Adds the site selector to the host.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function hostAddSite($arguments)
    {
        global $node;
        global $sub;
        global $tab;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
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

        $mac = trim(filter_input(INPUT_POST, 'mac'));

        if (str_replace('_', '-', $tab) != 'host-general') {
            self::getClass('HostManager')->getHostByMacAddresses($mac);
            $hostID = self::$Host->get('id');
        } else {
            $hostID = $arguments['Host']->get('id');
        }
        self::getClass('SiteHostAssociationManager')->destroy(
            array(
                'hostID' => $hostID
            )
        );
        $site = (int)filter_input(INPUT_POST, 'site');
        if ($site) {
            $insert_fields = array(
                'siteID',
                'hostID'
            );
            $insert_values = array();
            $insert_values[] = array(
                $site,
                $hostID
            );
            if (count($insert_values)) {
                self::getClass('SiteHostAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }
    }
    /**
     * Adds the site to import.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @retrun void
     */
    public function hostImport($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if (!in_array('accesscontrol', (array)self::$pluginsinstalled)) {
            $insertIndex = 4;
        } else {
            $insertIndex = 5;
        }
        self::getClass('SiteHostAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->load('hostID')
            ->set('siteID', $arguments['data'][$insertIndex])
            ->save();
    }
    /**
     * Adds the site to export.
     *
     * @param mixed $arguments The arguments to change.
     *
     * return void
     */
    public function hostExport($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $find = array(
            'hostID' => $arguments['Host']->id
        );
        $Sites = self::getSubObjectIDs(
            'SiteHostAssociation',
            $find,
            'siteID'
        );
        $cnt = self::getClass('SiteHostAssociationManager')->count(
            array('id' => $Sites)
        );
        if ($cnt !== 1) {
            $arguments['report']->addCSVCell('');
            return;
        }
        Route::listem(
            'site',
            'name',
            false,
            array('id' => $Sites)
        );
        $Sites = json_decode(
            Route::getData()
        );
        $Sites = $Sites->sites;
        foreach ((array)$Sites as &$Site) {
            $arguments['report']->addCSVCell(
                $Site->id
            );
            unset($Site);
        }
        unset($Sites);
    }
    /**
     * Removes site when host is destroyed.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostDestroy($arguments)
    {
        if (!in_array($this-node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::getClass('SiteHostAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
    }
    /**
     * Adds the site to host email stuff.
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
        $Sites = self::getSubObjectIDs(
            'SiteHostAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'siteID'
        );
        $cnt = self::getClass('SiteManager')
            ->count(array('id' => $Sites));
        if ($cnt !== 1) {
            $siteName = '';
        } else {
            foreach ((array)self::getClass('SiteManager')
                ->find(array('id' => $Sites)) as $Site
            ) {
                $siteName = $Site->get('name');
                unset($Site);
                break;
            }
        }
        self::arrayInsertAfter(
            "\nSite Used: ",
            $arguments['email'],
            "\nImaged from (Site): ",
            $siteName
        );
        self::array_insertAfter(
            "\nSite Imaged From (Site): ",
            $arguments['email'],
            "\nSiteName=",
            $siteName
        );
    }
    /**
     * Exposes site during host info request.
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
        $Sites = self::getSubObjectIDs(
            'SiteHostAssociation',
            array(
                'hostID' => $arguments['Host']->get('id')
            ),
            'siteID'
        );
        $cnt = self::getClass('SiteManager')
            ->count(array('id' => $Sites));
        if ($cnt !== 1) {
            $arguments['repFields']['site'] = '';
            return;
        }
        foreach ((array)self::getClass('SiteManager')
            ->find(array('id' => $Sites)) as &$Site
        ) {
            $arguments['repFields']['site'] = $Site
                ->get('name');
            unset($Site);
        }
    }
}
