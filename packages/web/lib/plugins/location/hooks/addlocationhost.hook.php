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
            [$this, 'hostAddLocationEdit']
        )->register(
            'HOST_ADD_FIELDS',
            [$this, 'hostAddLocationField']
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
            'name' => _('Location Association'),
            'id' => 'host-location',
            'generator' => function () use ($obj) {
                $this->hostLocation($obj);
            }
        ];
    }
    /**
     * The host location display
     *
     * @param object $obj The host object we're working with.
     *
     * @return void
     */
    public function hostLocation($obj)
    {
        Route::listem('locationassociation');
        $items = json_decode(
            Route::getData()
        );
        $location = 0;
        foreach ((array)$items->data as &$item) {
            if ($item->hostID == $obj->get('id')) {
                $location = $item->locationID;
                unset($item);
                break;
            }
            unset($item);
        }
        $locationID = (
            (int)filter_input(INPUT_POST, 'location') ?:
            $location
        );
        $locationSelector = self::getClass('LocationManager')
            ->buildSelectBox($locationID, 'location');

        $fields = [
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'location',
                _('Host Location')
            ) => $locationSelector
        ];

        $buttons = FOGPage::makeButton(
            'location-send',
            _('Update'),
            'btn btn-primary pull-right'
        );

        self::$HookManager->processEvent(
            'HOST_LOCATION_FIELDS',
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
            'host-location-form',
            FOGPage::makeTabUpdateURL(
                'host-location',
                $obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Location');
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
     * The location updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function hostLocationPost($obj)
    {
        $locationID = trim(
            (int)filter_input(INPUT_POST, 'location')
        );
        $insert_fields = ['hostID', 'locationID'];
        $insert_values = [];
        $hosts = [$obj->get('id')];
        if (count($hosts ?: [])) {
            Route::deletemass(
                'locationassociation',
                ['hostID' => $hosts]
            );
            foreach ((array)$hosts as $ind => &$hostID) {
                $insert_values[] = [$hostID, $locationID];
                unset($hostID);
            }
        }
        if (count($insert_values) > 0) {
            self::getClass('LocationAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }
    }
    /**
     * The host location selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddLocationEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'host') {
            return;
        }
        $obj = $arguments['Host'];
        try {
            switch ($tab) {
                case 'host-location':
                    $this->hostLocationPost($obj);
                    break;
                default:
                    return;
            }
            $arguments['code'] = HTTPResponseCodes::HTTP_ACCEPTED;
            $arguments['hook'] = 'HOST_EDIT_LOCATION_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Host Location Updated!'),
                    'title' => _('Host Location Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = (
                $arguments['serverFault'] ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $arguments['hook'] = 'HOST_EDIT_LOCATION_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Host Update Location Fail')
                ]
            );
        }
    }
    /**
     * The host location field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function hostAddLocationField($arguments)
    {
        global $node;
        if ($node != 'host') {
            return;
        }
        $locationID = (int)filter_input(INPUT_POST, 'location');
        $locationSelector = self::getClass('LocationManager')
            ->buildSelectBox($locationID, 'location');

        $arguments['fields'][
            FOGPage::makeLabel(
                'col-sm-3 control-label',
                'location',
                _('Host Location')
            )
        ] = $locationSelector;
    }
}
