<?php
/**
 * Associate host of a group to a Site.
 *
 * PHP version 7
 *
 * @category AddSiteGroup
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Associate host of a group to a Site.
 *
 * @category AddSiteGroup
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteGroup extends Hook
{
    public $name = 'AddSiteGroup';
    public $description = 'Add the hosts of a group to a Site';
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
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'groupSideMenu'
                )
            )
            ->register(
                'GROUP_GENERAL_EXTRA',
                array(
                    $this,
                    'groupFields'
                )
            )
            ->register(
                'GROUP_EDIT_SUCCESS',
                array(
                    $this,
                    'groupAddSite'
                )
            );
    }
    /**
     * This function add a side menu entry on the group page.
     * Add one entry calls 'Site association'
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function groupSideMenu($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        $link = $arguments['linkformat'];
        $this->arrayInsertAfter(
            "$link#group-image",
            $arguments['submenu'],
            "$link#group-site",
            _('Site Association')
        );
    }
    /**
     * Group fields.
     *
     * @param mixed $arguments THe arguments to modify.
     *
     * @return void
     */
    public function groupFields($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        if ($node != 'group') {
            return;
        }
        $restricted = self::getClass('AddSiteFilterSearch')
            ->isRestricted(
                self::$FOGUser->get('id')
            );
        if (!$restricted) {
            $siteID = self::getClass('AddSiteFilterSearch')
                ->getSiteIDbyUser(self::$FOGUser->get('id'));
        } else {
            $siteID = '';
        }
        echo '<!-- Site --><div id="group-site">';
        printf(
            '<h2>%s: %s</h2>',
            _('Site Association for'),
            $arguments['Group']->get('name')
        );
        printf(
            '<form method="post" action="%s&tab=group-site">',
            $arguments['formAction']
        );
        unset($arguments['headerData']);
        $arguments['attributes'] = array(
            array(),
            array(),
        );
        $arguments['templates'] = array(
            '${field}',
            '${input}',
        );
        $arguments['data'][] = array(
            'field' => self::getClass('SiteManager')->buildSelectBox($siteID),
            'input' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Update Sites')
            )
        );
        $arguments['render']->render();
        echo '</form></div>';
    }
    /**
     * Group add site.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function groupAddSite($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $tab;
        if ($node != 'group') {
            return;
        }
        if ($tab != 'group-site') {
            return;
        }
        self::getClass('SiteHostAssociationManager')->destroy(
            array(
                'hostID' => $arguments['Group']->get('hosts')
            )
        );
        if ($_REQUEST['site']
            && is_numeric($_REQUEST['site'])
            && $_REQUEST['site'] > 0
        ) {
            $insert_fields = array('siteID','hostID');
            $insert_values = array();
            foreach ((array)$arguments['Group']->get('hosts') as &$hostID) {
                $insert_values[] = array($_REQUEST['site'], $hostID);
                unset($hostID);
            }
            if (count($insert_values) > 0) {
                self::getClass('SiteHostAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }
    }
}
