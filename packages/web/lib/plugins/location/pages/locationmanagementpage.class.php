<?php
/**
 * Location management page.
 *
 * PHP version 5
 *
 * @category LocationManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location management page.
 *
 * @category LocationManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationManagementPage extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'location';
    /**
     * Initializes the Location management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Location Management';
        self::$foglang['ExportLocation'] = _('Export Locations');
        self::$foglang['ImportLocation'] = _('Import Locations');
        parent::__construct($this->name);
        global $id;
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                "$this->delformat" => self::$foglang['Delete'],
            );
            $this->notes = array(
                self::$foglang['Location'] => $this->obj->get('name'),
                sprintf(
                    '%s %s',
                    self::$foglang['Storage'],
                    self::$foglang['Group']
                ) => $this->obj->getStorageGroup()->get('name')
            );
            if ($this->obj->getStorageNode()->isValid()) {
                $this->notes[
                    sprintf(
                        '%s %s',
                        self::$foglang['Storage'],
                        self::$foglang['Node']
                    )
                ] = $this->obj->getStorageNode()->get('name');
            }
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" checked/>',
            _('Location Name'),
            _('Storage Group'),
            _('Storage Node'),
            _('Kernels/Inits from location'),
        );
        $this->templates = array(
            '<input type="checkbox" name="location[]" value='
            . '"${id}" class="toggle-action" checked/>',
            '<a href="?node=location&sub=edit&id=${id}" title="Edit">${name}</a>',
            '${storageGroup}',
            '${storageNode}',
            '${tftp}',
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l'),
            array('class' => 'l'),
            array('class' => 'c'),
            array('class' => 'r'),
        );
        self::$returnData = function (&$Location) {
            if (!$Location->isValid()) {
                return;
            }
            $this->data[] = array(
                'id' => $Location->get('id'),
                'name' => $Location->get('name'),
                'storageGroup' => $Location->get('storagegroup')->get('name'),
                'storageNode' => (
                    $Location->get('storagenode')->isValid() ?
                    $Location->get('storagenode')->get('name') :
                    _('Not Set')
                ),
                'tftp' => $Location->get('tftp') ? _('Yes') : _('No'),
            );
            unset($Location);
        };
    }
    /**
     * Show form for creating a new location entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Location');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $sgbuild = self::getClass('StorageGroupManager')->buildSelectBox();
        $snbuild = self::getClass('StorageNodeManager')->buildSelectBox();
        $fields = array(
            _('Location Name') => sprintf(
                '<input class="smaller" type="text" name="name" />'
            ),
            _('Storage Group') => $sgbuild,
            _('Storage Node') => $snbuild,
            _('Use inits and kernels from this node') => sprintf(
                '<input type="checkbox" name="tftp" value="on"%s/>',
                (
                    isset($_REQUEST['tftp']) ?
                    ' checked' :
                    ''
                )
            ),
            '&nbsp;' => sprintf(
                '<input name="add" class="smaller" type="submit" value="%s"/>',
                _('Add')
            ),
        );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'LOCATION_ADD',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf('<form method="post" action="%s">', $this->formAction);
        $this->render();
        echo '</form>';
    }
    /**
     * Actually create the location.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $name = trim($_REQUEST['name']);
            if (self::getClass('LocationManager')->exists(trim($_REQUEST['name']))) {
                throw new Exception(_('Location already Exists, please try again.'));
            }
            if (!$name) {
                throw new Exception(_('Please enter a name for this location.'));
            }
            if (empty($_REQUEST['storagegroup'])) {
                throw new Exception(
                    _('Please select the storage group this location relates to.')
                );
            }
            $NodeID = $_REQUEST['storagenode'];
            $sgID = $_REQUEST['storagegroup'];
            $sn = new StorageNode($NodeID);
            if ($sn->isValid()) {
                $sgID = $sn->getStorageGroup()->get('id');
            }
            $Location = self::getClass('Location')
                ->set('name', $name)
                ->set('storagegroupID', $sgID)
                ->set('storagenodeID', $NodeID)
                ->set('tftp', isset($_REQUEST['tftp']));
            if (!$Location->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Location Added, editing!'));
            self::redirect(
                sprintf(
                    '?node=location&sub=edit&id=%s',
                    $Location->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Present the location to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        $this->title = sprintf(
            '%s: %s',
            _('Edit'),
            $this->obj->get('name')
        );
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $sgbuild = self::getClass('StorageGroupManager')->buildSelectBox(
            (
                isset($_REQUEST['storagegroup'])
                && is_numeric($_REQUEST['storagegroup'])
                && $_REQUEST['storagegroup'] > 0 ?
                $_REQUEST['storagegroup'] :
                $this->obj->get('storagegroupID')
            )
        );
        $snbuild = self::getClass('StorageNodeManager')->buildSelectBox(
            (
                isset($_REQUEST['storagenode'])
                && is_numeric($_REQUEST['storagenode'])
                && $_REQUEST['storagenode'] > 0 ?
                $_REQUEST['storagenode'] :
                $this->obj->get('storagenodeID')
            )
        );
        $fields = array(
            _('Location Name') => sprintf(
                '<input class="smaller" type="text" name="name" value="%s"/>',
                $this->obj->get('name')
            ),
            _('Storage Group') => $sgbuild,
            _('Storage Node') => $snbuild,
            _('Use inits and kernels from this node') => sprintf(
                '<input type="checkbox" name="tftp" value="on"%s/>',
                (
                    $this->obj->get('tftp') ?
                    ' checked' :
                    ''
                )
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" class="smaller" name="update" value="%s"/>',
                _('Update')
            ),
        );
        foreach ((array)$fields as $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            unset($input);
        }
        unset($fields);
        self::$HookManager
            ->processEvent(
                'LOCATION_EDIT',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s&id=%d">',
            $this->formAction,
            $this->obj->get('id')
        );
        $this->render();
        echo '</form>';
    }
    /**
     * Actually update the location.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'LOCATION_EDIT_POST',
                array(
                    'Location' => &$this->obj
                )
            );
        try {
            if ($_REQUEST['name'] != $this->obj->get('name')
                && $this->obj->getManager()->exists($_REQUEST['name'])
            ) {
                throw new Exception(_('A location with that name already exists.'));
            }
            if (isset($_REQUEST['update'])) {
                if (empty($_REQUEST['storagegroup'])) {
                    throw new Exception(
                        _('A group is required for a location')
                    );
                }
                $NodeID = $_REQUEST['storagenode'];
                $sn = new StorageNode($NodeID);
                $sgID = $_REQUEST['storagegroup'];
                if ($sn->isValid()) {
                    $sgID = $sn->getStorageGroup()->get('id');
                }
                $this->obj
                    ->set('name', $_REQUEST['name'])
                    ->set('storagegroupID', $sgID)
                    ->set('storagenodeID', $NodeID)
                    ->set('tftp', isset($_REQUEST['tftp']));
                if (!$this->obj->save()) {
                    throw new Exception(_('Failed to update'));
                }
                self::setMessage(_('Location Updated'));
                self::redirect(
                    sprintf(
                        '?node=location&sub=edit&id=%d',
                        $this->obj->get('id')
                    )
                );
            }
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
}
