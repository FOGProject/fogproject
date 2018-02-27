<?php
/**
 * Adds the location choice to groups.
 *
 * PHP version 5
 *
 * @category AddLocationGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the location choice to groups.
 *
 * @category AddLocationGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationGroup extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLocationGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add Location to Groups';
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
                    'groupAddLocationEdit'
                )
            )
            ->register(
                'GROUP_ADD_FIELDS',
                array(
                    $this,
                    'groupAddLocationField'
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
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['obj'];

        $arguments['tabData'][] = [
            'name' => _('Location Association'),
            'id' => 'group-location',
            'generator' => function() use ($obj) {
                $this->groupLocation($obj);
            }
        ];
    }
    /**
     * The group location display
     *
     * @param object $obj The group object we're working with.
     *
     * @return void
     */
    public function groupLocation($obj)
    {
        $locationID = (int)filter_input(
            INPUT_POST,
            'location'
        );
        // Group Locations
        $locationSelector = self::getClass('LocationManager')
            ->buildSelectBox($locationID, 'location');
        $fields = [
            '<label for="location" class="col-sm-2 control-label">'
            . _('Group Location')
            . '</label>' => &$locationSelector
        ];
        self::$HookManager
            ->processEvent(
                'GROUP_LOCATION_FIELDS',
                [
                    'fields' => &$fields,
                    'obj' => &$obj
                ]
            );
        $rendered = FOGPage::formFields($fields);
        echo '<div class="box box-solid">';
        echo '<div class="box-body">';
        echo '<form id="group-location-form" class="form-horizontal" method="post" action="'
            . FOGPage::makeTabUpdateURL('group-location', $obj->get('id'))
            . '" novalidate>';
        echo $rendered;
        echo '</form>';
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<button class="btn btn-primary" id="location-send">'
            . _('Update')
            . '</button>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * The location updater element.
     *
     * @param object $obj The object we're working with.
     *
     * @return void
     */
    public function groupLocationPost($obj)
    {
        $locationID = trim(
            (int)filter_input(
                INPUT_POST,
                'location'
            )
        );
        $Location = new Location($locationID);
        if (!$Location->isValid() && is_numeric($locationID)) {
            throw new Exception(_('Select a valid location'));
        }
        $insert_fields = ['hostID', 'locationID'];
        $insert_values = [];
        $hosts = $obj->get('hosts');
        if (count($hosts) > 0) {
            self::getClass('LocationAssociationManager')->destroy(
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
     * The group location selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddLocationEdit($arguments)
    {
        global $tab;
        global $node;
        if ($node != 'group') {
            return;
        }
        $obj = $arguments['obj'];
        try {
            switch($tab) {
            case 'group-location':
                $this->groupLocationPost($obj);
                break;
            default:
                return;
            }
            $arguments['code'] = 201;
            $arguments['hook'] = 'GROUP_EDIT_LOCATION_SUCCESS';
            $arguments['msg'] = json_encode(
                [
                    'msg' => _('Group Location Updated!'),
                    'title' => _('Group Location Update Success')
                ]
            );
        } catch (Exception $e) {
            $arguments['code'] = 400;
            $arguments['hook'] = 'GROUP_EDIT_LOCATION_FAIL';
            $arguments['msg'] = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Group Update Location Fail')
                ]
            );
        }
    }
    /**
     * The group location field for function add.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddLocationField($arguments)
    {
        global $node;
        if ($node != 'group') {
            return;
        }
        $locationID = (int)filter_input(INPUT_POST, 'location');
        $arguments['fields'][
            '<label for="location" class="col-sm-2 control-label">'
            . _('Group Location')
            . '</label>'] = self::getClass('LocationManager')
            ->buildSelectBox($locationID, 'location');
    }
}
