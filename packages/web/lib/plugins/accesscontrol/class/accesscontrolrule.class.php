<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlRule
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRule
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRule extends FOGController
{
    /**
     * The example table.
     *
     * @var string
     */
    protected $databaseTable = 'rules';
    /**
     * The database fields and commonized items.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ruleID',
        'name' => 'ruleName',
        'type' => 'ruleType',
        'value' => 'ruleValue',
        'parent' => 'ruleParent',
        'createdBy' => 'ruleCreatedBy',
        'createdTime' => 'ruleCreatedTime',
        'node' => 'ruleNode'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'type',
        'value'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'accesscontrols'
    ];
    /**
     * Add role to access control rule
     *
     * @param array $addArray The roles to add.
     *
     * @return object
     */
    public function addRole($addArray)
    {
        return $this->addRemItem(
            'accesscontrols',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove role from access control rule.
     *
     * @param array $removeArray The roles to remove.
     *
     * @return object
     */
    public function removeRole($removeArray)
    {
        return $this->addRemItem(
            'accesscontrols',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Load Access Controls
     *
     * @return void
     */
    protected function loadAccesscontrols()
    {
        $find = ['accesscontrolruleID' => $this->get('id')];
        Route::ids(
            'accesscontrolassociation',
            $find,
            'accesscontrolID'
        );
        $roleIDs = json_decode(
            Route::getData(),
            true
        );
        $this->set('accesscontrols', (array)$roleIDs);
    }
    /**
     * Stores/updates the accesscontrol rule
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('AccessControl', 'accesscontrol')
            ->load();
    }
}
