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
    public $description = 'Add menu items to the management page';
    /**
     * The active flag (always true but for posterity
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
     * The group side menu
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupSideMenu($arguments)
    {
        global $node;
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        $link = $arguments['linkformat'];
        $this->arrayInsertAfter(
            "$link#group-image",
            $arguments['submenu'],
            "$link#group-location",
            _('Location Association')
        );
    }
    /**
     * The group fields.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupFields($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        global $node;
        if ($node != 'group') {
            return;
        }
        $Locations = self::getClass('LocationAssociationManager')->find(
            array(
                'hostID' => $arguments['Group']->get('hosts')
            )
        );
        foreach ((array)$Locations as &$Location) {
            if (!$Location->isValid()) {
                continue;
            }
            $locID = $Location->getLocation()->get('id');
            unset($Location);
            break;
        }
        echo '<!-- Location --><div id="group-location">';
        printf(
            '<h2>%s: %s</h2>',
            _('Location Association for'),
            $arguments['Group']->get('name')
        );
        printf(
            '<form method="post" action="%s&tab=group-location">',
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
            'field' => self::getClass('LocationManager')->buildSelectBox($locID),
            'input' => sprintf(
                '<input type="submit" value="%s"/>',
                _('Update Locations')
            )
        );
        $arguments['render']->render();
        echo '</form></div>';
    }
    /**
     * The group location selector.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function groupAddLocation($arguments)
    {
        global $node;
        global $tab;
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        if ($node != 'group') {
            return;
        }
        if ($tab != 'group-location') {
            return;
        }
        if (!($_REQUEST['location']
            && is_numeric($_REQUEST['location'])
            && $_REQUEST['location'] > 0)
        ) {
            self::getClass('LocationAssociationManager')->destroy(
                array(
                    'hostID' => $arguments['Group']->get('hosts')
                )
            );
        } else {
            $insert_fields = array('locationID','hostID');
            $insert_values = array();
            foreach ((array)$arguments['Group']->get('hosts') as &$hostID) {
                $insert_values[] = array($_REQUEST['location'], $hostID);
                unset($hostID);
            }
            if (count($insert_values) > 0) {
                self::getClass('LocationAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }
    }
}
$AddLocationGroup = new AddLocationGroup();
$HookManager
    ->register(
        'GROUP_GENERAL_EXTRA',
        array(
            $AddLocationGroup,
            'groupFields'
        )
    );
$HookManager
    ->register(
        'SUB_MENULINK_DATA',
        array(
            $AddLocationGroup,
            'groupSideMenu'
        )
    );
$HookManager
    ->register(
        'GROUP_EDIT_SUCCESS',
        array(
            $AddLocationGroup,
            'groupAddLocation'
        )
    );
