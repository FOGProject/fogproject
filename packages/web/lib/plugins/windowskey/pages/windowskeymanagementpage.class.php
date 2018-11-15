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
                "$this->linkformat#windowskey-gen" => self::$foglang['General'],
                $this->membership => self::$foglang['Membership'],
                "$this->delformat" => self::$foglang['Delete'],
            );
        }
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction"/>',
            _('Key Name')
        );
        $this->templates = array(
            '<input type="checkbox" name="windowskey[]" value="${id}" '
            . 'class="toggle-action"/>',
            '<a href="?node=windowskey&sub=edit&id=${id}">${name}</a>'
        );
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${name}'
            )
        );
        /**
         * Lambda function to return data either by list or search.
         *
         * @param object $WindowsKey the object to use
         *
         * @return void
         */
        self::$returnData = function (&$WindowsKey) {
            $this->data[] = array(
                'id' => $WindowsKey->id,
                'name' => $WindowsKey->name
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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('New Windows Key');
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $key = filter_input(
            INPUT_POST,
            'key'
        );
        $fields = array(
            '<label for="name">'
            . _('Windows Key Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="name" name="name" '
            . 'value="'
            . $name
            . '" required/>'
            . '</div>',
            '<label for="desc">'
            . _('Windows Key Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control" id="desc">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="productKey">'
            . _('Windows Key')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="productKey" name="key" '
            . 'value="'
            . $key
            . '" required/>'
            . '</div>',
            '<label for="add">'
            . _('Create New Key')
            . '</label>' => '<button type="submit" name="add" id="add" '
            . 'class="btn btn-info btn-block">'
            . _('Create')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'WINDOWS_KEY_FIELDS',
                array(
                    'fields' => &$fields,
                    'WindowsKey' => self::getClass('WindowsKey')
                )
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
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Actually create the windows key.
     *
     * @return void
     */
    public function addPost()
    {
        self::$HookManager->processEvent('WINDOWS_KEY_ADD');
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $key = filter_input(
            INPUT_POST,
            'key'
        );
        try {
            if (!isset($_POST['add'])) {
                throw new Exception(_('Not able to add'));
            }
            $exists = self::getClass('WindowsKeyManager')
                ->exists($name);
            if ($exists) {
                throw new Exception(
                    _('A Windows Key already exists with this name!')
                );
            }
            $WindowsKey = self::getClass('WindowsKey')
                ->set('name', $name)
                ->set('description', $description)
                ->set('key', $key);
            if (!$WindowsKey->save()) {
                throw new Exception(_('Add Windows Key failed!'));
            }
            $hook = 'WINDOWS_KEY_ADD_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Windows Key added!'),
                    'title' => _('Windows Key Create Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'WINDOWS_KEY_ADD_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Windows Key Create Fail')
                )
            );
        }
        self::$HookManager
            ->processEvent(
                $hook,
                array('WindowsKey' => &$WindowsKey)
            );
        unset($WindowsKey);
        echo $msg;
        exit;
    }
    /**
     * Display Windows Key General information.
     *
     * @return void
     */
    public function windowsKeyGeneral()
    {
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
        $this->title = _('Windows Key General');
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group')
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $name = (
            filter_input(
                INPUT_POST,
                'name'
            ) ?: $this->obj->get('name')
        );
        $description = (
            filter_input(
                INPUT_POST,
                'description'
            ) ?: $this->obj->get('description')
        );
        $key = (
            filter_input(
                INPUT_POST,
                'key'
            ) ?: $this->obj->get('key')
        );
        $keytest = self::aesdecrypt($key);
        if ($test_base64 = base64_decode($keytest)) {
            if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                $key = $test_base64;
            } elseif (mb_detect_encoding($keytest, 'utf-8', true)) {
                $key = $keytest;
            }
        }
        $fields = array(
            '<label for="name">'
            . _('Windows Key Name')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="name" name="name" '
            . 'value="'
            . $name
            . '" required/>'
            . '</div>',
            '<label for="desc">'
            . _('Windows Key Description')
            . '</label>' => '<div class="input-group">'
            . '<textarea name="description" class="form-control" id="desc">'
            . $description
            . '</textarea>'
            . '</div>',
            '<label for="productKey">'
            . _('Windows Key')
            . '</label>' => '<div class="input-group">'
            . '<input class="form-control" type="text" id="productKey" name="key" '
            . 'value="'
            . $key
            . '" required/>'
            . '</div>',
            '<label for="update">'
            . _('Make Changes?')
            . '</label>' => '<button type="submit" name="update" id="update" '
            . 'class="btn btn-info btn-block">'
            . _('Update')
            . '</button>'
        );
        self::$HookManager
            ->processEvent(
                'WINDOWS_KEY_FIELDS',
                array(
                    'fields' => &$fields,
                    'WindowsKey' => &$this->obj
                )
            );
        array_walk($fields, $this->fieldsToData);
        unset($fields);
        self::$HookManager
            ->processEvent(
                'WINDOWS_KEY_EDIT',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'headerData' => &$this->headerData,
                    'attributes' => &$this->attributes
                )
            );
        echo '<!-- General -->';
        echo '<div class="tab-pane fade in active" id="windowskey-gen">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '&tab=windowskey-gen">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->form,
            $this->templates,
            $this->attributes,
            $this->headerData
        );
    }
    /**
     * Present the windows key to edit the page.
     *
     * @return void
     */
    public function edit()
    {
        echo '<div class="col-xs-9 tab-content">';
        $this->windowsKeyGeneral();
        echo '</div>';
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
        $name = filter_input(
            INPUT_POST,
            'name'
        );
        $description = filter_input(
            INPUT_POST,
            'description'
        );
        $key = filter_input(
            INPUT_POST,
            'key'
        );
        try {
            $exists = self::getClass('WindowsKeyManager')
                ->exists($name);
            if ($name != $this->obj->get('name')
                && $exists
            ) {
                throw new Exception(
                    _('A Windows Key already exists with this name!')
                );
            }
            $this->obj
                ->set('name', $name)
                ->set('description', $description)
                ->set('key', $key);
            if (!$this->obj->save()) {
                throw new Exception(_('Update Windows Key failed!'));
            }
            $hook = 'WINDOWS_KEY_EDIT_POST_SUCCESS';
            $msg = json_encode(
                array(
                    'msg' => _('Windows Key updated!'),
                    'title' => _('Windows Key Update Success')
                )
            );
        } catch (Exception $e) {
            $hook = 'WINDOWS_KEY_EDIT_POST_FAIL';
            $msg = json_encode(
                array(
                    'error' => $e->getMessage(),
                    'title' => _('Windows Key Update Fail')
                )
            );
        }
        echo $msg;
        exit;
    }
    /**
     * Presents the membership information
     *
     * @return void
     */
    public function membership()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        $this->headerData = array(
            '<label for="toggler">'
            . '<input type="checkbox" name="toggle-checkbox'
            . $this->node
            . '1" class="toggle-checkboxAction1" id="toggler"/>'
            . '</label>',
            _('Image Name')
        );
        $this->templates = array(
            '<label for="image-${image_id}">'
            . '<input type="checkbox" name="image[]" class="toggle-'
            . 'image${check_num" id="image-${image_id}" '
            . 'value="${image_id}"/>'
            . '</label>',
            '<a href="?node=image&sub=edit&id=${image_id}">${image_name}</a>'
        );
        $this->attributes = array(
            array(
                'width' => 16,
                'class' => 'filter-false'
            ),
            array(
                'data-toggle' => 'tooltip',
                'data-placement' => 'bottom',
                'title' => _('Edit')
                . ' '
                . '${image_name}'
            )
        );
        Route::listem('image');
        $items = json_decode(
            Route::getData()
        );
        $items = $items->images;
        $getter = 'imagesnotinme';
        $returnData = function (&$item) use (&$getter) {
            $images = $this->obj->get($getter);
            if (!in_array($item->id, (array)$images)) {
                return;
            }
            $this->data[] = array(
                'image_id' => $item->id,
                'image_name' => $item->name,
                'check_num' => 1
            );
            unset($item);
        };
        array_walk($items, $returnData);
        echo '<!-- Membership -->';
        echo '<div class="col-xs-9">';
        echo '<div class="tab-pane fade in active" id="'
            . $this->node
            . '-membership">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Image Membership');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
            . '">';
        if (count($this->data)  > 0) {
            $notInMe = $meShow = 'image';
            $meShow .= 'MeShow';
            $notInMe .= 'NotInMe';
            echo '<div class="text-center">';
            echo '<div class="checkbox">';
            echo '<label for="'
                . $meShow
                . '"/>';
            echo '<input type="checkbox" name="'
                . $meShow
                . '" id="'
                . $meShow
                . '"/>';
            echo _('Check here to see what images can be added');
            echo '</label>';
            echo '</div>';
            echo '</div>';
            echo '<br/>';
            echo '<div class="hiddeninitially panel panel-info" id="'
                . $notInMe
                . '">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Add')
                . ' '
                . _('Images');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="updateimages" class="control-label col-xs-4">';
            echo _('Add selected images');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="addImages" '
                . 'id="updateimages" class="btn btn-info btn-block">'
                . _('Add')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates
        );
        $this->headerData = array(
            '<label for="toggler1">'
            . '<input type="checkbox" name="toggle-checkbox" '
            . 'class="toggle-checkboxAction" id="toggler1"/>'
            . '</label>',
            _('Image Name')
        );
        $this->templates = array(
            '<label for="imagerm-${image_id}">'
            . '<input type="checkbox" name="imagedel[]" '
            . 'value="${image_id}" class="toggle-action" id="'
            . 'imagerm-${image_id}"/>'
            . '</label>',
            '<a href="?node=image&sub=edit&id=${image_id}">${image_name}</a>'
        );
        $getter = 'images';
        array_walk($items, $returnData);
        if (count($this->data) > 0) {
            echo '<div class="panel panel-warning">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo _('Remove Images');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '<div class="form-group">';
            echo '<label for="remimages" class="control-label col-xs-4">';
            echo _('Remove selected images');
            echo '</label>';
            echo '<div class="col-xs-8">';
            echo '<button type="submit" name="remimages" class='
                . '"btn btn-danger btn-block" id="remimages">'
                . _('Remove')
                . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
        $reqitems = filter_input_array(
            INPUT_POST,
            array(
                'image' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                ),
                'imagedel' => array(
                    'flags' => FILTER_REQUIRE_ARRAY
                )
            )
        );
        $image = $reqitems['image'];
        $imagedel = $reqitems['imagedel'];
        if (isset($_POST['addImages'])) {
            $this->obj->addImage($image);
        }
        if (isset($_POST['remimages'])) {
            $this->obj->removeImage($imagedel);
        }
        if ($this->obj->save()) {
            self::redirect($this->formAction);
        }
    }
}
