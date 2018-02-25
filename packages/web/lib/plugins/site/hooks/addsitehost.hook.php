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
     * For posterity.
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
                'TABDATA_HOOK',
                array(
                    $this,
                    'hostTabData'
                )
            )
            ->register(
                'HOST_EDIT_SUCCESS',
                array(
                    $this,
                    'hostAddSiteEdit'
                )
            )
            ->register(
                'HOST_ADD_FIELDS',
                array(
                    $this,
                    'hostAddSiteField'
                )
            );
    }
    /**
     * The host tab data.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostTabData($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['tabData'][] = [
            'name' => _('Site Association'),
            'id' => 'host-site',
            'generator' => function() use ($obj) {
                $this->hostSite($obj);
            }
        ];
    }
    /**
     * The host site display
     *
     * @param object $obj The host object we're working with.
     *
     * @return void
     */
    public function hostSite($obj)
    {
        $siteID = (int)filter_input(
            INPUT_POST,
            'site'
        );
        // Host Sites
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');
        $fields = [
            '<label for="site" class="col-sm-2 control-label">'
            . _('Host Site')
            . '</label>' => &$siteSelector
        ];
        self::$HookManager
            ->processEvent(
                'HOST_SITE_FIELDS',
                [
                    'fields' => &$fields,
                    'Host' => &$obj
                ]
            );
        $rendered = FOGPage::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo '<form id="host-site-form" class="form-horizontal" method="post" action="'
            . FOGPage::makeTabUpdateURL('host-site', $obj->get('id'))
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
    public function hostSitePost($obj)
    {
        $siteID = trim(
            (int)filter_input(
                INPUT_POST,
                'site'
            )
        );
        $Site = new Site($siteID);
        if (!$Site->isValid() && is_numeric($siteID)) {
            throw new Exception(_('Select a valid site'));
        }
        $insert_fields = ['hostID', 'siteID'];
        $insert_values = [];
        $hosts = [$obj->get('id')];
        if (count($hosts) > 0) {
            self::getClass('SiteHostAssociationManager')->destroy(
                ['hostID' => $hosts]
            );
            foreach ((array)$hosts as $ind => &$hostID) {
                $insert_values[] = [$hostID, $siteID];
                unset($hostID);
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
     * The host site selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddSiteEdit($arguments)
    {
        global $tab;
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        $obj = $arguments['obj'];
        try{
            switch ($tab) {
            case 'host-site':
                $this->hostSitePost($obj);
                break;
            }
            $arguments['code'] = 201;
            $arguments['hook'] = 'HOST_EDIT_SITE_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Host Site Updated!'),
                    'title' => _('Host Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = 400;
            $arguments['hook'] = 'HOST_EDIT_SITE_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Update Site Fail')
                ]
            );
        }
    }
    /**
     * The host site field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddSiteField($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'host') {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $arguments['fields'][
            '<label for="site" class="col-sm-2 control-label">'
            . _('Host Site')
            . '</label>'] = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');
    }
}
