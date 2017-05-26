<?php
/**
 * Associate Users to a Site.
 *
 * PHP version 5
 *
 * @category AddSiteUSer
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Associate Users to a Site.
 *
 * @category AddSiteUSer
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSiteUser extends Hook
{
    public $name = 'AddSiteUser';
    public $description = 'Add Users to a Site';
    public $active = true;
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
                'USER_HEADER_DATA',
                array(
                    $this,
                    'userTableHeader'
                )
            )
            ->register(
                'USER_DATA',
                array(
                    $this,
                    'userData'
                )
            )
            ->register(
                'USER_FIELDS',
                array(
                    $this,
                    'userFields'
                )
            )
            ->register(
                'USER_ADD_SUCCESS',
                array(
                    $this,
                    'userAddSite'
                )
            )
            ->register(
                'USER_UPDATE_SUCCESS',
                array(
                    $this,
                    'userAddSite'
                )
            )
            ->register(
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'addNotes'
                )
            );
    }
    /**
     * This function modifies the header of the user page.
     * Add one column calls 'Associated Sites'
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userTableHeader($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }
        if (!in_array('accesscontrol', (array)self::$pluginsinstalled)) {
            $insertIndex = 3;
        } else {
            $insertIndex = 4;
        }
        $insertIndexRestricted = $insertIndex + 1;
        foreach ((array)$arguments['headerData'] as $index => &$str) {
            if ($index == $insertIndex) {
                $arguments['headerData'][$index] = _('Associated Sites');
                $arguments['headerData'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['headerData'] as $index => &$str) {
            if ($index == $insertIndexRestricted) {
                $arguments['headerData'][$index] = _('Is restricted');
                $arguments['headerData'][] = $str;
            }
            unset($str);
        }
    }
    /**
     * This function modifies the data of the user page.
     * Add one column calls 'Associated Sites'
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userData($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        if ($sub == 'pending') {
            return;
        }
        if (!in_array('accesscontrol', (array)self::$pluginsinstalled)) {
            $insertIndex = 3;
        } else {
            $insertIndex = 4;
        }
        $insertIndexRestricted = $insertIndex + 1;
        foreach ((array)$arguments['attributes'] as $index => &$str) {
            if ($index == $insertIndex || $index == $insertIndex + 1) {
                $arguments['attributes'][$index] = array();
                $arguments['attributes'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['attributes'] as $index => &$str) {
            if ($index == $insertIndexRestricted || $index == $insertIndex + 1) {
                $arguments['attributes'][$index] = array();
                $arguments['attributes'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['templates'] as $index => &$str) {
            if ($index == $insertIndex) {
                $arguments['templates'][$index] = '${site}';
                $arguments['templates'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['templates'] as $index => &$str) {
            if ($index == $insertIndexRestricted) {
                $arguments['templates'][$index] = '${isRestricted}';
                $arguments['templates'][] = $str;
            }
            unset($str);
        }
        foreach ((array)$arguments['data'] as $index => &$vals) {
            $find = array(
                'userID' => $vals['id']
            );
            $Sites = self::getSubObjectIDs(
                'SiteUserAssociation',
                $find,
                'siteID'
            );
            $isRestricted = self::getSubObjectIDs(
                'SiteUserRestriction',
                $find,
                'isRestricted'
            );
            $cnt = count($Sites);
            if ($cnt == 0) {
                $arguments['data'][$index]['site'] = _('No site');
            } else {
                $SiteNames = array_values(
                    array_unique(
                        array_filter(
                            self::getSubObjectIDs(
                                'Site',
                                array('id' => $Sites),
                                'name'
                            )
                        )
                    )
                );
                foreach ($SiteNames as $name) {
                    $sitenames .= $name.",";
                    unset($name);
                }

                $arguments['data'][$index]['site'] = substr(
                    $sitenames,
                    0,
                    strlen($sitenames)-1
                );
            }
            $arguments['data'][$index]['isRestricted'] = (
                $isRestricted[0] ?
                _('Yes') :
                _('No')
            );
            unset($vals);
            unset($Sites, $SiteNames, $sitenames);
        }
    }
    /**
     * This function adds a new column in the result table.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userFields($arguments)
    {
        global $node;
        global $sub;
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if ($node != 'user') {
            return;
        }
        $isRestricted = self::getSubObjectIDs(
            'SiteUserRestriction',
            array(
                'userID' => $arguments['User']->get('id')
            ),
            'isRestricted'
        );
        if (empty($isRestricted)) {
            $isRestricted = 0;
        } else {
            $isRestricted = $isRestricted[0];
        }
        self::arrayInsertAfter(
            _('User Name'),
            $arguments['fields'],
            _('Is Restricted User '),
            sprintf(
                '<input type="checkbox" name="isRestricted" id="isRestricted"%s/>'
                . '<label for="isRestricted"></label>',
                (
                    $isRestricted ?
                    ' checked' :
                    ''
                )
            )
        );
    }
    /**
     * This function adds one entry in the siteUserAssoc table in the DB
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function userAddSite($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
        $subs = array(
            'add',
            'edit',
            'addPost',
            'editPost'
        );
        if ($node != 'user') {
            return;
        }
        if (!in_array($sub, $subs)) {
            return;
        }
        self::getClass('SiteUserRestrictionManager')->destroy(
            array(
                'userID' => $arguments['User']->get('id')
            )
        );

        self::getClass('SiteUserRestriction')
            ->set('userID', $arguments['User']->get('id'))
            ->load('userID')
            ->set('isRestricted', isset($_REQUEST['isRestricted'])?1:0)
            ->save();
    }
    /**
     * This function adds role to notes
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function addNotes($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        global $node;
        global $sub;
        if ($node != 'user') {
            return;
        }
        if (count($arguments['notes']) < 1) {
            return;
        }
        $SiteIDs = self::getSubObjectIDs(
            'SiteUserAssociation',
            array(
                'userID' => $arguments['object']->get('id')
            ),
            'siteID'
        );
        $cnt = count($SiteIDs);
        if ($cnt == 0) {
            $sitenames = _('No Site');
        } else {
            $Sites = array_values(
                array_unique(
                    array_filter(
                        self::getSubObjectIDs(
                            'Site',
                            array('id' => $SiteIDs),
                            'name'
                        )
                    )
                )
            );
            foreach ($Sites as $index) {
                $sitenames .= $index." ";
                unset($index);
            }
        }
        $arguments['notes'][_('Sites')] = $sitenames;
    }
}
