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
    public $name = 'AddSiteHost';
    public $description = 'Add Hosts to a Site';
    public $active = true;
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
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'addNotes'
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
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
     * This function modifies the data of the user page.
     * Add one column calls 'Associated Sites'
     *
     * @param mixed $arguments The arguments to modify.
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
     * This function adds a new column in the result table.
     *
     * @param mixed $arguments The arguments to modify.
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
            $sID = $Sites[0];
        }
        self::arrayInsertAfter(
            _('Host Product Key'),
            $arguments['fields'],
            _('Associate Host to a Site '),
            self::getClass('SiteManager')->buildSelectBox(
                $sID
            )
        );
    }
    /**
     * This function adds one entry in the siteUserAssoc table in the DB
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function hostAddSite($arguments)
    {
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
        self::getClass('SiteHostAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Host']->get('id')
            )
        );
        $cnt = self::getClass('SiteManager')
            ->count(
                array('id' => $_REQUEST['site'])
            );
        if ($cnt !== 1) {
            return;
        }
        $Site = new Site($_REQUEST['site']);
        self::getClass('SiteHostAssociation')
            ->set('hostID', $arguments['Host']->get('id'))
            ->load('hostID')
            ->set('siteID', $_REQUEST['site'])
            ->set(
                'name',
                sprintf(
                    '%s-%s',
                    $Site->get('name'),
                    $arguments['Host']->get('name')
                )
            )
            ->save();
    }
    /**
     * This function adds role to notes
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function addNotes($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
        global $tab;
        if ($node != 'host') {
            return;
        }
        if (count($arguments['notes']) < 1) {
            return;
        }
        $SiteIDs = self::getSubObjectIDs(
            'SiteHostAssociation',
            array(
                'hostID' => $arguments['object']->get('id')
            ),
            'siteID'
        );
        $cnt = count($SiteIDs);
        if ($cnt !== 1) {
            $sID = _('No Site');
        } else {
            $Sites = array_values(
                array_unique(
                    array_filter(
                        self::getSubObjectIDs(
                            'Site',
                            array('id' => $SiteIDs),
                            'name'
                        )
                    )
                )
            );
            $sID = $Sites[0];
        }
        $arguments['notes'][_('Site')] = $sID;
    }
}
