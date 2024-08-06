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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'PLUGINS_INJECT_TABDATA',
            [$this, 'hostTabData']
        )->register(
            'HOST_EDIT_SUCCESS',
            [$this, 'hostAddSiteEdit']
        )->register(
            'HOST_ADD_FIELDS',
            [$this, 'hostAddSiteField']
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
        if ($node != 'host') {
            return;
        }
        $obj = $arguments['obj'];
        $arguments['pluginsTabData'][] = [
            'name' => _('Site Association'),
            'id' => 'host-site',
            'generator' => function () use ($obj) {
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
        Route::listem('sitehostassociation');
        $items = json_decode(
            Route::getData()
        );
        $site = 0;
        foreach ((array)$items->data as &$item) {
            if ($item->hostID == $obj->get('id')) {
                $site = $item->siteID;
                unset($item);
                break;
            }
            unset($item);
        }
        $siteID = (
            (int)filter_input(INPUT_POST, 'site') ?:
            $site
        );
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'site',
                _('Host Site')
            ) => $siteSelector
        ];

        $buttons = FOGPage::makeButton(
            'site-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'HOST_SITE_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'Host' => &$obj
            ]
        );
        $rendered = FOGPage::formFields($fields);
        unset($fields);

        echo FOGPage::makeFormTag(
            'form-horizontal',
            'host-site-form',
            FOGPage::makeTabUpdateURL(
                'host-site',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-url-encoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Site');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
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
            (int)filter_input(INPUT_POST, 'site')
        );
        $insert_fields = ['hostID', 'siteID'];
        $insert_values = [];
        $hosts = [$obj->get('id')];
        if (count($hosts ?: [])) {
            Route::deletemass(
                'sitehostassociation',
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
        if ($node != 'host') {
            return;
        }
        $obj = $arguments['Host'];
        try {
            switch ($tab) {
                case 'host-site':
                    $this->hostSitePost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'HOST_EDIT_SITE_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Host Site Updated!'),
                    'title' => _('Host Site Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
        if ($node != 'host') {
            return;
        }
        $siteID = (int)filter_input(INPUT_POST, 'site');
        $siteSelector = self::getClass('SiteManager')
            ->buildSelectBox($siteID, 'site');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'site',
                _('Host Site')
            )
        ] = $siteSelector;
    }
}
