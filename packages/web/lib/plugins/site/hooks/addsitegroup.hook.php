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
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The plugin this hook works on.
     *
     * @return void
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
                'TABDATA_HOOK',
                array(
                    $this,
                    'groupTabData'
                )
            )
            ->register(
                'GROUP_EDIT_SUCCESS',
                array(
                    $this,
                    'groupAddSiteEdit'
                )
            )
            ->register(
                'GROUP_ADD_FIELDS',
                array(
                    $this,
                    'groupAddSiteField'
                )
            );
    }
    /**
     * The group tab data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupTabData($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['tabData'][] = [
            'name' => _('Site Association'),
            'id' => 'group-site',
            'generator' => function() use ($obj) {
                $this->groupSite($obj);
            }
        ];
    }
    /**
     * The group site display
     *
     * @param object $obj The group object we're working with.
     *
     * @return void
     */
    public function groupSite($obj)
    {
        $siteID = (int)filter_input(
            INPUT_POST,
            'site'
        );
        // Group sites
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');
        $fields = [
            '<label for="site" class="col-sm-2 control-label">'
            . _('Group Site')
            . '</label>' => &$siteSelector
        ];
        self::$HookManager
            ->processEvent(
                'GROUP_SITE_FIELDS',
                [
                    'fields' => &$fields,
                    'Group' => &$obj
                ]
            );
        $rendered = FOGPage::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo '<form id="group-site-form" class="form-horizontal" method="post" action="'
            . FOGPage::makeTabUpdateURL('group-site', $obj->get('id'))
            . '" novalidate>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="site-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * The site updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function groupSitePost($obj)
    {
        $siteID = trim(
            (int)filter_input(
                INPUT_POST,
                'site'
            )
        );
        $Site = new Site($siteID);
        $insert_fields = ['hostID', 'siteID'];
        $insert_values = [];
        $hosts = $obj->get('hosts');
        if (count($hosts) > 0) {
            self::getClass('SiteHostAssociationManager')->destroy(
                ['hostID' => $hosts]
            );
            if ($siteID > 0) {
                foreach ((array)$hosts as $ind => &$hostID) {
                    $insert_values[] = [$hostID, $siteID];
                    unset($hostID);
                }
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('SiteHostAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The group site selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddSiteEdit($arguments)
    {
        global $tab;
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['Group'];
        try {
            switch ($tab) {
            case 'group-site':
                $this->groupSitePost($obj);
                break;
            }
            $arguments['code'] = 201;
            $argumetns['hook'] = 'GROUP_EDIT_SITE_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Group Site Updated!'),
                    'title' => _('Group Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = 400;
            $arguments['hook'] = 'GROUP_EDIT_SITE_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Update Site Fail')
                ]
            );
        }
    }
    /**
     * The group site field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddSiteField($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $arguments['fields'][
            '<label for="site" class="col-sm-2 control-label">'
            . _('Group Site')
            . '</label>'] = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');
    }
}
