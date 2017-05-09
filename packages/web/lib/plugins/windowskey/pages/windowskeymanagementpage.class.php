<?php
/**
 * Windows Keys management page.
 *
 * PHP version 5
 *
 * @category WindowsKeyManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Windows Keys management page.
 *
 * @category WindowsKeyManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyManagementPage extends FOGPage
{
    /**
     * The node this page operates on.
     *
     * @var string
     */
    public $node = 'windowskey';
    /**
     * Initializes the Windows key management page.
     *
     * @param string $name Something to lay it out as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Windows Key Management';
        /**
         * Add this page to the PAGES_WITH_OBJECTS hook event.
         */
        self::$HookManager->processEvent(
            'PAGES_WITH_OBJECTS',
            array('PagesWithObjects' => &$this->PagesWithObjects)
        );
        /**
         * Get our $_GET['node'], $_GET['sub'], and $_GET['id']
         * in a nicer to use format.
         */
        global $node;
        global $sub;
        global $id;
        self::$foglang['ExportWindowskey'] = _('Export Windows Keys');
        self::$foglang['ImportWindowskey'] = _('Import Windows Keys');
        parent::__construct($this->name);
        if ($id) {
            $this->subMenu = array(
                "$this->linkformat" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                "$this->delformat" => self::$foglang['Delete'],
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" checked/>',
            _('Key Name')
        );
        $this->templates = array(
            '<input type="checkbox" name="windowskey[]" value='
            . '"${id}" class="toggle-action" checked/>',
            '<a href="?node=windowskey&sub=edit&id=${id}" title="Edit">${name}</a>'
        );
        $this->attributes = array(
            array(
                'class' => 'l filter-false',
                'width' => 16
            ),
            array('class' => 'l')
        );
        self::$returnData = function (&$WindowsKey) {
            $this->data[] = array(
                'id' => $WindowsKey->get('id'),
                'name' => $WindowsKey->get('name')
            );
            unset($WindowsKey);
        };
    }
    /**
     * Show form for creating a new windows key entry.
     *
     * @return void
     */
    public function add()
    {
        $this->title = _('New Windows Key');
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _('Windows Key Name') => sprintf(
                '<input class="smaller" type="text" name="name" value="%s"/>',
                $_REQUEST['name']
            ),
            _('Windows Key Description') => sprintf(
                '<textarea name="description" '
                . 'rows="8" cols="40">%s</textarea>',
                $_REQUEST['description']
            ),
            _('Windows Key') => sprintf(
                '<input id="productKey" type="text" name="key" value="%s"/>',
                $_REQUEST['key']
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
                'WINDOWS_KEY_ADD',
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
     * Actually create the windows key.
     *
     * @return void
     */
    public function addPost()
    {
        try {
            $name = trim($_REQUEST['name']);
            $key = trim($_REQUEST['key']);
            $description = trim($_REQUEST['description']);
            $exists = self::getClass('WindowsKeyManager')
                ->exists($name);
            if ($exists) {
                throw new Exception(
                    _('Windows key already Exists, please try again.')
                );
            }
            if (empty($name)) {
                throw new Exception(_('Please enter a name for this key.'));
            }
            if (empty($key)) {
                throw new Exception(_('Please enter a product key.'));
            }
            $WindowsKey = self::getClass('WindowsKey')
                ->set('name', $name)
                ->set('description', $description)
                ->set('key', $key);
            if (!$WindowsKey->save()) {
                throw new Exception(_('Failed to create'));
            }
            self::setMessage(_('Key Added, editing!'));
            self::redirect(
                sprintf(
                    '?node=windowskey&sub=edit&id=%s',
                    $WindowsKey->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Present the windows key to edit the page.
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
        $decrypt = self::aesdecrypt($this->obj->get('key'));
        $fields = array(
            _('Windows Key Name') => sprintf(
                '<input class="smaller" type="text" name="name" value="%s"/>',
                (
                    $_REQUEST['name'] ?
                    $_REQUEST['name'] :
                    $this->obj->get('name')
                )
            ),
            _('Windows Key Description') => sprintf(
                '<textarea name="description" '
                . 'rows="8" cols="40">%s</textarea>',
                (
                    $_REQUEST['description'] ?
                    $_REQUEST['description'] :
                    $this->obj->get('description')
                )
            ),
            _('Windows Key') => sprintf(
                '<input id="productKey" type="text" name="key" value="%s"/>',
                (
                    $_REQUEST['key'] ?
                    $_REQUEST['key'] :
                    $decrypt
                )
            ),
            '&nbsp;' => sprintf(
                '<input type="submit" class="smaller" name="update" value="%s"/>',
                _('Update')
            ),
        );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'WINDOWS_KEY_EDIT',
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
     * Actually update the windows key.
     *
     * @return void
     */
    public function editPost()
    {
        self::$HookManager
            ->processEvent(
                'WINDOWS_KEY_EDIT_POST',
                array(
                    'WindowsKey' => &$this->obj
                )
            );
        try {
            $name = trim($_REQUEST['name']);
            $key = trim($_REQUEST['key']);
            $description = trim($_REQUEST['description']);
            $exists = self::getClass('WindowsKeyManager')
                ->exists($name);
            if ($name != $this->obj->get('name')
                && $exists
            ) {
                throw new Exception(
                    _('Windows key already Exists, please try again.')
                );
            }
            if (empty($name)) {
                throw new Exception(_('Please enter a name for this key.'));
            }
            if (empty($key)) {
                throw new Exception(_('Please enter a product key.'));
            }
            $this->obj
                ->set('name', $name)
                ->set('description', $description)
                ->set('key', $key);
            if (!$this->obj->save()) {
                throw new Exception(_('Failed to update'));
            }
            self::setMessage(_('Windows Key Updated'));
            self::redirect(
                sprintf(
                    '?node=windowskey&sub=edit&id=%d',
                    $this->obj->get('id')
                )
            );
        } catch (Exception $e) {
            self::setMessage($e->getMessage());
            self::redirect($this->formAction);
        }
    }
    /**
     * Presents the membership information
     *
     * @return void
     */
    public function membership()
    {
        $this->data = array();
        echo '<!-- Membership -->';
        echo '<div id="windowskey-membership">';
        $this->headerData = array(
            sprintf(
                '<input type="checkbox" name="toggle-checkbox%s" '
                . 'class="toggle-checkboxAction1"',
                $this->node
            ),
            _('Image Name')
        );
        $this->templates = array(
            sprintf(
                '<input type="checkbox" name="image[]" value="${image_id}" '
                . 'class="toggle-%s1"/>',
                'image'
            ),
            sprintf(
                '<a href="?node=%s&sub=edit&id=${image_id}" '
                . 'title="%s: ${image_name}">${image_name}</a>',
                'image',
                _('Edit')
            )
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'l filter-false'
            ),
            array(
                'width' => 150,
                'class' => 'l'
            )
        );
        extract(
            self::getSubObjectIDs(
                'Image',
                array(
                    'id' => $this->obj->get('imagesnotinme')
                ),
                array(
                    'name',
                    'id'
                )
            )
        );
        $itemParser = function (
            &$nam,
            &$index
        ) use (&$id) {
            $this->data[] = array(
                'image_id' => $id[$index],
                'image_name' => $nam,
            );
            unset(
                $nam,
                $id[$index],
                $index
            );
        };
        array_walk($name, $itemParser);
        if (count($this->data) > 0) {
            self::$HookManager
                ->processEvent(
                    'IMAGE_NOT_IN_ME',
                    array(
                        'headerData' => &$this->headerData,
                        'data' => &$this->data,
                        'templates' => &$this->templates,
                        'attributes' => &$this->attributes
                    )
                );
            printf(
                '<form method="post" action="%s"><label for="%sMeShow">'
                . '<p class="c">%s %ss %s %s&nbsp;&nbsp;<input '
                . 'type="checkbox" name="%sMeShow" id="%sMeShow"/>'
                . '</p></label><div id="%sNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                'image',
                _('Check here to see'),
                'image',
                _('not within this'),
                $this->node,
                'image',
                'image',
                'image',
                _('Modify Membership for'),
                $this->obj->get('name')
            );
            $this->render();
            printf(
                '</div><br/><p class="c"><input type='
                . '"submit" value="%s %s(s) to %s" name="addImages"/></p><br/>',
                _('Add'),
                _('Image'),
                $this->node
            );
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            sprintf(
                '%s %s',
                _('Image'),
                _('Name')
            ),
        );
        $this->templates = array(
            '<input type="checkbox" name="imagedel[]" '
            . 'value="${image_id}" class="toggle-action"/>',
            sprintf(
                '<a href="?node=%s&sub=edit&id=${image_id}" '
                . 'title="%s: ${image_name}">${image_name}</a>',
                $this->node,
                _('Image')
            ),
        );
        extract(
            self::getSubObjectIDs(
                'Image',
                array(
                    'id' => $this->obj->get('images')
                ),
                array(
                    'name',
                    'id'
                )
            )
        );
        array_walk($name, $itemParser);
        self::$HookManager
            ->processEvent(
                'IMAGE_MEMBERSHIP',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );
        $this->render();
        if (count($this->data)) {
            printf(
                '<p class="c"><input type="submit" '
                . 'value="%s %ss %s %s" name="remimages"/></p>',
                _('Delete Selected'),
                _('Images'),
                _('From'),
                $this->node
            );
        }
    }
    /**
     * Commonized membership actions
     *
     * @return void
     */
    public function membershipPost()
    {
        if (self::$ajax) {
            return;
        }
        if (isset($_REQUEST['addImages'])) {
            $this->obj->addImage($_REQUEST['image']);
        }
        if (isset($_REQUEST['remimages'])) {
            $this->obj->removeImage($_REQUEST['imagedel']);
        }
        if ($this->obj->save()) {
            self::setMessage(
                sprintf(
                    '%s %s',
                    $this->obj->get('name'),
                    _('saved successfully')
                )
            );
            self::redirect($this->formAction);
        }
    }
}
