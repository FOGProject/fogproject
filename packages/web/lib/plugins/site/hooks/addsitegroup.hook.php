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
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add the hosts of a group to a Site';
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
                'GROUP_EDIT_EXTRA',
                array(
                    $this,
                    'groupFields'
                )
            )
            ->register(
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'groupSideMenu'
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
        $arguments['attributes'] = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $arguments['templates'] = array(
            '${field}',
            '${input}'
        );
        $fields = array(
            '<label for="site">'
            . _('Site')
            . '</label>' => self::getClass('SiteManager')->buildSelectBox(
                $siteID
            ),
            '<label for="updatesite">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" class="btn btn-info btn-block" '
            . 'id="group-edit">'
            . _('Update')
            . '</button>'
        );
        $arguments['data'] = array();
        foreach ((array)$fields as $field => &$input) {
            $arguments['data'][] = array(
                'field' => $field,
                'input' => $input
            );
            unset($input);
        }
        unset($fields);
        echo '<!-- Site -->';
        echo '<div id="group-site" class="tab-pane fade">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Site Association');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $arguments['formAction']
            . '&tab=group-site">';
        $arguments['render']->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
        $site = (int)filter_input(INPUT_POST, 'site');
        if ($site) {
            $insert_fields = array(
                'siteID',
                'hostID'
            );
            $insert_values = array();
            foreach ((array)$arguments['Group']->get('hosts') as &$hostID) {
                $insert_values[] = array(
                    $site,
                    $hostID
                );
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
