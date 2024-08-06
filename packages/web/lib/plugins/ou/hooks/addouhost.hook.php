<?php
/**
 * Adds the ou choice to host.
 *
 * PHP version 5
 *
 * @category AddOUHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the ou choice to host.
 *
 * @category AddOUHost
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddOUHost extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddOUHost';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add OU to Hosts';
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
    public $node = 'ou';
    /**
     * Initialize object.
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
            [$this, 'hostAddOUEdit']
        )->register(
            'HOST_ADD_FIELDS',
            [$this, 'hostAddOUField']
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
            'name' => _('OU Association'),
            'id' => 'host-ou',
            'generator' => function () use ($obj) {
                $this->hostOU($obj);
            }
        ];
    }
    /**
     * The host ou display
     *
     * @param object $obj The host object we're working with.
     *
     * @return void
     */
    public function hostOU($obj)
    {
        Route::listem('ouassociation');
        $items = json_decode(
            Route::getData()
        );
        $ou = 0;
        foreach ((array)$items->data as &$item) {
            if ($item->hostID == $obj->get('id')) {
                $ou = $item->ouID;
                unset($item);
                break;
            }
            unset($item);
        }
        $ouID = (
            (int)filter_input(INPUT_POST, 'ou') ?:
            $ou
        );
        $ouSelector = self::getClass('OUManager')
            ->buildSelectBox($ouID, 'ou');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'ou',
                _('Host OU')
            ) => $ouSelector
        ];

        $buttons = FOGPage::makeButton(
            'ou-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'HOST_OU_FIELDS',
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
            'host-ou-form',
            FOGPage::makeTabUpdateURL(
                'host-ou',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('OU');
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
     * The ou updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function hostOUPost($obj)
    {
        $ouID = trim(
            (int)filter_input(INPUT_POST, 'ou')
        );
        $insert_fields = ['hostID', 'ouID'];
        $insert_values = [];
        $hosts = [$obj->get('id')];
        if (count($hosts ?: [])) {
            Route::deletemass(
                'ouassociation',
                ['hostID' => $hosts]
            );
            foreach ((array)$hosts as $ind => &$hostID) {
                $insert_values[] = [$hostID, $ouID];
                unset($hostID);
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('OUAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The host ou selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddOUEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'host') {
            return;
        }
        $obj = $arguments['Host'];
        try {
            switch ($tab) {
                case 'host-ou':
                    $this->hostOUPost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'HOST_EDIT_OU_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Host OU Updated!'),
                    'title' => _('Host OU Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'HOST_EDIT_OU_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Update OU Fail')
                ]
            );
        }
    }
    /**
     * The host ou field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddOUField($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $ouID = (int)filter_input(INPUT_POST, 'ou');
        $ouSelector = self::getClass('OUManager')
            ->buildSelectBox($ouID, 'ou');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'ou',
                _('Host OU')
            )
        ] = $ouSelector;
    }
}
